<?php

namespace App\Services;

use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use App\Services\ActivationService;
use CodeIgniter\Database\BaseConnection;
use ArgumentCountError;

class MpesaService
{
    protected MpesaTransactionModel $transactionModel;
    protected PaymentsModel $paymentsModel;
    protected ClientModel $clientModel;
    protected PackageModel $packageModel;
    protected SubscriptionModel $subscriptionModel;
    protected MpesaLogger $logger;
    protected BaseConnection $db;

    public function __construct(MpesaLogger $logger)
    {
        $this->logger = $logger;
        $this->transactionModel = new MpesaTransactionModel();
        $this->paymentsModel = new PaymentsModel();
        $this->clientModel = new ClientModel();
        $this->packageModel = new PackageModel();
        $this->subscriptionModel = new SubscriptionModel();
        $this->db = \Config\Database::connect();
    }

    /**
     * Initiates an M-PESA transaction (STK Push)
     */
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
                $this->logger->error("Client or package not found for STK push", [
                    'clientId' => $clientId,
                    'packageId' => $packageId
                ]);
                return null;
            }

            $accessToken = $this->requestAccessToken();
            if (!$accessToken) return null;

            $stkPayload = $this->buildStkPayload($client, $package, $amount, $phone);
            $stkResponse = $this->sendStkPush($accessToken, $stkPayload);

            if (!is_array($stkResponse) || !isset($stkResponse['ResponseCode']) || $stkResponse['ResponseCode'] !== "0") {
                $this->logger->error("STK push failed", $stkResponse ?? []);
                return null;
            }

            return $this->savePendingTransaction($clientId, $packageId, $amount, $phone, $stkResponse);

        } catch (\Throwable $e) {
            $this->logger->error("Exception in initiateTransaction", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    private function requestAccessToken(): ?string
    {
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
    }

    private function savePendingTransaction(int $clientId, int $packageId, float $amount, string $phone, array $stkResponse): ?string
    {
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
        $txErr = $this->db->error();
        if ($txErr['code'] !== 0) {
            $this->logger->error("DB error inserting mpesa transaction", $txErr);
            return null;
        }

        $this->logger->info("STK Push transaction saved", [
            'checkoutRequestID' => $checkoutRequestId,
            'insert_id' => $this->transactionModel->getInsertID()
        ]);

        return $checkoutRequestId;
    }

    /**
     * Handle M-PESA callback
     */
    public function handleCallback(array $payload): array
    {
        try {
            if (empty($payload['Body']['stkCallback'])) {
                $this->logger->error("Invalid callback payload (missing stkCallback)", $payload);
                return ['ResultCode' => 1, 'ResultDesc' => 'Invalid payload'];
            }

            $callback = $payload['Body']['stkCallback'];
            $merchantRequestId = $callback['MerchantRequestID'] ?? null;
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            $resultCode = (int)($callback['ResultCode'] ?? 1);
            $resultDesc = $callback['ResultDesc'] ?? '';

            $this->logger->info("Received callback", [
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc
            ]);

            $transaction = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();

            if (!$transaction) {
                $this->logger->error("Transaction not found for callback", ['checkout_request_id' => $checkoutRequestId]);
                $transaction = $this->safeInsertTransaction($merchantRequestId, $checkoutRequestId);
                if (!$transaction) {
                    return ['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'];
                }
            }

            if ($resultCode !== 0) {
                return $this->markTransactionFailed($transaction, $resultCode, $resultDesc);
            }

            return $this->processSuccessfulCallback($transaction, $callback, $resultDesc);

        } catch (\Throwable $e) {
            $this->logger->error("Exception in handleCallback", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return ['ResultCode' => 1, 'ResultDesc' => 'Internal error'];
        }
    }

    private function markTransactionFailed(array $transaction, int $resultCode, string $resultDesc): array
    {
        $this->transactionModel->update($transaction['id'], [
            'status' => 'Failed',
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $this->db->transStart();
        try {
            $paymentRow = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])->first();
            if ($paymentRow) {
                $this->paymentsModel->update($paymentRow['id'], [
                    'status' => 'failed',
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
                    'phone' => $transaction['phone_number'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $this->db->transComplete();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception while marking failed payments", ['err' => $e->getMessage()]);
        }

        return ['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'];
    }

    private function processSuccessfulCallback(array $transaction, array $callback, string $resultDesc): array
    {
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];
        $amount = null;
        $mpesaReceipt = null;
        $phone = null;
        $transactionDate = null;

        if (is_array($metadata)) {
            foreach ($metadata as $item) {
                $name = $item['Name'] ?? null;
                $value = $item['Value'] ?? null;
                if (!$name) continue;

                switch ($name) {
                    case 'Amount':
                        $amount = $value;
                        break;
                    case 'MpesaReceiptNumber':
                        $mpesaReceipt = $value;
                        break;
                    case 'PhoneNumber':
                        $phone = $value;
                        break;
                    case 'TransactionDate':
                        if ($value) {
                            $transactionDate = \DateTime::createFromFormat('YmdHis', substr((string)$value, 0, 14));
                            $transactionDate = $transactionDate ? $transactionDate->format('Y-m-d H:i:s') : null;
                        }
                        break;
                }
            }
        }

        if (empty($mpesaReceipt)) {
            $this->logger->error("Missing MpesaReceiptNumber in callback metadata", ['transaction' => $transaction]);
            return ['ResultCode' => 1, 'ResultDesc' => 'Missing MpesaReceiptNumber'];
        }

        // Duplicate payment protection
        $existingPayment = $this->paymentsModel->where('mpesa_receipt_number', $mpesaReceipt)->first();
        if ($existingPayment) {
            $this->logger->info("Duplicate payment detected - ignoring", ['mpesa_receipt' => $mpesaReceipt]);
            return ['ResultCode' => 0, 'ResultDesc' => 'Duplicate ignored'];
        }

        $this->db->transStart();
        try {
            // Create payment
            $paymentId = $this->paymentsModel->insert([
                'mpesa_transaction_id' => $transaction['id'],
                'client_id' => $transaction['client_id'] ?? null,
                'package_id' => $transaction['package_id'] ?? null,
                'amount' => $amount ?? $transaction['amount'],
                'payment_method' => 'mpesa',
                'status' => 'completed',
                'mpesa_receipt_number' => $mpesaReceipt,
                'phone' => $phone ?? $transaction['phone_number'],
                'transaction_date' => $transactionDate ?? $transaction['transaction_date'],
                'created_at' => date('Y-m-d H:i:s')
            ], true);

            // Create subscription record (Option B)
            if (!empty($transaction['client_id']) && !empty($transaction['package_id'])) {
                $package = $this->packageModel->find($transaction['package_id']);
                $startDate = date('Y-m-d H:i:s');
                $expiresOn = date('Y-m-d H:i:s', strtotime("+{$package['duration_length']} {$package['duration_unit']}"));

                $subscriptionId = $this->subscriptionModel->insert([
                    'client_id' => $transaction['client_id'],
                    'package_id' => $transaction['package_id'],
                    'payment_id' => $paymentId,
                    'router_id' => $package['router_id'] ?? null,
                    'start_date' => $startDate,
                    'end_date' => $expiresOn,
                    'expires_on' => $expiresOn,
                    'status' => 'active'
                ], true);

                $this->logger->info("Created new subscription record", [
                    'subscription_id' => $subscriptionId,
                    'client_id' => $transaction['client_id'],
                    'package_id' => $transaction['package_id']
                ]);

                // Router activation placeholder
                if (!empty($package['router_id'])) {
                    $this->logger->debug("Router activation placeholder called", [
                        'subscription_id' => $subscriptionId,
                        'router_id' => $package['router_id'],
                        'package_id' => $package['id'],
                        'package_name' => $package['name'],
                        'package_type' => $package['type']
                    ]);
                    $this->logger->info("Router activation placeholder succeeded", ['subscription_id' => $subscriptionId, 'router_id' => $package['router_id']]);
                }
            }

            // Update mpesa_transactions to success
            $this->transactionModel->update($transaction['id'], [
                'status' => 'Success',
                'mpesa_receipt_number' => $mpesaReceipt,
                'transaction_date' => $transactionDate ?? $transaction['transaction_date'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->transComplete();

        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception during payments/subscriptions transaction", ['err' => $e->getMessage()]);
            return ['ResultCode' => 0, 'ResultDesc' => 'Processed with internal error (see logs)'];
        }

        $this->logger->info("Callback processing finished successfully", [
            'checkout_request_id' => $callback['CheckoutRequestID'],
            'mpesa_receipt' => $mpesaReceipt
        ]);

        return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
    }

    private function safeInsertTransaction(?string $merchantRequestId, ?string $checkoutRequestId): ?array
    {
        try {
            $existing = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
            if ($existing) return $existing;

            $data = [
                'client_id' => 0,
                'package_id' => 0,
                'transaction_id' => 'TEMP_' . substr(md5(($checkoutRequestId ?? '') . microtime()), 0, 8),
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
            $this->logger->error("Exception in safeInsertTransaction", ['err' => $e->getMessage()]);
            return null;
        }
    }
}
