<?php

namespace Tests\Feature;

use App\Services\SaleImportService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class SalesImportTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;

    protected $seed      = 'OwnerSeeder';
    protected $refresh   = true;
    protected $namespace = null;

    private string $token;
    private int $ambassadorId;
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->basePath = APPPATH . 'Database';
        parent::setUp();

        $this->fixturePath = __DIR__ . '/../_support/fixtures/johnny-0226.pdf';

        $login        = $this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password']);
        $this->token  = json_decode($login->getJSON(), true)['token'];

        $amb = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->post('/api/v1/ambassadors', [
                'name'                   => 'Test Import Amb',
                'role_id'                => 1,
                'custom_commission_rate' => 10,
            ]);
        $this->ambassadorId = json_decode($amb->getJSON(), true)['data']['id'];
    }

    /**
     * Wraps the PDF fixture in an UploadedFile that emulates a real upload —
     * CI4's FeatureTestTrait doesn't natively support multipart, so we exercise
     * the service directly for the parse path. UploadedFile::isValid() relies on
     * is_uploaded_file() which only returns true for real HTTP uploads, so we
     * override it with an anonymous subclass.
     */
    private function uploadedFixture(string $sourcePath = null, string $name = null, string $mime = 'application/pdf'): UploadedFile
    {
        $sourcePath ??= $this->fixturePath;
        $name       ??= basename($sourcePath);

        $tmp = tempnam(sys_get_temp_dir(), 'sales-import-');
        copy($sourcePath, $tmp);

        return new class ($tmp, $name, $mime, null, UPLOAD_ERR_OK, true) extends UploadedFile {
            public function isValid(): bool { return $this->error === UPLOAD_ERR_OK; }
        };
    }

    public function test_parse_extracts_36_table_rows(): void
    {
        $result = (new SaleImportService())->parsePdf($this->uploadedFixture(), $this->ambassadorId);

        $this->assertCount(36, $result['rows']);
        $this->assertSame(36, $result['summary']['total']);
        $this->assertSame(36, $result['summary']['ready']);
        $this->assertSame(0, $result['summary']['duplicates']);

        foreach ($result['rows'] as $row) {
            $this->assertSame('Table', $row['sale_type']);
            $this->assertNotEmpty($row['table_number']);
            $this->assertGreaterThan(0, $row['gross_amount']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $row['date']);
            $this->assertStringStartsWith('Receipt: ', $row['remarks']);
            $this->assertNull($row['existing_sale']);
        }

        $sum = array_sum(array_column($result['rows'], 'gross_amount'));
        $this->assertEqualsWithDelta(77502.00, $sum, 0.01, 'Total should match PDF footer (RM 77,502.00).');
    }

    public function test_parse_flags_existing_receipt_as_duplicate(): void
    {
        // Pre-create a sale that matches one of the receipts in the PDF.
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->post('/api/v1/sales', [
                'ambassador_id' => $this->ambassadorId,
                'date'          => '2026-02-02',
                'sale_type'     => 'Table',
                'table_number'  => 'L10',
                'gross_amount'  => 6028.00,
                'remarks'       => 'Receipt: A00101202602030006',
            ]);

        $result = (new SaleImportService())->parsePdf($this->uploadedFixture(), $this->ambassadorId);

        $matching = array_values(array_filter(
            $result['rows'],
            fn(array $r): bool => $r['receipt'] === 'A00101202602030006',
        ));
        $this->assertCount(1, $matching);
        $this->assertNotNull($matching[0]['existing_sale']);
        $this->assertSame('draft', $matching[0]['existing_sale']['status']);
        $this->assertSame(1, $result['summary']['duplicates']);
        $this->assertSame(35, $result['summary']['ready']);
    }

    public function test_commit_creates_drafts(): void
    {
        $service    = new SaleImportService();
        $parsed     = $service->parsePdf($this->uploadedFixture(), $this->ambassadorId);
        $decisions  = array_map(static fn(array $r): array => [
            'action'       => 'create',
            'receipt'      => $r['receipt'],
            'date'         => $r['date'],
            'sale_type'    => $r['sale_type'],
            'table_number' => $r['table_number'],
            'gross_amount' => $r['gross_amount'],
        ], $parsed['rows']);

        $owner   = json_decode($this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password'])->getJSON(), true);
        $userId  = $owner['user']['id'] ?? null;
        $this->assertNotNull($userId);

        $result = $service->commit($decisions, $this->ambassadorId, (int) $userId);

        $this->assertSame(36, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([], $result['failed']);

        $list = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->get('/api/v1/sales?ambassador_id=' . $this->ambassadorId . '&status=draft&per_page=100');
        $list->assertStatus(200);
        $this->assertSame(36, json_decode($list->getJSON(), true)['meta']['total']);
    }

    public function test_commit_overwrite_draft_updates_in_place(): void
    {
        $created = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->post('/api/v1/sales', [
                'ambassador_id' => $this->ambassadorId,
                'date'          => '2026-02-02',
                'sale_type'     => 'Table',
                'table_number'  => 'OLD',
                'gross_amount'  => 1.00,
                'remarks'       => 'Receipt: A00101202602030006',
            ]);
        $existingId = json_decode($created->getJSON(), true)['data']['id'];

        $owner  = json_decode($this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password'])->getJSON(), true);
        $userId = (int) $owner['user']['id'];

        $result = (new SaleImportService())->commit([
            [
                'action'       => 'overwrite',
                'receipt'      => 'A00101202602030006',
                'date'         => '2026-02-02',
                'sale_type'    => 'Table',
                'table_number' => 'L10',
                'gross_amount' => 6028.00,
            ],
        ], $this->ambassadorId, $userId);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame([], $result['failed']);

        $reload = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->get("/api/v1/sales/{$existingId}");
        $data = json_decode($reload->getJSON(), true)['data'];
        $this->assertSame('L10', $data['table_number']);
        $this->assertEqualsWithDelta(6028.00, (float) $data['gross_amount'], 0.01);
    }

    public function test_commit_overwrite_skips_confirmed_sale(): void
    {
        $created = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->post('/api/v1/sales', [
                'ambassador_id' => $this->ambassadorId,
                'date'          => '2026-02-02',
                'sale_type'     => 'Table',
                'table_number'  => 'L10',
                'gross_amount'  => 6028.00,
                'remarks'       => 'Receipt: A00101202602030006',
            ]);
        $saleId = json_decode($created->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->post("/api/v1/sales/{$saleId}/confirm");

        $owner  = json_decode($this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password'])->getJSON(), true);
        $userId = (int) $owner['user']['id'];

        $result = (new SaleImportService())->commit([
            [
                'action'       => 'overwrite',
                'receipt'      => 'A00101202602030006',
                'date'         => '2026-02-02',
                'sale_type'    => 'Table',
                'table_number' => 'L99',
                'gross_amount' => 9999.00,
            ],
        ], $this->ambassadorId, $userId);

        $this->assertSame(0, $result['updated']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('confirmed', $result['failed'][0]['message']);

        // Verify the confirmed sale was NOT mutated
        $reload = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->get("/api/v1/sales/{$saleId}");
        $data = json_decode($reload->getJSON(), true)['data'];
        $this->assertSame('L10', $data['table_number']);
        $this->assertEqualsWithDelta(6028.00, (float) $data['gross_amount'], 0.01);
    }

    public function test_commit_skip_action_creates_no_sales(): void
    {
        $owner  = json_decode($this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password'])->getJSON(), true);
        $userId = (int) $owner['user']['id'];

        $result = (new SaleImportService())->commit([
            ['action' => 'skip', 'receipt' => 'A0001'],
            ['action' => 'skip', 'receipt' => 'A0002'],
        ], $this->ambassadorId, $userId);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $result['skipped']);
    }

    public function test_parse_rejects_non_pdf_upload(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sales-import-');
        file_put_contents($tmp, "not a pdf\n");

        $upload = new class ($tmp, 'fake.txt', 'text/plain', null, UPLOAD_ERR_OK, true) extends UploadedFile {
            public function isValid(): bool { return true; }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported file type');
        (new SaleImportService())->parsePdf($upload, $this->ambassadorId);
    }

    public function test_parse_rejects_unknown_ambassador(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ambassador not found');
        (new SaleImportService())->parsePdf($this->uploadedFixture(), 999999);
    }
}
