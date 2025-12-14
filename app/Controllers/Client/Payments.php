<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use App\Models\TransactionModel;
use App\Models\MpesaTransactionModel;
use App\Models\VoucherModel;
use App\Models\ClientModel;
use App\Libraries\RouterSync;

use App\Services\ActivationService;
use App\Services\MpesaLogger;
use App\Services\MpesaService;
use App\Services\LogService;

class Payments extends BaseController
{
    protected PackageModel $packageModel;
    protected SubscriptionModel $subscriptionModel;
    protected TransactionModel $transactionModel;
    protected MpesaTransactionModel $mpesaTransactionModel;
    protected VoucherModel $voucherModel;
    protected ClientModel $clientModel;

    protected MpesaLogger $mpesaLogger;
    protected ActivationService $activationService;
    protected MpesaService $mpesaService;
    protected LogService $logService;

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
        $this->logService = new LogService();

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
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $username = session()->get('client_username');
        $ip = $this->request->getIPAddress();

        helper(['mpesa_debug']);
        mpesa_debug_clear();
        mpesa_debug("=== New Payment Process Started ===");
        $this->logService->debug(
            'mpesa',
            'New Payment Process Started',
            ['username' => $username],
            $clientId,
            $ip
        );

        $packageId   = (int)$this->request->getPost('package_id');
        $voucherCode = trim($this->request->getPost('voucher_code'));
        $phone       = trim($this->request->getPost('phone'));

        $package = $this->packageModel->find($packageId);
        if (!$package) {
            mpesa_debug("❌ Package not found: {$packageId}");
            $this->logService->warning(
                'mpesa',
                'Package not found during payment process',
                ['packageId' => $packageId, 'username' => $username],
                $clientId,
                $ip
            );
            return redirect()->to('/client/packages')->with('error', 'Package not found.');
        }

        $client = $this->clientModel->find($clientId);
        $phone = $phone ?: ($client['phone'] ?? '');
        $clientUsername = $client['username'] ?? 'Unknown';

        // --- Voucher redemption --- (no subscription creation yet)
        if (!empty($voucherCode)) {
            mpesa_debug("Voucher detected — checking validity ({$voucherCode})");
            $this->logService->debug(
                'mpesa',
                'Voucher detected — checking validity',
                ['voucherCode' => $voucherCode, 'packageId' => $packageId, 'username' => $clientUsername],
                $clientId,
                $ip
            );

            $voucher = $this->voucherModel->isValidVoucher($voucherCode);
            if (!$voucher) {
                mpesa_debug("❌ Invalid or expired voucher: {$voucherCode}");
                $this->logService->warning(
                    'mpesa',
                    'Invalid or expired voucher attempted',
                    ['voucherCode' => $voucherCode, 'packageId' => $packageId, 'username' => $clientUsername],
                    $clientId,
                    $ip
                );
                return redirect()->back()->withInput()->with('error', 'Invalid or expired voucher code.');
            }

            mpesa_debug("✅ Voucher is valid. Payment still needs to be done.");
        }

        $amount = (float)$package['price'];
        if ($amount <= 0) {
            mpesa_debug("❌ Invalid amount: {$amount}");
            $this->logService->error(
                'mpesa',
                'Invalid payment amount.',
                ['amount' => $amount],
                $clientId,
                $ip
            );
            return redirect()->back()->with('error', 'Invalid payment amount.');
        }

        try {
            // --- Prevent duplicate pending transactions ---
            $existingPending = $this->mpesaTransactionModel
                ->where('client_id', $clientId)
                ->where('package_id', $packageId)
                ->where('status', 'Pending')
                ->orderBy('id', 'DESC')
                ->first();

            if ($existingPending) {
                mpesa_debug("⚠️ Existing pending transaction found: " . ($existingPending['checkout_request_id'] ?? 'N/A'));
                $this->logService->warning(
                    'mpesa',
                    'Existing pending transaction found.',
                    ['transaction_id' => $existingPending['id'], 'checkout_request_id' => $existingPending['checkout_request_id']],
                    $clientId,
                    $ip
                );
                return redirect()->to('/client/payments/waiting/' . ($existingPending['checkout_request_id'] ?? ''));
            }

            // --- Initiate M-PESA transaction ---
            mpesa_debug("🔸 Initiating M-PESA transaction for client {$clientUsername} (amount: {$amount})");
            $this->logService->debug(
                'mpesa',
                'Initiating M-PESA transaction',
                ['amount' => $amount, 'packageId' => $packageId, 'username' => $clientUsername],
                $clientId,
                $ip
            );

            $checkoutRequestId = $this->mpesaService->initiateTransaction(
                $clientId,
                $packageId,
                $amount,
                $phone
            );

            if (!$checkoutRequestId) {
                mpesa_debug("❌ Failed to initiate M-PESA transaction (no checkout id returned).");
                $this->logService->error(
                    'mpesa',
                    'Failed to initiate M-PESA transaction (no checkout id returned)',
                    ['amount' => $amount, 'packageId' => $packageId, 'username' => $clientUsername],
                    $clientId,
                    $ip
                );
                return redirect()->back()->with('error', 'STK push was not sent. Please try again.');
            }

            mpesa_debug("✅ M-PESA transaction initiated — checkoutRequestId: {$checkoutRequestId}");
            $this->logService->info(
                'mpesa',
                'M-PESA transaction initiated',
                ['checkoutRequestId' => $checkoutRequestId, 'amount' => $amount, 'packageId' => $packageId, 'username' => $clientUsername],
                $clientId,
                $ip
            );

            // No subscription creation yet — STK push sent
            return redirect()->to('/client/payments/waiting/' . $checkoutRequestId);
        } catch (\Throwable $e) {
            mpesa_debug("❌ Error initiating payment: " . $e->getMessage());
            $this->logService->error(
                'mpesa',
                'Failed to initiate payment',
                ['amount' => $amount, 'packageId' => $packageId, 'username' => $clientUsername, 'error' => $e->getMessage()],
                $clientId,
                $ip
            );
            return redirect()->back()->with('error', 'Failed to initiate payment: ' . $e->getMessage());
        }
    }

    public function waiting($checkoutRequestId = null)
    {
        if (!$checkoutRequestId) return redirect()->to('/client/dashboard')->with('error', 'Invalid transaction reference.');

        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login')->with('error', 'Please login.');

        $client = $this->clientModel->find($clientId);
        $mpesaTx = $this->mpesaTransactionModel->where('checkout_request_id', $checkoutRequestId)->first();
        $phone = $mpesaTx['phone'] ?? $client['phone'] ?? '';

        return view('client/payments/waiting', [
            'checkoutRequestId' => $checkoutRequestId,
            'phone' => $phone
        ]);
    }

    public function success($checkoutRequestId = null)
    {
        $ip = $this->request->getIPAddress();
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login')->with('error', 'Please login.');

        $mpesaTx = $checkoutRequestId
            ? $this->mpesaTransactionModel->where('checkout_request_id', $checkoutRequestId)->first()
            : null;

        $subscription = $this->subscriptionModel->where('client_id', $clientId)->orderBy('id', 'DESC')->first();
        if (!$subscription) return redirect()->to('/client/dashboard')->with('error', 'Subscription not found.');

        $package = $this->packageModel->find($subscription['package_id']);
        $client = $this->clientModel->find($clientId);
        $syncResult = $this->provisionRouter($client, $package);

        $this->logService->debug(
            'mpesa',
            'New subscription created after successful payment',
            ['package' => $package, 'subscription' => $subscription],
            $clientId,
            $ip
        );

        return view('client/payments/success', [
            'mpesaTx' => $mpesaTx,
            'subscription' => $subscription,
            'client' => $client,
            'package' => $package,
            'router_sync' => $syncResult
        ]);
    }

    public function checkStatus()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return $this->response->setJSON(['status' => 'unauthorized']);

        $mpesaTx = $this->mpesaTransactionModel->where('client_id', $clientId)->orderBy('id', 'DESC')->first();
        if (!$mpesaTx) return $this->response->setJSON(['status' => 'pending']);
        if (strtolower($mpesaTx['status']) === 'success') {
            $transaction = $this->transactionModel->where('client_id', $clientId)->orderBy('id', 'DESC')->first();
            return $this->response->setJSON([
                'status' => 'success',
                'transaction_id' => $transaction['id'] ?? 0
            ]);
        }

        return $this->response->setJSON(['status' => 'pending']);
    }

    public function status($checkoutRequestId)
    {
        $transaction = $this->mpesaTransactionModel->where('checkout_request_id', $checkoutRequestId)->first();
        return $this->response->setJSON(['status' => $transaction['status'] ?? 'NotFound']);
    }

    public function reconnectUsingMpesaCode(string $mpesaCode, int $clientId)
    {
        return $this->mpesaService->reconnectUsingMpesaCode($mpesaCode, $clientId);
    }

    // ====================== PRIVATE HELPERS ======================

    private function createSubscription(int $clientId, int $packageId, int $paymentId = 0, ?string $startDate = null)
    {
        $startDate = $startDate ?? date('Y-m-d H:i:s');
        $package = $this->packageModel->find($packageId);
        $expiresOn = $this->mpesaService->getExpiry($package, $startDate);

        // Expire old subscriptions
        $this->subscriptionModel->where('client_id', $clientId)
            ->where('package_id', $packageId)
            ->where('status', 'active')
            ->set(['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')])
            ->update();

        return $this->subscriptionModel->insert([
            'client_id' => $clientId,
            'package_id' => $packageId,
            'payment_id' => $paymentId ?: null,
            'start_date' => $startDate,
            'expires_on' => $expiresOn,
            'status' => 'active'
        ], true);
    }

    private function provisionRouter(array $client, array $package): array
    {
        try {
            return $this->routerSync->addUserToRouter($client, $package);
        } catch (\Throwable $e) {
            log_message('error', 'Router sync failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Router provisioning failed. Please contact support.'
            ];
        }
    }
}
