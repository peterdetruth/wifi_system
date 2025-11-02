<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use App\Models\MpesaLogsModel;
use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\TransactionModel;
use App\Models\SubscriptionModel;
use App\Models\PackageModel;
use App\Models\ClientModel;
use CodeIgniter\API\ResponseTrait;

class Mpesa extends BaseController
{
    use ResponseTrait;

    protected $db;
    protected $mpesaLogsModel;
    protected $mpesaTransactionModel;
    protected $paymentsModel;
    protected $transactionModel;
    protected $subscriptionModel;
    protected $packageModel;
    protected $clientModel;

    public function __construct()
    {
        // ✅ Initialize database connection
        $this->db = \Config\Database::connect();

        // ✅ Initialize models
        $this->mpesaLogsModel        = new MpesaLogsModel();
        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->paymentsModel         = new PaymentsModel();
        $this->transactionModel      = new TransactionModel();
        $this->subscriptionModel     = new SubscriptionModel();
        $this->packageModel          = new PackageModel();
        $this->clientModel           = new ClientModel();
    }

    /**
     * M-PESA callback endpoint
     */
    public function callback()
    {
        try {
            $data = $this->request->getJSON(true);
            $this->mpesa_debug("🔔 Incoming callback", $data);

            if (empty($data['Body']['stkCallback'])) {
                $this->mpesa_debug("❌ Invalid callback payload (no stkCallback key).");
                return $this->fail('Invalid callback payload');
            }

            $callback = $data['Body']['stkCallback'];
            $merchantRequestID  = $callback['MerchantRequestID'] ?? null;
            $checkoutRequestID  = $callback['CheckoutRequestID'] ?? null;
            $resultCode         = $callback['ResultCode'] ?? null;
            $resultDesc         = $callback['ResultDesc'] ?? null;

            $this->mpesa_debug("📬 Callback IDs", [
                'MerchantRequestID' => $merchantRequestID,
                'CheckoutRequestID' => $checkoutRequestID,
                'ResultCode' => $resultCode,
            ]);

            // ✅ Find transaction by CheckoutRequestID
            $transaction = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if (!$transaction) {
                $this->mpesa_debug("⚠️ No pending transaction found for checkout ID {$checkoutRequestID}");
                return $this->respond(['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'], 404);
            }

            // ❌ If failed
            if ($resultCode != 0) {
                $this->mpesa_debug("❌ Payment failed for {$checkoutRequestID}: {$resultDesc}");
                $this->mpesaTransactionModel->update($transaction['id'], [
                    'status' => 'Failed',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'], 200);
            }

            // ✅ If success, extract metadata
            $metadata = $callback['CallbackMetadata']['Item'] ?? [];
            $amount = $mpesaReceipt = $phone = $transactionDate = null;

            foreach ($metadata as $item) {
                $name = $item['Name'];
                $value = $item['Value'] ?? null;
                $this->mpesa_debug("🔸 Metadata: {$name} => {$value}");

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
                        $transactionDate = date('Y-m-d H:i:s', strtotime($value));
                        break;
                }
            }

            if (empty($mpesaReceipt)) {
                $this->mpesa_debug("❌ Missing MpesaReceiptNumber, aborting insert.");
                return $this->respond(['ResultCode' => 1, 'ResultDesc' => 'Missing MpesaReceiptNumber'], 400);
            }

            // ✅ Step 1: Duplicate Protection
            $existing = $this->paymentsModel
                ->where('mpesa_receipt', $mpesaReceipt)
                ->first();

            if ($existing) {
                $this->mpesa_debug("⚠️ Duplicate payment ignored for MpesaReceiptNumber: {$mpesaReceipt}");
                return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Duplicate ignored'], 200);
            }

            // ✅ Step 2: Update transaction status
            $this->mpesaTransactionModel->update($transaction['id'], [
                'status' => 'Success',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $this->mpesa_debug("💾 Updated transaction #{$transaction['id']} to Success");

            $clientId  = $transaction['client_id'];
            $packageId = $transaction['package_id'];

            // ✅ Step 3: Save payment
            $this->paymentsModel->insert([
                'client_id'        => $clientId,
                'package_id'       => $packageId,
                'amount'           => $amount,
                'phone'            => $phone,
                'mpesa_receipt'    => $mpesaReceipt,
                'transaction_date' => $transactionDate,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
            $this->mpesa_debug("✅ Payment saved successfully for {$mpesaReceipt}");

            // ✅ Step 4: Auto-activate package
            $trx = $this->db->table('mpesa_transactions')
                ->where('checkout_request_id', $checkoutRequestID)
                ->get()
                ->getRow();

            if ($trx) {
                $clientId  = $trx->client_id;
                $packageId = $trx->package_id;
                $amount    = $trx->amount;

                $package = $this->db->table('packages')->where('id', $packageId)->get()->getRow();

                if ($package) {
                    $duration = (int)$package->duration_length;
                    $unit     = strtolower($package->duration_unit);

                    $startDate = date('Y-m-d H:i:s');
                    $endDate   = match ($unit) {
                        'months'  => date('Y-m-d H:i:s', strtotime("+{$duration} months")),
                        'hours'   => date('Y-m-d H:i:s', strtotime("+{$duration} hours")),
                        'minutes' => date('Y-m-d H:i:s', strtotime("+{$duration} minutes")),
                        default   => date('Y-m-d H:i:s', strtotime("+{$duration} days")),
                    };

                    // Insert client package
                    $this->db->table('client_packages')->insert([
                        'client_id'  => $clientId,
                        'package_id' => $packageId,
                        'amount'     => $amount,
                        'start_date' => $startDate,
                        'end_date'   => $endDate,
                        'status'     => 'active'
                    ]);

                    // Update client to active
                    $this->db->table('clients')->where('id', $clientId)->update(['status' => 'active']);

                    $this->mpesa_debug("✅ Package {$package->name} activated for client #{$clientId}, valid until {$endDate}");

                    // ✅ Step 5: Send SMS notification
                    $client = $this->clientModel->find($clientId);
                    if ($client && !empty($client['phone'])) {
                        $smsMessage = "Dear {$client['full_name']}, your payment of KES {$amount} for {$package->name} has been received. Valid until {$endDate}. Thank you!";
                        $this->sendSms($client['phone'], $smsMessage);
                    }
                } else {
                    $this->mpesa_debug("❌ Package not found for ID: {$packageId}");
                }
            }

            $this->mpesa_debug("✅ Payment success — {$mpesaReceipt} | {$phone} | {$amount}");
            return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'], 200);

        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception: " . $e->getMessage());
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Send SMS notification (placeholder)
     */
    private function sendSms($phone, $message)
    {
        // Example stub — replace with your actual SMS provider
        $smsApiUrl = 'https://sms-provider.com/api/send';
        $payload = [
            'to'      => $phone,
            'message' => $message,
            'sender'  => 'WiFiSystem'
        ];

        $ch = curl_init($smsApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch);

        $this->mpesa_debug("📩 SMS sent to {$phone}: {$message}");
        return $response;
    }

    /**
     * Debug Logger
     */
    private function mpesa_debug($label, $data = null)
    {
        $msg = '[' . date('Y-m-d H:i:s') . "] $label";
        if ($data !== null) {
            $msg .= ' => ' . (is_array($data) ? json_encode($data) : $data);
        }
        log_message('info', $msg);
        file_put_contents(WRITEPATH . 'logs/mpesa_debug.log', $msg . PHP_EOL, FILE_APPEND);
    }
}
