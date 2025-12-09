<?php

namespace App\Services;

use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use CodeIgniter\Database\BaseConnection;
use App\Services\ActivationService;

class MpesaService
{
    protected MpesaTransactionModel $transactionModel;
    protected PaymentsModel $paymentsModel;
    protected ClientModel $clientModel;
    protected PackageModel $packageModel;
    protected MpesaLogger $logger;
    protected BaseConnection $db;

    public function __construct(MpesaLogger $logger)
    {
        $this->logger = $logger;
        $this->transactionModel = new MpesaTransactionModel();
        $this->paymentsModel = new PaymentsModel();
        $this->clientModel = new ClientModel();
        $this->packageModel = new PackageModel();
        $this->db = \Config\Database::connect();
    }

    // ------------------------------------------------------------------------
    // PUBLIC METHODS
    // ------------------------------------------------------------------------

    public function initiateTransaction(int $clientId, int $packageId, float $amount, string $phone): ?string
    {
        try {
            $client = $this->clientModel->find($clientId);
            $package = $this->packageModel->find($packageId);
            if (!$client || !$package) {
                $this->logger->error("Client or package not found for STK push", compact('clientId', 'packageId'));
                return null;
            }

            $accessToken = $this->requestAccessToken();
            if (!$accessToken) return $this->createFallbackTransaction($clientId, $packageId, $amount, $phone, $package);

            $stkPayload = $this->buildStkPayload($client, $package, $amount, $phone);
            $stkResponse = $this->sendStkPush($accessToken, $stkPayload);

            if (!is_array($stkResponse) || ($stkResponse['ResponseCode'] ?? '') !== "0") {
                $this->logger->warning("STK push failed, creating fallback transaction", $stkResponse ?? []);
                return $this->createFallbackTransaction($clientId, $packageId, $amount, $phone, $package);
            }

            $checkoutRequestId = $this->savePendingTransaction($clientId, $packageId, $amount, $phone, $stkResponse);
            $this->logger->info("STK push initiated successfully", compact('checkoutRequestId', 'clientId', 'packageId'));

            return $checkoutRequestId;
        } catch (\Throwable $e) {
            $this->logger->error("Exception in initiateTransaction", [
                'message' => $e->getMessage(),
                'clientId' => $clientId,
                'packageId' => $packageId
            ]);
            return $this->createFallbackTransaction($clientId, $packageId, $amount, $phone);
        }
    }

    /**
     * Handle M-PESA callback
     */
    public function handleCallback(array $payload): array
    {
        try {
            if (empty($payload['Body']['stkCallback'])) {
                $this->logger->error("Invalid callback payload", $payload);
                return ['ResultCode' => 1, 'ResultDesc' => 'Invalid payload'];
            }

            $callback = $payload['Body']['stkCallback'];
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            $merchantRequestId = $callback['MerchantRequestID'] ?? null;
            $resultCode = (int)($callback['ResultCode'] ?? 1);
            $resultDesc = $callback['ResultDesc'] ?? '';

            $this->logger->info("Received STK callback", compact('checkoutRequestId', 'merchantRequestId', 'resultCode', 'resultDesc'));

            $transaction = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first()
                ?: $this->safeInsertTransaction($merchantRequestId, $checkoutRequestId);

            if (!$transaction) return ['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'];

            if ($resultCode !== 0) return $this->markTransactionFailed($transaction, $resultCode, $resultDesc);

            return $this->processSuccessfulCallback($transaction, $callback, $resultDesc, $checkoutRequestId);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception in handleCallback", ['message' => $e->getMessage()]);
            return ['ResultCode' => 1, 'ResultDesc' => 'Internal error'];
        }
    }

    public function getExpiry(array $package, ?string $startDate = null): string
    {
        return $this->calculateSubscriptionExpiry($package, $startDate);
    }

    public function reconnectUsingMpesaCode(string $mpesaCode, int $clientId): array
    {
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

            $subId = $subscriptionModel->insert([
                'client_id' => $clientId,
                'package_id' => $package['id'],
                'payment_id' => $transaction['id'],
                'start_date' => $startDate,
                'expires_on' => $expiresOn,
                'status' => 'active'
            ], true);

            $activationService = new ActivationService($this->logger);
            $activationService->activate($clientId, $package['id'], (float)$transaction['amount']);

            $this->logger->info("Reconnect successful", compact('clientId', 'subId', 'mpesaCode'));

            return ['success' => true, 'message' => 'Reconnection successful.', 'subscription_id' => $subId, 'expires_on' => $expiresOn];
        } catch (\Throwable $e) {
            $this->logger->error("Exception during reconnectUsingMpesaCode", ['exception' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Internal error occurred.'];
        }
    }

    private function requestAccessToken(): ?string
    {
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
            if (!is_array($tokenResult) || empty($tokenResult['access_token'])) {
                $this->logger->error("Failed to obtain M-PESA access token", $tokenResult ?? $response);
                return null;
            }

            return $tokenResult['access_token'];
        } catch (\Throwable $e) {
            $this->logger->error("Exception requesting access token", ['exception' => $e->getMessage()]);
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

    private function sendStkPush(string $accessToken, array $payload): ?array
    {
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
            return null;
        }
    }

    private function savePendingTransaction(int $clientId, int $packageId, float $amount, string $phone, array $stkResponse): ?string
    {
        try {
            $merchantRequestId = $stkResponse['MerchantRequestID'] ?? null;
            $checkoutRequestId = $stkResponse['CheckoutRequestID'] ?? null;

            // Guard: if checkoutRequestId already exists, return it
            if ($checkoutRequestId) {
                $existing = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
                if ($existing) {
                    $this->logger->info("savePendingTransaction: checkout_request_id already exists, returning existing.");
                    return $checkoutRequestId;
                }
            }

            $insert = [
                'client_id' => $clientId,
                'package_id' => $packageId,
                'transaction_id' => null,
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'amount' => $amount,
                'phone_number' => $phone,
                'status' => 'Pending',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $this->transactionModel->insert($insert);
            $this->logger->info("STK Push transaction saved", [
                'checkoutRequestID' => $checkoutRequestId,
                'insert_id' => $this->transactionModel->getInsertID()
            ]);

            return $checkoutRequestId;
        } catch (\Throwable $e) {
            $this->logger->error("Exception saving pending transaction", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    private function createFallbackTransaction(int $clientId, int $packageId, float $amount, string $phone, ?array $package = null): ?string
    {
        try {
            if (!$package) {
                $package = $this->packageModel->find($packageId);
            }

            $insert = [
                'client_id' => $clientId,
                'package_id' => $packageId,
                'transaction_id' => 'TEMP_' . substr(md5(microtime()), 0, 8),
                'merchant_request_id' => 'N/A',
                'checkout_request_id' => 'N/A',
                'amount' => $amount,
                'phone_number' => $phone,
                'status' => 'Pending',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $this->transactionModel->insert($insert);
            $transactionId = $this->transactionModel->getInsertID();

            if ($package) {
                $startDate = date('Y-m-d H:i:s');
                $expiresOn = $this->calculateSubscriptionExpiry($package, $startDate);

                $subscriptionModel = new \App\Models\SubscriptionModel();
                $subscriptionModel->insert([
                    'client_id' => $clientId,
                    'package_id' => $packageId,
                    'payment_id' => null,
                    'start_date' => $startDate,
                    'expires_on' => $expiresOn,
                    'status' => 'pending'
                ]);

                $this->logger->info("Created fallback subscription with correct expiry", [
                    'client_id' => $clientId,
                    'package_id' => $packageId,
                    'expires_on' => $expiresOn
                ]);
            }

            return $transactionId;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to create fallback transaction", ['exception' => $e->getMessage()]);
            return null;
        }
    }


    private function calculateSubscriptionExpiry(array $package, ?string $startDate = null): string
    {
        $startDate = $startDate ?? date('Y-m-d H:i:s');

        if (!empty($package['duration_length']) && !empty($package['duration_unit'])) {
            return date(
                'Y-m-d H:i:s',
                strtotime("+{$package['duration_length']} {$package['duration_unit']}", strtotime($startDate))
            );
        }

        // fallback to 1 day
        return date('Y-m-d H:i:s', strtotime('+1 day', strtotime($startDate)));
    }



    private function processSuccessfulCallback(array $transaction, array $callback, string $resultDesc, string $checkoutRequestId): array
    {
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        $amount = null;
        $mpesaReceipt = null;
        $phone = null;
        $transactionDate = null;

        foreach ($metadata as $item) {
            switch ($item['Name'] ?? null) {
                case 'Amount':
                    $amount = $item['Value'] ?? null;
                    break;
                case 'MpesaReceiptNumber':
                    $mpesaReceipt = $item['Value'] ?? null;
                    break;
                case 'PhoneNumber':
                    $phone = $item['Value'] ?? null;
                    break;
                case 'TransactionDate':
                    if ($item['Value']) {
                        $transactionDate = \DateTime::createFromFormat('YmdHis', substr((string)$item['Value'], 0, 14));
                        $transactionDate = $transactionDate ? $transactionDate->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
                    }
                    break;
            }
        }

        if (empty($mpesaReceipt)) {
            $mpesaReceipt = 'UNKNOWN_' . $transaction['id'];
        }

        $subscriptionModel = new \App\Models\SubscriptionModel();

        // Update mpesa_transactions row first (best-effort)
        try {
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
        } catch (\Throwable $e) {
            $this->logger->error("Failed to update mpesa_transactions", ['exception' => $e->getMessage()]);
        }

        // Start DB transaction for atomic payment+subscription creation
        $this->db->transStart();
        try {
            // Try to obtain a lock on payment rows for this mpesa_transaction or receipt to avoid race
            // Use SELECT ... FOR UPDATE when supported (DB driver will accept raw query)
            $db = $this->db;
            $sql = "SELECT id FROM payments WHERE mpesa_transaction_id = ? OR mpesa_receipt_number = ? FOR UPDATE";
            $res = $db->query($sql, [$transaction['id'], $mpesaReceipt]);
            $existing = $res->getRowArray();

            // If payment already exists and subscription exists, bail out (idempotent)
            if ($existing) {
                $existingPaymentId = $existing['id'];
                $existingSub = $subscriptionModel->where('payment_id', $existingPaymentId)->first();
                if ($existingSub) {
                    $this->logger->info("Callback already processed (locked check).", [
                        'transaction_id' => $transaction['id'],
                        'payment_id' => $existingPaymentId,
                        'subscription_id' => $existingSub['id']
                    ]);
                    $this->db->transComplete();
                    return ['ResultCode' => 0, 'ResultDesc' => 'Callback already processed'];
                }
                // If payment exists but subscription does not, we'll continue and create subscription below using existingPaymentId
                $paymentId = $existingPaymentId;
            } else {
                // Payment does not exist — create it
                $paymentData = [
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
                ];

                // Insert payment — may throw duplicate-key if another worker inserted meanwhile
                try {
                    $paymentId = $this->paymentsModel->insert($paymentData, true);
                } catch (\Throwable $e) {
                    // Duplicate key or other race — re-query to get existing payment id
                    $this->logger->warning("Payment insert race occurred, selecting existing payment", ['exception' => $e->getMessage()]);
                    $existingPayment = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])
                        ->orWhere('mpesa_receipt_number', $mpesaReceipt)
                        ->first();
                    $paymentId = $existingPayment['id'] ?? null;
                }
            }

            if (!$paymentId) {
                // Something unexpected: cannot create nor find payment
                $this->logger->error("Could not create or find payment record for transaction", ['transaction_id' => $transaction['id']]);
                $this->db->transRollback();
                return ['ResultCode' => 1, 'ResultDesc' => 'Processing error'];
            }

            // Check again for existing subscription tied to this payment
            $existingSubForPayment = $subscriptionModel->where('payment_id', $paymentId)->first();
            if ($existingSubForPayment) {
                $this->logger->info("Subscription exists for payment (post-insert check)", [
                    'payment_id' => $paymentId,
                    'subscription_id' => $existingSubForPayment['id']
                ]);
                $this->db->transComplete();
                return ['ResultCode' => 0, 'ResultDesc' => 'Callback already processed'];
            }

            // Expire existing active subscriptions for same client + package
            $activeSubs = $subscriptionModel->where('client_id', $transaction['client_id'])
                ->where('package_id', $transaction['package_id'])
                ->where('status', 'active')
                ->findAll();
            foreach ($activeSubs as $sub) {
                $subscriptionModel->update($sub['id'], ['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')]);
            }

            // Create new subscription
            $startDate = date('Y-m-d H:i:s');
            $package = $this->packageModel->find($transaction['package_id']);
            $expiresOn = $this->calculateSubscriptionExpiry($package, $startDate);

            try {
                $subId = $subscriptionModel->insert([
                    'client_id' => $transaction['client_id'],
                    'package_id' => $transaction['package_id'],
                    'payment_id' => $paymentId,
                    'start_date' => $startDate,
                    'expires_on' => $expiresOn,
                    'status' => 'active'
                ], true);
            } catch (\Throwable $e) {
                // If subscription unique constraint triggers, fetch existing subscription
                $this->logger->warning("Subscription insert race or duplicate", ['exception' => $e->getMessage()]);
                $existing = $subscriptionModel->where('payment_id', $paymentId)->first();
                $subId = $existing['id'] ?? null;
            }

            if (!$subId) {
                $this->logger->error("Failed to create or find subscription for payment", ['payment_id' => $paymentId]);
                $this->db->transRollback();
                return ['ResultCode' => 1, 'ResultDesc' => 'Processing error'];
            }

            // Router provisioning and activation (outside DB critical section ideally)
            $this->db->transComplete();

            try {
                $activationService = new ActivationService($this->logger);
                $activationService->activate($transaction['client_id'], $transaction['package_id'], (float)$amount);
            } catch (\Throwable $e) {
                $this->logger->error("Activation failed after commit", ['exception' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception during payments/subscriptions transaction", ['exception' => $e->getMessage()]);
            return ['ResultCode' => 1, 'ResultDesc' => 'Processing error'];
        }

        $this->logger->info("Callback processing finished successfully", [
            'checkout_request_id' => $checkoutRequestId,
            'mpesa_receipt' => $mpesaReceipt
        ]);

        return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
    }


    private function safeInsertTransaction(?string $merchantRequestId, ?string $checkoutRequestId): ?array
    {
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
            return null;
        }
    }

    private function markTransactionFailed(array $transaction, int $resultCode, string $resultDesc): array
    {
        try {
            $this->transactionModel->update($transaction['id'], [
                'status' => 'Failed',
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to update transaction as failed", ['exception' => $e->getMessage()]);
        }

        $this->db->transStart();
        try {
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
        }

        return ['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'];
    }
}
