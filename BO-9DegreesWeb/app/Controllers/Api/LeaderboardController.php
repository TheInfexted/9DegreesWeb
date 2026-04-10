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
        return $this->ok($this->leaderboardService->getRankings($months));
    }

    public function months(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->leaderboardService->getAvailableMonths());
    }
}
