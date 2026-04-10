<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class LeaderboardAccessSettingsTest extends CIUnitTestCase
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

    public function test_leaderboard_excludes_external_partner(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/leaderboard?months[]=2025-12');
        $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true)['data'];
        foreach ($data as $entry) {
            $this->assertNotEquals('External Partner', $entry['role_name']);
        }
    }

    public function test_get_settings(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/settings');
        $result->assertStatus(200);
        $settings = json_decode($result->getJSON(), true)['data'];
        $this->assertArrayHasKey('company_name', $settings);
    }

    public function test_update_settings(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->withBodyFormat('json')
                       ->put('/api/v1/settings', [
                           'company_name'    => 'Updated Company',
                           'company_address' => '123 Test Street',
                       ]);
        $result->assertStatus(200);
        $this->assertEquals('Updated Company', json_decode($result->getJSON(), true)['data']['company_name']);
    }

    public function test_list_access_accounts(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->get('/api/v1/access');
        $result->assertStatus(200);
        $this->assertNotEmpty(json_decode($result->getJSON(), true)['data']);
    }

    public function test_change_password(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                       ->withBodyFormat('json')
                       ->put('/api/v1/settings/password', [
                           'current_password' => 'password',
                           'new_password'     => 'newpassword123',
                       ]);
        $result->assertStatus(200);

        // Verify login works with new password
        $login = $this->post('/api/v1/auth/login', ['username' => 'johnny', 'password' => 'newpassword123']);
        $login->assertStatus(200);
    }
}
