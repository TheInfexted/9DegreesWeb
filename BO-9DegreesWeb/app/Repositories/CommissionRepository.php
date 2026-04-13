<?php

namespace App\Repositories;

use App\Models\AmbassadorModel;
use App\Models\SaleModel;
use CodeIgniter\Database\BaseBuilder;

class CommissionRepository
{
    public function __construct(
        private SaleModel $saleModel = new SaleModel(),
        private AmbassadorModel $ambassadorModel = new AmbassadorModel()
    ) {}

    /**
     * Get monthly Table sales total (confirmed) for a given ambassador.
     * BGO sales are explicitly excluded.
     * Uses SUBSTR for SQLite + MySQL compatibility.
     */
    public function getMonthlyTableSalesTotal(int $ambassadorId, string $yearMonth): float
    {
        $row = $this->saleModel
            ->selectSum('gross_amount', 'total')
            ->where('ambassador_id', $ambassadorId)
            ->where('sale_type', 'Table')
            ->where('status', 'confirmed')
            ->where("SUBSTR(date, 1, 7)", $yearMonth)
            ->first();

        return (float) ($row['total'] ?? 0);
    }

    /**
     * Compute the effective commission rate for a sale.
     * Call this while the sale is still in 'draft' status so the
     * monthly confirmed total does not yet include the current sale.
     *
     * Rules (spec §10):
     * 1. BGO always 10%.
     * 2. Table: base rate = ambassador.custom_commission_rate
     * 3. If use_kpi_bonus=1 AND kpi IS NOT NULL AND commission_increase IS NOT NULL:
     *    - (confirmed monthly Table total + this sale amount) >= kpi → rate += commission_increase
     * 4. "Unassigned Sales" uses Johnny's profile.
     */
    public function computeEffectiveRate(int $saleId): float
    {
        $sale = $this->saleModel->find($saleId);
        if (!$sale) throw new \RuntimeException("Sale {$saleId} not found.", 404);

        if ($sale['sale_type'] === 'BGO') {
            return 10.00;
        }

        $ambassador = $this->resolveAmbassadorProfile((int) $sale['ambassador_id']);
        $baseRate   = (float) $ambassador['custom_commission_rate'];

        if (
            $ambassador['use_kpi_bonus']
            && $ambassador['kpi'] !== null
            && $ambassador['commission_increase'] !== null
        ) {
            $yearMonth      = substr($sale['date'], 0, 7);
            $confirmedTotal = $this->getMonthlyTableSalesTotal((int) $sale['ambassador_id'], $yearMonth);

            if ($this->isUnassignedSales((int) $sale['ambassador_id'])) {
                $johnny = $this->getJohnnyAmbassador();
                $confirmedTotal += $this->getMonthlyTableSalesTotal((int) $johnny['id'], $yearMonth);
            }

            $totalIncludingCurrent = $confirmedTotal + (float) $sale['gross_amount'];

            if ($totalIncludingCurrent >= (float) $ambassador['kpi']) {
                $baseRate += (float) $ambassador['commission_increase'];
            }
        }

        return round($baseRate, 2);
    }

    public function countReport(array $filters): int
    {
        // Count on the sales table only. Use db->table() — not $saleModel->builder() — so countAllResults()
        // does not corrupt the Model's shared builder used by findReportPaginated() in the same request.
        $b = $this->saleModel->db->table($this->saleModel->getTable());
        $b->where('status', 'confirmed');
        $this->applyReportFiltersToSalesBuilder($b, $filters);

        return (int) $b->countAllResults();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function findReportPaginated(array $filters, int $page, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $builder = $this->makeReportBuilder();
        $this->applyReportFilters($builder, $filters);
        // Do not use findAll() here: BaseModel::doFindAll() reapplies limit(0,0) and drops the chained limit.
        $builder->limit($perPage, $offset);

        return $builder->get()->getResultArray();
    }

    /**
     * Commission report: confirmed sales with frozen rates and commission amounts (full list).
     *
     * @return list<array<string,mixed>>
     */
    public function getReport(array $filters = []): array
    {
        $builder = $this->makeReportBuilder();
        $this->applyReportFilters($builder, $filters);

        return $builder->get()->getResultArray();
    }

    /**
     * @return array{total: float, table: float, bgo: float}
     */
    public function getReportSummary(array $filters): array
    {
        // Single-table aggregate: unqualified columns match the query builder's FROM table
        // (works with DBPrefix / SQLite tests; joins were unused for filters).
        $b = $this->saleModel->builder();
        $b->select(
            'COALESCE(SUM(ROUND(gross_amount * confirmed_commission_rate / 100, 2)), 0) AS total, '
            . 'COALESCE(SUM(CASE WHEN sale_type = \'Table\' THEN ROUND(gross_amount * confirmed_commission_rate / 100, 2) ELSE 0 END), 0) AS table_total, '
            . 'COALESCE(SUM(CASE WHEN sale_type = \'BGO\' THEN ROUND(gross_amount * confirmed_commission_rate / 100, 2) ELSE 0 END), 0) AS bgo_total',
            false
        )
            ->where('status', 'confirmed');
        $this->applyReportFiltersToSalesBuilder($b, $filters);

        $row = $b->get()->getRowArray();

        return [
            'total' => (float) ($row['total'] ?? 0),
            'table' => (float) ($row['table_total'] ?? 0),
            'bgo'   => (float) ($row['bgo_total'] ?? 0),
        ];
    }

    /**
     * Active ambassadors with at least one confirmed sale in the given calendar month (YYYY-MM).
     *
     * @return list<array<string,mixed>>
     */
    public function findAmbassadorsWithConfirmedSalesInMonth(string $yearMonth): array
    {
        $rows = $this->saleModel->builder()
            ->select('ambassador_id')
            ->where('status', 'confirmed')
            ->where("SUBSTR(date, 1, 7)", $yearMonth)
            ->groupBy('ambassador_id')
            ->get()->getResultArray();
        $ids = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['ambassador_id'], $rows)));
        if ($ids === []) {
            return [];
        }

        $db = $this->ambassadorModel->db;
        $tA = $db->prefixTable('ambassadors');
        $tT = $db->prefixTable('teams');

        return $this->ambassadorModel
            ->select("{$tA}.id, {$tA}.name, {$tT}.name as team_name", false)
            ->join('teams', "{$tT}.id = {$tA}.team_id", 'left')
            ->whereIn("{$tA}.id", $ids)
            ->where("{$tA}.status", 'active')
            ->orderBy("{$tA}.name", 'ASC')
            ->findAll();
    }

    /**
     * Sum of (gross_amount * confirmed_commission_rate / 100) for all confirmed
     * sales for a given ambassador and month.  Used to populate payout totals.
     */
    public function calculateTotalCommissionForUser(int $ambassadorId, string $yearMonth): float
    {
        $row = $this->saleModel
            ->select('SUM(ROUND(gross_amount * confirmed_commission_rate / 100, 2)) as total', false)
            ->where('ambassador_id', $ambassadorId)
            ->where('status', 'confirmed')
            ->where("SUBSTR(date, 1, 7)", $yearMonth)
            ->first();

        return (float) ($row['total'] ?? 0);
    }

    public function getAvailableMonths(): array
    {
        return $this->saleModel
            ->select("SUBSTR(date, 1, 7) as month")
            ->where('status', 'confirmed')
            ->groupBy("SUBSTR(date, 1, 7)")
            ->orderBy('month', 'DESC')
            ->findAll();
    }

    /** Physical sales table name including DBPrefix (required for raw SELECT / WHERE fragments). */
    private function prefixedSalesTable(): string
    {
        return $this->saleModel->db->prefixTable($this->saleModel->getTable());
    }

    private function makeReportBuilder(): SaleModel
    {
        $db = $this->saleModel->db;
        $tS = $this->prefixedSalesTable();
        $tA = $db->prefixTable('ambassadors');
        $tR = $db->prefixTable('roles');

        return $this->saleModel
            ->select(
                "{$tS}.*, {$tA}.name as ambassador_name, {$tR}.name as role_name, "
                . "ROUND({$tS}.gross_amount * {$tS}.confirmed_commission_rate / 100, 2) as commission_amount",
                false
            )
            ->join('ambassadors', "{$tA}.id = {$tS}.ambassador_id", 'left')
            ->join('roles', "{$tR}.id = {$tA}.role_id", 'left')
            ->where("{$tS}.status", 'confirmed')
            ->orderBy("{$tS}.date", 'DESC');
    }

    /**
     * @param array{ambassador_id?: int|string, month?: string} $filters
     */
    private function applyReportFilters(SaleModel $builder, array $filters): void
    {
        $t = $this->prefixedSalesTable();
        if (!empty($filters['ambassador_id'])) {
            $builder->where("{$t}.ambassador_id", $filters['ambassador_id']);
        }
        if (!empty($filters['month'])) {
            $builder->where("SUBSTR({$t}.date, 1, 7)", $filters['month']);
        }
    }

    /**
     * @param array{ambassador_id?: int|string, month?: string} $filters
     */
    private function applyReportFiltersToSalesBuilder(BaseBuilder $b, array $filters): void
    {
        if (!empty($filters['ambassador_id'])) {
            $b->where('ambassador_id', $filters['ambassador_id']);
        }
        if (!empty($filters['month'])) {
            $b->where("SUBSTR(date, 1, 7)", $filters['month']);
        }
    }

    private function resolveAmbassadorProfile(int $ambassadorId): array
    {
        if ($this->isUnassignedSales($ambassadorId)) {
            return $this->getJohnnyAmbassador();
        }
        return $this->ambassadorModel->find($ambassadorId);
    }

    private function isUnassignedSales(int $ambassadorId): bool
    {
        $amb = $this->ambassadorModel->find($ambassadorId);
        return $amb && $amb['name'] === 'Unassigned Sales';
    }

    private function getJohnnyAmbassador(): array
    {
        $johnny = $this->ambassadorModel->where('name', 'Johnny')->first();
        if (!$johnny) throw new \RuntimeException('Johnny ambassador profile not found.', 500);
        return $johnny;
    }
}
