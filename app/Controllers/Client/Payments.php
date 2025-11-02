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

            return view('client/payments/pending', [
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
        helper('mpesa_logger');
        mpesa_log("=== New M-PESA Payment Process Started ===");

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

        $expiryDate = $this->calculateExpiry($package['duration_length'], $package['duration_unit']);

        $subscriptionData = [
            'client_id'   => $clientId,
            'package_id'  => $packageId,
            'router_id'   => $package['router_id'],
            'start_date'  => date('Y-m-d H:i:s'),
            'expires_on'  => $expiryDate,
            'status'      => 'active'
        ];

        /**
         * 🔹 CASE 1: Voucher Redemption
         */
        if (!empty($voucherCode)) {
            $voucher = $this->voucherModel->isValidVoucher($voucherCode);

            if (!$voucher) {
                mpesa_log("❌ Voucher failed: Invalid or expired code.");
                return redirect()->back()->withInput()->with('error', 'Invalid or expired voucher code.');
            }

            $this->subscriptionModel->insert($subscriptionData);
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

            mpesa_log("✅ Voucher redeemed successfully for client {$clientUsername}.");

            $pdfPath = ReceiptHelper::generate($transactionId);
            ReceiptHelper::sendEmail($clientId, $pdfPath);

            return redirect()->to('/client/payments/success/' . $transactionId)
                ->with('success', 'Voucher redeemed successfully! Subscription activated.');
        }

        /**
         * 🔹 CASE 2: M-PESA PAYMENT FLOW
         */
        $amount = (int) $package['price'];

        // Step 1: Validate amount
        if ($amount <= 0) {
            mpesa_log("❌ Step 1 Failed: Invalid payment amount.");
            return redirect()->back()->with('error', 'Invalid payment amount.');
        }

        log_message('debug', 'M-PESA ENV CHECK: ' . json_encode([
            'key' => getenv('mpesa.consumer_key'),
            'secret' => getenv('mpesa.consumer_secret'),
            'shortcode' => getenv('mpesa.shortcode'),
            'env' => getenv('mpesa.environment')
        ]));

        // Step 2: Build config
        $mpesaConfig = [
            'consumer_key'    => getenv('MPESA_CONSUMER_KEY'),
            'consumer_secret' => getenv('MPESA_CONSUMER_SECRET'),
            'shortcode'       => getenv('MPESA_SHORTCODE') ?: '174379',
            'passkey'         => getenv('MPESA_PASSKEY'),
            'callback_url'    => getenv('MPESA_CALLBACK_URL') ?: base_url('mpesa/callback'),
            'environment'     => getenv('MPESA_ENVIRONMENT') ?: 'sandbox'
        ];

        // Step 3: Obtain Access Token
        mpesa_log("Step 3: Requesting M-PESA access token...");
        $credentials = base64_encode($mpesaConfig['consumer_key'] . ':' . $mpesaConfig['consumer_secret']);
        $tokenUrl = 'https://' . ($mpesaConfig['environment'] === 'production' ? 'api' : 'sandbox') . '.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            mpesa_log("❌ CURL ERROR during token request: $error");
            return redirect()->back()->with('error', 'M-PESA connection error: ' . $error);
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (isset($decoded['access_token'])) {
            $accessToken = $decoded['access_token'];
            mpesa_log("✅ Token obtained successfully.");
        } else {
            mpesa_log("❌ Invalid token response: " . $response);
            return redirect()->back()->with('error', 'M-PESA token not received. Response: ' . $response);
        }

        mpesa_log("✅ Step 3 Success: Access token received.");

        // Step 4: Prepare STK Push data
        mpesa_log("Step 4: Preparing STK push request for amount {$amount} to {$phone}...");
        $timestamp = date('YmdHis');
        $password  = base64_encode($mpesaConfig['shortcode'] . $mpesaConfig['passkey'] . $timestamp);

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

        // Step 5: Send STK Push
        mpesa_log("Step 5: Sending STK Push to M-PESA API...");
        $stkUrl = 'https://' . ($mpesaConfig['environment'] === 'production' ? 'api' : 'sandbox') . '.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
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
            mpesa_log("❌ Step 5 Failed: CURL Error => $stkError");
            return redirect()->back()->with('error', 'Step 3 Failed: STK Push request error (' . $stkError . ').');
        }

        mpesa_log("Raw STK Response: " . $stkResponse);
        $stkResult = json_decode($stkResponse, true);

        // Step 6: Validate STK Response
        if (!isset($stkResult['ResponseCode'])) {
            mpesa_log("❌ Step 6 Failed: No ResponseCode in STK response.");
            return redirect()->back()->with('error', 'Step 4 Failed: No valid response from M-PESA.');
        }

        if ($stkResult['ResponseCode'] != '0') {
            mpesa_log("❌ Step 6 Failed: STK Error => " . ($stkResult['errorMessage'] ?? 'Unknown error.'));
            return redirect()->back()->with('error', 'Step 5 Failed: ' . ($stkResult['errorMessage'] ?? 'Unknown STK error.'));
        }

        mpesa_log("✅ Step 6 Success: STK push accepted. MerchantRequestID={$stkResult['MerchantRequestID']} CheckoutRequestID={$stkResult['CheckoutRequestID']}");

        // Step 7: Save pending transaction
        $this->mpesaTransactionModel->insert([
            'client_id'        => $clientId,
            'client_username'  => $clientUsername,
            'package_id'       => $packageId,
            'package_length'   => $package['duration_length'] . ' ' . $package['duration_unit'],
            'amount'           => $amount,
            'phone'            => $phone,
            'merchant_request_id' => $stkResult['MerchantRequestID'] ?? null,
            'checkout_request_id' => $stkResult['CheckoutRequestID'] ?? null,
            'status'           => 'Pending',
            'created_at'       => date('Y-m-d H:i:s')
        ]);
        mpesa_log("✅ Step 7: Pending transaction saved in database.");
        mpesa_log("✅ Step 8: STK push flow complete. Awaiting user confirmation.");

        return redirect()->to('/client/payments/pending')
            ->with('success', 'STK Push sent successfully. Check your phone to complete payment.');
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

    public function pending()
    {
        $clientId = session()->get('client_id');
        if (!$clientId) return redirect()->to('/client/login');

        return view('client/payments/pending', [
            'title' => 'Payment Pending'
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
