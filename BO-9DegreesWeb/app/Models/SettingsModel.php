<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingsModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['key', 'value'];
    protected $useTimestamps = false;

    public function getAll(): array
    {
        $rows = $this->findAll();
        return array_combine(array_column($rows, 'key'), array_column($rows, 'value'));
    }

    public function setValues(array $data): array
    {
        foreach ($data as $key => $value) {
            $existing = $this->where('key', $key)->first();
            if ($existing) {
                $this->db->table('settings')->where('key', $key)->update([
                    'value'      => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        return $this->getAll();
    }
}
