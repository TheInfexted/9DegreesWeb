<?php

namespace App\Services;

use App\Repositories\LeaderboardRepository;

class LeaderboardService
{
    public function __construct(private LeaderboardRepository $repo = new LeaderboardRepository()) {}

    public function getRankings(array $yearMonths): array
    {
        return $this->repo->getRankings($yearMonths);
    }

    public function getAvailableMonths(): array
    {
        return $this->repo->getAvailableMonths();
    }
}
