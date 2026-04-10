<?php

namespace App\Services;

use App\Repositories\PayoutRepository;

class PayoutService
{
    private const MAX_RECEIPT_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const MAX_RECEIPT_FILES      = 10;
    private const RECEIPT_UPLOAD_PATH    = WRITEPATH . 'uploads/receipts/';

    public function __construct(
        private PayoutRepository $repo = new PayoutRepository(),
        private CommissionService $commissionService = new CommissionService()
    ) {}

    public function list(array $filters = []): array
    {
        return $this->repo->findAll($filters);
    }

    public function get(int $id): array
    {
        $payout = $this->repo->findById($id);
        if (!$payout) throw new \RuntimeException('Payout not found.', 404);
        return $payout;
    }

    public function create(int $ambassadorId, string $month): array
    {
        $normalizedMonth = date('Y-m-01', strtotime($month));

        $existing = $this->repo->findByAmbassadorAndMonth($ambassadorId, $normalizedMonth);
        if ($existing) throw new \RuntimeException('Payout already exists for this ambassador and month.', 400);

        $yearMonth       = date('Y-m', strtotime($normalizedMonth));
        $totalCommission = $this->commissionService->calculateTotalForUser($ambassadorId, $yearMonth);

        return $this->repo->create([
            'ambassador_id'    => $ambassadorId,
            'month'            => $normalizedMonth,
            'total_commission' => $totalCommission,
        ]);
    }

    public function createBatch(array $items): array
    {
        $created = [];
        $failed  = [];

        foreach ($items as $item) {
            try {
                $created[] = $this->create((int) $item['ambassador_id'], $item['month']);
            } catch (\RuntimeException $e) {
                $failed[] = ['item' => $item, 'error' => $e->getMessage()];
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }

    public function markAsPaid(int $id): array
    {
        $payout = $this->repo->findById($id);
        if (!$payout) throw new \RuntimeException('Payout not found.', 404);

        return $this->repo->update($id, ['paid_at' => date('Y-m-d H:i:s')]);
    }

    public function uploadReceipt(int $id, \CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        $payout = $this->repo->findById($id);
        if (!$payout) throw new \RuntimeException('Payout not found.', 404);

        $receipts = json_decode($payout['receipt_paths'] ?? '[]', true);
        if (count($receipts) >= self::MAX_RECEIPT_FILES) {
            throw new \RuntimeException('Maximum ' . self::MAX_RECEIPT_FILES . ' receipt files allowed.', 400);
        }
        if ($file->getSize() > self::MAX_RECEIPT_SIZE_BYTES) {
            throw new \RuntimeException('File size must not exceed 10 MB.', 400);
        }

        $dir = self::RECEIPT_UPLOAD_PATH . $id . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $safeName = time() . '_' . count($receipts) . '_' . preg_replace('/[^a-z0-9._-]/i', '_', $file->getClientName());
        $file->move($dir, $safeName);

        $receipts[] = ['path' => 'receipts/' . $id . '/' . $safeName, 'name' => $file->getClientName()];
        return $this->repo->update($id, ['receipt_paths' => json_encode($receipts)]);
    }

    public function deleteReceipt(int $id, int $index): array
    {
        $payout = $this->repo->findById($id);
        if (!$payout) throw new \RuntimeException('Payout not found.', 404);

        $receipts = json_decode($payout['receipt_paths'] ?? '[]', true);
        if (!isset($receipts[$index])) throw new \RuntimeException('Receipt not found.', 404);

        $filePath = WRITEPATH . 'uploads/' . $receipts[$index]['path'];
        if (file_exists($filePath)) unlink($filePath);

        array_splice($receipts, $index, 1);
        return $this->repo->update($id, ['receipt_paths' => json_encode(array_values($receipts))]);
    }

    public function delete(int $id): void
    {
        $payout = $this->repo->findById($id);
        if (!$payout) throw new \RuntimeException('Payout not found.', 404);

        $receipts = json_decode($payout['receipt_paths'] ?? '[]', true);
        foreach ($receipts as $receipt) {
            $path = WRITEPATH . 'uploads/' . $receipt['path'];
            if (file_exists($path)) unlink($path);
        }

        if (!empty($payout['payslip_path'])) {
            $path = WRITEPATH . 'uploads/' . $payout['payslip_path'];
            if (file_exists($path)) unlink($path);
        }

        $this->repo->delete($id);
    }

    public function getAvailableMonths(): array
    {
        return $this->repo->getAvailableMonths();
    }
}
