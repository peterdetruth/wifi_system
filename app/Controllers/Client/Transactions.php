<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\TransactionModel;
use App\Models\PackageModel;

class Transactions extends BaseController
{
    protected $transactionModel;
    protected $packageModel;

    public function __construct()
    {
        helper(['url', 'text']);

        $this->transactionModel = new TransactionModel();
        $this->packageModel     = new PackageModel();
    }

    /**
     * Display logged-in client's transactions
     */
    public function index()
    {
        $clientId = session()->get('client_id');

        if (!$clientId) {
            return redirect()
                ->to('/client/login')
                ->with('error', 'Please login');
        }

        // Fetch only this client's transactions with package info
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
