<?php

namespace App\Controllers\Api;

use App\Services\CommissionService;

class CommissionController extends BaseApiController
{
    private CommissionService $commissionService;

    public function __construct()
    {
        $this->commissionService = new CommissionService();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $filters = array_filter([
            'ambassador_id' => $this->request->getGet('ambassador_id'),
            'month'         => $this->request->getGet('month'),
        ]);
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?? 25)));
        $result  = $this->commissionService->listReportPaginated($filters, $page, $perPage);

        return $this->respond([
            'data' => $result['items'],
            'meta' => $result['meta'],
        ], 200);
    }

    public function summary(): \CodeIgniter\HTTP\ResponseInterface
    {
        $filters = array_filter([
            'ambassador_id' => $this->request->getGet('ambassador_id'),
            'month'         => $this->request->getGet('month'),
        ]);

        return $this->ok($this->commissionService->getReportSummary($filters));
    }

    public function months(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->commissionService->getAvailableMonths());
    }
}
