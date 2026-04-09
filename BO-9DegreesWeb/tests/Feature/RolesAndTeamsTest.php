<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class RolesAndTeamsTest extends CIUnitTestCase
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

    public function test_list_roles(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/roles');
        $result->assertStatus(200);
        $this->assertCount(3, json_decode($result->getJSON(), true)['data']);
    }

    public function test_create_team(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/teams', ['name' => 'Team Alpha']);
        $result->assertStatus(201);
        $this->assertEquals('Team Alpha', json_decode($result->getJSON(), true)['data']['name']);
    }

    public function test_list_teams(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
             ->post('/api/v1/teams', ['name' => 'Team Beta']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/teams');
        $result->assertStatus(200);
        $this->assertNotEmpty(json_decode($result->getJSON(), true)['data']);
    }

    public function test_update_team(): void
    {
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->post('/api/v1/teams', ['name' => 'Old Name']);
        $id     = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->withBodyFormat('json')
                       ->put("/api/v1/teams/{$id}", ['name' => 'New Name']);
        $result->assertStatus(200);
        $this->assertEquals('New Name', json_decode($result->getJSON(), true)['data']['name']);
    }
}
