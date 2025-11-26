<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentsModel extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'mpesa_transaction_id',
        'client_id',
        'package_id',
        'amount',
        'mpesa_receipt_number',
        'payment_method',
        'status',
        'phone',
        'transaction_date',
        'created_at'
    ];

    protected $useTimestamps = false;
}
