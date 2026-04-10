<?php
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        // Roles — only seed if table is empty
        if ($this->db->table('roles')->countAllResults() === 0) {
            $this->db->table('roles')->insertBatch([
                ['name' => 'Ambassador',       'commission_rate' => 10.00, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['name' => 'Leader',           'commission_rate' => 11.00, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                ['name' => 'External Partner', 'commission_rate' => 12.00, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ]);
        }

        // Settings — only seed if table is empty
        if ($this->db->table('settings')->countAllResults() === 0) {
            $this->db->table('settings')->insertBatch([
                ['key' => 'company_name',         'value' => '9 Degrees Sdn. Bhd.', 'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'company_address',      'value' => '',                    'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'company_registration', 'value' => '',                    'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'company_phone',        'value' => '',                    'updated_at' => date('Y-m-d H:i:s')],
                ['key' => 'company_email',        'value' => '',                    'updated_at' => date('Y-m-d H:i:s')],
            ]);
        }

        // Ambassadors + Owner user — only seed if Johnny doesn't exist
        if ($this->db->table('users')->where('username', 'johnny')->countAllResults() === 0) {
            // Look up Ambassador role by name
            $ambassadorRoleId = $this->db->table('roles')
                ->where('name', 'Ambassador')
                ->get()->getRow()->id;

            $this->db->table('ambassadors')->insert([
                'name'                   => 'Johnny',
                'full_name'              => 'Johnny',
                'role_id'                => $ambassadorRoleId,
                'team_id'                => null,
                'status'                 => 'active',
                'custom_commission_rate' => 10.00,
                'use_kpi_bonus'          => 0,
                'created_at'             => date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);
            $johnnyId = $this->db->insertID();

            $this->db->table('ambassadors')->insert([
                'name'                   => 'Unassigned Sales',
                'role_id'                => $ambassadorRoleId,
                'team_id'                => null,
                'status'                 => 'active',
                'custom_commission_rate' => 0.00,
                'use_kpi_bonus'          => 0,
                'created_at'             => date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('users')->insert([
                'username'      => 'johnny',
                'password_hash' => password_hash(env('OWNER_PASSWORD', 'password'), PASSWORD_BCRYPT),
                'role'          => 'owner',
                'ambassador_id' => $johnnyId,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
