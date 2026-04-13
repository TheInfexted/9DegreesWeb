<?php

namespace App\Controllers\Api;

use App\Services\PayoutService;

class PayoutController extends BaseApiController
{
    private PayoutService $payoutService;

    public function __construct()
    {
        $this->payoutService = new PayoutService();
    }

    public function summary(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->payoutService->getSummary($this->payoutFiltersFromRequest()));
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $filters = $this->payoutFiltersFromRequest();

        $pageGet = $this->request->getGet('page');
        if ($pageGet !== null && $pageGet !== '') {
            $page    = max(1, (int) $pageGet);
            $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?? 25)));
            $result  = $this->payoutService->listPaginated($filters, $page, $perPage);

            return $this->respond([
                'data' => $result['items'],
                'meta' => $result['meta'],
            ], 200);
        }

        return $this->ok($this->payoutService->list($filters));
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->payoutService->get((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $data = $this->json();
            return $this->created($this->payoutService->create((int) $data['ambassador_id'], $data['month']));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function createBatch(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $data = $this->json();
            return $this->ok($this->payoutService->createBatch($data['items'] ?? []));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function markPaid($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->payoutService->markAsPaid((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function uploadReceipt($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $file = $this->request->getFile('receipt');
            if (!$file || !$file->isValid()) return $this->badRequest('Valid receipt file required.');
            return $this->ok($this->payoutService->uploadReceipt((int) $id, $file));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function deleteReceipt($id = null, $index = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->payoutService->deleteReceipt((int) $id, (int) $index));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $this->payoutService->delete((int) $id);
            return $this->noContent();
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function months(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->ok($this->payoutService->getAvailableMonths());
    }

    public function downloadSummary($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $pdf = $this->payoutService->generateSummaryPdf((int) $id);
            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'attachment; filename="payout-summary-' . $id . '.pdf"')
                ->setBody($pdf);
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function generatePayslip($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            return $this->ok($this->payoutService->generatePayslipPdf((int) $id));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function downloadPayslip($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $payout = $this->payoutService->get((int) $id);
            if (empty($payout['payslip_path'])) return $this->badRequest('Payslip not generated yet. Call POST first.');

            $path = WRITEPATH . 'uploads/' . $payout['payslip_path'];
            if (!file_exists($path)) return $this->notFound('Payslip file not found.');

            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'attachment; filename="payslip-' . $id . '.pdf"')
                ->setBody(file_get_contents($path));
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    /**
     * @return array{month?: string, paid?: bool}
     */
    private function payoutFiltersFromRequest(): array
    {
        $filters = [];
        $month   = $this->request->getGet('month');
        if ($month !== null && $month !== '') {
            $filters['month'] = $month;
        }
        $paidRaw = $this->request->getGet('paid');
        if ($paidRaw !== null && $paidRaw !== '') {
            $pr = (string) $paidRaw;
            if (in_array($pr, ['1', 'true', 'yes'], true)) {
                $filters['paid'] = true;
            } elseif (in_array($pr, ['0', 'false', 'no'], true)) {
                $filters['paid'] = false;
            }
        }

        return $filters;
    }
}
