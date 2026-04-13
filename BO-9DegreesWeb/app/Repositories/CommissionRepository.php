<?php

namespace App\Repositories;

use App\Models\AmbassadorModel;
use App\Models\SaleModel;

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
        $builder = $this->makeReportBuilder();
        $this->applyReportFilters($builder, $filters);

        return $builder->countAllResults(false);
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

        return $builder->limit($perPage, $offset)->findAll();
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

        return $builder->findAll();
    }

    /**
     * @return array{total: float, table: float, bgo: float}
     */
    public function getReportSummary(array $filters): array
    {
        $b = $this->saleModel->builder();
        $b->select(
            'COALESCE(SUM(ROUND(sales.gross_amount * sales.confirmed_commission_rate / 100, 2)), 0) AS total, '
            . 'COALESCE(SUM(CASE WHEN sales.sale_type = \'Table\' THEN ROUND(sales.gross_amount * sales.confirmed_commission_rate / 100, 2) ELSE 0 END), 0) AS table_total, '
            . 'COALESCE(SUM(CASE WHEN sales.sale_type = \'BGO\' THEN ROUND(sales.gross_amount * sales.confirmed_commission_rate / 100, 2) ELSE 0 END), 0) AS bgo_total',
            false
        )
            ->join('ambassadors', 'ambassadors.id = sales.ambassador_id', 'left')
            ->join('roles', 'roles.id = ambassadors.role_id', 'left')
            ->where('sales.status', 'confirmed');

        if (!empty($filters['ambassador_id'])) {
            $b->where('sales.ambassador_id', $filters['ambassador_id']);
        }
        if (!empty($filters['month'])) {
            $b->where("SUBSTR(sales.date, 1, 7)", $filters['month']);
        }

        $row = $b->get()->getRowArray();

        return [
            'total' => (float) ($row['total'] ?? 0),
            'table' => (float) ($row['table_total'] ?? 0),
            'bgo'   => (float) ($row['bgo_total'] ?? 0),
        ];
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

    private function makeReportBuilder(): SaleModel
    {
        return $this->saleModel
            ->select(
                'sales.*, ambassadors.name as ambassador_name, roles.name as role_name, '
                . 'ROUND(sales.gross_amount * sales.confirmed_commission_rate / 100, 2) as commission_amount',
                false
            )
            ->join('ambassadors', 'ambassadors.id = sales.ambassador_id', 'left')
            ->join('roles', 'roles.id = ambassadors.role_id', 'left')
            ->where('sales.status', 'confirmed')
            ->orderBy('sales.date', 'DESC');
    }

    /**
     * @param array{ambassador_id?: int|string, month?: string} $filters
     */
    private function applyReportFilters(SaleModel $builder, array $filters): void
    {
        if (!empty($filters['ambassador_id'])) {
            $builder->where('sales.ambassador_id', $filters['ambassador_id']);
        }
        if (!empty($filters['month'])) {
            $builder->where("SUBSTR(sales.date, 1, 7)", $filters['month']);
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
