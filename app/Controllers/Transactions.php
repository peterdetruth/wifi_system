<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\TransactionModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use Config\Database;

class Transactions extends BaseController
{
    protected $transactionModel;
    protected $clientModel;
    protected $packageModel;

    public function __construct()
    {
        helper('filesystem');
        $this->transactionModel = new TransactionModel();
        $this->clientModel = new ClientModel();
        $this->packageModel = new PackageModel();
    }

    protected function checkLogin()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please login')->send();
        }
    }

    /**
     * List all transactions (with filters)
     */
    public function index()
    {
        $this->checkLogin();

        // Filters
        $clientId  = $this->request->getGet('client_id');
        $packageId = $this->request->getGet('package_id');
        $dateFrom  = $this->request->getGet('date_from');
        $dateTo    = $this->request->getGet('date_to');

        $builder = $this->transactionModel
            ->select('transactions.*, packages.name AS package_name, clients.username AS client_username')
            ->join('packages', 'packages.id = transactions.package_id', 'left')
            ->join('clients', 'clients.id = transactions.client_id', 'left');

        if ($clientId) {
            $builder->where('transactions.client_id', $clientId);
        }

        if ($packageId) {
            $builder->where('transactions.package_id', $packageId);
        }

        if ($dateFrom) {
            $builder->where('DATE(transactions.created_on) >=', $dateFrom);
        }

        if ($dateTo) {
            $builder->where('DATE(transactions.created_on) <=', $dateTo);
        }

        $builder->orderBy('transactions.created_on', 'DESC');

        $data['transactions'] = $builder->findAll();
        $data['clients']      = $this->clientModel->orderBy('id', 'DESC')->findAll();
        $data['packages']     = $this->packageModel->orderBy('id', 'DESC')->findAll();

        return view('admin/transactions/index', $data);
    }

    /**
     * Export filtered data to CSV
     */
    public function export()
    {
        $this->checkLogin();

        // Same filters as index()
        $clientId  = $this->request->getGet('client_id');
        $packageId = $this->request->getGet('package_id');
        $dateFrom  = $this->request->getGet('date_from');
        $dateTo    = $this->request->getGet('date_to');

        $builder = $this->transactionModel
            ->select('transactions.*, packages.name AS package_name, clients.username AS client_username')
            ->join('packages', 'packages.id = transactions.package_id', 'left')
            ->join('clients', 'clients.id = transactions.client_id', 'left');

        if ($clientId)  $builder->where('transactions.client_id', $clientId);
        if ($packageId) $builder->where('transactions.package_id', $packageId);
        if ($dateFrom)  $builder->where('DATE(transactions.created_on) >=', $dateFrom);
        if ($dateTo)    $builder->where('DATE(transactions.created_on) <=', $dateTo);

        $rows = $builder->orderBy('transactions.created_on', 'DESC')->findAll();

        // Create CSV
        $filename = "transactions_export_" . date('Ymd_His') . ".csv";
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename={$filename}");

        $file = fopen('php://output', 'w');

        // Header
        fputcsv($file, [
            'ID', 'Client', 'Package', 'Amount', 'Status',
            'Mpesa Receipt', 'Created On'
        ]);

        foreach ($rows as $t) {
            fputcsv($file, [
                $t['id'],
                $t['client_username'],
                $t['package_name'],
                $t['amount'],
                $t['status'],
                $t['mpesa_receipt_number'],
                $t['created_on'],
            ]);
        }

        fclose($file);
        exit();
    }

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

    public function update($id)
    {
        try {
            $data = $this->request->getPost();

            if ($this->transactionModel->update($id, $data)) {
                return redirect()->to('/admin/transactions')->with('success', 'Transaction updated successfully.');
            }

            $dbError = $this->transactionModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            if ($this->transactionModel->delete($id)) {
                return redirect()->to('/admin/transactions')->with('success', 'Transaction deleted successfully.');
            }

            $dbError = Database::connect()->error();
            return redirect()->to('/admin/transactions')->with('error', 'Failed to delete: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/transactions')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
