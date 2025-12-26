<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\MpesaTransactionModel;
use App\Models\PackageModel;

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

    public function index()
    {
        $clientId = session()->get('client_id');

        if (!$clientId) {
            return redirect()->to('/client/login')->with('error', 'Please login');
        }

        /* -------------------------
         * Sorting
         * ------------------------- */
        $allowedSorts = [
            'package' => 'packages.name',
            'amount'  => 'mpesa_transactions.amount',
            'status'  => 'mpesa_transactions.status',
            'date'    => 'mpesa_transactions.created_at',
        ];

        $sort  = $this->request->getGet('sort') ?? 'date';
        $order = strtolower($this->request->getGet('order') ?? 'desc');

        $sortColumn = $allowedSorts[$sort] ?? 'mpesa_transactions.created_at';
        $order      = in_array($order, ['asc', 'desc']) ? $order : 'desc';

        /* -------------------------
         * Status Filter
         * ------------------------- */
        $statusFilter = strtolower($this->request->getGet('status') ?? '');

        $builder = $this->mpesaTransactionModel
            ->select('mpesa_transactions.*, packages.name AS package_name')
            ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
            ->where('mpesa_transactions.client_id', $clientId);

        if (in_array($statusFilter, ['success', 'pending', 'failed'])) {
            $builder->where('mpesa_transactions.status', $statusFilter);
        }

        $transactions = $builder
            ->orderBy($sortColumn, $order)
            ->paginate(10);

        return view('client/transactions/index', [
            'transactions'  => $transactions,
            'pager'         => $this->mpesaTransactionModel->pager,
            'sort'          => $sort,
            'order'         => $order,
            'statusFilter'  => $statusFilter,
        ]);
    }
}
