<?php

namespace App\Controllers;
use App\Models\AdminModel;
use CodeIgniter\Controller;
use Config\Database;

class Admins extends BaseController
{
    protected $adminModel;

    public function __construct()
    {
        // helper(['form', 'url']);
        $this->adminModel = new AdminModel();
    }

    protected function requireLogin()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please login first.')->send();
        }
    }

    // Accept both 'superadmin' and 'super_admin' strings
    protected function isSuperAdmin(): bool
    {
        $r = session()->get('role');
        return in_array($r, ['superadmin', 'super_admin'], true);
    }

    protected function requireSuperAdmin()
    {
        if (! $this->isSuperAdmin()) {
            return redirect()->to('/admin/dashboard')->with('error', 'Access denied. Super admin only.')->send();
        }
    }

    public function index() {
        $this->requireLogin();
        $this->requireSuperAdmin();

        $data['admins'] = $this->adminModel->orderBy('id','DESC')->findAll();

        echo view('admin/admins/index', $data);
    }

    public function create()
    {
        $this->requireLogin();
        $this->requireSuperAdmin();
        echo view('admin/admins/create');
    }

    public function store()
    {
        try {
            $data = $this->request->getPost();

            if ($this->adminModel->save($data)) {
                return redirect()->to('/admin/admins')->with('success', 'Admin created successfully.');
            }

            $dbError = $this->adminModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to create admin: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->requireLogin();
        $this->requireSuperAdmin();

        $admin = $this->adminModel->find($id);
        if (! $admin) {
            return redirect()->to('/admin/admins')->with('error', 'Admin not found.');
        }

        echo view('admin/admins/edit', ['admin' => $admin]);
    }

    public function update($id)
    {
        try {
            $data = $this->request->getPost();

            if ($this->adminModel->update($id, $data)) {
                return redirect()->to('/admin/admins')->with('success', 'Admin updated successfully.');
            }

            $dbError = $this->adminModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update admin: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            if ($this->adminModel->delete($id)) {
                return redirect()->to('/admin/admins')->with('success', 'Admin deleted successfully.');
            }

            $dbError = Database::connect()->error();
            return redirect()->to('/admin/admins')->with('error', 'Failed to delete admin: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/admins')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
