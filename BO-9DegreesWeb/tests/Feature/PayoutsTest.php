<?php

namespace Tests\Feature;

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
}
