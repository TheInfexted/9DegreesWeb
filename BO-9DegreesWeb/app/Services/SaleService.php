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
            'table_number'  => $data['sale_type'] === 'Table' ? ($data['table_number'] ?? null) : null,
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

        if (isset($allowed['sale_type']) && $allowed['sale_type'] === 'BGO') {
            $allowed['table_number'] = null;
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
        if ($data['sale_type'] === 'Table' && empty($data['table_number'])) {
            throw new \RuntimeException('table_number is required for Table sales.', 422);
        }
    }
}
