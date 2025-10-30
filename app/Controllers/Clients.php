<?php
namespace App\Controllers;

use App\Models\ClientModel;
use Config\Database;

class Clients extends BaseController
{
    protected $clientModel;
    public function __construct()
    {
        // helper(['form','url']);
        $this->clientModel = new ClientModel();
    }

    protected function ensureLogin() {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error','Please login')->send();
        }
    }

    public function index()
    {
        $this->ensureLogin();

        // optional filter for status ?status=active
        $status = $this->request->getGet('status');
        if ($status && in_array($status, ['active','inactive'])) {
            $data['clients'] = $this->clientModel->where('status', $status)->orderBy('id','DESC')->findAll();
        } else {
            $data['clients'] = $this->clientModel->orderBy('id','DESC')->findAll();
        }

        echo view('admin/clients/index', $data);
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
        if (! $client) return redirect()->to('/admin/clients')->with('error','Client not found.');

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

    // optional toggle active/inactive
    public function toggleStatus($id)
    {
        $this->ensureLogin();
        $client = $this->clientModel->find($id);
        if (! $client) return redirect()->back()->with('error','Client not found.');

        $new = $client['status'] === 'active' ? 'inactive' : 'active';
        $this->clientModel->update($id, ['status' => $new]);
        return redirect()->back()->with('success','Client status updated.');
    }
}
