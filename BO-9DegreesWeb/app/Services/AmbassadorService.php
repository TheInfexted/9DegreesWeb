<?php

namespace App\Services;

use App\Repositories\AmbassadorRepository;

class AmbassadorService
{
    private const PROTECTED_NAMES    = ['Johnny', 'Unassigned Sales'];
    private const MAX_COMMISSION_RATE = 12.0;

    public function __construct(private AmbassadorRepository $repo = new AmbassadorRepository()) {}

    public function list(array $filters = []): array
    {
        return $this->repo->findAll($filters);
    }

    public function get(int $id): array
    {
        $amb = $this->repo->findById($id);
        if (!$amb) throw new \RuntimeException('Ambassador not found.', 404);
        return $amb;
    }

    public function create(array $data): array
    {
        $this->validateData($data);
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): array
    {
        $amb = $this->repo->findById($id);
        if (!$amb) throw new \RuntimeException('Ambassador not found.', 404);
        if (isset($data['custom_commission_rate'])) {
            $this->validateCommissionRate((float) $data['custom_commission_rate']);
        }
        return $this->repo->update($id, $data);
    }

    public function softDelete(int $id): array
    {
        $amb = $this->repo->findById($id);
        if (!$amb) throw new \RuntimeException('Ambassador not found.', 404);
        if (in_array($amb['name'], self::PROTECTED_NAMES)) {
            throw new \RuntimeException('Cannot deactivate system ambassadors.', 400);
        }
        return $this->repo->update($id, ['status' => 'inactive']);
    }

    private function validateData(array $data): void
    {
        if (empty($data['name'])) throw new \RuntimeException('Name is required.', 422);
        if (empty($data['role_id'])) throw new \RuntimeException('Role is required.', 422);
        if (isset($data['custom_commission_rate'])) {
            $this->validateCommissionRate((float) $data['custom_commission_rate']);
        }
    }

    private function validateCommissionRate(float $rate): void
    {
        if ($rate < 0 || $rate > self::MAX_COMMISSION_RATE) {
            throw new \RuntimeException('Commission rate must be between 0 and 12.', 422);
        }
    }
}
