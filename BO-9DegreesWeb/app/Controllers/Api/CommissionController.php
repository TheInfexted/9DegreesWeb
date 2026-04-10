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
        return $this->ok($this->commissionService->getReport($filters));
    }

    public function months(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->commissionService->getAvailableMonths());
    }
}
