<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVouchersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'code'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'purpose'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'package_type' => ['type' => 'ENUM("Hotspot","PPPOE")'],
            'router_id'    => ['type' => 'INT', 'null' => true],
            'package_id'   => ['type' => 'INT', 'null' => true],
            'phone'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('vouchers');
    }

    public function down()
    {
        $this->forge->dropTable('vouchers');
    }
}
