<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class CommissionTest extends CIUnitTestCase
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

        // Ambassador with 8% base rate, KPI = 5000, commission_increase = 2%, no KPI bonus by default
        $amb = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                    ->post('/api/v1/ambassadors', [
                        'name'                   => 'Commission Test Amb',
                        'role_id'                => 1,
                        'custom_commission_rate' => 8,
                        'kpi'                    => 5000,
                        'commission_increase'    => 2,
                        'use_kpi_bonus'          => 0,
                    ]);
        $this->ambassadorId = json_decode($amb->getJSON(), true)['data']['id'];
    }

    /** BGO sales always get 10% regardless of ambassador rate */
    public function test_bgo_sale_always_10_percent(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'BGO',
                           'gross_amount'  => 2000,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post("/api/v1/sales/{$saleId}/confirm");
        $result->assertStatus(200);

        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals('confirmed', $data['status']);
        $this->assertEquals(10.00, (float) $data['confirmed_commission_rate']);
    }

    /** Table sale below KPI threshold → base rate only */
    public function test_table_sale_below_kpi_uses_base_rate(): void
    {
        // Enable KPI bonus
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->withBodyFormat('json')
             ->put("/api/v1/ambassadors/{$this->ambassadorId}", ['use_kpi_bonus' => 1]);

        // Sale amount (1000) + no prior confirmed sales = 1000 < KPI 5000
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T01',
                           'gross_amount'  => 1000,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post("/api/v1/sales/{$saleId}/confirm");
        $result->assertStatus(200);

        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(8.00, (float) $data['confirmed_commission_rate']);
    }

    /** Table sale that meets KPI threshold → base rate + commission_increase */
    public function test_table_sale_meeting_kpi_uses_bonus_rate(): void
    {
        // Enable KPI bonus
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->withBodyFormat('json')
             ->put("/api/v1/ambassadors/{$this->ambassadorId}", ['use_kpi_bonus' => 1]);

        // Sale amount 5000 >= KPI 5000 → should get bonus
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T02',
                           'gross_amount'  => 5000,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post("/api/v1/sales/{$saleId}/confirm");
        $result->assertStatus(200);

        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(10.00, (float) $data['confirmed_commission_rate']); // 8 + 2
    }

    /** Confirmed rate is frozen — changing ambassador rate afterwards has no effect */
    public function test_confirmed_rate_is_frozen(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T03',
                           'gross_amount'  => 3000,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        // Confirm at 8%
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post("/api/v1/sales/{$saleId}/confirm");

        // Change ambassador's rate
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->withBodyFormat('json')
             ->put("/api/v1/ambassadors/{$this->ambassadorId}", ['custom_commission_rate' => 12]);

        // Fetch the sale — rate must still be 8
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get("/api/v1/sales/{$saleId}");
        $result->assertStatus(200);
        $this->assertEquals(8.00, (float) json_decode($result->getJSON(), true)['data']['confirmed_commission_rate']);
    }

    /** Voiding a sale clears the confirmed rate */
    public function test_void_sale_clears_confirmed_rate(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-01',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T04',
                           'gross_amount'  => 2000,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post("/api/v1/sales/{$saleId}/confirm");

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post("/api/v1/sales/{$saleId}/void");
        $result->assertStatus(200);

        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals('void', $data['status']);
        $this->assertNull($data['confirmed_commission_rate']);
    }
}
