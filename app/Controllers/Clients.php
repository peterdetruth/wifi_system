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

    protected function ensureLogin()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please login')->send();
        }
    }

    /**
     * List clients with filters + pagination
     */
    public function index()
    {
        $this->ensureLogin();

        $subscriptionModel = new SubscriptionModel();

        // Filters
        $statusFilter = $this->request->getGet('status'); // active / expired / cancelled
        $search = $this->request->getGet('search');

        // Pagination
        $perPage = 20;
        $page = $this->request->getVar('page') ?? 1;

        // Base query
        $builder = $this->clientModel->orderBy('id', 'DESC');

        // Apply search
        if ($search) {
            $builder->groupStart()
                ->like('full_name', $search)
                ->orLike('username', $search)
                ->orLike('email', $search)
                ->orLike('phone', $search)
                ->groupEnd();
        }

        // Fetch paginated clients first
        $clients = $builder->paginate($perPage);
        $pager = $this->clientModel->pager;

        // Now compute subscription counts and status
        foreach ($clients as &$client) {
            // Count all subscriptions
            $totalSubs = $subscriptionModel
                ->where('client_id', $client['id'])
                ->countAllResults();

            // Count active subscriptions
            $activeSubs = $subscriptionModel
                ->where('client_id', $client['id'])
                ->where('status', 'active')
                ->countAllResults();

            // Determine client status (based on active subscriptions)
            $computedStatus = $activeSubs > 0 ? 'active' : ($totalSubs > 0 ? 'inactive' : 'none');

            $client['subscriptions_count'] = $totalSubs;
            $client['active_subscriptions_count'] = $activeSubs;
            $client['status'] = $computedStatus;
        }

        // Apply status filter after subscription counts
        if ($statusFilter && in_array($statusFilter, ['active','expired','cancelled'])) {
            $clients = array_filter($clients, fn($c) => $c['status'] === $statusFilter);
        }

        echo view('admin/clients/index', [
            'clients' => $clients,
            'pager'   => $pager,
            'status'  => $statusFilter,
            'search'  => $search
        ]);
    }

    public function create()
    {
        $this->ensureLogin();
        echo view('admin/clients/create');
    }

    public function store()
    {
        try {
            $data = $this->request->getPost();
            if ($this->clientModel->save($data)) {
                return redirect()->to('/admin/clients')->with('success', 'Client created successfully.');
            }
            $dbError = $this->clientModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to create client: ' . print_r($dbError,true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->ensureLogin();
        $client = $this->clientModel->find($id);
        if (! $client) return redirect()->to('/admin/clients')->with('error', 'Client not found.');
        echo view('admin/clients/edit', ['client' => $client]);
    }

    public function update($id)
    {
        try {
            $data = $this->request->getPost();
            if ($this->clientModel->update($id, $data)) {
                return redirect()->to('/admin/clients')->with('success', 'Client updated successfully.');
            }
            $dbError = $this->clientModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update client: ' . print_r($dbError,true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            if ($this->clientModel->delete($id)) {
                return redirect()->to('/admin/clients')->with('success', 'Client deleted successfully.');
            }
            $dbError = Database::connect()->error();
            return redirect()->to('/admin/clients')->with('error', 'Failed to delete client: ' . print_r($dbError,true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/clients')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function view($id)
    {
        $this->ensureLogin();
        $client = $this->clientModel->find($id);
        if (! $client) return redirect()->to('/admin/clients')->with('error', 'Client not found.');
        $subscriptions = $this->subscriptionModel->where('client_id', $id)->orderBy('created_at', 'DESC')->findAll();
        echo view('admin/clients/view', [
            'client' => $client,
            'subscriptions' => $subscriptions
        ]);
    }
}
