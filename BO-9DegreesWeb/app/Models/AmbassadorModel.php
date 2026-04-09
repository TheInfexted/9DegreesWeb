<?php

namespace App\Models;

use CodeIgniter\Model;

class AmbassadorModel extends Model
{
    protected $table         = 'ambassadors';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'name', 'full_name', 'ic', 'role_id', 'team_id', 'status',
        'bank_name', 'bank_account_number', 'bank_owner_name',
        'custom_commission_rate', 'kpi', 'commission_increase', 'use_kpi_bonus',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function active(): static
    {
        return $this->where('status', 'active');
    }
}
