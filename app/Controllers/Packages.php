<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PackageModel;
use App\Models\RouterModel;
use Config\Database;

class Packages extends BaseController
{
    protected $packageModel;
    protected $routerModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->packageModel = new PackageModel();
        $this->routerModel  = new RouterModel();
    }

    /**
     * Require login helper
     */
    protected function requireLoginRedirect()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/login')->with('error', 'Please login')->send();
        }
    }

    /**
     * Display all packages
     */
    public function index()
    {
        $this->requireLoginRedirect();

        $packageModel = new \App\Models\PackageModel();
        $routerModel = new \App\Models\RouterModel();

        // Get all packages
        $packages = $packageModel->findAll();

        // Get all routers as [id => name]
        $routers = [];
        foreach ($routerModel->findAll() as $router) {
            $routers[$router['id']] = $router['name'];
        }

        // Pass both to the view
        $data = [
            'title' => 'Packages',
            'packages' => $packages,
            'routers' => $routers
        ];

        return view('admin/packages/index', $data);
    }

    /**
     * Show create form
     */
    public function create()
    {
        $this->requireLoginRedirect();

        $data = [
            'routers' => $this->routerModel->findAll(),
            'title'   => 'Create Package'
        ];

        return view('admin/packages/create', $data);
    }

    private function calculateValidityDays($length, $unit)
    {
        switch (strtolower($unit)) {
            case 'day':
            case 'days':
                return $length;
            case 'week':
            case 'weeks':
                return $length * 7;
            case 'month':
            case 'months':
                return $length * 30;
            case 'year':
            case 'years':
                return $length * 365;
            default:
                return 7; // fallback
        }
    }

    /**
     * Save new package
     */
    public function store()
    {
        $this->requireLoginRedirect();

        try {
            $data = [
                'name'             => $this->request->getPost('name'),
                'type'             => $this->request->getPost('type'),
                'account_type'     => $this->request->getPost('account_type'),
                'price'            => $this->request->getPost('price'),
                'devices'          => $this->request->getPost('devices'),
                'duration_length'  => $this->request->getPost('duration_length'),
                'duration_unit'    => $this->request->getPost('duration_unit'),
                'router_id'        => $this->request->getPost('router_id'),
                'bandwidth_value'  => $this->request->getPost('bandwidth_value'),
                'bandwidth_unit'   => $this->request->getPost('bandwidth_unit'),
                'burst_enabled'    => $this->request->getPost('burst_enabled') ? 1 : 0,
                'hotspot_plan_type'    => $this->request->getPost('hotspot_plan_type'),
                'hotspot_devices' => $this->request->getPost('hotspot_devices'),
            ];

            // Handle burst settings only if enabled
            if ($this->request->getPost('burst_enabled')) {
                $data = array_merge($data, [
                    'upload_burst_rate_value'        => $this->request->getPost('upload_burst_rate_value'),
                    'upload_burst_rate_unit'         => $this->request->getPost('upload_burst_rate_unit'),
                    'download_burst_rate_value'      => $this->request->getPost('download_burst_rate_value'),
                    'download_burst_rate_unit'       => $this->request->getPost('download_burst_rate_unit'),
                    'upload_burst_threshold_value'   => $this->request->getPost('upload_burst_threshold_value'),
                    'upload_burst_threshold_unit'    => $this->request->getPost('upload_burst_threshold_unit'),
                    'download_burst_threshold_value' => $this->request->getPost('download_burst_threshold_value'),
                    'download_burst_threshold_unit'  => $this->request->getPost('download_burst_threshold_unit'),
                    'upload_burst_time'              => $this->request->getPost('upload_burst_time'),
                    'download_burst_time'            => $this->request->getPost('download_burst_time'),
                ]);
            } else {
                // If burst not enabled, set all burst fields to NULL
                $burstFields = [
                    'upload_burst_rate_value', 'upload_burst_rate_unit',
                    'download_burst_rate_value', 'download_burst_rate_unit',
                    'upload_burst_threshold_value', 'upload_burst_threshold_unit',
                    'download_burst_threshold_value', 'download_burst_threshold_unit',
                    'upload_burst_time', 'download_burst_time'
                ];
                foreach ($burstFields as $field) {
                    $data[$field] = null;
                }
            }

            $length = $this->request->getPost('duration_length');
            $unit = $this->request->getPost('duration_unit');

            $data['validity_days'] = $this->calculateValidityDays($length, $unit);

            if ($this->packageModel->save($data)) {
                return redirect()->to('/admin/packages')->with('success', 'Package created successfully.');
            }

            $dbError = $this->packageModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to create package: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Edit package form
     */
    public function edit($id)
    {
        $this->requireLoginRedirect();

        $package = $this->packageModel->find($id);
        if (! $package) {
            return redirect()->to('/admin/packages')->with('error', 'Package not found.');
        }

        $routerModel = new \App\Models\RouterModel();
        $routers = $routerModel->findAll();

        $data = [
            'title' => 'Edit Package',
            'package' => $package,
            'routers' => $routers
        ];

        return view('admin/packages/edit', $data);
    }

    /**
     * Update existing package
     */
    public function update($id)
    {
        $this->requireLoginRedirect();

        try {
            $data = [
                'name'             => $this->request->getPost('name'),
                'type'             => $this->request->getPost('type'),
                'account_type'     => $this->request->getPost('account_type'),
                'price'            => $this->request->getPost('price'),
                'duration_length'  => $this->request->getPost('duration_length'),
                'duration_unit'    => $this->request->getPost('duration_unit'),
                'router_id'        => $this->request->getPost('router_id'),
                'bandwidth_value'  => $this->request->getPost('bandwidth_value'),
                'bandwidth_unit'   => $this->request->getPost('bandwidth_unit'),
                'burst_enabled'    => $this->request->getPost('burst_enabled') ? 1 : 0,
                'hotspot_plan_type'    => $this->request->getPost('hotspot_plan_type'),
                'hotspot_devices' => $this->request->getPost('hotspot_devices'),
            ];

            if ($this->request->getPost('burst_enabled')) {
                $data = array_merge($data, [
                    'upload_burst_rate_value'        => $this->request->getPost('upload_burst_rate_value'),
                    'upload_burst_rate_unit'         => $this->request->getPost('upload_burst_rate_unit'),
                    'download_burst_rate_value'      => $this->request->getPost('download_burst_rate_value'),
                    'download_burst_rate_unit'       => $this->request->getPost('download_burst_rate_unit'),
                    'upload_burst_threshold_value'   => $this->request->getPost('upload_burst_threshold_value'),
                    'upload_burst_threshold_unit'    => $this->request->getPost('upload_burst_threshold_unit'),
                    'download_burst_threshold_value' => $this->request->getPost('download_burst_threshold_value'),
                    'download_burst_threshold_unit'  => $this->request->getPost('download_burst_threshold_unit'),
                    'upload_burst_time'              => $this->request->getPost('upload_burst_time'),
                    'download_burst_time'            => $this->request->getPost('download_burst_time'),
                ]);
            } else {
                $burstFields = [
                    'upload_burst_rate_value', 'upload_burst_rate_unit',
                    'download_burst_rate_value', 'download_burst_rate_unit',
                    'upload_burst_threshold_value', 'upload_burst_threshold_unit',
                    'download_burst_threshold_value', 'download_burst_threshold_unit',
                    'upload_burst_time', 'download_burst_time'
                ];
                foreach ($burstFields as $field) {
                    $data[$field] = null;
                }
            }

            if ($this->packageModel->update($id, $data)) {
                return redirect()->to('/admin/packages')->with('success', 'Package updated successfully.');
            }

            $length = $this->request->getPost('duration_length');
            $unit = $this->request->getPost('duration_unit');

            $data['validity_days'] = $this->calculateValidityDays($length, $unit);

            $dbError = $this->packageModel->errors() ?: Database::connect()->error();
            return redirect()->back()->withInput()->with('error', 'Failed to update package: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete package
     */
    public function delete($id)
    {
        $this->requireLoginRedirect();

        try {
            if ($this->packageModel->delete($id)) {
                return redirect()->to('/admin/packages')->with('success', 'Package deleted successfully.');
            }

            $dbError = Database::connect()->error();
            return redirect()->to('/admin/packages')->with('error', 'Failed to delete package: ' . print_r($dbError, true));

        } catch (\Exception $e) {
            return redirect()->to('/admin/packages')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
