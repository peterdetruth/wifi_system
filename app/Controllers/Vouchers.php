<?php

namespace App\Controllers;

use App\Models\VoucherModel;
use App\Models\RouterModel;
use App\Models\PackageModel;
use CodeIgniter\Controller;
use Config\Database;

class Vouchers extends BaseController
{
    protected $voucherModel;
    protected $routerModel;
    protected $packageModel;

    public function __construct()
    {
        $this->voucherModel = new VoucherModel();
        $this->routerModel = new RouterModel();
        $this->packageModel = new PackageModel();
    }

    private function ensureLogin()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please login first')->send();
        }
    }

    // ======================================
    // INDEX: List all vouchers
    // ======================================
    public function index()
    {
        $this->ensureLogin();

        $data['vouchers'] = $this->voucherModel->getAllWithPackage();
        return view('admin/vouchers/index', $data);
    }

    // ======================================
    // CREATE: Show form
    // ======================================
    public function create()
    {
        $this->ensureLogin();

        $data['routers']  = $this->routerModel->orderBy('name', 'ASC')->findAll();

        $data['packages'] = $this->packageModel
            ->where('type', 'hotspot')
            ->orderBy('id', 'ASC')
            ->findAll();

        return view('admin/vouchers/create', $data);
    }


    // ======================================
    // STORE: Save new voucher
    // ======================================
    public function store()
    {
        helper('time');

        $packageId = $this->request->getPost('package_id');
        $package = $this->packageModel->find($packageId);

        if (!$package) {
            return redirect()->back()->with('error', 'Invalid package selected.');
        }

        // Calculate expiry dynamically
        $expiresOn = calculate_expiry(null, $package['duration_length'], $package['duration_unit']);

        $data = [
            'package_id' => $packageId,
            'router_id'  => $this->request->getPost('router_id'),
            'purpose'    => $this->request->getPost('purpose'),
            'phone'      => $this->request->getPost('phone'),
            'expires_on' => $expiresOn,
            'status'     => 'unused',
            'code'       => strtoupper(bin2hex(random_bytes(4))),
        ];

        $this->voucherModel->save($data);

        return redirect()->to('/admin/vouchers')->with('success', 'Voucher created successfully!');
    }

    // ======================================
    // EDIT: Load form with existing data
    // ======================================
    public function edit($id)
    {
        $this->ensureLogin();

        $voucher = $this->voucherModel->find($id);
        if (!$voucher) {
            return redirect()->to('/admin/vouchers')->with('error', 'Voucher not found.');
        }

        $data['voucher']  = $voucher;
        $data['routers']  = $this->routerModel->orderBy('name', 'ASC')->findAll();
        $data['packages'] = $this->packageModel->orderBy('id', 'ASC')->findAll();

        return view('admin/vouchers/edit', $data);
    }

    // ======================================
    // UPDATE: Apply changes
    // ======================================
    public function update($id)
    {
        $this->ensureLogin();

        try {
            $data = $this->request->getPost();

            if ($this->voucherModel->update($id, $data)) {
                return redirect()->to('/admin/vouchers')->with('success', 'Voucher updated successfully.');
            }

            $dbError = $this->voucherModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update voucher: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // ======================================
    // DELETE: Remove a voucher
    // ======================================
    public function delete($id)
    {
        $this->ensureLogin();

        try {
            if ($this->voucherModel->delete($id)) {
                return redirect()->to('/admin/vouchers')->with('success', 'Voucher deleted successfully.');
            }

            $dbError = Database::connect()->error();
            return redirect()->to('/admin/vouchers')->with('error', 'Failed to delete voucher: ' . print_r($dbError, true));
        } catch (\Exception $e) {
            return redirect()->to('/admin/vouchers')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
