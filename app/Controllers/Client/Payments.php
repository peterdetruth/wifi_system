<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use App\Models\TransactionModel;
use App\Models\MpesaTransactionModel;
use App\Models\VoucherModel;
use App\Models\ClientModel;

class Payments extends BaseController
{
    protected $packageModel;
    protected $subscriptionModel;
    protected $transactionModel;
    protected $mpesaTransactionModel;
    protected $voucherModel;
    protected $clientModel;

    public function __construct()
    {
        $this->packageModel = new PackageModel();
        $this->subscriptionModel = new SubscriptionModel();
        $this->transactionModel = new TransactionModel();
        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->voucherModel = new VoucherModel();
        $this->clientModel = new ClientModel();
        helper(['form', 'url']);
    }

    /**
     * Show checkout page for selected package
     */
    public function checkout($packageId)
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $package = $this->packageModel->find($packageId);
        if (!$package) return redirect()->to('/client/packages')->with('error', 'Package not found.');

        return view('client/payments/checkout', [
            'package' => $package
        ]);
    }

    /**
     * Process payment or voucher redemption
     */
    public function process()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $packageId = $this->request->getPost('package_id');
        $voucherCode = trim($this->request->getPost('voucher_code'));
        $phone = trim($this->request->getPost('phone'));

        $package = $this->packageModel->find($packageId);
        if (!$package) return redirect()->to('/client/packages')->with('error', 'Package not found.');

        $client = $this->clientModel->find($clientId);
        $clientUsername = $client['username'] ?? 'Unknown';
        $phone = $phone ?: ($client['phone'] ?? '');

        // 🔹 Calculate expiry date using duration_length + duration_unit
        $expiryDate = $this->calculateExpiry($package['duration_length'], $package['duration_unit']);

        $subscriptionData = [
            'client_id' => $clientId,
            'package_id' => $packageId,
            'router_id' => $package['router_id'],
            'start_date' => date('Y-m-d H:i:s'),
            'expires_on' => $expiryDate,
            'status' => 'active'
        ];

        // 🔹 Case 1: Voucher Redemption
        if (!empty($voucherCode)) {
            $voucher = $this->voucherModel->isValidVoucher($voucherCode);

            if (!$voucher) {
                return redirect()->back()->withInput()->with('error', 'Invalid or expired voucher code.');
            }

            $this->subscriptionModel->insert($subscriptionData);
            $this->voucherModel->markAsUsed($voucherCode, $clientId);

            return redirect()->to('/client/subscriptions')
                ->with('success', 'Voucher redeemed successfully! Subscription activated.');
        }

        // 🔹 Case 2: Payment
        $mpesaCode = 'MP' . strtoupper(bin2hex(random_bytes(4)));
        $amount = $package['price'];

        // Insert mpesa transaction
        $this->mpesaTransactionModel->insert([
            'client_id' => $clientId,
            'client_username' => $clientUsername,
            'package_id' => $packageId,
            'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
            'amount' => $amount,
            'transaction_id' => $mpesaCode,
            'phone' => $phone,
            'status' => 'Completed',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Insert transaction
        $this->transactionModel->insert([
            'client_id' => $clientId,
            'package_id' => $packageId,
            'package_type' => $package['type'],
            'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
            'amount' => $amount,
            'method' => 'mpesa',
            'mpesa_code' => $mpesaCode,
            'router_id' => $package['router_id'],
            'router_status' => 'active',
            'online_status' => 'offline',
            'status' => 'success',
            'created_on' => date('Y-m-d H:i:s'),
            'expires_on' => $expiryDate
        ]);

        // Create subscription
        $this->subscriptionModel->insert($subscriptionData);

        return redirect()->to('/client/subscriptions')->with('success', 'Payment successful! Subscription activated.');
    }

    /**
     * Calculate expiry date from duration fields
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
