<?php

namespace App\Controllers;

use App\Models\PackageModel;
use App\Services\MpesaService;
use App\Services\MpesaLogger;

class Home extends BaseController
{
    protected PackageModel $packageModel;
    protected MpesaService $mpesaService;

    public function __construct()
    {
        $this->packageModel = new PackageModel();

        // Load helpers
        helper(['form', 'url']);

        // Initialize MpesaService with logger
        $this->mpesaService = new MpesaService(new MpesaLogger());
    }

    /**
     * Home page view
     */
    public function index(): string
    {
        $packages = $this->packageModel->findAll();
        return view('home/index', ['packages' => $packages]);
    }

    /**
     * AJAX endpoint:
     * Reconnect using M-PESA code (e.g., TKK00APFAV)
     */
    public function reconnectMpesa()
    {
        // Validate input
        $mpesaCode = trim($this->request->getPost('mpesa_code'));
        $clientId  = session('client_id');

        if (!$clientId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'You must be logged in to reconnect.'
            ]);
        }

        if (!$mpesaCode) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Please enter a valid M-PESA code.'
            ]);
        }

        try {

            // Process reconnect request
            $result = $this->mpesaService->reconnectUsingMpesaCode(
                strtoupper($mpesaCode),
                $clientId
            );

            // Service already returns array with success + message + optional data
            return $this->response->setJSON($result);

        } catch (\Throwable $e) {

            // Fail-safe response
            return $this->response->setJSON([
                'success' => false,
                'message' => 'System error: ' . $e->getMessage()
            ]);
        }
    }
}
