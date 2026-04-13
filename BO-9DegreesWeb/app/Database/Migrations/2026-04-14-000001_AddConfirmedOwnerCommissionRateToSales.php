<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConfirmedOwnerCommissionRateToSales extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sales', [
            'confirmed_owner_commission_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
                'default'    => null,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sales', 'confirmed_owner_commission_rate');
    }
}
