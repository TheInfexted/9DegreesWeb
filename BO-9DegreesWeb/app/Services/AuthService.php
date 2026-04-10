<?php

namespace App\Services;

use App\Libraries\JWTHandler;
use App\Models\UserModel;

class AuthService
{
    public function __construct(
        private UserModel $userModel = new UserModel(),
        private JWTHandler $jwt = new JWTHandler()
    ) {}

    /**
     * @return array{token: string, expires_at: int, user: array}
     *
     * @throws \RuntimeException on invalid credentials
     */
    public function login(string $username, string $password): array
    {
        $user = $this->userModel->findByUsername($username);

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('Invalid credentials.', 401);
        }

        $payload = [
            'user_id'       => $user['id'],
            'username'      => $user['username'],
            'role'          => $user['role'],
            'ambassador_id' => $user['ambassador_id'],
        ];

        $token = $this->jwt->encode($payload);

        return [
            'token'      => $token,
            'expires_at' => $this->jwt->getExpiresAt(),
            'user'       => [
                'id'            => $user['id'],
                'username'      => $user['username'],
                'role'          => $user['role'],
                'ambassador_id' => $user['ambassador_id'],
            ],
        ];
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->userModel->find($userId);

        if (! $user || ! password_verify($currentPassword, $user['password_hash'])) {
            throw new \RuntimeException('Current password is incorrect.', 400);
        }

        $this->userModel->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
    }
}
