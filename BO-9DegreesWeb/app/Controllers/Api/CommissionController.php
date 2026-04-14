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
        try {
            $filters = array_filter([
                'ambassador_id' => $this->request->getGet('ambassador_id'),
                'month'         => $this->validatedMonth($this->request->getGet('month')),
            ]);
            $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?? 25)));
            $result  = $this->commissionService->listReportPaginated($filters, $page, $perPage);

            return $this->respond([
                'data' => $result['items'],
                'meta' => $result['meta'],
            ], 200);
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e));
        }
    }

    public function summary(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $filters = array_filter([
                'ambassador_id' => $this->request->getGet('ambassador_id'),
                'month'         => $this->validatedMonth($this->request->getGet('month')),
            ]);

            return $this->ok($this->commissionService->getReportSummary($filters));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $this->exceptionHttpStatus($e));
        }
    }

    public function months(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->commissionService->getAvailableMonths());
    }

    /** Active ambassadors who have confirmed sales in the given month (YYYY-MM). */
    public function ambassadorsForMonth(): \CodeIgniter\HTTP\ResponseInterface
    {
        $month = (string) ($this->request->getGet('month') ?? '');
        if ($month === '' || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $this->badRequest('Query parameter month (YYYY-MM) is required.');
        }

        return $this->ok($this->commissionService->listAmbassadorsWithSalesInMonth($month));
    }
}
