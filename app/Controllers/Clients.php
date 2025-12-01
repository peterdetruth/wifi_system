<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\SubscriptionModel;
use Config\Database;

class Clients extends BaseController
{
    protected $clientModel;
    protected $subscriptionModel;

    public function __construct()
    {
        $this->clientModel = new ClientModel();
        $this->subscriptionModel = new SubscriptionModel();
    }

    /**
     * Ensure user is logged in
     */
    protected function ensureLogin()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please login')->send();
        }
    }

    /**
     * List all clients with filters, search, and pagination
     */
    public function index()
    {
        $this->ensureLogin();

        $subscriptionModel = $this->subscriptionModel;

        $statusFilter = $this->request->getGet('status');
        $searchFilter = $this->request->getGet('search');

        $perPage = 3; // Items per page
        $page = $this->request->getVar('page') ?? 1;

        // Build query with optional search filter
        $builder = $this->clientModel->orderBy('id', 'DESC');

        if ($searchFilter) {
            $builder->groupStart()
                ->like('full_name', $searchFilter)
                ->orLike('username', $searchFilter)
                ->orLike('email', $searchFilter)
                ->groupEnd();
        }

        // Get all filtered clients first
        $allClients = $builder->findAll();

        // Compute status & subscriptions count for each client
        foreach ($allClients as &$client) {
            $activeSubs = $subscriptionModel
                ->where('client_id', $client['id'])
                ->where('status', 'active')
                ->countAllResults();

            $totalSubs = $subscriptionModel
                ->where('client_id', $client['id'])
                ->countAllResults();

            $client['status'] = $activeSubs > 0 ? 'active' : 'inactive';
            $client['active_subscriptions_count'] = $activeSubs;
            $client['subscriptions_count'] = $totalSubs;
        }

        // Apply status filter (after computing active/inactive)
        if ($statusFilter && in_array($statusFilter, ['active', 'inactive'])) {
            $allClients = array_filter($allClients, fn($c) => $c['status'] === $statusFilter);
        }

        // Pagination manually (slice filtered array)
        $totalItems = count($allClients);
        $clients = array_slice($allClients, ($page - 1) * $perPage, $perPage);

        // Use CodeIgniter Pager
        $pager = service('pager');
        $pager->makeLinks($page, $perPage, $totalItems, 'default_full');

        echo view('admin/clients/index', [
            'clients' => $clients,
            'pager' => $pager,
            'status' => $statusFilter,
            'search' => $searchFilter
        ]);
    }

    /**
     * Create client form
     */
    public function create()
    {
        $this->ensureLogin();
        echo view('admin/clients/create');
    }

    /**
     * Store new client
     */
    public function store()
    {
        try {
            $data = $this->request->getPost();

            if ($this->clientModel->save($data)) {
                return redirect()->to('/admin/clients')->with('success', 'Client created successfully.');
            }

            $dbError = $this->clientModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to create client: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Edit client
     */
    public function edit($id)
    {
        $this->ensureLogin();
        $client = $this->clientModel->find($id);

        if (! $client) {
            return redirect()->to('/admin/clients')->with('error', 'Client not found.');
        }

        echo view('admin/clients/edit', ['client' => $client]);
    }

    /**
     * Update client
     */
    public function update($id)
    {
        try {
            $data = $this->request->getPost();

            if ($this->clientModel->update($id, $data)) {
                return redirect()->to('/admin/clients')->with('success', 'Client updated successfully.');
            }

            $dbError = $this->clientModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update client: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete client
     */
    public function delete($id)
    {
        try {
            if ($this->clientModel->delete($id)) {
                return redirect()->to('/admin/clients')->with('success', 'Client deleted successfully.');
            }

            $dbError = Database::connect()->error();
            return redirect()->to('/admin/clients')->with('error', 'Failed to delete client: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/clients')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * View client + subscriptions
     */
    public function view($id)
    {
        $this->ensureLogin();

        $db = \Config\Database::connect();

        // Get client
        $client = $this->clientModel->find($id);
        if (! $client) {
            return redirect()->to('/admin/clients')->with('error', 'Client not found.');
        }

        // Get subscriptions with package name and router name
        $subscriptions = $db->table('subscriptions s')
            ->select('s.id, s.payment_id, s.client_id, s.package_id, s.router_id, s.status, s.start_date, s.expires_on, s.created_at, p.name AS package_name, r.name AS router_name')
            ->join('packages p', 'p.id = s.package_id', 'left')
            ->join('routers r', 'r.id = s.router_id', 'left')
            ->where('s.client_id', $id)
            ->orderBy('s.created_at', 'DESC')
            ->get()
            ->getResultArray();

        // Get Mpesa transactions with package name
        $mpesa_transactions = $db->table('mpesa_transactions m')
            ->select('m.id, m.client_id, m.package_id, m.transaction_id, m.amount, m.phone_number, m.transaction_date, m.status, m.created_at, m.mpesa_receipt, p.name AS package_name')
            ->join('packages p', 'p.id = m.package_id', 'left')
            ->where('m.client_id', $id)
            ->orderBy('m.created_at', 'DESC')
            ->get()
            ->getResultArray();

        echo view('admin/clients/view', [
            'client' => $client,
            'subscriptions' => $subscriptions,
            'mpesa_transactions' => $mpesa_transactions
        ]);
    }
}
