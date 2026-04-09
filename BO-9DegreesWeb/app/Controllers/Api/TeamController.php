<?php

namespace App\Controllers\Api;

use App\Services\TeamService;

class TeamController extends BaseApiController
{
    public function __construct(private TeamService $service = new TeamService()) {}

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->service->list());
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $teams = $this->service->list();
            $team  = array_values(array_filter($teams, fn($t) => $t['id'] == $id))[0] ?? null;
            return $team ? $this->ok($team) : $this->notFound('Team not found.');
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->created($this->service->create($this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->service->update((int) $id, $this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $this->service->delete((int) $id);
            return $this->noContent();
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function assignLeader($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $ambassadorId = $this->json()['ambassador_id'] ?? null;
            if (!$ambassadorId) return $this->badRequest('ambassador_id is required.');
            return $this->ok($this->service->assignLeader((int) $id, (int) $ambassadorId));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }
}
