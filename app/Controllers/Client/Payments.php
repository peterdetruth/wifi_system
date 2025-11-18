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

    public function __construct()
    {
        $this->packageModel = new PackageModel();
        $this->subscriptionModel = new SubscriptionModel();
        $this->transactionModel = new TransactionModel();
        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->voucherModel = new VoucherModel();
        $this->clientModel = new ClientModel();

        helper(['form', 'url', 'text']);

        // Custom logger
        $this->mpesaLogger = new MpesaLogger();

        // Services
        $this->activationService = new ActivationService($this->mpesaLogger);
        $this->mpesaService = new MpesaService($this->mpesaLogger);
    }

    /**
     * Show checkout page
     */
    public function checkout($packageId)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $package = $this->packageModel->find($packageId);
        if (!$package) return redirect()->to('/client/packages')->with('error', 'Package not found.');

        return view('client/payments/checkout', ['package' => $package]);
    }

    /**
     * Process payment or voucher redemption
     */
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

        // Voucher redemption
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
                return redirect()->to('/client/payments/success/0')
                    ->with('success', 'Voucher redeemed successfully! Subscription activated.');
            } catch (\Throwable $e) {
                mpesa_debug("❌ Voucher redemption failed: " . $e->getMessage());
                return redirect()->back()->with('error', 'Voucher redemption failed: ' . $e->getMessage());
            }
        }

        // M-PESA payment
        $amount = (float)$package['price'];
        if ($amount <= 0) {
            mpesa_debug("❌ Invalid amount: {$amount}");
            return redirect()->back()->with('error', 'Invalid payment amount.');
        }

        try {
            $db = \Config\Database::connect();
            $db->transStart();

            $subscriptionData = [
                'client_id'  => $clientId,
                'package_id' => $packageId,
                'amount'     => $amount,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date'   => $this->calculateExpiry($package['duration_length'], $package['duration_unit']),
                'status'     => 'pending'
            ];

            $db->table('client_packages')->insert($subscriptionData);
            $clientPackageId = $db->insertID();
            $db->transComplete();

            if ($db->transStatus() === false) {
                $error = $db->error();
                mpesa_debug("❌ DB Error inserting client_packages: " . json_encode($error));
                throw new \Exception('Database commit failed during client_packages creation.');
            }

            mpesa_debug("🔹 Pending client_packages row created: {$clientPackageId}");

            // Initiate M-PESA transaction
            $success = $this->mpesaService->initiateTransaction(
                $clientId,
                $packageId,
                $amount,
                $phone,
                null,
                null
            );

            if (!$success) throw new \Exception("Failed to initiate M-PESA transaction.");

            mpesa_debug("✅ M-PESA transaction initiated for client {$clientUsername}");

            return view('client/payments/waiting', [
                'clientPackageId' => $clientPackageId,
                'amount' => $amount,
                'phone' => $phone
            ]);
        } catch (\Throwable $e) {
            mpesa_debug("❌ Error initiating payment: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to initiate payment: ' . $e->getMessage());
        }
    }

    /**
     * Waiting page
     */
    public function waiting($clientPackageId = null)
    {
        if (!$clientPackageId) {
            return redirect()->to('/client/dashboard')
                ->with('error', 'Invalid payment reference.');
        }

        return view('client/payments/waiting', [
            'clientPackageId' => $clientPackageId
        ]);
    }


    /**
     * Success page
     */
    public function success($transactionId)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $transaction = $this->transactionModel
            ->where('id', $transactionId)
            ->where('client_id', $clientId)
            ->first();

        if (!$transaction) return redirect()->to('/client/dashboard')->with('error', 'Transaction not found.');

        $package = $this->packageModel->find($transaction['package_id']);

        return view('client/payments/success', [
            'package' => $package,
            'subscription' => [
                'expires_on' => $transaction['expires_on'] ?? date('Y-m-d H:i:s', strtotime('+' . $package['duration_length'] . ' ' . $package['duration_unit']))
            ]
        ]);
    }

    /**
     * Pending page
     */
    public function pending($checkoutRequestID = null)
    {
        if (!$checkoutRequestID) return redirect()->to('/client/dashboard')->with('error', 'Invalid transaction reference.');

        return view('client/payments/pending', ['checkoutRequestID' => $checkoutRequestID]);
    }

    /**
     * Check payment status via Ajax
     */
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
                'transaction_id' => $transaction['id']
            ]);
        }

        return $this->response->setJSON(['status' => 'pending']);
    }

    /**
     * Get transaction status by CheckoutRequestID
     */
    public function status($checkoutRequestID)
    {
        $transaction = $this->mpesaTransactionModel
            ->where('checkout_request_id', $checkoutRequestID)
            ->first();

        if (!$transaction) return $this->response->setJSON(['status' => 'NotFound']);

        return $this->response->setJSON(['status' => $transaction['status']]);
    }

    /**
     * Calculate expiry datetime
     */
    private function calculateExpiry($length, $unit)
    {
        $unit = strtolower(trim($unit));
        switch ($unit) {
            case 'minutes':
            case 'minute':
                $interval = "+$length minutes";
                break;
            case 'hours':
            case 'hour':
                $interval = "+$length hours";
                break;
            case 'days':
            case 'day':
                $interval = "+$length days";
                break;
            case 'months':
            case 'month':
                $interval = "+$length months";
                break;
            default:
                $interval = "+$length days";
        }
        return date('Y-m-d H:i:s', strtotime($interval));
    }
}
