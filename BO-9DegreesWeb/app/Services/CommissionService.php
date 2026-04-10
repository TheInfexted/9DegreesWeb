<?php

namespace App\Services;

use App\Repositories\CommissionRepository;

class CommissionService
{
    public function __construct(
        private CommissionRepository $repo = new CommissionRepository()
    ) {}

    /**
     * Resolve the commission rate to freeze at sale confirmation.
     * Delegates to CommissionRepository which holds the full spec logic.
     */
    public function resolveRate(array $sale, array $ambassador): float
    {
        return $this->repo->computeEffectiveRate((int) $sale['id']);
    }

    public function getReport(array $filters = []): array
    {
        return $this->repo->getReport($filters);
    }

    public function getAvailableMonths(): array
    {
        return $this->repo->getAvailableMonths();
    }
}
