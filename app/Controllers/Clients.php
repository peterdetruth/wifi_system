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
     * List all clients
     */
    public function index()
    {
        $this->ensureLogin();

        $subscriptionModel = new SubscriptionModel();

        // Optional status filter
        $statusFilter = $this->request->getGet('status');

        // Pagination
        $perPage = 20; // Change this anytime
        $page = $this->request->getVar('page') ?? 1;

        // Base client query
        $builder = $this->clientModel->orderBy('id', 'DESC');

        // First, get paginated clients (no filters yet)
        $clients = $builder->paginate($perPage);
        $pager = $this->clientModel->pager;

        // Loop through paginated clients
        foreach ($clients as &$client) {

            // Count active subscriptions
            $activeSubs = $subscriptionModel
                ->where('client_id', $client['id'])
                ->where('status', 'active')
                ->countAllResults();

            // Count ALL subscriptions (active + expired + cancelled)
            $totalSubs = $subscriptionModel
                ->where('client_id', $client['id'])
                ->countAllResults();

            $client['status'] = $activeSubs > 0 ? 'active' : 'inactive';
            $client['active_subscriptions_count'] = $activeSubs;
            $client['subscriptions_count'] = $totalSubs;
        }

        // Apply status filter **after** pagination
        if ($statusFilter && in_array($statusFilter, ['active', 'inactive'])) {
            $clients = array_filter($clients, fn($c) => $c['status'] === $statusFilter);
        }

        echo view('admin/clients/index', [
            'clients' => $clients,
            'pager'   => $pager,
            'status'  => $statusFilter
        ]);
    }

    /**
     * Create form
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
     * View individual client + subscriptions
     */
    public function view($id)
    {
        $this->ensureLogin();

        $client = $this->clientModel->find($id);
        if (! $client) {
            return redirect()->to('/admin/clients')->with('error', 'Client not found.');
        }

        $subscriptions = $this->subscriptionModel
            ->where('client_id', $id)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        echo view('admin/clients/view', [
            'client'        => $client,
            'subscriptions' => $subscriptions
        ]);
    }
}
