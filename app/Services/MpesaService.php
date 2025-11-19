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
        // Use BaseConnection so CI db transaction helpers are recognized by static analyzers / Intelephense
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

            // 🔹 Request access token
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
            $accessToken = $tokenResult['access_token'];

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
                'AccountReference'  => 'Hotspot_' . ($client['username'] ?? $clientId),
                'TransactionDesc'   => 'Payment for ' . ($package['name'] ?? $packageId)
            ];

            // 🔹 Send STK Push
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, getenv('MPESA_STK_URL'));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $accessToken"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $stkResponse = curl_exec($ch);
            curl_close($ch);

            $stkResult = json_decode($stkResponse, true);
            if (!is_array($stkResult) || !isset($stkResult['ResponseCode']) || $stkResult['ResponseCode'] !== "0") {
                $this->logger->error("STK push failed", $stkResult ?? $stkResponse);
                return null;
            }

            $merchantRequestId = $stkResult['MerchantRequestID'] ?? null;
            $checkoutRequestId = $stkResult['CheckoutRequestID'] ?? null;

            // 🔹 Save pending transaction in mpesa_transactions
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

            $this->logger->info("STK Push transaction saved", ['checkoutRequestID' => $checkoutRequestId, 'insert_id' => $this->transactionModel->getInsertID()]);

            return $checkoutRequestId;
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

            // find transaction
            $transaction = $this->transactionModel->where('checkout_request_id', $checkoutRequestId)->first();

            if (!$transaction) {
                // attempt safe insert placeholder to allow reconciliation
                $this->logger->error("Transaction not found for callback", ['checkout_request_id' => $checkoutRequestId]);
                $transaction = $this->safeInsertTransaction($merchantRequestId, $checkoutRequestId);
                if (!$transaction) {
                    return ['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'];
                }
            }

            // If error from M-PESA
            if ($resultCode !== 0) {
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

                // update or insert a failed payments row and mark pending client_packages as failed
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
                    // update client_packages pending -> failed
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

            // Success path
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
                                // Safaricom sometimes sends integer datetime like 20251116185233
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
                // continue: try to persist payments & activation for recovery
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
                    // Try constructing ActivationService with logger first; if its ctor signature differs, fall back.
                    try {
                        $activationService = new ActivationService($this->logger);
                    } catch (ArgumentCountError $e) {
                        $activationService = new ActivationService();
                    }

                    // call activate if client & package present
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
                    // don't fail the whole flow
                }

                $this->db->transComplete();
            } catch (\Throwable $e) {
                $this->db->transRollback();
                $this->logger->error("Exception during payments/client_packages transaction", ['err' => $e->getMessage()]);
                // still respond OK to Safaricom to prevent retries, but note internal error
                return ['ResultCode' => 0, 'ResultDesc' => 'Processed with internal error (see logs)'];
            }

            $this->logger->info("Callback processing finished successfully", ['checkout_request_id' => $checkoutRequestId, 'mpesa_receipt' => $mpesaReceipt]);
            return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
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
