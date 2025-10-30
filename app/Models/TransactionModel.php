<?php

namespace App\Models;

use CodeIgniter\Model;

class TransactionModel extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'client_username',
        'client_fullname',
        'package_type',
        'package_length',
        'package_id',
        'created_on',
        'expires_on',
        'method',
        'mpesa_code',
        'router_id',
        'router_status',
        'online_status',
        'status',
        'amount'
    ];

    protected $useTimestamps = false; // since `created_on` is manually handled
}
