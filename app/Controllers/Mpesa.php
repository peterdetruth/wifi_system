<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MpesaTransactionModel;
use App\Models\TransactionModel;
use App\Models\ClientModel;

class Mpesa extends BaseController
{
    protected $mpesaModel;
    protected $transactionModel;

    public function __construct()
    {
        $this->mpesaModel = new MpesaTransactionModel();
        $this->transactionModel = new TransactionModel();
    }

    public function index()
    {
        // Join mpesa_transactions with transactions + clients for richer data
        $data['mpesa_transactions'] = $this->mpesaModel
            ->select('mpesa_transactions.*, transactions.status AS txn_status, transactions.amount AS txn_amount, transactions.method AS txn_method, clients.username AS client_username')
            ->join('transactions', 'transactions.mpesa_code = mpesa_transactions.transaction_id', 'left')
            ->join('clients', 'clients.id = transactions.client_id', 'left')
            ->orderBy('mpesa_transactions.created_at', 'DESC')
            ->findAll();

        return view('admin/mpesa/index', $data);
    }
}
