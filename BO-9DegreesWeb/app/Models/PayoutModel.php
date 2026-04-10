<?php

namespace App\Models;

use CodeIgniter\Model;

class PayoutModel extends Model
{
    protected $table         = 'payouts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'ambassador_id', 'month', 'total_commission', 'paid_at', 'receipt_paths', 'payslip_path',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
