<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\MpesaTransactionModel;
use App\Models\PackageModel;

class Transactions extends BaseController
{
    protected MpesaTransactionModel $transactionModel;

    public function __construct()
    {
        $this->transactionModel = new MpesaTransactionModel();
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
            ->select('
                mpesa_transactions.*,
                packages.name AS package_name,
                packages.type AS package_type,
                packages.account_type AS package_account_type
            ')
            ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
            ->where('mpesa_transactions.client_id', $clientId)
            ->orderBy('mpesa_transactions.created_at', 'DESC')
            ->findAll();

        return view('client/transactions/index', [
            'transactions' => $transactions
        ]);
    }
}
