<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table      = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'username', 'email', 'password_hash', 'role', 'ambassador_id', 'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findByUsername(string $username): ?array
    {
        $user = $this->where('username', $username)->where('is_active', 1)->first();

        // MySQL default collations treat = as case-insensitive; require exact stored casing.
        if ($user !== null && $user['username'] !== $username) {
            return null;
        }

        return $user;
    }
}
