<?php

namespace App\Services;

use App\Repositories\AmbassadorRepository;
use App\Repositories\PayoutRepository;

class PayoutService
{
    private const MAX_RECEIPT_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const MAX_RECEIPT_FILES      = 10;
    private const RECEIPT_UPLOAD_PATH    = WRITEPATH . 'uploads/receipts/';
    private const PAYSLIP_UPLOAD_PATH    = WRITEPATH . 'uploads/payslips/';

    public function __construct(
        private PayoutRepository $repo = new PayoutRepository(),
        private CommissionService $commissionService = new CommissionService(),
        private AmbassadorRepository $ambassadorRepo = new AmbassadorRepository()
    ) {}

    public function list(array $filters = []): array
    {
        return $this->repo->findAll($filters);
    }

    /**
     * @return array{count: int, commission_total: float}
     */
    public function getSummary(array $filters): array
    {
        return $this->repo->getAggregateSummary($filters);
    }

    /**
     * @return array{items: list<array<string,mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function listPaginated(array $filters, int $page, int $perPage): array
    {
        $perPage  = max(1, min(100, $perPage));
        $total    = $this->repo->countFiltered($filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));
        $items    = $total === 0 ? [] : $this->repo->findPaginated($filters, $page, $perPage);

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

    public function get(int $id): array
    {
        $payout = $this->repo->findById($id);
        if (!$payout) throw new \RuntimeException('Payout not found.', 404);
        return $payout;
    }

    public function create(int $ambassadorId, string $month): array
    {
        $this->assertPayoutAllowedForAmbassador($ambassadorId);

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

    public function generateSummaryPdf(int $id): string
    {
        $data    = $this->buildPdfData($id);
        $html    = view('pdf/payout_summary', $data);
        $service = new PdfService();
        return $service->generate($html, $data['payout']['reference'] . '.pdf');
    }

    public function generatePayslipPdf(int $id): array
    {
        $data    = $this->buildPdfData($id);
        $html    = view('pdf/payslip', $data);
        $service = new PdfService();
        $pdf     = $service->generate($html, $data['payout']['payslip_reference'] . '.pdf');

        $dir = self::PAYSLIP_UPLOAD_PATH . $id . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = $data['payout']['payslip_reference'] . '.pdf';
        file_put_contents($dir . $filename, $pdf);

        $relativePath = 'payslips/' . $id . '/' . $filename;
        $this->repo->update($id, ['payslip_path' => $relativePath]);

        return $this->repo->findById($id);
    }

    private function buildPdfData(int $id): array
    {
        $payout = $this->repo->findById($id);
        if (!$payout) throw new \RuntimeException('Payout not found.', 404);

        $yearMonth = date('Y-m', strtotime($payout['month']));
        $sales     = $this->commissionService->getReport([
            'ambassador_id' => $payout['ambassador_id'],
            'month'         => $yearMonth,
        ]);

        $tableSales      = array_values(array_filter($sales, fn($s) => $s['sale_type'] === 'Table'));
        $bgoSales        = array_values(array_filter($sales, fn($s) => $s['sale_type'] === 'BGO'));
        $tableCommission = array_sum(array_column($tableSales, 'commission_amount'));
        $bgoCommission   = array_sum(array_column($bgoSales, 'commission_amount'));

        $settings = $this->getSettings();

        $periodDate  = strtotime($payout['month']);
        $periodLabel = date('F Y', $periodDate);
        $periodStart = date('01 M Y', $periodDate);
        $periodEnd   = date('t M Y', $periodDate);

        $ambassadorSlug = str_replace(' ', '', ucwords((string) $payout['ambassador_name']));
        $monthCode      = strtoupper(date('MY', $periodDate));

        $payout['period_label']      = $periodLabel;
        $payout['period_full_label'] = "{$periodStart} – {$periodEnd}";
        $payout['reference']         = "{$monthCode}_9DEG_COMM_{$ambassadorSlug}";
        $payout['payslip_reference'] = "{$monthCode}_9DEG_PS_{$ambassadorSlug}";

        return [
            'payout'              => $payout,
            'sales'               => $sales,
            'summary'             => [
                'table_sales'      => array_sum(array_column($tableSales, 'gross_amount')),
                'bgo_sales'        => array_sum(array_column($bgoSales, 'gross_amount')),
                'table_commission' => $tableCommission,
                'bgo_commission'   => $bgoCommission,
                'kpi_applied'      => false,
            ],
            'companyName'         => $settings['company_name']         ?? '9 Degrees',
            'companyAddress'      => $settings['company_address']       ?? '',
            'companyRegistration' => $settings['company_registration']  ?? '',
            'companyPhone'        => $settings['company_phone']         ?? '',
            'generatedDate'       => date('d/m/Y'),
        ];
    }

    private function getSettings(): array
    {
        $rows = db_connect()->table('settings')->get()->getResultArray();
        return array_combine(array_column($rows, 'key'), array_column($rows, 'value'));
    }

    private function assertPayoutAllowedForAmbassador(int $ambassadorId): void
    {
        $johnny = $this->ambassadorRepo->findByName('Johnny');
        if ($johnny !== null && $ambassadorId === (int) $johnny['id']) {
            throw new \RuntimeException('Payouts cannot be created for the owner account.', 400);
        }
    }
}
