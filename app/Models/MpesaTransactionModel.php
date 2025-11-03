<?php namespace App\Models;

use CodeIgniter\Model;

class MpesaTransactionModel extends Model
{
    protected $table = 'mpesa_transactions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'client_id',
        'client_username',
        'package_id',
        'package_length',
        'amount',
        'phone',
        'merchant_request_id',
        'checkout_request_id',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'transaction_id' => 'required|is_unique[mpesa_transactions.transaction_id,id,{id}]',
        'amount'         => 'required|decimal'
    ];

    protected $validationMessages = [
        'transaction_id' => ['is_unique' => 'This transaction ID already exists.']
    ];
}
