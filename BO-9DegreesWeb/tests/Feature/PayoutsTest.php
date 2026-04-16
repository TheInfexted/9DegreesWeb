<?php

namespace Tests\Feature;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class PayoutsTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;

    protected $seed      = 'OwnerSeeder';
    protected $refresh   = true;
    protected $namespace = null;
    private string $token;
    private int $ambassadorId;

    protected function setUp(): void
    {
        $this->basePath = APPPATH . 'Database';
        parent::setUp();

        $result      = $this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password']);
        $this->token = json_decode($result->getJSON(), true)['token'];

        $amb = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                    ->post('/api/v1/ambassadors', [
                        'name'                   => 'Payout Amb',
                        'role_id'                => 1,
                        'custom_commission_rate' => 10,
                    ]);
        $this->ambassadorId = json_decode($amb->getJSON(), true)['data']['id'];

        // Create and confirm a BGO sale so commission exists
        $sale   = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'BGO',
                           'gross_amount'  => 1000.00,
                       ]);
        $saleId = json_decode($sale->getJSON(), true)['data']['id'];
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post("/api/v1/sales/{$saleId}/confirm");
    }

    public function test_create_payout(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/payouts', [
                           'ambassador_id' => $this->ambassadorId,
                           'month'         => '2025-12-01',
                       ]);
        $result->assertStatus(201);
        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(100.00, (float) $data['total_commission']); // 1000 * 10%
        $this->assertNull($data['paid_at']);
    }

    public function test_payout_for_johnny_rejected(): void
    {
        $johnnyId = (int) \Config\Database::connect()->table('ambassadors')->where('name', 'Johnny')->get()->getRow()->id;

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/payouts', [
                           'ambassador_id' => $johnnyId,
                           'month'         => '2025-12-01',
                       ]);
        $result->assertStatus(400);
    }

    public function test_duplicate_payout_returns_400(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post('/api/v1/payouts', ['ambassador_id' => $this->ambassadorId, 'month' => '2025-12-01']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/payouts', ['ambassador_id' => $this->ambassadorId, 'month' => '2025-12-01']);
        $result->assertStatus(400);
    }

    public function test_mark_payout_as_paid(): void
    {
        $create   = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                         ->post('/api/v1/payouts', ['ambassador_id' => $this->ambassadorId, 'month' => '2025-12-01']);
        $payoutId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post("/api/v1/payouts/{$payoutId}/mark-paid");
        $result->assertStatus(200);
        $this->assertNotNull(json_decode($result->getJSON(), true)['data']['paid_at']);
    }

    public function test_payouts_index_paginated_returns_rows(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post('/api/v1/payouts', ['ambassador_id' => $this->ambassadorId, 'month' => '2025-12-01']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/payouts', ['page' => 1, 'per_page' => 25]);
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertIsArray($body['data']);
        $this->assertNotEmpty($body['data']);
        $this->assertArrayHasKey('meta', $body);
        $this->assertGreaterThanOrEqual(1, (int) ($body['meta']['total'] ?? 0));
        $this->assertArrayHasKey('ambassador_name', $body['data'][0]);
    }

    /**
     * Helper: build an UploadedFile pointing at a real temp file, with isValid() stubbed out
     * (is_uploaded_file() returns false outside a real HTTP request). Mirrors SalesImportTest.
     */
    private function makeUploadedFixture(string $name, string $mime, string $contents): \CodeIgniter\HTTP\Files\UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'receipt-test-');
        file_put_contents($tmp, $contents);

        return new class ($tmp, $name, $mime, null, UPLOAD_ERR_OK, true) extends \CodeIgniter\HTTP\Files\UploadedFile {
            public function isValid(): bool { return $this->error === UPLOAD_ERR_OK; }

            /** Feature tests are not real HTTP uploads; move_uploaded_file would fail. */
            public function move(string $targetPath, ?string $name = null, bool $overwrite = false): bool
            {
                $targetPath = rtrim($targetPath, '/') . '/';
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777 & ~umask(), true);
                }
                $name        ??= $this->getName();
                $destination = $overwrite ? $targetPath . $name : $this->getDestination($targetPath . $name);
                $src          = $this->getRealPath() ?: $this->getPathname();
                if (!@copy($src, $destination)) {
                    throw HTTPException::forMoveFailed(basename((string) $src), $targetPath, 'copy() failed');
                }
                @chmod($targetPath, 0777 & ~umask());
                $this->hasMoved = true;
                $this->path     = $targetPath;
                $this->name     = basename($destination);
                @unlink($src);

                return true;
            }
        };
    }

    public function test_download_receipt_returns_file_with_correct_mime(): void
    {
        // Create a payout
        $create   = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                         ->post('/api/v1/payouts', ['ambassador_id' => $this->ambassadorId, 'month' => '2025-12-01']);
        $payoutId = (int) json_decode($create->getJSON(), true)['data']['id'];

        // Exercise the upload via the service (HTTP multipart isn't supported by FeatureTestTrait)
        $contents = '%PDF-1.4 fake pdf contents';
        $file     = $this->makeUploadedFixture('bank-receipt.pdf', 'application/pdf', $contents);
        (new \App\Services\PayoutService())->uploadReceipt($payoutId, $file);

        // Now download receipt at index 0 via HTTP
        $dl = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                   ->get("/api/v1/payouts/{$payoutId}/receipt/0");
        $dl->assertStatus(200);
        $this->assertSame('application/pdf', $dl->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="bank-receipt.pdf"', $dl->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame($contents, (string) $dl->response()->getBody());
    }

    public function test_download_receipt_invalid_index_returns_404(): void
    {
        $create   = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                         ->post('/api/v1/payouts', ['ambassador_id' => $this->ambassadorId, 'month' => '2025-12-01']);
        $payoutId = (int) json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get("/api/v1/payouts/{$payoutId}/receipt/99");
        $result->assertStatus(404);
    }

    public function test_download_receipt_unknown_payout_returns_404(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/payouts/999999/receipt/0');
        $result->assertStatus(404);
    }
}
