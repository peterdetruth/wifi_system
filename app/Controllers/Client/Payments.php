<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use App\Models\TransactionModel;
use App\Models\MpesaTransactionModel;
use App\Models\VoucherModel;
use App\Models\ClientModel;
use App\Helpers\ReceiptHelper;

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
     * Initiate M-PESA STK Push
     */
    public function initiatePayment($packageId)
    {
        helper('text');

        $package = $this->packageModel->find($packageId);
        $client = $this->clientModel->find(session()->get('client_id'));

        if (!$package || !$client) {
            return redirect()->back()->with('error', 'Invalid client or package.');
        }

        $amount = (int) $package['price'];
        $phone = $client['phone'];

        // 🔹 Request access token
        $consumerKey = getenv('MPESA_CONSUMER_KEY');
        $consumerSecret = getenv('MPESA_CONSUMER_SECRET');
        $credentials = base64_encode("$consumerKey:$consumerSecret");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $result = json_decode($response);
        curl_close($ch);

        if (!isset($result->access_token)) {
            return redirect()->back()->with('error', 'Failed to obtain M-PESA access token.');
        }

        $accessToken = $result->access_token;

        // 🔹 Prepare STK request
        $BusinessShortCode = getenv('MPESA_SHORTCODE');
        $Passkey = getenv('MPESA_PASSKEY');
        $Timestamp = date('YmdHis');
        $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

        $stkData = [
            'BusinessShortCode' => $BusinessShortCode,
            'Password'          => $Password,
            'Timestamp'         => $Timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $BusinessShortCode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => getenv('MPESA_CALLBACK_URL'),
            'AccountReference'  => 'Hotspot_' . $client['username'],
            'TransactionDesc'   => 'Payment for ' . $package['name']
        ];

        // 🔹 Initiate STK Push
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        if (isset($result['ResponseCode']) && $result['ResponseCode'] == "0") {
            $this->mpesaTransactionModel->insert([
                'client_id' => $client['id'],
                'client_username' => $client['username'],
                'package_id' => $package['id'],
                'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
                'amount' => $amount,
                'merchant_request_id' => $result['MerchantRequestID'] ?? null,
                'checkout_request_id' => $result['CheckoutRequestID'] ?? null,
                'status' => 'Pending',
                'created_at' => date('Y-m-d H:i:s'),
                'phone' => $phone
            ]);

            $checkoutRequestID = $result['CheckoutRequestID'] ?? null;
            return view('client/payments/pending', [
                'package' => $package,
                'client' => $client,
                'amount' => $amount,
                'phone' => $phone,
                'checkoutRequestID' => $checkoutRequestID
            ]);
        } else {
            return redirect()->back()->with('error', 'STK push failed: ' . ($result['errorMessage'] ?? 'Unknown error'));
        }
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

        return view('client/payments/checkout', ['package' => $package]);
    }

    /**
     * Process payment or voucher redemption
     */
    public function process()
    {
        helper(['mpesa_logger', 'mpesa_debug']);
        mpesa_debug_clear();
        mpesa_debug("=== New Payment Process Started ===");
        mpesa_log("=== New Payment Process Started ===");

        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        $packageId   = $this->request->getPost('package_id');
        $voucherCode = trim($this->request->getPost('voucher_code'));
        $phone       = trim($this->request->getPost('phone'));

        $package = $this->packageModel->find($packageId);
        if (!$package) {
            mpesa_debug("❌ Package not found: {$packageId}");
            mpesa_log("❌ Package not found: {$packageId}");
            return redirect()->to('/client/packages')->with('error', 'Package not found.');
        }

        $client = $this->clientModel->find($clientId);
        $clientUsername = $client['username'] ?? 'Unknown';
        $phone = $phone ?: ($client['phone'] ?? '');
        $expiryDate = $this->calculateExpiry($package['duration_length'], $package['duration_unit']);

        $subscriptionData = [
            'client_id'  => $clientId,
            'package_id' => $packageId,
            'router_id'  => $package['router_id'],
            'start_date' => date('Y-m-d H:i:s'),
            'expires_on' => $expiryDate,
            'status'     => 'active'
        ];

        // 🔹 CASE 1: Voucher
        if (!empty($voucherCode)) {
            mpesa_debug("Voucher detected — checking validity ({$voucherCode})");
            mpesa_log("Voucher detected — checking validity ({$voucherCode})");

            $voucher = $this->voucherModel->isValidVoucher($voucherCode);
            if (!$voucher) {
                mpesa_debug("❌ Invalid or expired voucher: {$voucherCode}");
                mpesa_log("❌ Invalid or expired voucher: {$voucherCode}");
                return redirect()->back()->withInput()->with('error', 'Invalid or expired voucher code.');
            }

            $db = \Config\Database::connect();
            $db->transStart();
            try {
                if (!$this->subscriptionModel->insert($subscriptionData)) {
                    throw new \Exception('Failed to create subscription: ' . json_encode($this->subscriptionModel->errors()));
                }
                $this->voucherModel->markAsUsed($voucherCode, $clientId);
                $transactionId = $this->transactionModel->insert([
                    'client_id'      => $clientId,
                    'package_id'     => $packageId,
                    'package_type'   => $package['type'],
                    'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
                    'amount'         => 0,
                    'method'         => 'voucher',
                    'mpesa_code'     => $voucherCode,
                    'router_id'      => $package['router_id'],
                    'status'         => 'success',
                    'created_on'     => date('Y-m-d H:i:s'),
                    'expires_on'     => $expiryDate
                ]);
                $db->transComplete();
                if ($db->transStatus() === false) throw new \Exception('Database commit failed during voucher redemption.');

                $pdfPath = ReceiptHelper::generate($transactionId);
                ReceiptHelper::sendEmail($clientId, $pdfPath);

                mpesa_debug("✅ Voucher successfully redeemed for {$clientUsername}.");
                mpesa_log("✅ Voucher successfully redeemed for {$clientUsername}.");

                return redirect()->to('/client/payments/success/' . $transactionId)
                    ->with('success', 'Voucher redeemed successfully! Subscription activated.');
            } catch (\Exception $e) {
                $db->transRollback();
                mpesa_debug("❌ Voucher redemption failed: " . $e->getMessage());
                mpesa_log("❌ Voucher redemption failed: " . $e->getMessage());
                return redirect()->back()->with('error', 'Voucher redemption failed: ' . $e->getMessage());
            }
        }

        // 🔹 CASE 2: M-PESA Payment
        $amount = (int) $package['price'];
        if ($amount <= 0) {
            mpesa_debug("❌ Invalid amount: {$amount}");
            mpesa_log("❌ Invalid amount: {$amount}");
            return redirect()->back()->with('error', 'Invalid payment amount.');
        }

        // Steps for M-PESA payment are handled in initiatePayment() as above
        return $this->initiatePayment($packageId);
    }

    public function waiting()
    {
        return view('client/payments/waiting');
    }

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
            'subscription' => ['expires_on' => $transaction['expires_on'] ?? date('Y-m-d H:i:s', strtotime('+' . $package['duration_length'] . ' ' . $package['duration_unit']))]
        ]);
    }

    public function pending($checkoutRequestID = null)
    {
        if (!$checkoutRequestID) return redirect()->to('/client/dashboard')->with('error', 'Invalid transaction reference.');

        return view('client/payments/pending', ['checkoutRequestID' => $checkoutRequestID]);
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
                'transaction_id' => $transaction['id']
            ]);
        }

        return $this->response->setJSON(['status' => 'pending']);
    }

    public function status($checkoutRequestID)
    {
        $mpesaTransactionModel = new \App\Models\MpesaTransactionModel();

        $transaction = $mpesaTransactionModel
            ->where('checkout_request_id', $checkoutRequestID)
            ->first();

        if (!$transaction) {
            return $this->response->setJSON(['status' => 'NotFound']);
        }

        return $this->response->setJSON(['status' => $transaction['status']]);
    }


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
