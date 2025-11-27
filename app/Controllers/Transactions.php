<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\MpesaTransactionModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use Config\Database;

class Transactions extends BaseController
{
    protected $mpesaTransactionModel;
    protected $clientModel;
    protected $packageModel;

    public function __construct()
    {
        helper(['filesystem', 'url']);

        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->clientModel           = new ClientModel();
        $this->packageModel          = new PackageModel();
    }

    protected function checkLogin()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login')
                ->with('error', 'Please login')
                ->send();
        }
    }

    /**
     * List all M-PESA transactions (Admin + Filters)
     */
    public function index()
    {
        $this->checkLogin();

        // Filters
        $clientId  = $this->request->getGet('client_id');
        $packageId = $this->request->getGet('package_id');
        $dateFrom  = $this->request->getGet('date_from');
        $dateTo    = $this->request->getGet('date_to');

        $builder = $this->mpesaTransactionModel
            ->select('
                mpesa_transactions.*,
                packages.name AS package_name,
                clients.username AS client_username
            ')
            ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
            ->join('clients', 'clients.id = mpesa_transactions.client_id', 'left');

        // Apply filters
        if (!empty($clientId)) {
            $builder->where('mpesa_transactions.client_id', $clientId);
        }

        if (!empty($packageId)) {
            $builder->where('mpesa_transactions.package_id', $packageId);
        }

        if (!empty($dateFrom)) {
            $builder->where('DATE(mpesa_transactions.created_at) >=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $builder->where('DATE(mpesa_transactions.created_at) <=', $dateTo);
        }

        $builder->orderBy('mpesa_transactions.created_at', 'DESC');

        $data['transactions'] = $builder->findAll();
        $data['clients']      = $this->clientModel->orderBy('id', 'DESC')->findAll();
        $data['packages']     = $this->packageModel->orderBy('id', 'DESC')->findAll();

        return view('admin/transactions/index', $data);
    }

    /**
     * Export filtered M-PESA transactions to CSV
     */
    public function export()
    {
        $this->checkLogin();

        // Same filters as index()
        $clientId  = $this->request->getGet('client_id');
        $packageId = $this->request->getGet('package_id');
        $dateFrom  = $this->request->getGet('date_from');
        $dateTo    = $this->request->getGet('date_to');

        $builder = $this->mpesaTransactionModel
            ->select('
                mpesa_transactions.*,
                packages.name AS package_name,
                clients.username AS client_username
            ')
            ->join('packages', 'packages.id = mpesa_transactions.package_id', 'left')
            ->join('clients', 'clients.id = mpesa_transactions.client_id', 'left');

        if (!empty($clientId))  $builder->where('mpesa_transactions.client_id', $clientId);
        if (!empty($packageId)) $builder->where('mpesa_transactions.package_id', $packageId);
        if (!empty($dateFrom))  $builder->where('DATE(mpesa_transactions.created_at) >=', $dateFrom);
        if (!empty($dateTo))    $builder->where('DATE(mpesa_transactions.created_at) <=', $dateTo);

        $rows = $builder
            ->orderBy('mpesa_transactions.created_at', 'DESC')
            ->findAll();

        // CSV Output
        $filename = "mpesa_transactions_export_" . date('Ymd_His') . ".csv";

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename={$filename}");

        $file = fopen('php://output', 'w');

        // CSV Header
        fputcsv($file, [
            'ID',
            'Client Username',
            'Package Name',
            'Amount',
            'Phone',
            'M-PESA Receipt',
            'Status',
            'Created At'
        ]);

        // Rows
        foreach ($rows as $row) {
            fputcsv($file, [
                $row['id'],
                $row['client_username'],
                $row['package_name'],
                $row['amount'],
                $row['phone'],
                $row['mpesa_receipt'],
                $row['status'],
                $row['created_at'],
            ]);
        }

        fclose($file);
        exit();
    }

    /**
     * Store transaction (manual admin creation)
     */
    public function store()
    {
        try {
            $data = $this->request->getPost();

            if ($this->mpesaTransactionModel->save($data)) {
                return redirect()
                    ->to('/admin/transactions')
                    ->with('success', 'Transaction created successfully.');
            }

            $dbError = $this->mpesaTransactionModel->errors() ?: Database::connect()->error();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create transaction: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing transaction
     */
    public function update($id)
    {
        try {
            $data = $this->request->getPost();

            if ($this->mpesaTransactionModel->update($id, $data)) {
                return redirect()
                    ->to('/admin/transactions')
                    ->with('success', 'Transaction updated successfully.');
            }

            $dbError = $this->mpesaTransactionModel->errors() ?: Database::connect()->error();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update transaction: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete a transaction
     */
    public function delete($id)
    {
        try {
            if ($this->mpesaTransactionModel->delete($id)) {
                return redirect()
                    ->to('/admin/transactions')
                    ->with('success', 'Transaction deleted successfully.');
            }

            $dbError = Database::connect()->error();

            return redirect()
                ->to('/admin/transactions')
                ->with('error', 'Failed to delete transaction: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()
                ->to('/admin/transactions')
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
