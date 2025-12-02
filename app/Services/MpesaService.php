<?php

namespace App\Services;

use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
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

    public function initiateTransaction(
        int $clientId,
        int $packageId,
        float $amount,
        string $phone
    ): ?string {
        try {
            $client = $this->clientModel->find($clientId);
            $package = $this->packageModel->find($packageId);

            if (!$client || !$package) {
                $this->logger->error("Client or package not found for STK push", compact('clientId', 'packageId'));
                return null;
            }

            $accessToken = $this->requestAccessToken();
            if (!$accessToken) return null;

            $stkPayload = $this->buildStkPayload($client, $package, $amount, $phone);
            $stkResponse = $this->sendStkPush($accessToken, $stkPayload);

            // STK push failed, create fallback transaction
            if (!is_array($stkResponse) || ($stkResponse['ResponseCode'] ?? '') !== "0") {
                $this->logger->warning("STK push failed, creating fallback transaction", $stkResponse ?? []);
                return $this->createFallbackTransaction($clientId, $packageId, $amount, $phone, $package);
            }

            // Save pending transaction
            $checkoutRequestId = $this->savePendingTransaction($clientId, $packageId, $amount, $phone, $stkResponse);
            $this->logger->info("STK push initiated successfully", [
                'checkoutRequestId' => $checkoutRequestId,
                'clientId' => $clientId,
                'packageId' => $packageId
            ]);

            return $checkoutRequestId;
        } catch (\Throwable $e) {
            $this->logger->error("Exception in initiateTransaction", [
                'message' => $e->getMessage(),
                'clientId' => $clientId,
                'packageId' => $packageId
            ]);

            // fallback on exception
            return $this->createFallbackTransaction($clientId, $packageId, $amount, $phone, $package ?? null);
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

            $this->logger->info("Received STK callback", [
                'checkoutRequestId' => $checkoutRequestId,
                'merchantRequestId' => $merchantRequestId,
                'resultCode' => $resultCode,
                'resultDesc' => $resultDesc
            ]);

            // Find or safely create the transaction
            $transaction = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
            if (!$transaction) {
                $transaction = $this->safeInsertTransaction($merchantRequestId, $checkoutRequestId);
            }

            if (!$transaction) {
                return ['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'];
            }

            if ($resultCode !== 0) {
                return $this->markTransactionFailed($transaction, $resultCode, $resultDesc);
            }

            // Extract callback metadata
            $metadata = $callback['CallbackMetadata']['Item'] ?? [];
            $amount = $mpesaReceipt = $phone = $transactionDate = null;
            foreach ($metadata as $item) {
                switch ($item['Name'] ?? null) {
                    case 'Amount':
                        $amount = $item['Value'];
                        break;
                    case 'MpesaReceiptNumber':
                        $mpesaReceipt = $item['Value'];
                        break;
                    case 'PhoneNumber':
                        $phone = $item['Value'];
                        break;
                    case 'TransactionDate':
                        $transactionDate = isset($item['Value'])
                            ? \DateTime::createFromFormat('YmdHis', substr((string)$item['Value'], 0, 14))->format('Y-m-d H:i:s')
                            : date('Y-m-d H:i:s');
                        break;
                }
            }

            if (empty($mpesaReceipt)) $mpesaReceipt = 'UNKNOWN_' . $transaction['id'];

            // Start atomic transaction
            $this->db->transStart();

            // Update transaction
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

            // Update or create payment
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

            $paymentRow = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])->first();
            $paymentId = $paymentRow
                ? $this->paymentsModel->update($paymentRow['id'], $paymentData)
                : $this->paymentsModel->insert($paymentData, true);

            // Expire old subscriptions
            $subscriptionModel = new \App\Models\SubscriptionModel();
            $activeSubs = $subscriptionModel
                ->where('client_id', $transaction['client_id'])
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

            $subId = $subscriptionModel->insert([
                'client_id' => $transaction['client_id'],
                'package_id' => $transaction['package_id'],
                'payment_id' => $paymentId,
                'start_date' => $startDate,
                'expires_on' => $expiresOn,
                'status' => 'active'
            ], true);

            // Call activation service
            $activationService = new ActivationService($this->logger);
            $activationService->activate($transaction['client_id'], $transaction['package_id'], (float)$amount);

            $this->db->transComplete();

            $this->logger->info("Callback processed successfully", [
                'checkoutRequestId' => $checkoutRequestId,
                'mpesaReceipt' => $mpesaReceipt,
                'subscription_id' => $subId
            ]);

            return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception in handleCallback", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return ['ResultCode' => 1, 'ResultDesc' => 'Internal error'];
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

    public function getExpiry(array $package, ?string $startDate = null): string
    {
        return $this->calculateSubscriptionExpiry($package, $startDate);
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

        if (empty($mpesaReceipt)) $mpesaReceipt = 'UNKNOWN_' . $transaction['id'];

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

        $this->db->transStart();
        try {
            $paymentRow = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])->first();
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

            if ($paymentRow) {
                $this->paymentsModel->update($paymentRow['id'], $paymentData);
                $paymentId = $paymentRow['id'];
            } else {
                $paymentId = $this->paymentsModel->insert($paymentData, true);
            }

            $subscriptionModel = new \App\Models\SubscriptionModel();
            $existingSubs = $subscriptionModel->where('client_id', $transaction['client_id'])
                ->where('package_id', $transaction['package_id'])
                ->where('status', 'active')
                ->findAll();

            foreach ($existingSubs as $sub) {
                $subscriptionModel->update($sub['id'], ['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')]);
            }

            $startDate = date('Y-m-d H:i:s');
            $package = $this->packageModel->find($transaction['package_id']);
            $expiresOn = $this->calculateSubscriptionExpiry($package, $startDate);

            $subId = $subscriptionModel->insert([
                'client_id' => $transaction['client_id'],
                'package_id' => $transaction['package_id'],
                'payment_id' => $paymentId,
                'start_date' => $startDate,
                'expires_on' => $expiresOn,
                'status' => 'active'
            ], true);

            $this->logger->info("Created new subscription record", [
                'subscription_id' => $subId,
                'client_id' => $transaction['client_id'],
                'package_id' => $transaction['package_id']
            ]);

            $routerId = $package['router_id'] ?? null;
            $this->logger->debug("Router activation placeholder called", [
                'subscription_id' => $subId,
                'router_id' => $routerId,
                'package_id' => $transaction['package_id'],
                'package_name' => $package['name'] ?? null,
                'package_type' => $package['type'] ?? null
            ]);

            $activationService = new ActivationService($this->logger);
            $activationService->activate($transaction['client_id'], $transaction['package_id'], (float)$amount);

            $this->db->transComplete();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception during payments/subscriptions transaction", ['exception' => $e->getMessage()]);
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

    public function reconnectUsingMpesaCode(string $mpesaCode, int $clientId): array
    {
        try {
            // Step 1: Lookup transaction by receipt number
            $transaction = $this->transactionModel
                ->where('mpesa_receipt_number', $mpesaCode)
                ->where('client_id', $clientId)
                ->where('status', 'Success')
                ->first();

            if (!$transaction) {
                $this->logger->info("Reconnect failed: invalid or unsuccessful M-PESA code", [
                    'mpesa_code' => $mpesaCode,
                    'client_id' => $clientId
                ]);
                return ['success' => false, 'message' => 'Invalid or unsuccessful M-PESA code.'];
            }

            // Step 2: Check if code has already been used for a subscription
            $subscriptionModel = new \App\Models\SubscriptionModel();
            $existingSub = $subscriptionModel
                ->where('payment_id', $transaction['id'])
                ->first();

            if ($existingSub) {
                return ['success' => false, 'message' => 'This M-PESA code has already been used for reconnection.'];
            }

            // Step 3: Get package info for duration calculation
            $package = $this->packageModel->find($transaction['package_id']);
            if (!$package) {
                $this->logger->error("Package not found during reconnect", [
                    'package_id' => $transaction['package_id']
                ]);
                return ['success' => false, 'message' => 'Package not found.'];
            }

            $startDate = date('Y-m-d H:i:s');
            // Calculate expiration using duration_length & duration_unit
            $expiresOn = (new \DateTime($startDate))
                ->modify("+{$package['duration_length']} {$package['duration_unit']}")
                ->format('Y-m-d H:i:s');

            // Step 4: Insert new subscription
            $subId = $subscriptionModel->insert([
                'client_id' => $clientId,
                'package_id' => $package['id'],
                'payment_id' => $transaction['id'],
                'start_date' => $startDate,
                'expires_on' => $expiresOn,
                'status' => 'active'
            ], true);

            $this->logger->info("Reconnect successful", [
                'client_id' => $clientId,
                'subscription_id' => $subId,
                'mpesa_code' => $mpesaCode
            ]);

            // Step 5: Optionally, call activation service
            $activationService = new ActivationService($this->logger);
            $activationService->activate($clientId, $package['id'], (float)$transaction['amount']);

            return [
                'success' => true,
                'message' => 'Reconnection successful.',
                'subscription_id' => $subId,
                'expires_on' => $expiresOn
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Exception during reconnectUsingMpesaCode", [
                'exception' => $e->getMessage(),
                'mpesa_code' => $mpesaCode,
                'client_id' => $clientId
            ]);

            return ['success' => false, 'message' => 'Internal error occurred.'];
        }
    }
}
