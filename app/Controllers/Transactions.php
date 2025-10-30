<?php
namespace App\Controllers\Admin;
namespace App\Controllers;
use App\Controllers\BaseController;
use App\Models\TransactionModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use Config\Database;
use CodeIgniter\Controller;

class Transactions extends BaseController
{
    protected $transactionModel;
    protected $clientModel;
    protected $packageModel;

    public function __construct()
    {
        $this->transactionModel = new TransactionModel();
        $this->clientModel = new ClientModel();
        $this->packageModel = new PackageModel();
    }

    protected function checkLogin() {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error','Please login')->send();
        }
    }

    public function index()
    {
        $this->checkLogin();
        $data['transactions'] = $this->transactionModel
            ->select('transactions.*, packages.name AS package_name, clients.username AS client_username')
            ->join('packages', 'packages.id = transactions.package_id', 'left')
            ->join('clients', 'clients.id = transactions.client_id', 'left')
            ->orderBy('transactions.created_on', 'DESC')
            ->findAll();
        $data['clients'] = $this->clientModel->orderBy('id','DESC')->findAll();
        $data['packages'] = $this->packageModel->orderBy('id','DESC')->findAll();

        // print_r($data);exit();

        echo view('admin/transactions/index', $data);
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
            return redirect()->back()->withInput()->with('error', 'Failed to update transaction: ' . print_r($dbError, true));
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
            return redirect()->to('/admin/transactions')->with('error', 'Failed to delete transaction: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/transactions')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
