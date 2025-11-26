<?php

namespace App\Models;

use CodeIgniter\Model;

class SubscriptionModel extends Model
{
    protected $table = 'subscriptions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'package_id',
        'router_id',
        'payment_id',
        'start_date',
        'expires_on',
        'status'
    ];

    protected $useTimestamps = false;
    protected $returnType    = 'array';
}
