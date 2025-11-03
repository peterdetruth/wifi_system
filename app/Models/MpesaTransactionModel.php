<?php

namespace App\Models;

use CodeIgniter\Model;

class MpesaTransactionModel extends Model
{
    protected $table = 'mpesa_transactions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'transaction_id',
        'merchant_request_id',
        'checkout_request_id',
        'amount',
        'mpesa_receipt_number',
        'phone_number',
        'transaction_date',
        'result_code',
        'result_desc',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'checkout_request_id' => 'required|string|max_length[100]',
        'transaction_id'      => 'permit_empty|string|max_length[100]',
        'amount'              => 'permit_empty|decimal',
        'status'              => 'permit_empty|in_list[Pending,Success,Failed]'
    ];
}
