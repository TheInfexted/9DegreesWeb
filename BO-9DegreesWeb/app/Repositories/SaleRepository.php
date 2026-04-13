<?php

namespace App\Repositories;

use App\Models\SaleModel;

class SaleRepository
{
    public function __construct(private SaleModel $model = new SaleModel()) {}

    public function countFiltered(array $filters, bool $applyStatusFilter = true): int
    {
        $b = $this->baseSalesOnlyQuery($filters, $applyStatusFilter);

        return (int) $b->countAllResults();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function findPaginated(array $filters, int $page, int $perPage, bool $applyStatusFilter = true): array
    {
        $perPage = max(1, $perPage);
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        return $this->makeListBuilder($filters, $applyStatusFilter)
            ->limit($perPage, $offset)
            ->findAll();
    }

    /**
     * Draft sale IDs matching list filters (ambassador, month, type, team), ignoring any status filter.
     *
     * @return list<int>
     */
    public function findDraftIdsMatchingFilters(array $filters): array
    {
        $b = $this->baseSalesOnlyQuery($filters, false);
        $b->where('status', 'draft');
        $b->select('id');
        $rows = $b->get()->getResultArray();

        return array_map(static fn(array $r): int => (int) $r['id'], $rows);
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

    /**
     * @return array{count: int, gross_total: float}
     */
    public function getAggregateSummary(array $filters): array
    {
        $b = $this->baseSalesOnlyQuery($filters, true);
        $b->select('COUNT(*) AS cnt, COALESCE(SUM(gross_amount), 0) AS gross_total', false);
        $row = $b->get()->getRowArray();

        return [
            'count'       => (int) ($row['cnt'] ?? 0),
            'gross_total' => (float) ($row['gross_total'] ?? 0),
        ];
    }

    private function makeListBuilder(array $filters, bool $applyStatusFilter): SaleModel
    {
        $builder = $this->model
            ->select('sales.*, ambassadors.name as ambassador_name, teams.name as team_name')
            ->join('ambassadors', 'ambassadors.id = sales.ambassador_id', 'left')
            ->join('teams', 'teams.id = sales.team_id', 'left')
            ->orderBy('sales.date', 'DESC');
        $this->applyListFiltersToModel($builder, $filters, $applyStatusFilter);

        return $builder;
    }

    /**
     * Filters only touch `sales` columns — no joins (avoids ambiguous columns in SQLite COUNT subqueries).
     */
    private function baseSalesOnlyQuery(array $filters, bool $applyStatusFilter): \CodeIgniter\Database\BaseBuilder
    {
        $b = $this->model->builder();
        $this->applyListFiltersToBuilder($b, $filters, $applyStatusFilter);

        return $b;
    }

    private function applyListFiltersToModel(SaleModel $builder, array $filters, bool $applyStatusFilter): void
    {
        if ($applyStatusFilter && !empty($filters['status'])) {
            $builder->where('sales.status', $filters['status']);
        }
        if (!empty($filters['ambassador_id'])) {
            $builder->where('sales.ambassador_id', $filters['ambassador_id']);
        }
        if (!empty($filters['team_id'])) {
            $builder->where('sales.team_id', $filters['team_id']);
        }
        if (!empty($filters['month'])) {
            $builder->where("SUBSTR(sales.date, 1, 7)", $filters['month']);
        }
        if (!empty($filters['sale_type'])) {
            $builder->where('sales.sale_type', $filters['sale_type']);
        }
    }

    /** For plain `sales` table query builder (no joins) — unqualified columns avoid SQLite ambiguity. */
    private function applyListFiltersToBuilder(\CodeIgniter\Database\BaseBuilder $b, array $filters, bool $applyStatusFilter): void
    {
        if ($applyStatusFilter && !empty($filters['status'])) {
            $b->where('status', $filters['status']);
        }
        if (!empty($filters['ambassador_id'])) {
            $b->where('ambassador_id', $filters['ambassador_id']);
        }
        if (!empty($filters['team_id'])) {
            $b->where('team_id', $filters['team_id']);
        }
        if (!empty($filters['month'])) {
            $b->where("SUBSTR(date, 1, 7)", $filters['month']);
        }
        if (!empty($filters['sale_type'])) {
            $b->where('sale_type', $filters['sale_type']);
        }
    }
}
