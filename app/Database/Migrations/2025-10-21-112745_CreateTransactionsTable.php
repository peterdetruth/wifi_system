<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTransactionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'fullname'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'type'          => ['type' => 'ENUM("Hotspot","PPPOE")'],
            'created_on'    => ['type' => 'DATETIME'],
            'expires_on'    => ['type' => 'DATETIME', 'null' => true],
            'mpesa_code'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'router'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'        => ['type' => 'ENUM("active","expired")', 'default' => 'active'],
            'online_status' => ['type' => 'ENUM("online","offline")', 'default' => 'offline'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('transactions');
    }

    public function down()
    {
        $this->forge->dropTable('transactions');
    }
}
