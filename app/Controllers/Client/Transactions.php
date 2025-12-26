<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\MpesaTransactionModel;
use App\Models\PackageModel;

class Transactions extends BaseController
{
    protected MpesaTransactionModel $mpesaTransactionModel;
    protected PackageModel $packageModel;

    public function __construct()
    {
        helper(['url', 'text']);

        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->packageModel          = new PackageModel();
    }

    public function index()
    {
        $clientId = session()->get('client_id');

        if (!$clientId) {
            return redirect()->to('/client/login')->with('error', 'Please login');
        }

        $perPage = 10;

        // Allowed sorting fields (VERY IMPORTANT for security)
        $allowedSorts = [
            'package' => 'packages.name',
            'amount'  => 'mpesa_transactions.amount',
            'status'  => 'mpesa_transactions.status',
            'date'    => 'mpesa_transactions.created_at',
        ];

        // Read query params
        $sort  = $this->request->getGet('sort') ?? 'date';
        $order = strtolower($this->request->getGet('order') ?? 'desc');

        // Validate
        $sortColumn = $allowedSorts[$sort] ?? $allowedSorts['date'];
        $order      = in_array($order, ['asc', 'desc']) ? $order : 'desc';

        $transactions = $this->mpesaTransactionModel
            ->select('mpesa_transactions.*, packages.name AS package_name')
            ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
            ->where('mpesa_transactions.client_id', $clientId)
            ->orderBy($sortColumn, $order)
            ->paginate($perPage);

        return view('client/transactions/index', [
            'transactions' => $transactions,
            'pager'        => $this->mpesaTransactionModel->pager,
            'sort'         => $sort,
            'order'        => $order,
        ]);
    }
}
