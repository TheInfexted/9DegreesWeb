<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateTeamsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'null' => false, 'default' => 'active'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('teams');
    }

    public function down(): void { $this->forge->dropTable('teams'); }
}
