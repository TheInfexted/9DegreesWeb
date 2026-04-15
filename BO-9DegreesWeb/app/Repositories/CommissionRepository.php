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
     * 2. Table + "Unassigned Sales": ambassador slice 0% (full Table pool goes to owner → Johnny at confirm).
     * 3. Table (otherwise): base rate = ambassador.custom_commission_rate
     * 4. If use_kpi_bonus=1 AND kpi IS NOT NULL AND commission_increase IS NOT NULL:
     *    - (confirmed monthly Table total + this sale amount) >= kpi → rate += commission_increase for this confirm.
     *    - After confirm, syncFrozenCommissionRatesForAmbassadorMonth() updates every confirmed Table sale in that
     *      month so the bonus applies to the whole month once KPI is met (and is stripped if KPI is no longer met).
     * 5. "Unassigned Sales" Table KPI path is skipped (handled by rule 2). BGO Unassigned is still 10%.
     */
    public function computeEffectiveRate(int $saleId): float
    {
        $sale = $this->saleModel->find($saleId);
        if (!$sale) throw new \RuntimeException("Sale {$saleId} not found.", 404);

        if ($sale['sale_type'] === 'BGO') {
            return 10.00;
        }

        if ($sale['sale_type'] === 'Table' && $this->isUnassignedSales((int) $sale['ambassador_id'])) {
            return 0.00;
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

            $totalIncludingCurrent = $confirmedTotal + (float) $sale['gross_amount'];

            if ($totalIncludingCurrent >= (float) $ambassador['kpi']) {
                $baseRate += (float) $ambassador['commission_increase'];
            }
        }

        return round($baseRate, 2);
    }

    /**
     * Frozen rates at confirm: ambassador slice (payouts) + owner remainder on Table pool.
     * Unassigned Sales + Table: ambassador 0%, owner = full pool (12%) — all to Johnny.
     *
     * @return array{ambassador_rate: float, owner_rate: float}
     */
    public function resolveFrozenCommissionRates(int $saleId): array
    {
        $sale = $this->saleModel->find($saleId);
        if (!$sale) {
            throw new \RuntimeException("Sale {$saleId} not found.", 404);
        }

        $ambassadorRate = $this->computeEffectiveRate($saleId);

        if ($sale['sale_type'] === 'BGO') {
            return ['ambassador_rate' => $ambassadorRate, 'owner_rate' => 0.0];
        }

        $pool = (float) config('Commission')->tableCommissionPoolPercent;
        if ($ambassadorRate > $pool) {
            throw new \RuntimeException(
                'Ambassador commission rate exceeds the table commission pool (12%).',
                422
            );
        }

        return [
            'ambassador_rate' => $ambassadorRate,
            'owner_rate'      => round($pool - $ambassadorRate, 2),
        ];
    }

    /**
     * Snapshot of the ambassador base % at confirm time (before KPI bump). Used to keep prior-month sales
     * correct when custom_commission_rate is edited later.
     *
     * @return float BGO → 10; Unassigned Table → 0; Table otherwise → custom_commission_rate
     */
    public function resolveFrozenCommissionBaseRate(int $saleId): float
    {
        $sale = $this->saleModel->find($saleId);
        if (!$sale) {
            throw new \RuntimeException("Sale {$saleId} not found.", 404);
        }

        if ($sale['sale_type'] === 'BGO') {
            return 10.00;
        }

        if ($sale['sale_type'] === 'Table' && $this->isUnassignedSales((int) $sale['ambassador_id'])) {
            return 0.00;
        }

        $amb = $this->ambassadorModel->find((int) $sale['ambassador_id']);
        if (!$amb) {
            throw new \RuntimeException('Ambassador not found.', 404);
        }

        return round((float) $amb['custom_commission_rate'], 2);
    }

    /**
     * Recompute frozen ambassador/owner rates for all confirmed Table sales of an ambassador in YYYY-MM.
     * Unassigned Sales rows are skipped. Uses confirmed_commission_base_rate when set; otherwise infers legacy rows.
     */
    public function syncFrozenCommissionRatesForAmbassadorMonth(int $ambassadorId, string $yearMonth): void
    {
        if ($this->isUnassignedSales($ambassadorId)) {
            return;
        }

        $amb = $this->ambassadorModel->find($ambassadorId);
        if (!$amb) {
            return;
        }

        $sales = $this->saleModel
            ->where('ambassador_id', $ambassadorId)
            ->where('status', 'confirmed')
            ->where('sale_type', 'Table')
            ->where("SUBSTR(date, 1, 7)", $yearMonth)
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($sales === []) {
            return;
        }

        $totalGross = 0.0;
        foreach ($sales as $s) {
            $totalGross += (float) $s['gross_amount'];
        }

        $useKpi = (int) ($amb['use_kpi_bonus'] ?? 0) === 1;
        $kpi    = $amb['kpi'] !== null && $amb['kpi'] !== '' ? (float) $amb['kpi'] : null;
        $inc    = $amb['commission_increase'] !== null && $amb['commission_increase'] !== ''
            ? (float) $amb['commission_increase']
            : null;

        $kpiMet = $useKpi && $kpi !== null && $inc !== null && $totalGross >= $kpi;

        $pool = (float) config('Commission')->tableCommissionPoolPercent;

        foreach ($sales as $sale) {
            $baseRaw = $sale['confirmed_commission_base_rate'] ?? null;
            if ($baseRaw === null || $baseRaw === '') {
                $base = $this->inferLegacyBaseCommission($sale, $amb);
            } else {
                $base = round((float) $baseRaw, 2);
            }

            $ambRate = $base;
            if ($kpiMet) {
                $ambRate += (float) $inc;
            }
            $ambRate = round($ambRate, 2);

            if ($ambRate > $pool) {
                throw new \RuntimeException(
                    'Ambassador commission rate exceeds the table commission pool (12%).',
                    422
                );
            }

            $ownerRate = round($pool - $ambRate, 2);

            $this->saleModel->update((int) $sale['id'], [
                'confirmed_commission_rate'       => $ambRate,
                'confirmed_owner_commission_rate' => $ownerRate,
            ]);
        }
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

        $johnnyScope = $this->johnnyReportFilterAmbassadorId($filters);
        $builder     = $this->makeReportBuilder();
        $this->applyReportFilters($builder, $filters);
        // Do not use findAll() here: BaseModel::doFindAll() reapplies limit(0,0) and drops the chained limit.
        $builder->limit($perPage, $offset);

        $rows = $builder->get()->getResultArray();

        return $johnnyScope !== null
            ? $this->applyJohnnyFilteredReportRows($rows, $johnnyScope)
            : $rows;
    }

    /**
     * Commission report: confirmed sales with frozen rates and commission amounts (full list).
     *
     * @return list<array<string,mixed>>
     */
    public function getReport(array $filters = []): array
    {
        $johnnyScope = $this->johnnyReportFilterAmbassadorId($filters);
        $builder     = $this->makeReportBuilder();
        $this->applyReportFilters($builder, $filters);

        $rows = $builder->get()->getResultArray();

        return $johnnyScope !== null
            ? $this->applyJohnnyFilteredReportRows($rows, $johnnyScope)
            : $rows;
    }

    /**
     * @return array{total: float, table: float, bgo: float}
     */
    public function getReportSummary(array $filters): array
    {
        $johnnyScopeId = $this->johnnyReportFilterAmbassadorId($filters);
        $pool          = (float) config('Commission')->tableCommissionPoolPercent;
        $lineAmb       = 'ROUND(gross_amount * confirmed_commission_rate / 100, 2)';
        if ($johnnyScopeId !== null) {
            $jid       = (int) $johnnyScopeId;
            $lineOwner = 'CASE WHEN sale_type = \'Table\' AND ambassador_id != ' . $jid
                . ' AND confirmed_owner_commission_rate IS NULL '
                . 'THEN ROUND(gross_amount * (CASE WHEN ' . $pool . ' - confirmed_commission_rate > 0 THEN '
                . $pool . ' - confirmed_commission_rate ELSE 0 END) / 100, 2) '
                . 'ELSE ROUND(gross_amount * COALESCE(confirmed_owner_commission_rate, 0) / 100, 2) END';
            $effectiveLine = 'CASE WHEN ambassador_id != ' . (int) $johnnyScopeId
                . ' THEN ' . $lineOwner . ' ELSE ' . $lineAmb . ' END';
        } else {
            $effectiveLine = $lineAmb;
        }

        $b = $this->saleModel->builder();
        $b->select(
            'COALESCE(SUM(' . $effectiveLine . '), 0) AS total, '
            . 'COALESCE(SUM(CASE WHEN sale_type = \'Table\' THEN (' . $effectiveLine . ') ELSE 0 END), 0) AS table_total, '
            . 'COALESCE(SUM(CASE WHEN sale_type = \'BGO\' THEN (' . $effectiveLine . ') ELSE 0 END), 0) AS bgo_total',
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
            ->whereNotIn("{$tA}.name", ['Johnny', 'Unassigned Sales'])
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

    /**
     * When the report is filtered to Johnny, remap rate/amount per row in PHP so owner slices
     * from other ambassadors' Table sales always show pool − ambassador (and survive driver/ORM quirks).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function applyJohnnyFilteredReportRows(array $rows, int $johnnyId): array
    {
        foreach ($rows as $i => $row) {
            $rows[$i] = $this->presentRowForJohnnyCommissionReport($row, $johnnyId);
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function presentRowForJohnnyCommissionReport(array $row, int $johnnyId): array
    {
        $aid = (int) ($row['ambassador_id'] ?? 0);
        if ($aid !== $johnnyId && ($row['sale_type'] ?? '') === 'Table') {
            $ownerRate = $this->effectiveTableOwnerRatePercent($row);
            $gross     = (float) ($row['gross_amount'] ?? 0);
            $row['report_commission_rate']  = $ownerRate;
            $row['commission_amount']       = round($gross * $ownerRate / 100, 2);
            $row['owner_commission_amount'] = round($gross * $ownerRate / 100, 2);
        } else {
            $ambRate = (float) ($row['confirmed_commission_rate'] ?? 0);
            $gross   = (float) ($row['gross_amount'] ?? 0);
            $row['report_commission_rate'] = $ambRate;
            $row['commission_amount']      = round($gross * $ambRate / 100, 2);
        }

        return $row;
    }

    /**
     * Stored owner %, or pool − ambassador when owner was not persisted (legacy rows).
     */
    private function effectiveTableOwnerRatePercent(array $row): float
    {
        $raw = $row['confirmed_owner_commission_rate'] ?? null;
        if ($raw !== null && $raw !== '') {
            return round((float) $raw, 2);
        }

        $pool = (float) config('Commission')->tableCommissionPoolPercent;
        $amb  = (float) ($row['confirmed_commission_rate'] ?? 0);

        return max(0.0, round($pool - $amb, 2));
    }

    private function makeReportBuilder(): SaleModel
    {
        $db = $this->saleModel->db;
        $tS = $this->prefixedSalesTable();
        $tA = $db->prefixTable('ambassadors');
        $tR = $db->prefixTable('roles');

        $commissionExpr = 'ROUND(' . $tS . '.gross_amount * ' . $tS . '.confirmed_commission_rate / 100, 2)';
        $reportRateExpr = $tS . '.confirmed_commission_rate';

        return $this->saleModel
            ->select(
                "{$tS}.*, {$tA}.name as ambassador_name, {$tR}.name as role_name, "
                . "{$commissionExpr} as commission_amount, "
                . "ROUND({$tS}.gross_amount * COALESCE({$tS}.confirmed_owner_commission_rate, 0) / 100, 2) as owner_commission_amount, "
                . "{$reportRateExpr} as report_commission_rate",
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
            $this->applyAmbassadorScopeToReportQuery($builder, (int) $filters['ambassador_id'], $t);
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
            $this->applyAmbassadorScopeToSalesBuilder($b, (int) $filters['ambassador_id']);
        }
        if (!empty($filters['month'])) {
            $b->where("SUBSTR(date, 1, 7)", $filters['month']);
        }
    }

    /**
     * When the report is filtered to Johnny, include Table sales by other ambassadors
     * where Johnny earns the owner remainder (confirmed_owner_commission_rate).
     */
    private function johnnyReportFilterAmbassadorId(array $filters): ?int
    {
        if (empty($filters['ambassador_id'])) {
            return null;
        }
        $wantId   = (int) $filters['ambassador_id'];
        $johnnyId = $this->tryJohnnyAmbassadorId();

        return ($johnnyId !== null && $wantId === $johnnyId) ? $johnnyId : null;
    }

    private function tryJohnnyAmbassadorId(): ?int
    {
        $johnny = $this->ambassadorModel->where('name', 'Johnny')->first();

        return $johnny ? (int) $johnny['id'] : null;
    }

    private function applyAmbassadorScopeToReportQuery(SaleModel $builder, int $ambassadorId, string $tSales): void
    {
        $johnnyId = $this->tryJohnnyAmbassadorId();
        if ($johnnyId !== null && $ambassadorId === $johnnyId) {
            $builder->groupStart()
                ->where("{$tSales}.ambassador_id", $johnnyId)
                ->orGroupStart()
                ->where("{$tSales}.sale_type", 'Table')
                ->where("{$tSales}.ambassador_id !=", $johnnyId)
                ->groupEnd()
                ->groupEnd();

            return;
        }
        $builder->where("{$tSales}.ambassador_id", $ambassadorId);
    }

    private function applyAmbassadorScopeToSalesBuilder(BaseBuilder $b, int $ambassadorId): void
    {
        $johnnyId = $this->tryJohnnyAmbassadorId();
        if ($johnnyId !== null && $ambassadorId === $johnnyId) {
            $b->groupStart()
                ->where('ambassador_id', $johnnyId)
                ->orGroupStart()
                ->where('sale_type', 'Table')
                ->where('ambassador_id !=', $johnnyId)
                ->groupEnd()
                ->groupEnd();

            return;
        }
        $b->where('ambassador_id', $ambassadorId);
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

    /**
     * Best-effort base % for rows confirmed before confirmed_commission_base_rate existed.
     */
    private function inferLegacyBaseCommission(array $sale, array $amb): float
    {
        $confirmed = round((float) ($sale['confirmed_commission_rate'] ?? 0), 2);
        $custom    = round((float) ($amb['custom_commission_rate'] ?? 0), 2);
        $inc       = $amb['commission_increase'] !== null && $amb['commission_increase'] !== ''
            ? round((float) $amb['commission_increase'], 2)
            : null;

        $useKpi = (int) ($amb['use_kpi_bonus'] ?? 0) === 1;

        if ($useKpi && $inc !== null && abs($confirmed - ($custom + $inc)) < 0.001) {
            return $custom;
        }
        if (abs($confirmed - $custom) < 0.001) {
            return $custom;
        }

        return $confirmed;
    }
}
