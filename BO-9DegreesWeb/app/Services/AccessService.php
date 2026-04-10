<?php

namespace App\Services;

use App\Models\UserModel;

class AccessService
{
    public function __construct(private UserModel $model = new UserModel()) {}

    public function list(): array
    {
        return $this->model
            ->select('users.*, ambassadors.name as ambassador_name')
            ->join('ambassadors', 'ambassadors.id = users.ambassador_id', 'left')
            ->findAll();
    }

    public function create(array $data): array
    {
        if (empty($data['username']) || empty($data['password'])) {
            throw new \RuntimeException('Username and password are required.', 422);
        }
        if ($this->model->where('username', $data['username'])->first()) {
            throw new \RuntimeException('Username already taken.', 400);
        }

        $id = $this->model->insert([
            'username'      => $data['username'],
            'email'         => $data['email'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => $data['role'] ?? 'ambassador',
            'ambassador_id' => $data['ambassador_id'] ?? null,
            'is_active'     => 1,
        ], true);

        return $this->model->find($id);
    }

    public function update(int $id, array $data): array
    {
        $user = $this->model->find($id);
        if (!$user) throw new \RuntimeException('User not found.', 404);
        if ($user['role'] === 'owner') throw new \RuntimeException('Cannot modify the owner account.', 403);

        $allowed = array_intersect_key($data, array_flip(['role', 'is_active', 'email', 'ambassador_id']));
        $this->model->update($id, $allowed);
        return $this->model->find($id);
    }

    public function delete(int $id): void
    {
        $user = $this->model->find($id);
        if (!$user) throw new \RuntimeException('User not found.', 404);
        if ($user['role'] === 'owner') throw new \RuntimeException('Cannot delete the owner account.', 403);

        $this->model->update($id, ['is_active' => 0]);
    }
}
