<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use App\Models\PaymentsModel;
use App\Models\MpesaTransactionModel;
use Config\Database;

class Payments extends BaseController
{
    protected PaymentsModel $paymentModel;
    protected ClientModel $clientModel;
    protected PackageModel $packageModel;
    protected MpesaTransactionModel $mpesaModel;

    public function __construct()
    {
        $this->paymentModel = new PaymentsModel();
        $this->clientModel  = new ClientModel();
        $this->packageModel = new PackageModel();
        $this->mpesaModel   = new MpesaTransactionModel();
    }

    /**
     * Check admin login
     */
    protected function checkLogin()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()
                ->to('/login')
                ->with('error', 'Please login')
                ->send();
        }
    }

    /**
     * Show all payments
     */
    public function index()
    {
        $this->checkLogin();

        $perPage = 20; // number of records per page
        $currentPage = (int) $this->request->getGet('page') ?: 1;

        $builder = $this->paymentModel
            ->select("
            payments.*,
            clients.username AS client_username,
            packages.name AS package_name,
            mpesa_transactions.mpesa_receipt_number AS mpesa_code
        ")
            ->join('clients', 'clients.id = payments.client_id', 'left')
            ->join('packages', 'packages.id = payments.package_id', 'left')
            ->join('mpesa_transactions', 'mpesa_transactions.id = payments.mpesa_transaction_id', 'left')
            ->orderBy('payments.created_at', 'DESC');

        $totalPayments = $builder->countAllResults(false);

        $payments = $builder->get($perPage, ($currentPage - 1) * $perPage)->getResultArray();

        $clients  = $this->clientModel->orderBy('id', 'DESC')->findAll();
        $packages = $this->packageModel->orderBy('id', 'DESC')->findAll();

        return view('admin/payments/index', [
            'payments'       => $payments,
            'clients'        => $clients,
            'packages'       => $packages,
            'perPage'        => $perPage,
            'currentPage'    => $currentPage,
            'totalPayments'  => $totalPayments
        ]);
    }
}
