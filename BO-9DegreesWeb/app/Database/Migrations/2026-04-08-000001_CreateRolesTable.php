<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateRolesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'commission_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false, 'default' => 0.00],
            'created_at'      => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at'      => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('roles');
    }

    public function down(): void { $this->forge->dropTable('roles'); }
}
