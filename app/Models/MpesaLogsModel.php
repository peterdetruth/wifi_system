<?php

namespace App\Models;

use CodeIgniter\Model;

class MpesaLogsModel extends Model
{
    protected $table = 'mpesa_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = ['raw_callback', 'created_at'];
    public $timestamps = false;
}
