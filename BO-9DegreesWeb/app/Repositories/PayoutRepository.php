<?php

namespace App\Repositories;

use App\Models\PayoutModel;

class PayoutRepository
{
    public function __construct(private PayoutModel $model = new PayoutModel()) {}

    public function countFiltered(array $filters): int
    {
        return $this->makeListBuilder($filters)->countAllResults(false);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function findPaginated(array $filters, int $page, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        return $this->makeListBuilder($filters)
            ->limit($perPage, $offset)
            ->findAll();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function findAll(array $filters = []): array
    {
        return $this->makeListBuilder($filters)->findAll();
    }

    public function findById(int $id): ?array
    {
        return $this->model
            ->select('payouts.*, ambassadors.name as ambassador_name, ambassadors.full_name,
                      ambassadors.ic, ambassadors.bank_name, ambassadors.bank_account_number,
                      ambassadors.bank_owner_name, roles.name as role_name, teams.name as team_name')
            ->join('ambassadors', 'ambassadors.id = payouts.ambassador_id', 'left')
            ->join('roles', 'roles.id = ambassadors.role_id', 'left')
            ->join('teams', 'teams.id = ambassadors.team_id', 'left')
            ->find($id);
    }

    public function findByAmbassadorAndMonth(int $ambassadorId, string $month): ?array
    {
        return $this->model->where('ambassador_id', $ambassadorId)->where('month', $month)->first();
    }

    public function create(array $data): array
    {
        $id = $this->model->insert($data, true);
        return $this->findById($id);
    }

    public function update(int $id, array $data): array
    {
        $this->model->update($id, $data);
        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $this->model->delete($id);
    }

    public function getAvailableMonths(): array
    {
        return $this->model
            ->select("SUBSTR(month, 1, 7) as month")
            ->groupBy("SUBSTR(month, 1, 7)")
            ->orderBy('month', 'DESC')
            ->findAll();
    }

    /**
     * @return array{count: int, commission_total: float}
     */
    public function getAggregateSummary(array $filters): array
    {
        $b = $this->model->builder();
        if (!empty($filters['month'])) {
            $b->where("SUBSTR(month, 1, 7)", $filters['month']);
        }
        if (isset($filters['paid'])) {
            $filters['paid'] ? $b->where('paid_at IS NOT NULL', null, false)
                             : $b->where('paid_at IS NULL', null, false);
        }
        $b->select('COUNT(*) AS cnt, COALESCE(SUM(total_commission), 0) AS commission_total', false);
        $row = $b->get()->getRowArray();

        return [
            'count'             => (int) ($row['cnt'] ?? 0),
            'commission_total'  => (float) ($row['commission_total'] ?? 0),
        ];
    }

    private function makeListBuilder(array $filters): PayoutModel
    {
        $builder = $this->model
            ->select('payouts.*, ambassadors.name as ambassador_name, ambassadors.full_name,
                      ambassadors.bank_name, ambassadors.bank_account_number, ambassadors.bank_owner_name,
                      roles.name as role_name, teams.name as team_name')
            ->join('ambassadors', 'ambassadors.id = payouts.ambassador_id', 'left')
            ->join('roles', 'roles.id = ambassadors.role_id', 'left')
            ->join('teams', 'teams.id = ambassadors.team_id', 'left')
            ->orderBy('payouts.month', 'DESC');

        if (!empty($filters['month'])) {
            $builder->where("SUBSTR(payouts.month, 1, 7)", $filters['month']);
        }
        if (isset($filters['paid'])) {
            $filters['paid'] ? $builder->where('payouts.paid_at IS NOT NULL', null, false)
                             : $builder->where('payouts.paid_at IS NULL', null, false);
        }

        return $builder;
    }
}
