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
        $client  = $this->clientModel->find(session()->get('client_id'));

        if (!$package || !$client) {
            return redirect()->back()->with('error', 'Invalid client or package.');
        }

        $amount = (int) $package['price'];
        $phone  = $client['phone'];

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
            // 🔹 Save transaction with Safaricom IDs
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

            return view('client/payments/pending' . $checkoutRequestID, [
                'package' => $package,
                'client' => $client,
                'amount' => $amount,
                'phone' => $phone
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

        return view('client/payments/checkout', [
            'package' => $package
        ]);
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

        /**
         * 🔹 CASE 1: Voucher Redemption (No M-PESA)
         */
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
                // 1️⃣ Create subscription
                if (!$this->subscriptionModel->insert($subscriptionData)) {
                    throw new \Exception('Failed to create subscription: ' . json_encode($this->subscriptionModel->errors()));
                }

                // 2️⃣ Mark voucher as used
                $this->voucherModel->markAsUsed($voucherCode, $clientId);

                // 3️⃣ Record transaction (voucher)
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
                if ($db->transStatus() === false) {
                    throw new \Exception('Database commit failed during voucher redemption.');
                }

                // 4️⃣ Generate & email receipt
                $pdfPath = ReceiptHelper::generate($transactionId);
                ReceiptHelper::sendEmail($clientId, $pdfPath);

                mpesa_debug("✅ Voucher successfully redeemed. Subscription activated for {$clientUsername}.");
                mpesa_log("✅ Voucher successfully redeemed. Subscription activated for {$clientUsername}.");

                return redirect()->to('/client/payments/success/' . $transactionId)
                    ->with('success', 'Voucher redeemed successfully! Subscription activated.');
            } catch (\Exception $e) {
                $db->transRollback();
                mpesa_debug("❌ Voucher redemption failed: " . $e->getMessage());
                mpesa_log("❌ Voucher redemption failed: " . $e->getMessage());
                return redirect()->back()->with('error', 'Voucher redemption failed: ' . $e->getMessage());
            }
        }

        /**
         * 🔹 CASE 2: M-PESA Payment Flow
         */
        $amount = (int) $package['price'];
        if ($amount <= 0) {
            mpesa_debug("❌ Invalid amount: {$amount}");
            mpesa_log("❌ Invalid amount: {$amount}");
            return redirect()->back()->with('error', 'Invalid payment amount.');
        }

        // Step 1: Build config
        $mpesaConfig = [
            'consumer_key'    => getenv('MPESA_CONSUMER_KEY') ?: getenv('mpesa.consumer_key'),
            'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: getenv('mpesa.consumer_secret'),
            'shortcode'       => getenv('MPESA_SHORTCODE') ?: getenv('mpesa.shortcode') ?: '174379',
            'passkey'         => getenv('MPESA_PASSKEY') ?: getenv('mpesa.passkey'),
            'callback_url'    => getenv('MPESA_CALLBACK_URL') ?: getenv('mpesa.callback_url') ?: base_url('mpesa/callback'),
            'environment'     => getenv('MPESA_ENVIRONMENT') ?: getenv('mpesa.environment') ?: 'sandbox'
        ];

        mpesa_debug("Step 1: Configuration loaded for environment: {$mpesaConfig['environment']}");
        mpesa_log("Step 1: Configuration loaded for environment: {$mpesaConfig['environment']}");

        // Step 2: Obtain access token
        $credentials = base64_encode($mpesaConfig['consumer_key'] . ':' . $mpesaConfig['consumer_secret']);
        $tokenUrl = 'https://' . ($mpesaConfig['environment'] === 'production' ? 'api' : 'sandbox')
            . '.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            mpesa_debug("❌ Token request CURL error: {$curlError}");
            mpesa_log("❌ Token request CURL error: {$curlError}");
            return redirect()->back()->with('error', 'M-PESA connection error: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['access_token'])) {
            mpesa_debug("❌ Access token not received: {$response}");
            mpesa_log("❌ Access token not received: {$response}");
            return redirect()->back()->with('error', 'Failed to obtain M-PESA token.');
        }

        $accessToken = $decoded['access_token'];
        mpesa_debug("✅ Step 2: Access token received.");
        mpesa_log("✅ Step 2: Access token received.");

        // Step 3: Build STK Push payload
        $timestamp = date('YmdHis');
        $password = base64_encode($mpesaConfig['shortcode'] . $mpesaConfig['passkey'] . $timestamp);
        $stkData = [
            'BusinessShortCode' => $mpesaConfig['shortcode'],
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $mpesaConfig['shortcode'],
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $mpesaConfig['callback_url'],
            'AccountReference'  => $clientUsername,
            'TransactionDesc'   => 'Subscription Payment'
        ];

        mpesa_debug("Step 3: Sending STK Push for {$phone}, amount={$amount}");
        mpesa_log("Step 3: Sending STK Push for {$phone}, amount={$amount}");

        $stkUrl = 'https://' . ($mpesaConfig['environment'] === 'production' ? 'api' : 'sandbox')
            . '.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $ch = curl_init($stkUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $stkResponse = curl_exec($ch);
        $stkError = curl_error($ch);
        curl_close($ch);

        if ($stkError) {
            mpesa_debug("❌ STK CURL error: {$stkError}");
            mpesa_log("❌ STK CURL error: {$stkError}");
            return redirect()->back()->with('error', 'STK Push request failed (' . $stkError . ').');
        }

        $stkResult = json_decode($stkResponse, true);
        mpesa_debug("Raw STK Response: " . $stkResponse);
        mpesa_log("Raw STK Response: " . $stkResponse);

        if (!isset($stkResult['ResponseCode']) || $stkResult['ResponseCode'] != '0') {
            $msg = $stkResult['errorMessage'] ?? 'Unknown STK error.';
            mpesa_debug("❌ STK Push rejected: {$msg}");
            mpesa_log("❌ STK Push rejected: {$msg}");
            return redirect()->back()->with('error', 'STK Push rejected: ' . $msg);
        }

        // Step 4: Save pending M-PESA transaction
        $this->mpesaTransactionModel->insert([
            'client_id'           => $clientId,
            'client_username'     => $clientUsername,
            'package_id'          => $packageId,
            'package_length'      => $package['duration_length'] . ' ' . $package['duration_unit'],
            'amount'              => $amount,
            'phone'               => $phone,
            'merchant_request_id' => $stkResult['MerchantRequestID'] ?? null,
            'checkout_request_id' => $stkResult['CheckoutRequestID'] ?? null,
            'status'              => 'Pending',
            'created_at'          => date('Y-m-d H:i:s')
        ]);

        mpesa_debug("✅ Pending transaction recorded — awaiting M-PESA callback.");
        mpesa_log("✅ Pending transaction recorded — awaiting M-PESA callback.");
        $checkoutRequestID = $stkResult['CheckoutRequestID'] ?? null;

        return redirect()->to('/client/payments/pending/' . $checkoutRequestID)
            ->with('success', 'STK Push sent. Check your phone to complete payment.');
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

        if (!$transaction) {
            return redirect()->to('/client/dashboard')->with('error', 'Transaction not found.');
        }

        $package = $this->packageModel->find($transaction['package_id']);

        return view('client/payments/success', [
            'package'      => $package,
            'subscription' => $subscription ?? ['expires_on' => date('Y-m-d H:i:s', strtotime('+' . $package['duration_length'] . ' ' . $package['duration_unit']))],
        ]);
    }

    public function pending($checkoutRequestID = null)
    {
        if (!$checkoutRequestID) {
            return redirect()->to('/client/dashboard')
                ->with('error', 'Invalid or missing transaction reference.');
        }

        return view('client/payments/pending', [
            'checkoutRequestID' => $checkoutRequestID,
        ]);
    }

    public function checkStatus()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) {
            return $this->response->setJSON(['status' => 'unauthorized']);
        }

        $mpesaTx = $this->mpesaTransactionModel
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->first();

        if (!$mpesaTx) {
            return $this->response->setJSON(['status' => 'pending']);
        }

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
