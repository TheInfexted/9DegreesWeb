<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;

    protected $seed      = 'OwnerSeeder';
    protected $refresh   = true;
    protected $namespace = null; // Run ALL namespace migrations (including App)

    protected function setUp(): void
    {
        // Point the seeder at app/Database/Seeds, not tests/_support/Database/Seeds
        $this->basePath = APPPATH . 'Database';
        parent::setUp();
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $result = $this->post('/api/v1/auth/login', [
            'username' => 'johnny',
            'password' => 'password',
        ]);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('token', $json);
        $this->assertArrayHasKey('user', $json);
        $this->assertEquals('owner', $json['user']['role']);
    }

    public function test_login_with_invalid_password_returns_401(): void
    {
        $result = $this->post('/api/v1/auth/login', [
            'username' => 'johnny',
            'password' => 'wrongpassword',
        ]);

        $result->assertStatus(401);
    }

    public function test_protected_route_without_token_returns_401(): void
    {
        $result = $this->get('/api/v1/ambassadors');
        $result->assertStatus(401);
    }
}
