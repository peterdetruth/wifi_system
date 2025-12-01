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

    public function index()
    {
        $this->ensureLogin();

        $subscriptionModel = new \App\Models\SubscriptionModel();

        // Optional filter for status ?status=active/inactive
        $statusFilter = $this->request->getGet('status');
        $clients = $this->clientModel->orderBy('id', 'DESC')->findAll();

        foreach ($clients as &$client) {
            $activeSubs = $subscriptionModel
                ->where('client_id', $client['id'])
                ->where('status', 'active')
                ->countAllResults();

            $client['status'] = $activeSubs > 0 ? 'active' : 'inactive';
            $client['active_subscriptions_count'] = $activeSubs;
        }

        // Apply status filter if provided
        if ($statusFilter && in_array($statusFilter, ['active', 'inactive'])) {
            $clients = array_filter($clients, fn($c) => $c['status'] === $statusFilter);
        }

        // Pass clients **and statusFilter** to the view
        echo view('admin/clients/index', [
            'clients' => $clients,
            'status' => $statusFilter // <--- this fixes the undefined variable error
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
            return redirect()->back()->withInput()->with('error', 'Failed to create client: ' . print_r($dbError, true));
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
            return redirect()->back()->withInput()->with('error', 'Failed to update client: ' . print_r($dbError, true));
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
            return redirect()->to('/admin/clients')->with('error', 'Failed to delete client: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/clients')->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // Optional: View client details
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
