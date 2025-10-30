<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;

class Packages extends BaseController
{
    protected $packageModel;

    public function __construct()
    {
        $this->packageModel = new \App\Models\PackageModel();
        helper(['url', 'form']);
    }

    public function index()
    {
        $packages = $this->packageModel->findAll();
        return view('client/packages/index', ['packages' => $packages]);
    }

    public function view($id)
    {
        $package = $this->packageModel->find($id);
        if (!$package) {
            return redirect()->to('/client/packages')->with('error', 'Package not found.');
        }
        return view('client/packages/view', ['package' => $package]);
    }

    public function subscribe($packageId)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login');
        }

        // 🔹 Redirect to Payments controller
        return redirect()->to('/client/payments/checkout/' . $packageId);
    }
}
