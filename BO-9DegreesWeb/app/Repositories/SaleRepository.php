<?php

namespace App\Repositories;

use App\Models\SaleModel;

class SaleRepository
{
    public function __construct(private SaleModel $model = new SaleModel()) {}

    public function findAll(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $builder = $this->model
            ->select('sales.*, ambassadors.name as ambassador_name, teams.name as team_name')
            ->join('ambassadors', 'ambassadors.id = sales.ambassador_id', 'left')
            ->join('teams', 'teams.id = sales.team_id', 'left')
            ->orderBy('sales.date', 'DESC');

        if (!empty($filters['status']))        $builder->where('sales.status', $filters['status']);
        if (!empty($filters['ambassador_id'])) $builder->where('sales.ambassador_id', $filters['ambassador_id']);
        if (!empty($filters['team_id']))       $builder->where('sales.team_id', $filters['team_id']);
        if (!empty($filters['month']))         $builder->where("SUBSTR(sales.date, 1, 7)", $filters['month']);
        if (!empty($filters['sale_type']))     $builder->where('sales.sale_type', $filters['sale_type']);

        return $builder->paginate($perPage, 'default', $page);
    }

    public function findById(int $id): ?array
    {
        return $this->model
            ->select('sales.*, ambassadors.name as ambassador_name, teams.name as team_name')
            ->join('ambassadors', 'ambassadors.id = sales.ambassador_id', 'left')
            ->join('teams', 'teams.id = sales.team_id', 'left')
            ->find($id);
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
            ->select("SUBSTR(date, 1, 7) as month")
            ->where('status', 'confirmed')
            ->groupBy("SUBSTR(date, 1, 7)")
            ->orderBy('month', 'DESC')
            ->findAll();
    }

    public function getLatestDefaults(): ?array
    {
        return $this->model
            ->select('date, ambassador_id, team_id')
            ->orderBy('created_at', 'DESC')
            ->first();
    }
}
