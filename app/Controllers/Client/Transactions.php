<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\PackageModel;
use App\Models\MpesaTransactionModel;

class Transactions extends BaseController
{
    protected $mpesaTransactionModel;
    protected $packageModel;

    public function __construct()
    {
        helper(['url', 'text']);

        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->packageModel          = new PackageModel();
    }

    /**
     * Display logged-in client's Mpesa transactions
     */
    public function index()
    {
        $clientId = session()->get('client_id');

        if (!$clientId) {
            return redirect()
                ->to('/client/login')
                ->with('error', 'Please login');
        }

        // Fetch M-PESA transactions linked to the client
        $transactions = $this->mpesaTransactionModel
            ->select('mpesa_transactions.*, packages.name AS package_name')
            ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
            ->where('mpesa_transactions.client_id', $clientId)
            ->orderBy('mpesa_transactions.created_at', 'DESC')
            ->findAll();

        return view('client/transactions/index', [
            'transactions' => $transactions
        ]);
    }
}
