<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoutersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45],
            'status'     => ['type' => 'ENUM("active","inactive")', 'default' => 'inactive'],
            'last_seen'  => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('routers');
    }

    public function down()
    {
        $this->forge->dropTable('routers');
    }
}
