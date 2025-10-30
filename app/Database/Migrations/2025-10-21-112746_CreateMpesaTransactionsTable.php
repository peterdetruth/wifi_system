<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMpesaTransactionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'auto_increment' => true],
            'user'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'plan_id'        => ['type' => 'INT'],
            'amount'         => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'transaction_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('mpesa_transactions');
    }

    public function down()
    {
        $this->forge->dropTable('mpesa_transactions');
    }
}
