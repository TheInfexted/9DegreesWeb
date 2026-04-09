<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreatePayoutsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'auto_increment' => true],
            'ambassador_id'    => ['type' => 'INT', 'null' => false],
            'month'            => ['type' => 'DATE', 'null' => false],
            'total_commission' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => false],
            'paid_at'          => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'receipt_paths'    => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'payslip_path'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'default' => null],
            'created_at'       => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
            'updated_at'       => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('ambassador_id', 'ambassadors', 'id');
        $this->forge->addUniqueKey(['ambassador_id', 'month'], 'uq_ambassador_month');
        $this->forge->addKey(['ambassador_id', 'month']);
        $this->forge->createTable('payouts');
    }

    public function down(): void { $this->forge->dropTable('payouts'); }
}
