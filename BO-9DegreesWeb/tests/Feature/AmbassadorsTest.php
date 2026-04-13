<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class AmbassadorsTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;

    protected $seed      = 'OwnerSeeder';
    protected $refresh   = true;
    protected $namespace = null;
    private string $token;

    protected function setUp(): void
    {
        $this->basePath = APPPATH . 'Database';
        parent::setUp();
        $result      = $this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'password']);
        $this->token = json_decode($result->getJSON(), true)['token'];
    }

    public function test_create_ambassador(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/ambassadors', [
                           'name'                   => 'Alex Tan',
                           'role_id'                => 1,
                           'custom_commission_rate' => 10.00,
                           'use_kpi_bonus'          => 0,
                       ]);
        $result->assertStatus(201);
        $this->assertEquals('Alex Tan', json_decode($result->getJSON(), true)['data']['name']);
    }

    /** Empty string from a select "no team" option must not become FK 0. */
    public function test_create_ambassador_with_empty_team_id(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/ambassadors', [
                           'name'                   => 'No Team User',
                           'role_id'                => 1,
                           'team_id'                => '',
                           'custom_commission_rate' => 10.00,
                           'use_kpi_bonus'          => 0,
                       ]);
        $result->assertStatus(201);
        $this->assertNull(json_decode($result->getJSON(), true)['data']['team_id']);
    }

    public function test_list_ambassadors(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/ambassadors');
        $result->assertStatus(200);
        $this->assertNotEmpty(json_decode($result->getJSON(), true)['data']);
    }

    public function test_list_ambassadors_paginated_includes_meta(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/ambassadors?page=1&per_page=1');
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('meta', $json);
        $this->assertCount(1, $json['data']);
        $this->assertSame(1, $json['meta']['per_page']);
        $this->assertGreaterThanOrEqual(2, $json['meta']['total']);
    }

    public function test_list_ambassadors_search_by_q(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/ambassadors?q=Johnny');
        $result->assertStatus(200);
        $names = array_column(json_decode($result->getJSON(), true)['data'], 'name');
        $this->assertContains('Johnny', $names);
    }

    public function test_soft_delete_ambassador(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/ambassadors', [
                           'name'                   => 'To Delete',
                           'role_id'                => 1,
                           'custom_commission_rate' => 10,
                       ]);
        $id = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->delete("/api/v1/ambassadors/{$id}");
        $result->assertStatus(200);
        $this->assertEquals('inactive', json_decode($result->getJSON(), true)['data']['status']);
    }

    public function test_update_rejects_base_plus_kpi_bonus_over_table_pool(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/ambassadors', [
                           'name'                   => 'KPI Cap',
                           'role_id'                => 1,
                           'custom_commission_rate' => 10,
                           'kpi'                    => 1000,
                           'commission_increase'    => 2,
                           'use_kpi_bonus'          => 1,
                       ]);
        $id = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->withBodyFormat('json')
                       ->put("/api/v1/ambassadors/{$id}", ['commission_increase' => 3]);
        $result->assertStatus(422);
    }
}
