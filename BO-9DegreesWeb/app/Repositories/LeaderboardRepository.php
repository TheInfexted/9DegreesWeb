<?php

namespace App\Repositories;

use App\Models\SaleModel;

class LeaderboardRepository
{
    public function __construct(private SaleModel $saleModel = new SaleModel()) {}

    public function getRankings(array $yearMonths): array
    {
        if (empty($yearMonths)) return [];

        $rows = $this->saleModel
            ->select('sales.ambassador_id, sales.gross_amount, ambassadors.name as ambassador_name,
                      roles.name as role_name, teams.name as team_name')
            ->join('ambassadors', 'ambassadors.id = sales.ambassador_id')
            ->join('roles', 'roles.id = ambassadors.role_id')
            ->join('teams', 'teams.id = ambassadors.team_id', 'left')
            ->where('sales.status', 'confirmed')
            ->where('roles.name !=', 'External Partner')
            ->whereNotIn('ambassadors.name', ['Johnny', 'Unassigned Sales'])
            ->where("SUBSTR(date, 1, 7) IN ('" . implode("','", $yearMonths) . "')", null, false)
            ->get()->getResultArray();

        // Aggregate in PHP to avoid DB-level GROUP BY compatibility issues
        $aggregated = [];
        foreach ($rows as $row) {
            $id = $row['ambassador_id'];
            if (!isset($aggregated[$id])) {
                $aggregated[$id] = [
                    'ambassador_id'   => $id,
                    'ambassador_name' => $row['ambassador_name'],
                    'role_name'       => $row['role_name'],
                    'team_name'       => $row['team_name'],
                    'sale_count'      => 0,
                    'total_amount'    => 0.0,
                ];
            }
            $aggregated[$id]['sale_count']++;
            $aggregated[$id]['total_amount'] += (float) $row['gross_amount'];
        }

        usort($aggregated, fn($a, $b) =>
            $b['total_amount'] <=> $a['total_amount'] ?: $a['sale_count'] <=> $b['sale_count']
        );

        return array_values($aggregated);
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
}
