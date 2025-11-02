<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentsModel extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'amount',
        'mpesa_receipt',
        'phone',
        'transaction_date',
        'created_at'
    ];

    protected $useTimestamps = false;
}
