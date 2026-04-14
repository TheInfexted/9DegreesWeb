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
        $this->assertEquals(0.00, (float) $data['confirmed_owner_commission_rate']);
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
        $this->assertEquals(4.00, (float) $data['confirmed_owner_commission_rate']);
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
        $this->assertEquals(2.00, (float) $data['confirmed_owner_commission_rate']);
    }

    /** KPI bonus disabled → base rate only; owner still gets 12% pool remainder */
    public function test_table_sale_kpi_off_10_percent_base_owner_two_percent(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->withBodyFormat('json')
             ->put("/api/v1/ambassadors/{$this->ambassadorId}", [
                 'custom_commission_rate' => 10,
                 'use_kpi_bonus'          => 0,
             ]);

        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-02',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T-KPI-OFF',
                           'gross_amount'  => 500,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post("/api/v1/sales/{$saleId}/confirm");
        $result->assertStatus(200);

        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(10.00, (float) $data['confirmed_commission_rate']);
        $this->assertEquals(2.00, (float) $data['confirmed_owner_commission_rate']);
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
        $payload = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(8.00, (float) $payload['confirmed_commission_rate']);
        $this->assertEquals(4.00, (float) $payload['confirmed_owner_commission_rate']);
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
        $this->assertNull($data['confirmed_owner_commission_rate']);
    }

    public function test_commissions_index_empty_ok(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/commissions?page=1&per_page=25');
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json['data']);
        $this->assertSame(0, (int) ($json['meta']['total'] ?? 0));
    }

    /** Commission list and summary must agree when filtering by month (regression: count query vs joined select). */
    public function test_commission_list_matches_summary_for_month(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2025-12-15',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T-LIST',
                           'gross_amount'  => 2500,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post("/api/v1/sales/{$saleId}/confirm");

        $summary = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                        ->get('/api/v1/commissions/summary?month=2025-12');
        $summary->assertStatus(200);
        $sum = json_decode($summary->getJSON(), true)['data'];
        $this->assertGreaterThan(0, (float) ($sum['total'] ?? 0));

        $list = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                     ->get('/api/v1/commissions?month=2025-12&page=1&per_page=25');
        $list->assertStatus(200);
        $json = json_decode($list->getJSON(), true);
        $this->assertGreaterThanOrEqual(1, (int) ($json['meta']['total'] ?? 0));
        $this->assertNotEmpty($json['data']);
        $this->assertArrayHasKey('owner_commission_amount', $json['data'][0]);

        $amb = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                    ->get('/api/v1/commissions/ambassadors-for-month?month=2025-12');
        $amb->assertStatus(200);
        $rows = json_decode($amb->getJSON(), true)['data'];
        $this->assertIsArray($rows);
        $ids = array_column($rows, 'id');
        $this->assertContains($this->ambassadorId, $ids);
    }

    /** Filtering commissions to Johnny must include Table owner remainder on other ambassadors' sales. */
    public function test_johnny_filter_includes_owner_share_from_other_ambassadors_table_sales(): void
    {
        $johnnyId = (int) \Config\Database::connect()->table('ambassadors')->where('name', 'Johnny')->get()->getRow()->id;

        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $this->ambassadorId,
                           'date'          => '2026-01-10',
                           'sale_type'     => 'Table',
                           'table_number'  => 'T-JOHNNY-VIEW',
                           'gross_amount'  => 2500,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post("/api/v1/sales/{$saleId}/confirm");

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get("/api/v1/commissions?ambassador_id={$johnnyId}&month=2026-01&page=1&per_page=25");
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $rows = $json['data'];
        $this->assertNotEmpty($rows);

        $match = null;
        foreach ($rows as $r) {
            if (($r['table_number'] ?? '') === 'T-JOHNNY-VIEW') {
                $match = $r;
                break;
            }
        }
        $this->assertNotNull($match, 'Expected other ambassador Table sale in Johnny-filtered report');
        $this->assertEqualsWithDelta(100.0, (float) $match['commission_amount'], 0.02);
        $this->assertEqualsWithDelta(4.0, (float) ($match['report_commission_rate'] ?? 0), 0.02);
    }

    /** Table sales under Unassigned: no ambassador slice; full Table pool (12%) is owner (Johnny). */
    public function test_unassigned_table_sale_zero_ambassador_full_pool_owner(): void
    {
        $unassignedId = (int) \Config\Database::connect()->table('ambassadors')->where('name', 'Unassigned Sales')->get()->getRow()->id;

        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/sales', [
                           'ambassador_id' => $unassignedId,
                           'date'          => '2026-02-01',
                           'sale_type'     => 'Table',
                           'table_number'  => 'U-01',
                           'gross_amount'  => 1000,
                       ]);
        $saleId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post("/api/v1/sales/{$saleId}/confirm");
        $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(0.0, (float) $data['confirmed_commission_rate']);
        $this->assertEquals(12.0, (float) $data['confirmed_owner_commission_rate']);
    }
}
