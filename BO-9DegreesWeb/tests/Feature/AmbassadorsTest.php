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

    public function test_list_ambassadors(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/ambassadors');
        $result->assertStatus(200);
        $this->assertNotEmpty(json_decode($result->getJSON(), true)['data']);
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
}
