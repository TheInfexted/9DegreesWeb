<?php

namespace App\Services;

use App\Repositories\AmbassadorRepository;
use App\Repositories\SaleRepository;
use Config\Database;

class SaleService
{
    public function __construct(
        private SaleRepository $saleRepo = new SaleRepository(),
        private AmbassadorRepository $ambassadorRepo = new AmbassadorRepository()
    ) {}

    /**
     * @return array{items: list<array<string,mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function listPaginated(array $filters, int $page, int $perPage): array
    {
        $perPage  = max(1, min(100, $perPage));
        $total    = $this->saleRepo->countFiltered($filters, true);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));
        $items    = $total === 0 ? [] : $this->saleRepo->findPaginated($filters, $page, $perPage, true);

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @return array{count: int, gross_total: float}
     */
    public function getSummary(array $filters): array
    {
        return $this->saleRepo->getAggregateSummary($filters);
    }

    /**
     * Confirm every draft sale matching scope filters (ambassador, team, month, sale type). Status filter is ignored.
     *
     * @return array{confirmed: int, failed: list<array{id:int,message:string}>}
     */
    public function confirmAllDrafts(array $scopeFilters, CommissionService $commissionService): array
    {
        $ids = $this->saleRepo->findDraftIdsMatchingFilters($scopeFilters);
        sort($ids);

        $confirmed = 0;
        $failed    = [];

        foreach ($ids as $id) {
            try {
                $this->confirm($id, $commissionService);
                $confirmed++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'id'      => $id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return ['confirmed' => $confirmed, 'failed' => $failed];
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

        if (array_key_exists('ambassador_id', $allowed)) {
            $ambassador = $this->ambassadorRepo->findById((int) $allowed['ambassador_id']);
            if (!$ambassador) throw new \RuntimeException('Ambassador not found.', 404);
        }

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

        $db = Database::connect();
        $db->transStart();
        try {
            $rates = $commissionService->resolveFrozenCommissionRates($id);
            $base  = $commissionService->resolveFrozenCommissionBaseRate($id);

            $ok = $this->saleRepo->confirmIfDraft($id, [
                'status'                            => 'confirmed',
                'confirmed_commission_rate'         => $rates['ambassador_rate'],
                'confirmed_owner_commission_rate'   => $rates['owner_rate'],
                'confirmed_commission_base_rate'      => $base,
                'confirmed_at'                      => date('Y-m-d H:i:s'),
            ]);
            if (!$ok) {
                $db->transRollback();
                throw new \RuntimeException('Sale is no longer in draft status.', 409);
            }

            $yearMonth = substr((string) $sale['date'], 0, 7);
            $commissionService->syncFrozenCommissionRatesForAmbassadorMonth((int) $sale['ambassador_id'], $yearMonth);

            if ($db->transComplete() === false) {
                throw new \RuntimeException('Could not confirm sale.', 500);
            }

            $updated = $this->saleRepo->findById($id);
            if (!$updated) throw new \RuntimeException('Sale not found after confirm.', 500);
            return $updated;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function void(int $id, CommissionService $commissionService): array
    {
        $sale = $this->saleRepo->findById($id);
        if (!$sale) throw new \RuntimeException('Sale not found.', 404);
        if ($sale['status'] === 'void') throw new \RuntimeException('Sale is already voided.', 400);

        $wasConfirmed = $sale['status'] === 'confirmed';
        $syncTable    = $sale['sale_type'] === 'Table' && $wasConfirmed;
        $ambassadorId = (int) $sale['ambassador_id'];
        $yearMonth    = substr((string) $sale['date'], 0, 7);

        $db = Database::connect();
        $db->transStart();
        try {
            $updated = $this->saleRepo->update($id, [
                'status'                            => 'void',
                'confirmed_commission_rate'         => null,
                'confirmed_owner_commission_rate'   => null,
                'confirmed_commission_base_rate'    => null,
                'confirmed_at'                      => null,
            ]);

            if ($syncTable) {
                $commissionService->syncFrozenCommissionRatesForAmbassadorMonth($ambassadorId, $yearMonth);
            }

            if ($db->transComplete() === false) {
                throw new \RuntimeException('Could not void sale.', 500);
            }

            return $updated;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
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
