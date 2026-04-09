<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateAmbassadorsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'auto_increment' => true],
            'name'                   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'full_name'              => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'default' => null],
            'ic'                     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            'role_id'                => ['type' => 'INT', 'null' => false],
            'team_id'                => ['type' => 'INT', 'null' => true, 'default' => null],
            'status'                 => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'null' => false, 'default' => 'active'],
            'bank_name'              => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'bank_account_number'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null],
            'bank_owner_name'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'default' => null],
            'custom_commission_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false, 'default' => 0.00],
            'kpi'                    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'default' => null],
            'commission_increase'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true, 'default' => null],
            'use_kpi_bonus'          => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'created_at'             => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at'             => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('role_id', 'roles', 'id');
        $this->forge->addForeignKey('team_id', 'teams', 'id');
        $this->forge->addKey('team_id');
        $this->forge->addKey('role_id');
        $this->forge->addKey('status');
        $this->forge->createTable('ambassadors');
    }

    public function down(): void { $this->forge->dropTable('ambassadors'); }
}
