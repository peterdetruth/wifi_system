<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\TransactionModel;

class Transactions extends BaseController
{
    protected $transactionModel;

    public function __construct()
    {
        $this->transactionModel = new TransactionModel();
        helper(['url', 'text']);
    }

    /**
     * Show client's transactions with package name (joined)
     */
    public function index()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login');
        }

        // Join packages so we can show package name
        $transactions = $this->transactionModel
            ->select('transactions.*, packages.name AS package_name')
            ->join('packages', 'packages.id = transactions.package_id', 'left')
            ->where('transactions.client_id', $clientId)
            ->orderBy('transactions.created_on', 'DESC')
            ->findAll();

        return view('client/transactions/index', [
            'transactions' => $transactions
        ]);
    }
}
