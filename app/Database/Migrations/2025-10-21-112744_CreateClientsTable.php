<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'fullname'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'username'     => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
            'account_type' => ['type' => 'ENUM("personal","business")', 'default' => 'personal'],
            'email'        => ['type' => 'VARCHAR', 'constraint' => 150, 'unique' => true],
            'password'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'phone'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'status'       => ['type' => 'ENUM("active","inactive")', 'default' => 'inactive'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('clients');
    }

    public function down()
    {
        $this->forge->dropTable('clients');
    }
}
