<?php

namespace App\Controllers\Api;

use App\Services\CommissionService;
use App\Services\SaleService;

class SaleController extends BaseApiController
{
    private SaleService $saleService;
    private CommissionService $commissionService;

    public function __construct()
    {
        $this->saleService       = new SaleService();
        $this->commissionService = new CommissionService();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $filters = array_filter([
            'status'        => $this->request->getGet('status'),
            'ambassador_id' => $this->request->getGet('ambassador_id'),
            'team_id'       => $this->request->getGet('team_id'),
            'month'         => $this->request->getGet('month'),
            'sale_type'     => $this->request->getGet('sale_type'),
        ]);
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 50);
        return $this->ok($this->saleService->list($filters, $page, $perPage));
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->saleService->get((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $createdBy = $this->currentUser()->user_id;
            return $this->created($this->saleService->create($this->json(), $createdBy));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->saleService->update((int) $id, $this->json()));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $this->saleService->delete((int) $id);
            return $this->noContent();
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function confirm($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->saleService->confirm((int) $id, $this->commissionService));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function void($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->saleService->void((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function months(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->saleService->getAvailableMonths());
    }

    public function latestDefaults(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->saleService->getLatestDefaults());
    }
}
