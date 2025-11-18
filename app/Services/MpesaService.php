<?php

namespace App\Services;

use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\ClientModel;
use App\Models\PackageModel;
use CodeIgniter\Database\ConnectionInterface;

class MpesaService
{
    protected MpesaTransactionModel $transactionModel;
    protected PaymentsModel $paymentsModel;
    protected ClientModel $clientModel;
    protected PackageModel $packageModel;
    protected MpesaLogger $logger;
    protected ConnectionInterface $db;

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
                $this->logger->error("Client or package not found", ['clientId' => $clientId, 'packageId' => $packageId]);
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

            $result = json_decode($response, true);
            if (!isset($result['access_token'])) {
                $this->logger->error("Failed to obtain M-PESA access token", $result);
                return null;
            }

            $accessToken = $result['access_token'];

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

            if (!isset($stkResult['ResponseCode']) || $stkResult['ResponseCode'] !== "0") {
                $this->logger->error("STK push failed", $stkResult);
                return null;
            }

            $merchantRequestId = $stkResult['MerchantRequestID'] ?? null;
            $checkoutRequestId = $stkResult['CheckoutRequestID'] ?? null;

            // 🔹 Save pending transaction
            $data = [
                'client_id' => $clientId,
                'client_username' => $client['username'],
                'package_id' => $packageId,
                'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
                'amount' => $amount,
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'status' => 'Pending',
                'created_at' => date('Y-m-d H:i:s'),
                'phone' => $phone
            ];

            $this->transactionModel->insert($data);
            $this->logger->info("STK Push transaction saved", ['checkoutRequestID' => $checkoutRequestId]);

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
     * Handle M-PESA callback
     */
    public function handleCallback(array $callbackData): array
    {
        try {
            if (empty($callbackData['Body']['stkCallback'])) {
                $this->logger->error("Invalid callback payload", $callbackData);
                return ['ResultCode' => 1, 'ResultDesc' => 'Invalid payload'];
            }

            $callback = $callbackData['Body']['stkCallback'];
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
                return ['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'];
            }

            if ($resultCode !== 0) {
                $this->transactionModel->update($transaction['id'], [
                    'status' => 'Failed',
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                return ['ResultCode' => 0, 'ResultDesc' => 'Failure acknowledged'];
            }

            // Success
            $metadata = $callback['CallbackMetadata']['Item'] ?? [];
            $amount = $mpesaReceipt = $phone = $transactionDate = null;
            foreach ($metadata as $item) {
                switch ($item['Name'] ?? '') {
                    case 'Amount': $amount = $item['Value']; break;
                    case 'MpesaReceiptNumber': $mpesaReceipt = $item['Value']; break;
                    case 'PhoneNumber': $phone = $item['Value']; break;
                    case 'TransactionDate': $transactionDate = date('Y-m-d H:i:s', strtotime($item['Value'])); break;
                }
            }

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
            $this->logger->info("Transaction marked successful", ['transaction_id' => $transaction['id']]);

            return ['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'];
        } catch (\Throwable $e) {
            $this->logger->error("Exception in handleCallback", ['message' => $e->getMessage()]);
            return ['ResultCode' => 1, 'ResultDesc' => 'Internal error'];
        }
    }
}
