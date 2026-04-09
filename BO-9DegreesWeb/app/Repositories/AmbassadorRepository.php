<?php

namespace App\Repositories;

use App\Models\AmbassadorModel;

class AmbassadorRepository
{
    public function __construct(private AmbassadorModel $model = new AmbassadorModel()) {}

    public function findAll(array $filters = []): array
    {
        $builder = $this->model->select('ambassadors.*, roles.name as role_name, teams.name as team_name')
            ->join('roles', 'roles.id = ambassadors.role_id', 'left')
            ->join('teams', 'teams.id = ambassadors.team_id', 'left');

        if (!empty($filters['status'])) {
            $builder->where('ambassadors.status', $filters['status']);
        }
        if (!empty($filters['name'])) {
            $builder->like('ambassadors.name', $filters['name']);
        }

        return $builder->findAll();
    }

    public function findById(int $id): ?array
    {
        return $this->model->select('ambassadors.*, roles.name as role_name, teams.name as team_name')
            ->join('roles', 'roles.id = ambassadors.role_id', 'left')
            ->join('teams', 'teams.id = ambassadors.team_id', 'left')
            ->find($id);
    }

    public function findByName(string $name): ?array
    {
        return $this->model->where('name', $name)->first();
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
}
