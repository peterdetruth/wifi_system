<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MpesaTransactionModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use Config\Database;

class Transactions extends BaseController
{
    protected MpesaTransactionModel $transactionModel;
    protected ClientModel $clientModel;
    protected PackageModel $packageModel;

    public function __construct()
    {
        $this->transactionModel = new MpesaTransactionModel();
        $this->clientModel = new ClientModel();
        $this->packageModel = new PackageModel();
    }

    /**
     * Ensure admin is logged in
     */
    protected function checkLogin()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please login')->send();
        }
    }

    /**
     * List all transactions
     */
    public function index()
    {
        $this->checkLogin();

        $transactions = $this->transactionModel
            ->select('
                mpesa_transactions.*,
                packages.name AS package_name,
                packages.type AS package_type,
                packages.account_type AS package_account_type,
                clients.username AS client_username
            ')
            ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
            ->join('clients', 'clients.id = mpesa_transactions.client_id', 'left')
            ->orderBy('mpesa_transactions.created_at', 'DESC')
            ->findAll();

        $clients = $this->clientModel->orderBy('id', 'DESC')->findAll();
        $packages = $this->packageModel->orderBy('id', 'DESC')->findAll();

        return view('admin/transactions/index', [
            'transactions' => $transactions,
            'clients'      => $clients,
            'packages'     => $packages
        ]);
    }

    /**
     * Store a new transaction
     */
    public function store()
    {
        try {
            $data = $this->request->getPost();

            if ($this->transactionModel->save($data)) {
                return redirect()->to('/admin/transactions')->with('success', 'Transaction created successfully.');
            }

            $dbError = $this->transactionModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to create transaction: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing transaction
     */
    public function update($id)
    {
        try {
            $data = $this->request->getPost();

            if ($this->transactionModel->update($id, $data)) {
                return redirect()->to('/admin/transactions')->with('success', 'Transaction updated successfully.');
            }

            $dbError = $this->transactionModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update transaction: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete a transaction
     */
    public function delete($id)
    {
        try {
            if ($this->transactionModel->delete($id)) {
                return redirect()->to('/admin/transactions')->with('success', 'Transaction deleted successfully.');
            }

            $dbError = Database::connect()->error();
            return redirect()->to('/admin/transactions')->with('error', 'Failed to delete transaction: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()->to('/admin/transactions')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
