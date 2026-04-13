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

    /**
     * @return array{items: list<array<string,mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function listPaginated(array $filters, int $page, int $perPage): array
    {
        $perPage  = max(1, min(100, $perPage));
        $total    = $this->repo->countFiltered($filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));
        $items    = $total === 0 ? [] : $this->repo->findPaginated($filters, $page, $perPage);

        return [
            'items' => $items,
            'meta'  => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'last_page'  => $lastPage,
            ],
        ];
    }

    public function get(int $id): array
    {
        $amb = $this->repo->findById($id);
        if (!$amb) throw new \RuntimeException('Ambassador not found.', 404);
        return $amb;
    }

    public function create(array $data): array
    {
        $data = $this->normalizePayload($data, null);
        $this->validateData($data);
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): array
    {
        $amb = $this->repo->findById($id);
        if (!$amb) throw new \RuntimeException('Ambassador not found.', 404);
        $data = $this->normalizePayload($data, $amb);
        if (isset($data['custom_commission_rate'])) {
            $this->validateCommissionRate((float) $data['custom_commission_rate']);
        }
        $this->validateTableCommissionCap($data, $amb);

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

    /**
     * HTML selects send ""; empty team must be NULL, not 0 (FK to teams fails).
     */
    /**
     * @param array<string,mixed>|null $existing Current row for partial updates (merge KPI fields when judging use_kpi_bonus).
     */
    private function normalizePayload(array $data, ?array $existing): array
    {
        if (array_key_exists('team_id', $data)) {
            $v = $data['team_id'];
            if ($v === '' || $v === null || $v === false || $v === '0' || $v === 0) {
                $data['team_id'] = null;
            } else {
                $tid = filter_var($v, FILTER_VALIDATE_INT);
                $data['team_id'] = ($tid !== false && $tid > 0) ? $tid : null;
            }
        }

        foreach (['kpi', 'commission_increase'] as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === '' || $data[$key] === null)) {
                $data[$key] = null;
            }
        }

        if (array_key_exists('use_kpi_bonus', $data)) {
            $kpi = array_key_exists('kpi', $data)
                ? $data['kpi']
                : ($existing['kpi'] ?? null);
            $inc = array_key_exists('commission_increase', $data)
                ? $data['commission_increase']
                : ($existing['commission_increase'] ?? null);
            $kpiOk = $kpi !== null && $kpi !== '';
            $incOk = $inc !== null && $inc !== '';
            if (!$kpiOk || !$incOk) {
                $data['use_kpi_bonus'] = 0;
            } else {
                $v                     = $data['use_kpi_bonus'];
                $data['use_kpi_bonus'] = ($v === true || $v === 1 || $v === '1' || $v === 1.0) ? 1 : 0;
            }
        }

        return $data;
    }

    private function validateData(array $data): void
    {
        if (empty($data['name'])) throw new \RuntimeException('Name is required.', 422);
        if (empty($data['role_id'])) throw new \RuntimeException('Role is required.', 422);
        if (isset($data['custom_commission_rate'])) {
            $this->validateCommissionRate((float) $data['custom_commission_rate']);
        }
        $this->validateTableCommissionCap($data, null);
    }

    private function validateCommissionRate(float $rate): void
    {
        if ($rate < 0 || $rate > self::MAX_COMMISSION_RATE) {
            throw new \RuntimeException('Commission rate must be between 0 and 12.', 422);
        }
    }

    /**
     * Table pool is 12%; ambassador rate + KPI bump must not exceed it.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $existing
     */
    private function validateTableCommissionCap(array $data, ?array $existing): void
    {
        $base = isset($data['custom_commission_rate'])
            ? (float) $data['custom_commission_rate']
            : (float) ($existing['custom_commission_rate'] ?? 0);

        $incRaw = array_key_exists('commission_increase', $data)
            ? $data['commission_increase']
            : ($existing['commission_increase'] ?? null);
        $inc = ($incRaw !== null && $incRaw !== '')
            ? (float) $incRaw
            : null;

        $useKpi = array_key_exists('use_kpi_bonus', $data)
            ? (int) $data['use_kpi_bonus']
            : (int) ($existing['use_kpi_bonus'] ?? 0);

        if ($useKpi === 1 && $inc !== null && $base + $inc > self::MAX_COMMISSION_RATE) {
            throw new \RuntimeException('Base commission plus KPI bonus cannot exceed 12%.', 422);
        }
    }
}
