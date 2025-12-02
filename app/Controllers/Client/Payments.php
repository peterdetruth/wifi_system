<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use App\Models\TransactionModel;
use App\Models\MpesaTransactionModel;
use App\Models\VoucherModel;
use App\Models\ClientModel;
use App\Services\ActivationService;
use App\Services\MpesaLogger;
use App\Services\MpesaService;
use App\Libraries\RouterSync;

class Payments extends BaseController
{
    protected $packageModel;
    protected $subscriptionModel;
    protected $transactionModel;
    protected $mpesaTransactionModel;
    protected $voucherModel;
    protected $clientModel;

    protected $mpesaLogger;
    protected $activationService;
    protected $mpesaService;

    protected RouterSync $routerSync;

    public function __construct()
    {
        $this->packageModel = new PackageModel();
        $this->subscriptionModel = new SubscriptionModel();
        $this->transactionModel = new TransactionModel();
        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->voucherModel = new VoucherModel();
        $this->clientModel = new ClientModel();

        helper(['form', 'url', 'text']);

        $this->mpesaLogger = new MpesaLogger();
        $this->activationService = new ActivationService($this->mpesaLogger);
        $this->mpesaService = new MpesaService($this->mpesaLogger);

        // Library handles router provisioning
        $this->routerSync = new RouterSync();
    }

    public function checkout($packageId)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $package = $this->packageModel->find($packageId);
        if (!$package) return redirect()->to('/client/packages')->with('error', 'Package not found.');

        return view('client/payments/checkout', ['package' => $package]);
    }

    public function process()
    {
        helper(['mpesa_debug']);
        mpesa_debug_clear();
        mpesa_debug("=== New Payment Process Started ===");

        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $packageId   = (int)$this->request->getPost('package_id');
        $voucherCode = trim($this->request->getPost('voucher_code'));
        $phone       = trim($this->request->getPost('phone'));

        $package = $this->packageModel->find($packageId);
        if (!$package) {
            mpesa_debug("❌ Package not found: {$packageId}");
            return redirect()->to('/client/packages')->with('error', 'Package not found.');
        }

        $client = $this->clientModel->find($clientId);
        $phone = $phone ?: ($client['phone'] ?? '');
        $clientUsername = $client['username'] ?? 'Unknown';

        // --- Voucher redemption ---
        if (!empty($voucherCode)) {
            mpesa_debug("Voucher detected — checking validity ({$voucherCode})");
            $voucher = $this->voucherModel->isValidVoucher($voucherCode);
            if (!$voucher) {
                mpesa_debug("❌ Invalid or expired voucher: {$voucherCode}");
                return redirect()->back()->withInput()->with('error', 'Invalid or expired voucher code.');
            }

            try {
                $activated = $this->activationService->activate($clientId, $packageId, 0);
                if (!$activated) throw new \Exception("ActivationService failed.");

                $this->voucherModel->markAsUsed($voucherCode, $clientId);

                mpesa_debug("✅ Voucher successfully redeemed for {$clientUsername}.");

                // Provision router automatically
                try {
                    $syncResult = $this->routerSync->addUserToRouter($client, $package);
                } catch (\Throwable $e) {
                    log_message('error', 'Router sync failed: ' . $e->getMessage());
                    $syncResult = [
                        'success' => false,
                        'message' => 'Router provisioning failed. Please contact support.'
                    ];
                }

                return view('client/payments/success', [
                    'transaction' => ['id' => 0, 'voucher' => $voucherCode],
                    'client' => $client,
                    'package' => $package,
                    'router_sync' => $syncResult
                ]);
            } catch (\Throwable $e) {
                mpesa_debug("❌ Voucher redemption failed: " . $e->getMessage());
                return redirect()->back()->with('error', 'Voucher redemption failed: ' . $e->getMessage());
            }
        }

        // --- M-PESA payment ---
        $amount = (float)$package['price'];
        if ($amount <= 0) {
            mpesa_debug("❌ Invalid amount: {$amount}");
            return redirect()->back()->with('error', 'Invalid payment amount.');
        }

        try {
            mpesa_debug("🔸 Initiating M-PESA transaction for client {$clientUsername} (clientId={$clientId}, packageId={$packageId}, amount={$amount})");

            $checkoutRequestId = $this->mpesaService->initiateTransaction(
                $clientId,
                $packageId,
                $amount,
                $phone
            );

            if (!$checkoutRequestId) {
                mpesa_debug("❌ Failed to initiate M-PESA transaction (no checkout id returned).");
                throw new \Exception("Failed to initiate M-PESA transaction.");
            }

            mpesa_debug("✅ M-PESA transaction initiated — checkoutRequestId: {$checkoutRequestId}");

            // Redirect to waiting page for polling
            return redirect()->to('/client/payments/waiting/' . $checkoutRequestId);
        } catch (\Throwable $e) {
            mpesa_debug("❌ Error initiating payment: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to initiate payment: ' . $e->getMessage());
        }
    }

    public function waiting($checkoutRequestId = null)
    {
        if (!$checkoutRequestId) {
            return redirect()->to('/client/dashboard')->with('error', 'Invalid transaction reference.');
        }

        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login')->with('error', 'Please login.');
        }

        // Find client to display phone
        $client = $this->clientModel->find($clientId);
        $mpesaTx = $this->mpesaTransactionModel
            ->where('checkout_request_id', $checkoutRequestId)
            ->first();

        $phone = $mpesaTx['phone'] ?? $client['phone'] ?? '';

        return view('client/payments/waiting', [
            'checkoutRequestId' => $checkoutRequestId,
            'phone' => $phone
        ]);
    }

    /**
     * Payment success page
     */
    public function success($checkoutRequestID = null)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login')->with('error', 'Please login.');
        }

        $mpesaTx = null;
        $subscription = null;

        // --- 1️⃣ Try to find M-PESA transaction if ID provided ---
        if ($checkoutRequestID) {
            $mpesaTx = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();
        }

        // --- 2️⃣ Find the latest subscription for this client ---
        $subscription = $this->subscriptionModel
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->first();

        if (!$subscription) {
            return redirect()->to('/client/dashboard')->with('error', 'Subscription not found.');
        }

        $package = $this->packageModel->find($subscription['package_id']);
        $client = $this->clientModel->find($clientId);

        // --- 3️⃣ Router provisioning ---
        try {
            $syncResult = $this->routerSync->addUserToRouter($client, $package);
        } catch (\Throwable $e) {
            log_message('error', 'Router sync failed: ' . $e->getMessage());
            $syncResult = [
                'success' => false,
                'message' => 'Router provisioning failed. Please contact support.'
            ];
        }

        // --- 4️⃣ Prepare data for view ---
        $data = [
            'mpesaTx' => $mpesaTx,
            'subscription' => $subscription,
            'client' => $client,
            'package' => $package,
            'router_sync' => $syncResult
        ];

        return view('client/payments/success', $data);
    }



    public function checkStatus()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return $this->response->setJSON(['status' => 'unauthorized']);

        $mpesaTx = $this->mpesaTransactionModel
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->first();

        if (!$mpesaTx) return $this->response->setJSON(['status' => 'pending']);

        if (strtolower($mpesaTx['status']) === 'success') {
            $transaction = $this->transactionModel
                ->where('client_id', $clientId)
                ->orderBy('id', 'DESC')
                ->first();

            return $this->response->setJSON([
                'status' => 'success',
                'transaction_id' => $transaction['id'] ?? 0
            ]);
        }

        return $this->response->setJSON(['status' => 'pending']);
    }

    public function status($checkoutRequestID)
    {
        $transaction = $this->mpesaTransactionModel
            ->where('checkout_request_id', $checkoutRequestID)
            ->first();

        if (!$transaction) return $this->response->setJSON(['status' => 'NotFound']);

        return $this->response->setJSON(['status' => $transaction['status']]);
    }
}
