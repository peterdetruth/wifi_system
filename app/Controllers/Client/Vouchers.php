<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\VoucherModel;
use App\Models\SubscriptionModel;
use App\Models\PackageModel;

class Vouchers extends BaseController
{
    protected $voucherModel;
    protected $subscriptionModel;
    protected $packageModel;

    public function __construct()
    {
        $this->voucherModel = new VoucherModel();
        $this->subscriptionModel = new SubscriptionModel();
        $this->packageModel = new PackageModel();
        helper(['form', 'url', 'time']);
    }

    /**
     * Show the voucher redemption page
     */
    public function redeem()
    {
        $data['title'] = 'Redeem Voucher';
        return view('client/vouchers/redeem', $data);
    }

    /**
     * Handle voucher redemption
     */
    public function redeemPost()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return redirect()->to('/client/login');
        }

        $voucherCode = $this->request->getPost('voucher_code');

        if (!$voucherCode) {
            return redirect()->back()->with('error', 'Please enter a voucher code.');
        }

        $voucher = $this->voucherModel->isValidVoucher($voucherCode);

        if (!$voucher) {
            return redirect()->back()->with('error', 'Invalid or expired voucher.');
        }

        // Fetch package info
        $package = $this->packageModel->find($voucher['package_id']);
        if (!$package) {
            return redirect()->back()->with('error', 'Voucher package not found.');
        }

        // Calculate expiry based on package duration
        $expiresOn = date('Y-m-d H:i:s', strtotime('+' . $package['duration_length'] . ' ' . $package['duration_unit']));

        // Create subscription
        $subData = [
            'client_id' => $clientId,
            'package_id' => $package['id'],
            'router_id' => $package['router_id'],
            'start_date' => date('Y-m-d H:i:s'),
            'expires_on' => $expiresOn,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->subscriptionModel->insert($subData);

        // Mark voucher as used
        $this->voucherModel->markAsUsed($voucherCode, $clientId);

        return redirect()->to('/client/subscriptions')->with('success', 'Voucher redeemed successfully! Your package is now active.');
    }
}
