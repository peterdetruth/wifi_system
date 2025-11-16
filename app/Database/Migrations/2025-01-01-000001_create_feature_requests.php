<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFeatureRequests extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'status'        => ['type' => 'ENUM', 'constraint' => ['pending', 'complete'], 'default' => 'pending'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('feature_requests');
    }

    public function down()
    {
        $this->forge->dropTable('feature_requests');
    }
}
