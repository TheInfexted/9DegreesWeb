<?php

namespace App\Models;

use CodeIgniter\Model;

class SaleModel extends Model
{
    protected $table         = 'sales';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'ambassador_id', 'team_id', 'date', 'table_number', 'sale_type',
        'gross_amount', 'status', 'remarks', 'confirmed_commission_rate',
        'confirmed_at', 'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
