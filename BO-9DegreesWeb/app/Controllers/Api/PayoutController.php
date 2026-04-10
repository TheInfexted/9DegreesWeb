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

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $paid = $this->request->getGet('paid');
        $filters = array_filter([
            'month' => $this->request->getGet('month'),
            'paid'  => $paid !== null ? (bool) $paid : null,
        ], fn($v) => $v !== null);
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

    // Implemented in Task 9
    public function downloadSummary($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(['message' => 'Not yet implemented.'], 501);
    }

    public function generatePayslip($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(['message' => 'Not yet implemented.'], 501);
    }

    public function downloadPayslip($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->respond(['message' => 'Not yet implemented.'], 501);
    }
}
