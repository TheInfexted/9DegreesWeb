<?php

namespace App\Services;

use App\Models\AmbassadorModel;
use App\Models\TeamModel;

class TeamService
{
    public function __construct(
        private TeamModel $teamModel = new TeamModel(),
        private AmbassadorModel $ambassadorModel = new AmbassadorModel()
    ) {}

    public function list(): array
    {
        $teams = $this->teamModel->findAll();
        foreach ($teams as &$team) {
            $team['leader'] = $this->ambassadorModel
                ->where('ambassadors.team_id', $team['id'])
                ->join('roles', 'roles.id = ambassadors.role_id')
                ->where('roles.name', 'Leader')
                ->where('ambassadors.status', 'active')
                ->first();
        }
        return $teams;
    }

    public function create(array $data): array
    {
        if (empty($data['name'])) {
            throw new \RuntimeException('Team name is required.', 422);
        }
        $id = $this->teamModel->insert(['name' => $data['name'], 'status' => 'active'], true);
        return $this->teamModel->find($id);
    }

    public function update(int $id, array $data): array
    {
        $team = $this->teamModel->find($id);
        if (!$team) throw new \RuntimeException('Team not found.', 404);

        $allowed = array_filter($data, fn($k) => in_array($k, ['name', 'status']), ARRAY_FILTER_USE_KEY);
        $this->teamModel->update($id, $allowed);
        return $this->teamModel->find($id);
    }

    public function delete(int $id): void
    {
        $team = $this->teamModel->find($id);
        if (!$team) throw new \RuntimeException('Team not found.', 404);
        $this->teamModel->delete($id);
    }

    public function assignLeader(int $teamId, int $ambassadorId): array
    {
        $ambassador = $this->ambassadorModel->find($ambassadorId);
        if (!$ambassador) throw new \RuntimeException('Ambassador not found.', 404);

        $role = db_connect()->table('roles')->where('id', $ambassador['role_id'])->get()->getRowArray();
        if (!$role || $role['name'] !== 'Leader') {
            throw new \RuntimeException('Ambassador must have the Leader commission role.', 400);
        }

        $this->ambassadorModel->update($ambassadorId, ['team_id' => $teamId]);
        return $this->teamModel->find($teamId);
    }
}
