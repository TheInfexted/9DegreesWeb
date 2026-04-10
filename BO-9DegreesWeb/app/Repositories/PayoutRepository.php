<?php

namespace App\Repositories;

use App\Models\PayoutModel;

class PayoutRepository
{
    public function __construct(private PayoutModel $model = new PayoutModel()) {}

    public function findAll(array $filters = []): array
    {
        $builder = $this->model
            ->select('payouts.*, ambassadors.name as ambassador_name, ambassadors.full_name,
                      ambassadors.bank_name, ambassadors.bank_account_number, ambassadors.bank_owner_name,
                      roles.name as role_name, teams.name as team_name')
            ->join('ambassadors', 'ambassadors.id = payouts.ambassador_id', 'left')
            ->join('roles', 'roles.id = ambassadors.role_id', 'left')
            ->join('teams', 'teams.id = ambassadors.team_id', 'left')
            ->orderBy('payouts.month', 'DESC');

        if (!empty($filters['month'])) $builder->where("SUBSTR(payouts.month, 1, 7)", $filters['month']);
        if (isset($filters['paid'])) {
            $filters['paid'] ? $builder->where('payouts.paid_at IS NOT NULL', null, false)
                             : $builder->where('payouts.paid_at IS NULL', null, false);
        }

        return $builder->findAll();
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
}
