<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function __construct(private AuthService $authService = new AuthService()) {}

    public function login(): ResponseInterface
    {
        // Support both JSON body and form POST
        $data     = $this->request->getJSON(true) ?? [];
        $username = $data['username'] ?? $this->request->getPost('username') ?? '';
        $password = $data['password'] ?? $this->request->getPost('password') ?? '';

        if (empty($username) || empty($password)) {
            return $this->respond(['message' => 'Username and password required.'], 422);
        }

        try {
            $result = $this->authService->login($username, $password);

            return $this->respond($result, 200);
        } catch (\RuntimeException $e) {
            return $this->respond(['message' => $e->getMessage()], $e->getCode() ?: 401);
        }
    }

    public function logout(): ResponseInterface
    {
        return $this->respond(['message' => 'Logged out.'], 200);
    }
}
