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

    /**
     * @return array{items: list<array<string,mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function listReportPaginated(array $filters, int $page, int $perPage): array
    {
        $perPage  = max(1, min(100, $perPage));
        $total    = $this->repo->countReport($filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));
        $items    = $total === 0 ? [] : $this->repo->findReportPaginated($filters, $page, $perPage);

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @return array{total: float, table: float, bgo: float}
     */
    public function getReportSummary(array $filters): array
    {
        return $this->repo->getReportSummary($filters);
    }

    public function calculateTotalForUser(int $ambassadorId, string $yearMonth): float
    {
        return $this->repo->calculateTotalCommissionForUser($ambassadorId, $yearMonth);
    }

    public function getAvailableMonths(): array
    {
        return $this->repo->getAvailableMonths();
    }
}
