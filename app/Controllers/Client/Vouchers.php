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
        helper(['form', 'url']);
    }

    /**
     * Handle voucher redemption (supports AJAX)
     */
    public function redeemPost()
    {
        $clientId = session()->get('client_id');

        if (!$clientId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'You must be logged in.'
            ]);
        }

        $voucherCode = $this->request->getPost('voucher_code');

        if (!$voucherCode) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Please enter a voucher code.'
            ]);
        }

        $voucher = $this->voucherModel->isValidVoucher($voucherCode);

        if (!$voucher) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid or expired voucher.'
            ]);
        }

        $package = $this->packageModel->find($voucher['package_id']);

        if (!$package) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Package linked to voucher not found.'
            ]);
        }

        $expiresOn = date('Y-m-d H:i:s', strtotime('+' . $package['duration_length'] . ' ' . $package['duration_unit']));

        // Activate subscription
        $this->subscriptionModel->insert([
            'client_id'   => $clientId,
            'package_id'  => $package['id'],
            'router_id'   => $package['router_id'],
            'start_date'  => date('Y-m-d H:i:s'),
            'expires_on'  => $expiresOn,
            'status'      => 'active',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Mark voucher used
        $this->voucherModel->markAsUsed($voucherCode, $clientId);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Voucher redeemed successfully!'
        ]);
    }
}
