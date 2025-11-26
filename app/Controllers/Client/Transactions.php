<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;

class Transactions extends BaseController
{
    public function __construct()
    {
        helper(['url', 'text', 'TransactionHelper']);
    }

    /**
     * Show client's transactions
     */
    public function index()
    {
        $clientId = session()->get('client_id');

        if (!$clientId) {
            return redirect()
                ->to('/client/login')
                ->with('error', 'Please login');
        }

        // Fetch only this client's transactions
        $transactions = getTransactions($clientId, false);

        return view('client/transactions/index', [
            'transactions' => $transactions
        ]);
    }
}
