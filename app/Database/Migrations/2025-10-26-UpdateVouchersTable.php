<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateVouchersTable extends Migration
{
    public function up()
    {
        $fields = [
            'purpose' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'after'      => 'code'
            ],
            'status' => [
                'type'       => 'ENUM("unused","used","inactive")',
                'default'    => 'unused',
                'after'      => 'purpose'
            ],
        ];

        $this->forge->addColumn('vouchers', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('vouchers', 'purpose');
        $this->forge->dropColumn('vouchers', 'status');
    }
}
