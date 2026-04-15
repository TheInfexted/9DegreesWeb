<?php

namespace App\Controllers\Api;

use App\Services\CommissionService;
use App\Services\SaleImportService;
use App\Services\SaleService;

class SaleController extends BaseApiController
{
    private SaleService $saleService;
    private CommissionService $commissionService;
    private SaleImportService $saleImportService;

    public function __construct()
    {
        $this->saleService       = new SaleService();
        $this->commissionService = new CommissionService();
        $this->saleImportService = new SaleImportService();
    }

    public function summary(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $filters = array_filter([
                'status'        => $this->request->getGet('status'),
                'ambassador_id' => $this->request->getGet('ambassador_id'),
                'team_id'       => $this->request->getGet('team_id'),
                'month'         => $this->validatedMonth($this->request->getGet('month')),
                'sale_type'     => $this->request->getGet('sale_type'),
            ]);

            return $this->ok($this->saleService->getSummary($filters));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e));
        }
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $filters = array_filter([
                'status'        => $this->request->getGet('status'),
                'ambassador_id' => $this->request->getGet('ambassador_id'),
                'team_id'       => $this->request->getGet('team_id'),
                'month'         => $this->validatedMonth($this->request->getGet('month')),
                'sale_type'     => $this->request->getGet('sale_type'),
            ]);
            $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?? 25)));
            $result  = $this->saleService->listPaginated($filters, $page, $perPage);

            return $this->respond([
                'data' => $result['items'],
                'meta' => $result['meta'],
            ], 200);
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e));
        }
    }

    public function confirmDrafts(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $body    = $this->json();
            $filters = [];
            if (!empty($body['ambassador_id'])) {
                $filters['ambassador_id'] = (int) $body['ambassador_id'];
            }
            if (!empty($body['team_id'])) {
                $filters['team_id'] = (int) $body['team_id'];
            }
            if (!empty($body['month'])) {
                $filters['month'] = $this->validatedMonth((string) $body['month']);
            }
            if (!empty($body['sale_type']) && in_array($body['sale_type'], ['Table', 'BGO'], true)) {
                $filters['sale_type'] = $body['sale_type'];
            }

            return $this->ok($this->saleService->confirmAllDrafts($filters, $this->commissionService));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e));
        }
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

    public function parseImport(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $file = $this->request->getFile('file');
            if (!$file) {
                return $this->badRequest('PDF file is required.');
            }
            $ambassadorId = (int) $this->request->getPost('ambassador_id');
            if ($ambassadorId <= 0) {
                return $this->badRequest('ambassador_id is required.');
            }
            return $this->ok($this->saleImportService->parsePdf($file, $ambassadorId));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e, 400));
        }
    }

    public function commitImport(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $body         = $this->json();
            $ambassadorId = (int) ($body['ambassador_id'] ?? 0);
            if ($ambassadorId <= 0) {
                return $this->badRequest('ambassador_id is required.');
            }
            $decisions = $body['decisions'] ?? null;
            if (!is_array($decisions) || $decisions === []) {
                return $this->badRequest('decisions must be a non-empty array.');
            }
            $createdBy = $this->currentUser()->user_id;

            return $this->ok($this->saleImportService->commit($decisions, $ambassadorId, $createdBy));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e));
        }
    }
}
