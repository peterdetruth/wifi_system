<?php

namespace App\Services;

use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use App\Services\ActivationService;
use App\Services\LogService;

class MpesaService
{
    protected MpesaTransactionModel $transactionModel;
    protected PaymentsModel $paymentsModel;
    protected ClientModel $clientModel;
    protected PackageModel $packageModel;
    protected MpesaLogger $logger;
    protected BaseConnection $db;
    protected IncomingRequest $request;
    protected LogService $logService;

    public function __construct(MpesaLogger $logger)
    {
        $this->logger = $logger;
        $this->transactionModel = new MpesaTransactionModel();
        $this->paymentsModel = new PaymentsModel();
        $this->clientModel = new ClientModel();
        $this->packageModel = new PackageModel();
        $this->db = \Config\Database::connect();
        $this->logService = new LogService();
        $this->request = service('request');
    }

    // ------------------------------------------------------------------------
    // PUBLIC METHODS
    // ------------------------------------------------------------------------

    public function initiateTransaction(int $clientId, int $packageId, float $amount, string $phone): ?string
    {
        helper(['mpesa_debug']);
        mpesa_debug("💡 Initiating M-PESA STK Push for client {$clientId}, package {$packageId}, amount {$amount}, phone {$phone}");

        try {
            $client = $this->clientModel->find($clientId);
            $package = $this->packageModel->find($packageId);

            if (!$client || !$package) {
                mpesa_debug("❌ Client or package not found");
                return null;
            }

            $payload = $this->buildStkPayload($client, $package, $amount, $phone);
            mpesa_debug("Payload prepared: " . json_encode($payload));

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                mpesa_debug("❌ Failed to obtain access token");
                $this->logger->error("Failed to obtain access token", [
                    'client_id' => $clientId,
                    'package_id' => $packageId
                ]);
                return null;
            }

            $response = $this->sendStkPush($accessToken, $payload);
            if ($response === null) {
                mpesa_debug("❌ STK push request failed (null response)");
                return null;
            }

            mpesa_debug("STK Push raw response: " . json_encode($response));

            $checkoutRequestId = $response['CheckoutRequestID'] ?? null;
            if (!$checkoutRequestId) {
                mpesa_debug("❌ STK push failed, missing CheckoutRequestID: " . json_encode($response));
                $this->logger->error("STK push failed", ['response' => $response]);
                return null;
            }

            // Save pending transaction using robust flow
            return $this->savePendingTransaction($clientId, $packageId, $amount, $phone, $response);
        } catch (\Throwable $e) {
            mpesa_debug("❌ Exception during STK push: " . $e->getMessage());
            $this->logger->error("Exception during initiateTransaction", [
                'exception' => $e->getMessage(),
                'client_id' => $clientId,
                'package_id' => $packageId
            ]);
            return null;
        }
    }

    public function getAccessToken(): ?string
    {
        return $this->requestAccessToken();
    }

    private function sendStkPush(string $accessToken, array $payload): ?array
    {
        $ip = $this->request->getIPAddress();
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, getenv('MPESA_STK_URL'));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $accessToken"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            return json_decode($response, true);
        } catch (\Throwable $e) {
            $this->logger->error("Exception sending STK Push", ['exception' => $e->getMessage()]);
            $this->logService->error('mpesa', 'Exception sending STK Push', ['error' => $e->getMessage()], null, $ip);
            return null;
        }
    }

    private function buildStkPayload(array $client, array $package, float $amount, string $phone): array
    {
        $BusinessShortCode = getenv('MPESA_SHORTCODE');
        $Passkey = getenv('MPESA_PASSKEY');
        $Timestamp = date('YmdHis');
        $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

        return [
            'BusinessShortCode' => $BusinessShortCode,
            'Password'          => $Password,
            'Timestamp'         => $Timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $BusinessShortCode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => getenv('MPESA_CALLBACK_URL'),
            'AccountReference'  => 'Hotspot_' . ($client['username'] ?? $client['id']),
            'TransactionDesc'   => 'Payment for ' . ($package['name'] ?? $package['id'])
        ];
    }

    private function requestAccessToken(): ?string
    {
        $ip = $this->request->getIPAddress();
        try {
            $consumerKey = getenv('MPESA_CONSUMER_KEY');
            $consumerSecret = getenv('MPESA_CONSUMER_SECRET');
            $credentials = base64_encode("$consumerKey:$consumerSecret");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, getenv('MPESA_OAUTH_URL'));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $tokenResult = json_decode($response, true);
            return $tokenResult['access_token'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error("Exception requesting access token", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    public function handleCallback(array $payload): array
    {
        $ip = $this->request->getIPAddress();

        if (empty($payload['Body']['stkCallback'])) {
            return ['ResultCode' => 1, 'ResultDesc' => 'Invalid payload'];
        }

        $callback = $payload['Body']['stkCallback'];

        $checkoutRequestId  = $callback['CheckoutRequestID'] ?? null;
        $merchantRequestId  = $callback['MerchantRequestID'] ?? null;
        $resultCode         = (int) ($callback['ResultCode'] ?? 1);
        $resultDesc         = $callback['ResultDesc'] ?? 'Unknown';

        if (!$checkoutRequestId) {
            return ['ResultCode' => 1, 'ResultDesc' => 'Missing CheckoutRequestID'];
        }

        // Find existing transaction or insert fallback
        $transaction = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
        if (!$transaction) {
            $transaction = $this->safeInsertTransaction($merchantRequestId, $checkoutRequestId);
        }

        if ($resultCode !== 0) {
            return $this->markTransactionFailed($transaction, $resultCode, $resultDesc);
        }

        return $this->processSuccessfulCallback($transaction, $callback, $resultDesc, $checkoutRequestId);
    }

    // ------------------------------------------------------------------------
    // PUBLIC HELPER METHODS
    // ------------------------------------------------------------------------

    public function getExpiry(array $package, ?string $startDate = null): string
    {
        return $this->calculateSubscriptionExpiry($package, $startDate);
    }

    public function reconnectUsingMpesaCode(string $mpesaCode, int $clientId): array
    {
        $ip = $this->request->getIPAddress();
        try {
            $transaction = $this->transactionModel
                ->where('mpesa_receipt_number', $mpesaCode)
                ->where('client_id', $clientId)
                ->where('status', 'Success')
                ->first();

            if (!$transaction) return ['success' => false, 'message' => 'Invalid or unsuccessful M-PESA code.'];

            $subscriptionModel = new SubscriptionModel();
            $existingSub = $subscriptionModel->where('payment_id', $transaction['id'])->first();
            if ($existingSub) return ['success' => false, 'message' => 'This M-PESA code has already been used.'];

            $package = $this->packageModel->find($transaction['package_id']);
            if (!$package) return ['success' => false, 'message' => 'Package not found.'];

            $startDate = date('Y-m-d H:i:s');
            $expiresOn = $this->calculateSubscriptionExpiry($package, $startDate);

            // Atomic transaction + payment/subscription creation
            $this->db->transStart();
            try {
                $paymentId = $this->paymentsModel->insert([
                    'mpesa_transaction_id' => $transaction['id'],
                    'client_id' => $clientId,
                    'package_id' => $package['id'],
                    'amount' => $transaction['amount'],
                    'payment_method' => 'mpesa',
                    'status' => 'completed',
                    'mpesa_receipt_number' => $mpesaCode,
                    'phone' => $transaction['phone_number'],
                    'transaction_date' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ], true);

                $subId = (new SubscriptionModel())->insert([
                    'client_id' => $clientId,
                    'package_id' => $package['id'],
                    'payment_id' => $paymentId,
                    'start_date' => $startDate,
                    'expires_on' => $expiresOn,
                    'status' => 'active'
                ], true);

                $this->db->transComplete();

                $activationService = new ActivationService($this->logger);
                $activationService->activate($clientId, $package['id'], (float)$transaction['amount']);

                $this->logger->info("Reconnect successful", compact('clientId', 'subId', 'mpesaCode'));
                $this->logService->info(
                    'mpesa',
                    'Reconnect successful',
                    ['clientId' => $clientId, 'packageId' => $package['id'], 'subscription_id' => $subId, 'expires_on' => $expiresOn],
                    $clientId,
                    $ip
                );

                return ['success' => true, 'message' => 'Reconnection successful.', 'subscription_id' => $subId, 'expires_on' => $expiresOn];
            } catch (\Throwable $e) {
                $this->db->transRollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->logger->error("Exception during reconnectUsingMpesaCode", ['exception' => $e->getMessage()]);
            $this->logService->error(
                'mpesa',
                'Exception during reconnectUsingMpesaCode',
                ['error' => $e->getMessage()],
                $clientId,
                $ip
            );
            return ['success' => false, 'message' => 'Internal error occurred.'];
        }
    }

    // ------------------------------------------------------------------------
    // PRIVATE HELPER METHODS
    // ------------------------------------------------------------------------

    private function savePendingTransaction(int $clientId, int $packageId, float $amount, string $phone, array $stkResponse): ?string
    {
        $ip = $this->request->getIPAddress();
        try {
            $merchantRequestId = $stkResponse['MerchantRequestID'] ?? null;
            $checkoutRequestId = $stkResponse['CheckoutRequestID'] ?? null;

            if (!$checkoutRequestId) return null;

            // If transaction exists, return existing
            $existing = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
            if ($existing) return $checkoutRequestId;

            $insert = [
                'client_id' => $clientId,
                'package_id' => $packageId,
                'transaction_id' => null,
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'amount' => $amount,
                'phone_number' => $phone,
                'status' => 'Pending',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->transactionModel->insert($insert);
            $this->logger->info("STK Push transaction saved", ['checkoutRequestID' => $checkoutRequestId, 'insert_id' => $this->transactionModel->getInsertID()]);
            $this->logService->info('mpesa', 'STK Push transaction saved', ['checkoutRequestID' => $checkoutRequestId], $clientId, $ip);

            return $checkoutRequestId;
        } catch (\Throwable $e) {
            $this->logger->error("Exception saving pending transaction", ['exception' => $e->getMessage()]);
            $this->logService->error('mpesa', 'Exception saving pending transaction', ['error' => $e->getMessage()], $clientId, $ip);
            return null;
        }
    }

    private function safeInsertTransaction(?string $merchantRequestId, ?string $checkoutRequestId): ?array
    {
        $ip = $this->request->getIPAddress();
        try {
            $existing = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
            if ($existing) return $existing;

            $fallbackTransactionId = 'TEMP_' . substr(md5(($checkoutRequestId ?? '') . microtime()), 0, 8);
            $data = [
                'client_id' => 0,
                'package_id' => 0,
                'transaction_id' => $fallbackTransactionId,
                'merchant_request_id' => $merchantRequestId ?? 'N/A',
                'checkout_request_id' => $checkoutRequestId ?? 'N/A',
                'amount' => 0,
                'phone_number' => null,
                'status' => 'Pending',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->transactionModel->insert($data);
            return $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
        } catch (\Throwable $e) {
            $this->logger->error("Exception in safeInsertTransaction", ['exception' => $e->getMessage()]);
            $this->logService->error('mpesa', 'Exception in safeInsertTransaction', ['error' => $e->getMessage()], null, $ip);
            return null;
        }
    }

    private function markTransactionFailed(array $transaction, int $resultCode, string $resultDesc): array
    {
        $ip = $this->request->getIPAddress();
        try {
            $this->transactionModel->update($transaction['id'], [
                'status' => 'Failed',
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->transStart();
            $existingPayment = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])->first();
            if ($existingPayment) {
                $this->paymentsModel->update($existingPayment['id'], [
                    'status' => 'failed',
                    'mpesa_receipt_number' => $existingPayment['mpesa_receipt_number'] ?? 'FAILED',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                $this->paymentsModel->insert([
                    'mpesa_transaction_id' => $transaction['id'],
                    'client_id' => $transaction['client_id'] ?? null,
                    'package_id' => $transaction['package_id'] ?? null,
                    'amount' => $transaction['amount'] ?? 0,
                    'payment_method' => 'mpesa',
                    'status' => 'failed',
                    'mpesa_receipt_number' => 'FAILED',
                    'phone' => $transaction['phone_number'] ?? null,
                    'transaction_date' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            $this->db->transComplete();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception while marking failed payments/subscriptions", ['exception' => $e->getMessage()]);
            $this->logService->error('mpesa', 'Exception while marking failed payments/subscriptions', ['error' => $e->getMessage()], null, $ip);
        }

        return ['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'];
    }

    private function calculateSubscriptionExpiry(array $package, ?string $startDate = null): string
    {
        $startDate = $startDate ?? date('Y-m-d H:i:s');

        if (!empty($package['duration_length']) && !empty($package['duration_unit'])) {
            return date('Y-m-d H:i:s', strtotime("+{$package['duration_length']} {$package['duration_unit']}", strtotime($startDate)));
        }

        return date('Y-m-d H:i:s', strtotime('+1 day', strtotime($startDate)));
    }

    private function processSuccessfulCallback(array $transaction, array $callback, string $resultDesc, string $checkoutRequestId): array
    {
        $ip = $this->request->getIPAddress();
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];

        $amount = null;
        $mpesaReceipt = null;
        $phone = null;
        $transactionDate = date('Y-m-d H:i:s');

        foreach ($metadata as $item) {
            switch ($item['Name'] ?? null) {
                case 'Amount': $amount = $item['Value'] ?? null; break;
                case 'MpesaReceiptNumber': $mpesaReceipt = $item['Value'] ?? null; break;
                case 'PhoneNumber': $phone = $item['Value'] ?? null; break;
                case 'TransactionDate':
                    if ($item['Value']) {
                        $transactionDateObj = \DateTime::createFromFormat('YmdHis', substr((string)$item['Value'], 0, 14));
                        $transactionDate = $transactionDateObj ? $transactionDateObj->format('Y-m-d H:i:s') : $transactionDate;
                    }
                    break;
            }
        }

        if (empty($mpesaReceipt)) {
            $mpesaReceipt = 'UNKNOWN_' . $transaction['id'];
        }

        // Start atomic payment+subscription creation
        $this->db->transStart();
        try {
            // Update mpesa_transactions
            $this->transactionModel->update($transaction['id'], [
                'amount' => $amount ?? $transaction['amount'],
                'phone_number' => $phone ?? $transaction['phone_number'],
                'mpesa_receipt_number' => $mpesaReceipt,
                'transaction_date' => $transactionDate ?? $transaction['transaction_date'],
                'status' => 'Success',
                'result_code' => 0,
                'result_desc' => $resultDesc,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Create payment if not exists
            $existingPayment = $this->paymentsModel
                ->where('mpesa_transaction_id', $transaction['id'])
                ->orWhere('mpesa_receipt_number', $mpesaReceipt)
                ->first();

            $paymentId = $existingPayment['id'] ?? $this->paymentsModel->insert([
                'mpesa_transaction_id' => $transaction['id'],
                'client_id' => $transaction['client_id'],
                'package_id' => $transaction['package_id'],
                'amount' => $amount ?? $transaction['amount'],
                'payment_method' => 'mpesa',
                'status' => 'completed',
                'mpesa_receipt_number' => $mpesaReceipt,
                'phone' => $phone,
                'transaction_date' => $transactionDate,
                'created_at' => date('Y-m-d H:i:s')
            ], true);

            // Expire existing active subscriptions
            $subscriptionModel = new SubscriptionModel();
            $activeSubs = $subscriptionModel->where('client_id', $transaction['client_id'])
                ->where('package_id', $transaction['package_id'])
                ->where('status', 'active')
                ->findAll();
            foreach ($activeSubs as $sub) {
                $subscriptionModel->update($sub['id'], ['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')]);
            }

            // Create new subscription
            $package = $this->packageModel->find($transaction['package_id']);
            $startDate = date('Y-m-d H:i:s');
            $expiresOn = $this->calculateSubscriptionExpiry($package, $startDate);

            $subId = $subscriptionModel->insert([
                'client_id' => $transaction['client_id'],
                'package_id' => $transaction['package_id'],
                'payment_id' => $paymentId,
                'start_date' => $startDate,
                'expires_on' => $expiresOn,
                'status' => 'active'
            ], true);

            $this->db->transComplete();

            // Activate router/service
            $activationService = new ActivationService($this->logger);
            $activationService->activate($transaction['client_id'], $transaction['package_id'], (float)$amount);

            return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception during payments/subscriptions transaction", ['exception' => $e->getMessage()]);
            $this->logService->error('mpesa', 'Exception during payments/subscriptions transaction', ['error' => $e->getMessage()], null, $ip);
            return ['ResultCode' => 1, 'ResultDesc' => 'Processing error'];
        }
    }
}
