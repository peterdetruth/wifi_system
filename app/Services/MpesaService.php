<?php

namespace App\Services;

use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use CodeIgniter\Database\BaseConnection;
use App\Services\ActivationService;
use ArgumentCountError;

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

    /**
     * Initiates an M-PESA transaction (STK Push) and returns checkoutRequestID
     * or null on failure.
     *
     * @return string|null
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

    /**
     * Request M-PESA OAuth access token
     */
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

    /**
     * Build STK payload for M-PESA transaction
     */
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

    /**
     * Send STK push request to M-PESA API
     */
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

    /**
     * Save pending transaction in database
     */
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
     * Handle M-PESA callback (expects decoded JSON payload)
     * Returns an array with ResultCode and ResultDesc for the caller.
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

    /**
     * Mark transaction as failed and update related payments and client_packages
     */
    private function markTransactionFailed(array $transaction, int $resultCode, string $resultDesc): array
    {
        $this->transactionModel->update($transaction['id'], [
            'status' => 'Failed',
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $dbErr = $this->db->error();
        if ($dbErr['code'] !== 0) {
            $this->logger->error("DB Error updating mpesa_transactions to Failed", $dbErr);
        } else {
            $this->logger->info("mpesa_transaction marked Failed", ['transaction_id' => $transaction['id']]);
        }

        $this->db->transStart();
        try {
            $existingPayment = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])->first();
            if ($existingPayment) {
                $this->paymentsModel->update($existingPayment['id'], [
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

            if (!empty($transaction['client_id']) && !empty($transaction['package_id'])) {
                $this->db->table('client_packages')
                    ->where('client_id', $transaction['client_id'])
                    ->where('package_id', $transaction['package_id'])
                    ->where('status', 'pending')
                    ->update(['status' => 'failed', 'updated_at' => date('Y-m-d H:i:s')]);

                $err = $this->db->error();
                if ($err['code'] !== 0) {
                    $this->logger->error("DB error updating client_packages to failed", $err);
                } else {
                    $this->logger->info("client_packages marked failed", ['client_id' => $transaction['client_id'],'package_id' => $transaction['package_id']]);
                }
            }

            $this->db->transComplete();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception while marking failed payments/client_packages", ['exception' => $e->getMessage()]);
        }

        return ['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'];
    }

    /**
     * Process a successful callback
     */
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
                            if (is_numeric($value) && strlen((string)$value) >= 14) {
                                $transactionDate = \DateTime::createFromFormat('YmdHis', substr((string)$value, 0, 14));
                                $transactionDate = $transactionDate ? $transactionDate->format('Y-m-d H:i:s') : null;
                            } else {
                                $ts = @strtotime($value);
                                $transactionDate = $ts ? date('Y-m-d H:i:s', $ts) : null;
                            }
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
        $existingPaymentByReceipt = $this->paymentsModel->where('mpesa_receipt_number', $mpesaReceipt)->first();
        if ($existingPaymentByReceipt) {
            $this->logger->info("Duplicate payment detected - ignoring", ['mpesa_receipt' => $mpesaReceipt]);
            return ['ResultCode' => 0, 'ResultDesc' => 'Duplicate ignored'];
        }

        // Update mpesa_transactions -> success
        $updateTx = [
            'amount' => $amount ?? $transaction['amount'],
            'phone_number' => $phone ?? $transaction['phone_number'],
            'mpesa_receipt_number' => $mpesaReceipt,
            'transaction_date' => $transactionDate ?? $transaction['transaction_date'],
            'status' => 'Success',
            'result_code' => 0,
            'result_desc' => $resultDesc,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->transactionModel->update($transaction['id'], $updateTx);
        $dbErr = $this->db->error();
        if ($dbErr['code'] !== 0) {
            $this->logger->error("DB Error updating mpesa_transactions (success)", $dbErr);
        } else {
            $this->logger->info("mpesa_transaction updated", ['transaction_id' => $transaction['id']]);
        }

        // Atomic updates: payments + client_packages + subscription activation
        $this->db->transStart();
        try {
            // payments: update or insert
            $paymentRow = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])->first();
            if ($paymentRow) {
                $this->paymentsModel->update($paymentRow['id'], [
                    'status' => 'completed',
                    'amount' => $amount ?? $paymentRow['amount'],
                    'mpesa_receipt_number' => $mpesaReceipt,
                    'phone' => $phone ?? $paymentRow['phone'],
                    'transaction_date' => $transactionDate ?? $paymentRow['transaction_date'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                $this->paymentsModel->insert([
                    'mpesa_transaction_id' => $transaction['id'],
                    'client_id' => $transaction['client_id'] ?? null,
                    'package_id' => $transaction['package_id'] ?? null,
                    'amount' => $amount,
                    'payment_method' => 'mpesa',
                    'status' => 'completed',
                    'mpesa_receipt_number' => $mpesaReceipt,
                    'phone' => $phone,
                    'transaction_date' => $transactionDate,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $payErr = $this->db->error();
            if ($payErr['code'] !== 0) {
                $this->logger->error("DB Error inserting/updating payments", $payErr);
                throw new \Exception('DB error saving payment');
            }

            // client_packages: pending -> active
            if (!empty($transaction['client_id']) && !empty($transaction['package_id'])) {
                $this->db->table('client_packages')
                    ->where('client_id', $transaction['client_id'])
                    ->where('package_id', $transaction['package_id'])
                    ->where('status', 'pending')
                    ->orderBy('created_at', 'DESC')
                    ->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);

                $cpErr = $this->db->error();
                if ($cpErr['code'] !== 0) {
                    $this->logger->error("DB Error updating client_packages to active", $cpErr);
                    throw new \Exception('DB error updating client_packages');
                }
                $this->logger->info("client_packages updated to active", ['client_id' => $transaction['client_id'], 'package_id' => $transaction['package_id']]);
            } else {
                $this->logger->info("Skipping client_packages update - missing client or package on transaction", ['transaction' => $transaction]);
            }

            // ActivationService: ensure subscription insertion
            try {
                try {
                    $activationService = new ActivationService($this->logger);
                } catch (ArgumentCountError $e) {
                    $activationService = new ActivationService();
                }

                if (!empty($transaction['client_id']) && !empty($transaction['package_id'])) {
                    $activated = $activationService->activate((int)$transaction['client_id'], (int)$transaction['package_id'], (float)$amount);
                    if ($activated) {
                        $this->logger->info("ActivationService succeeded", ['client_id' => $transaction['client_id'], 'package_id' => $transaction['package_id']]);
                    } else {
                        $this->logger->info("ActivationService returned false (admin may need to reconcile)", ['client_id' => $transaction['client_id'], 'package_id' => $transaction['package_id']]);
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->error("ActivationService exception", ['err' => $e->getMessage()]);
            }

            $this->db->transComplete();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception during payments/client_packages transaction", ['err' => $e->getMessage()]);
            return ['ResultCode' => 0, 'ResultDesc' => 'Processed with internal error (see logs)'];
        }

        $this->logger->info("Callback processing finished successfully", ['checkout_request_id' => $callback['CheckoutRequestID'], 'mpesa_receipt' => $mpesaReceipt]);
        return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
    }

    /**
     * Create a safe placeholder transaction if none exists for a callback.
     */
    private function safeInsertTransaction(?string $merchantRequestId, ?string $checkoutRequestId): ?array
    {
        try {
            $existing = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
            if ($existing) {
                return $existing;
            }

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
            $err = $this->db->error();
            if ($err['code'] !== 0) {
                $this->logger->error("DB Error in safeInsertTransaction", $err);
                return null;
            }

            return $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();
        } catch (\Throwable $e) {
            $this->logger->error("Exception in safeInsertTransaction", ['err' => $e->getMessage()]);
            return null;
        }
    }
}
