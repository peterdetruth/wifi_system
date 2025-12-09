<?php

namespace App\Models;

use CodeIgniter\Model;

class LogModel extends Model
{
    protected $table = 'logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'level', 'type', 'message', 'context', 'user_id', 'ip_address', 'created_at'
    ];
    protected $useTimestamps = false; // manually inserting created_at
}
