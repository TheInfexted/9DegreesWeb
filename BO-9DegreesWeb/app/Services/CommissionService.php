<?php

namespace App\Services;

use App\Repositories\AmbassadorRepository;
use App\Repositories\SaleRepository;

/**
 * Commission rate resolution and commission calculations.
 * Full implementation in Task 7 — this stub allows Task 6 to compile.
 */
class CommissionService
{
    public function __construct(
        private SaleRepository $saleRepo = new SaleRepository(),
        private AmbassadorRepository $ambassadorRepo = new AmbassadorRepository()
    ) {}

    /**
     * Resolve the commission rate to freeze at sale confirmation.
     * Rules (full logic implemented in Task 7):
     *   1. BGO sale → always 10%
     *   2. ambassador is Unassigned Sales → 0%
     *   3. use_kpi_bonus + KPI met → role_rate + commission_increase
     *   4. custom_commission_rate > 0 → use custom rate
     *   5. fallback → role commission_rate
     */
    public function resolveRate(array $sale, array $ambassador): float
    {
        // Task 7 will expand this
        return (float) ($ambassador['custom_commission_rate'] ?? 0);
    }

    public function getCommissionsForMonth(string $month, array $filters = []): array
    {
        // Task 7 full implementation
        return [];
    }

    public function getAvailableMonths(): array
    {
        return $this->saleRepo->getAvailableMonths();
    }
}
