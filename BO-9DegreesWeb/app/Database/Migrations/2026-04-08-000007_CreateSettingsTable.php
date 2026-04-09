<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateSettingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'value'      => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('key');
        $this->forge->createTable('settings');
    }

    public function down(): void { $this->forge->dropTable('settings'); }
}
