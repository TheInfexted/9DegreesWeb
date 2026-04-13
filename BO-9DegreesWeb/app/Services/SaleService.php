<?php

namespace App\Services;

use App\Repositories\AmbassadorRepository;
use App\Repositories\SaleRepository;

class SaleService
{
    public function __construct(
        private SaleRepository $saleRepo = new SaleRepository(),
        private AmbassadorRepository $ambassadorRepo = new AmbassadorRepository()
    ) {}

    public function list(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        return $this->saleRepo->findAll($filters, $page, $perPage);
    }

    public function get(int $id): array
    {
        $sale = $this->saleRepo->findById($id);
        if (!$sale) throw new \RuntimeException('Sale not found.', 404);
        return $sale;
    }

    public function create(array $data, int $createdBy): array
    {
        $this->validateSaleData($data);
        $ambassador = $this->ambassadorRepo->findById((int) $data['ambassador_id']);
        if (!$ambassador) throw new \RuntimeException('Ambassador not found.', 404);

        $record = [
            'ambassador_id' => (int) $data['ambassador_id'],
            'team_id'       => $ambassador['team_id'],
            'date'          => $data['date'],
            'sale_type'     => $data['sale_type'],
            'table_number'  => $this->normalizeTableNumber($data['table_number'] ?? null),
            'gross_amount'  => (float) $data['gross_amount'],
            'status'        => 'draft',
            'remarks'       => $data['remarks'] ?? null,
            'created_by'    => $createdBy,
        ];

        return $this->saleRepo->create($record);
    }

    public function update(int $id, array $data): array
    {
        $sale = $this->saleRepo->findById($id);
        if (!$sale) throw new \RuntimeException('Sale not found.', 404);
        if ($sale['status'] === 'void') throw new \RuntimeException('Cannot edit a voided sale.', 400);

        $allowed = array_intersect_key($data, array_flip([
            'ambassador_id', 'date', 'sale_type', 'table_number', 'gross_amount', 'remarks',
        ]));

        if (array_key_exists('table_number', $allowed)) {
            $allowed['table_number'] = $this->normalizeTableNumber($allowed['table_number']);
        }

        $effectiveType  = $allowed['sale_type'] ?? $sale['sale_type'];
        $effectiveTable = array_key_exists('table_number', $allowed)
            ? $allowed['table_number']
            : $this->normalizeTableNumber($sale['table_number'] ?? null);
        if ($effectiveType === 'Table' && $effectiveTable === null) {
            throw new \RuntimeException('table_number is required for Table sales.', 422);
        }

        return $this->saleRepo->update($id, $allowed);
    }

    public function delete(int $id): void
    {
        $sale = $this->saleRepo->findById($id);
        if (!$sale) throw new \RuntimeException('Sale not found.', 404);
        if ($sale['status'] !== 'draft') throw new \RuntimeException('Only draft sales can be deleted.', 400);
        $this->saleRepo->delete($id);
    }

    public function confirm(int $id, CommissionService $commissionService): array
    {
        $sale = $this->saleRepo->findById($id);
        if (!$sale) throw new \RuntimeException('Sale not found.', 404);
        if ($sale['status'] !== 'draft') throw new \RuntimeException('Only draft sales can be confirmed.', 400);

        $ambassador = $this->ambassadorRepo->findById($sale['ambassador_id']);
        $rate = $commissionService->resolveRate($sale, $ambassador);

        return $this->saleRepo->update($id, [
            'status'                    => 'confirmed',
            'confirmed_commission_rate' => $rate,
            'confirmed_at'              => date('Y-m-d H:i:s'),
        ]);
    }

    public function void(int $id): array
    {
        $sale = $this->saleRepo->findById($id);
        if (!$sale) throw new \RuntimeException('Sale not found.', 404);
        if ($sale['status'] === 'void') throw new \RuntimeException('Sale is already voided.', 400);

        return $this->saleRepo->update($id, [
            'status'                    => 'void',
            'confirmed_commission_rate' => null,
            'confirmed_at'              => null,
        ]);
    }

    public function getAvailableMonths(): array
    {
        return $this->saleRepo->getAvailableMonths();
    }

    public function getLatestDefaults(): ?array
    {
        return $this->saleRepo->getLatestDefaults();
    }

    private function validateSaleData(array $data): void
    {
        if (empty($data['ambassador_id'])) throw new \RuntimeException('ambassador_id is required.', 422);
        if (empty($data['date']))          throw new \RuntimeException('date is required.', 422);
        if (empty($data['sale_type']) || !in_array($data['sale_type'], ['Table', 'BGO'])) {
            throw new \RuntimeException('sale_type must be Table or BGO.', 422);
        }
        if (empty($data['gross_amount']) || (float) $data['gross_amount'] <= 0) {
            throw new \RuntimeException('gross_amount must be greater than 0.', 422);
        }
        if ($data['sale_type'] === 'Table') {
            $tn = isset($data['table_number']) ? trim((string) $data['table_number']) : '';
            if ($tn === '') {
                throw new \RuntimeException('table_number is required for Table sales.', 422);
            }
        }
    }

    private function normalizeTableNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
