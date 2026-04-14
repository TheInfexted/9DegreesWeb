<?php

namespace App\Controllers\Api;

use App\Services\LeaderboardService;

class LeaderboardController extends BaseApiController
{
    private LeaderboardService $leaderboardService;

    public function __construct()
    {
        $this->leaderboardService = new LeaderboardService();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $months = $this->request->getGet('months') ?? [];
        if (is_string($months)) $months = [$months];
        if (!is_array($months)) $months = [];
        $months = array_values(array_filter(
            $months,
            static fn($m): bool => is_string($m) && preg_match('/^\d{4}-\d{2}$/', $m) === 1
        ));

        return $this->ok($this->leaderboardService->getRankings($months));
    }

    public function months(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->leaderboardService->getAvailableMonths());
    }
}
