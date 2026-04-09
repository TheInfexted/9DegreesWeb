<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => false],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'default' => null],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'role'          => ['type' => 'ENUM', 'constraint' => ['owner', 'admin', 'leader', 'ambassador'], 'null' => false, 'default' => 'ambassador'],
            'ambassador_id' => ['type' => 'INT', 'null' => true, 'default' => null],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'created_at'    => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at'    => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->addUniqueKey('ambassador_id');
        $this->forge->addForeignKey('ambassador_id', 'ambassadors', 'id', '', 'SET NULL');
        $this->forge->createTable('users');
    }

    public function down(): void { $this->forge->dropTable('users'); }
}
