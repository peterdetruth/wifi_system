<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePackagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'type'         => ['type' => 'ENUM("Hotspot","PPPOE")', 'default' => 'Hotspot'],
            'duration'     => ['type' => 'ENUM("daily","weekly","monthly")', 'default' => 'daily'],
            'price'        => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'devices'      => ['type' => 'INT', 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('packages');
    }

    public function down()
    {
        $this->forge->dropTable('packages');
    }
}
