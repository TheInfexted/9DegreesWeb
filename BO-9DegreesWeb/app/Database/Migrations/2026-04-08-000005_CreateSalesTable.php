<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateSalesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                        => ['type' => 'INT', 'auto_increment' => true],
            'ambassador_id'             => ['type' => 'INT', 'null' => false],
            'team_id'                   => ['type' => 'INT', 'null' => true, 'default' => null],
            'date'                      => ['type' => 'DATE', 'null' => false],
            'table_number'              => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            'sale_type'                 => ['type' => 'ENUM', 'constraint' => ['Table', 'BGO'], 'null' => false],
            'gross_amount'              => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => false],
            'status'                    => ['type' => 'ENUM', 'constraint' => ['draft', 'confirmed', 'void'], 'null' => false, 'default' => 'draft'],
            'remarks'                   => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'confirmed_commission_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true, 'default' => null],
            'confirmed_at'              => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'created_by'                => ['type' => 'INT', 'null' => true, 'default' => null],
            'created_at'                => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at'                => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('ambassador_id', 'ambassadors', 'id');
        $this->forge->addForeignKey('team_id', 'teams', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'ambassadors', 'id', '', 'SET NULL');
        $this->forge->addKey('date');
        $this->forge->addKey('ambassador_id');
        $this->forge->addKey('team_id');
        $this->forge->addKey('status');
        $this->forge->createTable('sales');
    }

    public function down(): void { $this->forge->dropTable('sales'); }
}
