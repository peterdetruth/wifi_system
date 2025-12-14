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

    public function initiateTransaction(
        int $clientId,
        int $packageId,
        float $amount,
        string $phone
    ): ?string {
        $ip = $this->request->getIPAddress();

        try {
            // ------------------------------------------------------------------
            // 1. Validate client & package
            // ------------------------------------------------------------------
            $client  = $this->clientModel->find($clientId);
            $package = $this->packageModel->find($packageId);

            if (!$client || !$package) {
                $this->logService->error(
                    'mpesa',
                    'Client or package not found before STK initiation',
                    compact('clientId', 'packageId'),
                    $clientId,
                    $ip
                );
                return null;
            }

            // ------------------------------------------------------------------
            // 2. Normalize & validate phone (CRITICAL)
            // ------------------------------------------------------------------
            $phone = preg_replace('/\D/', '', $phone);

            if (str_starts_with($phone, '0')) {
                $phone = '254' . substr($phone, 1);
            }

            if (!preg_match('/^2547\d{8}$/', $phone)) {
                $this->logService->error(
                    'mpesa',
                    'Invalid phone format for STK push',
                    ['phone' => $phone],
                    $clientId,
                    $ip
                );
                return null;
            }

            // ------------------------------------------------------------------
            // 3. Get access token (NO FALLBACK PRETENDING)
            // ------------------------------------------------------------------
            $accessToken = $this->requestAccessToken();
            if (!$accessToken) {
                $this->logService->error(
                    'mpesa',
                    'Failed to obtain M-PESA access token',
                    null,
                    $clientId,
                    $ip
                );
                return null;
            }

            // ------------------------------------------------------------------
            // 4. Build & send STK push
            // ------------------------------------------------------------------
            $payload     = $this->buildStkPayload($client, $package, $amount, $phone);
            $stkResponse = $this->sendStkPush($accessToken, $payload);

            $this->logService->debug(
                'mpesa',
                'Raw STK response received',
                ['response' => $stkResponse],
                $clientId,
                $ip
            );

            // ------------------------------------------------------------------
            // 5. STRICT success validation (THIS WAS MISSING)
            // ------------------------------------------------------------------
            if (
                !is_array($stkResponse) ||
                ($stkResponse['ResponseCode'] ?? null) !== '0' ||
                empty($stkResponse['CheckoutRequestID']) ||
                empty($stkResponse['MerchantRequestID'])
            ) {
                $this->logService->error(
                    'mpesa',
                    'STK push rejected by Safaricom',
                    ['response' => $stkResponse],
                    $clientId,
                    $ip
                );
                return null;
            }

            // ------------------------------------------------------------------
            // 6. Save pending transaction (REAL STK ONLY)
            // ------------------------------------------------------------------
            $checkoutRequestId = $stkResponse['CheckoutRequestID'];

            $this->transactionModel->insert([
                'client_id'           => $clientId,
                'package_id'          => $packageId,
                'merchant_request_id' => $stkResponse['MerchantRequestID'],
                'checkout_request_id' => $checkoutRequestId,
                'amount'              => $amount,
                'phone_number'        => $phone,
                'status'              => 'Pending',
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

            $this->logService->info(
                'mpesa',
                'STK push successfully initiated',
                [
                    'checkout_request_id' => $checkoutRequestId,
                    'amount'              => $amount,
                    'phone'               => $phone
                ],
                $clientId,
                $ip
            );

            // ------------------------------------------------------------------
            // 7. RETURN REAL CheckoutRequestID ONLY
            // ------------------------------------------------------------------
            return $checkoutRequestId;
        } catch (\Throwable $e) {
            $this->logService->error(
                'mpesa',
                'Exception during STK initiation',
                ['error' => $e->getMessage()],
                $clientId,
                $ip
            );

            return null;
        }
    }

    /**
     * Handle M-PESA callback
     */
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

        // Find existing transaction
        $transaction = $this->transactionModel
            ->where('checkout_request_id', $checkoutRequestId)
            ->first();

        if (!$transaction) {
            return ['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'];
        }

        // ----------------------------
        // FAILED PAYMENT
        // ----------------------------
        if ($resultCode !== 0) {
            $this->transactionModel->update($transaction['id'], [
                'status'       => 'Failed',
                'result_code'  => $resultCode,
                'result_desc'  => $resultDesc,
                'updated_at'   => date('Y-m-d H:i:s')
            ]);

            return ['ResultCode' => 0, 'ResultDesc' => 'Failure recorded'];
        }

        // ----------------------------
        // SUCCESSFUL PAYMENT
        // ----------------------------

        // Extract metadata
        $metadata = $callback['CallbackMetadata']['Item'] ?? [];

        $amount        = null;
        $receipt       = null;
        $phone         = null;
        $transactionAt = date('Y-m-d H:i:s');

        foreach ($metadata as $item) {
            switch ($item['Name'] ?? '') {
                case 'Amount':
                    $amount = $item['Value'];
                    break;
                case 'MpesaReceiptNumber':
                    $receipt = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $phone = $item['Value'];
                    break;
                case 'TransactionDate':
                    $transactionAt = \DateTime::createFromFormat(
                        'YmdHis',
                        (string) $item['Value']
                    )?->format('Y-m-d H:i:s') ?? $transactionAt;
                    break;
            }
        }

        if (!$receipt) {
            return ['ResultCode' => 1, 'ResultDesc' => 'Missing receipt'];
        }

        // Start atomic DB transaction
        $this->db->transStart();

        // Update mpesa_transactions
        $this->transactionModel->update($transaction['id'], [
            'status'                  => 'Success',
            'amount'                  => $amount,
            'mpesa_receipt_number'    => $receipt,
            'phone_number'            => $phone,
            'transaction_date'        => $transactionAt,
            'result_code'             => 0,
            'result_desc'             => $resultDesc,
            'updated_at'              => date('Y-m-d H:i:s')
        ]);

        // Check if payment already exists (idempotent)
        $existingPayment = $this->paymentsModel
            ->where('mpesa_transaction_id', $transaction['id'])
            ->first();

        if (!$existingPayment) {
            $paymentId = $this->paymentsModel->insert([
                'mpesa_transaction_id' => $transaction['id'],
                'client_id'            => $transaction['client_id'],
                'package_id'           => $transaction['package_id'],
                'amount'               => $amount,
                'payment_method'       => 'mpesa',
                'status'               => 'completed',
                'mpesa_receipt_number' => $receipt,
                'phone'                => $phone,
                'transaction_date'     => $transactionAt,
                'created_at'           => date('Y-m-d H:i:s')
            ], true);
        } else {
            $paymentId = $existingPayment['id'];
        }

        // Check if subscription already exists
        $subscriptionModel = new SubscriptionModel();
        $existingSub = $subscriptionModel
            ->where('payment_id', $paymentId)
            ->first();

        if (!$existingSub) {
            $package = $this->packageModel->find($transaction['package_id']);

            $startDate = date('Y-m-d H:i:s');
            $expiresOn = $this->calculateSubscriptionExpiry($package, $startDate);

            $subscriptionModel->insert([
                'client_id'   => $transaction['client_id'],
                'package_id'  => $transaction['package_id'],
                'payment_id'  => $paymentId,
                'start_date'  => $startDate,
                'expires_on'  => $expiresOn,
                'status'      => 'active'
            ]);
        }

        $this->db->transComplete();

        // Activation (AFTER commit)
        try {
            $activationService = new ActivationService($this->logger);
            $activationService->activate(
                $transaction['client_id'],
                $transaction['package_id'],
                (float) $amount
            );
        } catch (\Throwable $e) {
            log_message('error', 'Activation failed: ' . $e->getMessage());
        }

        return ['ResultCode' => 0, 'ResultDesc' => 'Processed successfully'];
    }

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
            $this->logService->info(
                'mpesa',
                'Reconnect successful',
                ['clientId' => $clientId, 'packageId' => $package['id'], 'subscription_id' => $subId, 'expires_o' => $expiresOn],
                $clientId,
                $ip
            );

            return ['success' => true, 'message' => 'Reconnection successful.', 'subscription_id' => $subId, 'expires_on' => $expiresOn];
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
            if (!is_array($tokenResult) || empty($tokenResult['access_token'])) {
                $this->logger->error("Failed to obtain M-PESA access token", $tokenResult ?? $response);
                $this->logService->error(
                    'mpesa',
                    'Failed to obtain M-PESA access token',
                    null,
                    null,
                    $ip
                );
                return null;
            }

            return $tokenResult['access_token'];
        } catch (\Throwable $e) {
            $this->logger->error("Exception requesting access token", ['exception' => $e->getMessage()]);
            $this->logService->error(
                'mpesa',
                'Exception requesting access token',
                ['error' => $e->getMessage()],
                null,
                $ip
            );
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
            $this->logService->error(
                'mpesa',
                'Exception sending STK Push',
                ['error' => $e->getMessage()],
                null,
                $ip
            );
            return null;
        }
    }

    private function savePendingTransaction(int $clientId, int $packageId, float $amount, string $phone, array $stkResponse): ?string
    {
        $ip = $this->request->getIPAddress();
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
            $this->logService->info(
                'mpesa',
                'STK Push transaction saved',
                ['checkoutRequestID' => $checkoutRequestId, 'transaction_insert_id' => $this->transactionModel->getInsertID()],
                $clientId,
                $ip
            );

            return $checkoutRequestId;
        } catch (\Throwable $e) {
            $this->logger->error("Exception saving pending transaction", ['exception' => $e->getMessage()]);
            $this->logService->error(
                'mpesa',
                'Exception saving pending transaction',
                ['error' => $e->getMessage()],
                $clientId,
                $ip
            );
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
        $ip = $this->request->getIPAddress();

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
            $this->logService->error(
                'mpesa',
                'Failed to update mpesa_transactions.',
                ['error' => $e->getMessage()],
                null,
                $ip
            );
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
                    $this->logService->info(
                        'mpesa',
                        'Callback already processed (locked check)',
                        ['transaction_id' => $transaction['id'], 'payment_id' => $existingPaymentId, 'subscription_id' => $existingSub['id']],
                        null,
                        $ip
                    );
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
                    $this->logService->warning(
                        'mpesa',
                        'Payment insert race occurred, selecting existing payment',
                        ['error' => $e->getMessage()],
                        null,
                        $ip
                    );
                    $existingPayment = $this->paymentsModel->where('mpesa_transaction_id', $transaction['id'])
                        ->orWhere('mpesa_receipt_number', $mpesaReceipt)
                        ->first();
                    $paymentId = $existingPayment['id'] ?? null;
                }
            }

            if (!$paymentId) {
                // Something unexpected: cannot create nor find payment
                $this->logger->error("Could not create or find payment record for transaction", ['transaction_id' => $transaction['id']]);
                $this->logService->error(
                    'mpesa',
                    'Could not create or find payment record for transaction',
                    ['transaction_id' => $transaction['id']],
                    null,
                    $ip
                );
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
                $this->logService->debug(
                    'mpesa',
                    'Subscription exists for payment (post-insert check)',
                    ['payment_id' => $paymentId, 'subscription_id' => $existingSubForPayment['id']],
                    null,
                    $ip
                );
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
                $this->logService->warning(
                    'mpesa',
                    'Subscription insert race or duplicate',
                    ['error' => $e->getMessage()],
                    null,
                    $ip
                );
                $existing = $subscriptionModel->where('payment_id', $paymentId)->first();
                $subId = $existing['id'] ?? null;
            }

            if (!$subId) {
                $this->logger->error("Failed to create or find subscription for payment", ['payment_id' => $paymentId]);
                $this->logService->error(
                    'mpesa',
                    'Failed to create or find subscription for payment',
                    ['payment_id' => $paymentId],
                    null,
                    $ip
                );
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
                $this->logService->error(
                    'mpesa',
                    'Activation failed after commit',
                    ['error' => $e->getMessage()],
                    null,
                    $ip
                );
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception during payments/subscriptions transaction", ['exception' => $e->getMessage()]);
            $this->logService->error(
                'mpesa',
                'Exception during payments/subscriptions transaction',
                ['error' => $e->getMessage()],
                null,
                $ip
            );
            return ['ResultCode' => 1, 'ResultDesc' => 'Processing error'];
        }

        $this->logger->info("Callback processing finished successfully", [
            'checkout_request_id' => $checkoutRequestId,
            'mpesa_receipt' => $mpesaReceipt
        ]);
        $this->logService->info(
            'mpesa',
            'Callback processing finished successfully',
            ['checkout_request_id' => $checkoutRequestId, 'mpesa_receipt' => $mpesaReceipt],
            null,
            $ip
        );

        return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
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
            $this->logService->error(
                'mpesa',
                'Exception in safeInsertTransaction',
                ['error' => $e->getMessage()],
                null,
                $ip
            );
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
        } catch (\Throwable $e) {
            $this->logger->error("Failed to update transaction as failed", ['exception' => $e->getMessage()]);
            $this->logService->error(
                'mpesa',
                'Failed to update transaction as failed',
                ['error' => $e->getMessage()],
                null,
                $ip
            );
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
            $this->logService->error(
                'mpesa',
                'Exception while marking failed payments/subscriptions',
                ['error' => $e->getMessage()],
                null,
                $ip
            );
        }

        return ['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'];
    }
}
