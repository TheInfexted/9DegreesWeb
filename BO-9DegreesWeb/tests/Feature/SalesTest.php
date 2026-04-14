<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class SalesTest extends CIUnitTestCase
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
        $result         = $this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password']);
        $this->token    = json_decode($result->getJSON(), true)['token'];
        $amb            = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                               ->post('/api/v1/ambassadors', [
                                   'name'                   => 'Test Amb',
                                   'role_id'                => 1,
                                   'custom_commission_rate' => 10,
                               ]);
        $this->ambassadorId = json_decode($amb->getJSON(), true)['data']['id'];
    }

    public function test_create_table_sale(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T04',
                           'gross_amount'  => 4200.00,
                       ]);
        $result->assertStatus(201);
        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals('draft', $data['status']);
        $this->assertEquals('Table', $data['sale_type']);
    }

    public function test_create_bgo_sale(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'BGO',
                           'gross_amount'  => 1800.00,
                       ]);
        $result->assertStatus(201);
        $this->assertNull(json_decode($result->getJSON(), true)['data']['table_number']);
    }

    public function test_create_bgo_sale_with_table_number(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'BGO',
                           'table_number'  => 'VIP-2',
                           'gross_amount'  => 1800.00,
                       ]);
        $result->assertStatus(201);
        $this->assertEquals('VIP-2', json_decode($result->getJSON(), true)['data']['table_number']);
    }

    public function test_list_sales_with_filters(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post('/api/v1/sales', [
                 'ambassador_id' => $this->ambassadorId,
                 'date'          => '2025-12-01',
                 'sale_type'     => 'BGO',
                 'gross_amount'  => 100,
             ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/sales?status=draft&page=1');
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('meta', $json);
        $this->assertNotEmpty($json['data']);
        $this->assertGreaterThanOrEqual(1, $json['meta']['total']);
    }

    public function test_confirm_all_drafts(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post('/api/v1/sales', [
                 'ambassador_id' => $this->ambassadorId,
                 'date'          => '2025-12-05',
                 'sale_type'     => 'BGO',
                 'gross_amount'  => 50,
             ]);
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post('/api/v1/sales', [
                 'ambassador_id' => $this->ambassadorId,
                 'date'          => '2025-12-06',
                 'sale_type'     => 'BGO',
                 'gross_amount'  => 60,
             ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->withBodyFormat('json')
                       ->post('/api/v1/sales/confirm-drafts', ['month' => '2025-12']);
        $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(2, $data['confirmed']);
        $this->assertSame([], $data['failed']);

        $list = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                      ->get('/api/v1/sales?status=draft&month=2025-12&page=1');
        $this->assertEquals(0, json_decode($list->getJSON(), true)['meta']['total']);
    }

    public function test_delete_draft_sale(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'BGO',
                           'gross_amount'  => 100,
                       ]);
        $id = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->delete("/api/v1/sales/{$id}");
        $result->assertStatus(204);
    }

    public function test_update_sale_with_nonexistent_ambassador_returns_404(): void
    {
        // Create a draft sale first
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-10',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T-UPD',
                           'gross_amount'  => 1000,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        // Try to reassign to ambassador ID 999999 (does not exist)
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->withBodyFormat('json')
                       ->put("/api/v1/sales/{$saleId}", ['ambassador_id' => 999999]);
        $result->assertStatus(404);
        $this->assertStringContainsString('Ambassador not found', json_decode($result->getJSON(), true)['message']);
    }
}
