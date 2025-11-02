<?php

namespace App\Controllers;

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

    protected $mpesaLogsModel;
    protected $mpesaTransactionModel;
    protected $paymentsModel;
    protected $transactionModel;
    protected $subscriptionModel;
    protected $packageModel;
    protected $clientModel;

    public function __construct()
    {
        $this->mpesaLogsModel       = new MpesaLogsModel();
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
            mpesa_debug("🔔 Incoming callback: " . json_encode($data));

            // Defensive checks
            if (empty($data['Body']['stkCallback'])) {
                mpesa_debug("❌ Invalid callback payload (no stkCallback key).");
                return $this->fail('Invalid callback payload');
            }

            $callback = $data['Body']['stkCallback'];
            $merchantRequestID  = $callback['MerchantRequestID'] ?? null;
            $checkoutRequestID  = $callback['CheckoutRequestID'] ?? null;
            $resultCode         = $callback['ResultCode'] ?? null;
            $resultDesc         = $callback['ResultDesc'] ?? null;

            mpesa_debug("📬 Callback IDs — Merchant: {$merchantRequestID}, Checkout: {$checkoutRequestID}, ResultCode: {$resultCode}");

            // Find pending transaction
            $transaction = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if (!$transaction) {
                mpesa_debug("⚠️ No pending transaction found for checkout ID {$checkoutRequestID}");
                return $this->respond(['ResultCode' => 1, 'ResultDesc' => 'Transaction not found'], 404);
            }

            // If failed
            if ($resultCode != 0) {
                mpesa_debug("❌ Payment failed for {$checkoutRequestID}: {$resultDesc}");
                $this->mpesaTransactionModel->update($transaction['id'], [
                    'status' => 'Failed',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'], 200);
            }

            // If success, extract metadata
            $metadata = $callback['CallbackMetadata']['Item'] ?? [];
            $amount = $mpesaReceipt = $phone = $transactionDate = null;

            foreach ($metadata as $item) {
                $name = $item['Name'];
                $value = $item['Value'] ?? null;
                mpesa_debug("🔸 Metadata: {$name} => {$value}");

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

            mpesa_debug("✅ Extracted — Amount: {$amount}, Receipt: {$mpesaReceipt}, Phone: {$phone}, Date: {$transactionDate}");

            if (empty($mpesaReceipt)) {
                mpesa_debug("❌ Missing MpesaReceiptNumber, aborting insert.");
                return $this->respond(['ResultCode' => 1, 'ResultDesc' => 'Missing MpesaReceiptNumber'], 400);
            }

            // Update transaction status
            $this->mpesaTransactionModel->update($transaction['id'], [
                'status' => 'Success',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            mpesa_debug("💾 Updated transaction #{$transaction['id']} to Success");

            // ✅ Fix: get client_id from $transaction instead of undefined variable
            $clientId = $transaction['client_id'];
            $packageId = $transaction['package_id'];

            // Create payment record
            $this->paymentsModel->insert([
                'client_id'        => $clientId,
                'package_id'       => $packageId,
                'amount'           => $amount,
                'phone'            => $phone,
                'mpesa_receipt'    => $mpesaReceipt,
                'transaction_date' => $transactionDate,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            mpesa_debug("✅ Payment saved successfully for {$mpesaReceipt}");
            mpesa_log("✅ Payment success — {$mpesaReceipt} | {$phone} | {$amount}");

            return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'], 200);
        } catch (\Throwable $e) {
            mpesa_debug("🔥 Exception: " . $e->getMessage());
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * 🧩 Helper to calculate expiry
     */
    private function calculateExpiry($length, $unit)
    {
        $unit = strtolower(trim($unit));
        switch ($unit) {
            case 'minute':
            case 'minutes':
                $interval = "+$length minutes";
                break;
            case 'hour':
            case 'hours':
                $interval = "+$length hours";
                break;
            case 'day':
            case 'days':
                $interval = "+$length days";
                break;
            case 'month':
            case 'months':
                $interval = "+$length months";
                break;
            default:
                $interval = "+$length days";
        }
        return date('Y-m-d H:i:s', strtotime($interval));
    }

    /**
     * 🧠 Debug Logger - Step-by-step visibility
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
