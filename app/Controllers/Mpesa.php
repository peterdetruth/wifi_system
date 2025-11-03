<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\PackageModel;
use App\Models\ClientModel;
use CodeIgniter\API\ResponseTrait;

class Mpesa extends BaseController
{
    use ResponseTrait;

    protected $db;
    protected $mpesaTransactionModel;
    protected $paymentsModel;
    protected $packageModel;
    protected $clientModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();

        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->paymentsModel         = new PaymentsModel();
        $this->packageModel          = new PackageModel();
        $this->clientModel           = new ClientModel();
    }

    /**
     * 🔹 1. Called when client initiates payment (STK Push request)
     * Auto-inserts transaction record with `Pending` status.
     */
    public function initiateTransaction($clientId, $packageId, $amount, $phone, $merchantRequestId, $checkoutRequestId)
    {
        try {
            // Check for duplicates first
            $existing = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestId)
                ->first();

            if ($existing) {
                $this->mpesa_debug("⚠️ Transaction already exists for checkoutRequestId {$checkoutRequestId}");
                return true;
            }

            // Insert new record
            $this->mpesaTransactionModel->insert([
                'client_id'           => $clientId,
                'package_id'          => $packageId,
                'amount'              => $amount,
                'phone'               => $phone,
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'status'              => 'Pending',
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

            $this->mpesa_debug("🧾 New transaction initiated for Client {$clientId}, Package {$packageId}");
            return true;
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 initiateTransaction() error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔹 2. Callback from M-PESA once user approves/rejects STK push.
     */
    public function callback()
    {
        try {
            /** @var \CodeIgniter\HTTP\IncomingRequest $request */
            $request = $this->request;
            $data = $request->getJSON(true);
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
                'ResultCode'        => $resultCode,
            ]);

            // Look up transaction
            $transaction = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            // Auto-create placeholder if not found
            if (!$transaction) {
                $this->mpesa_debug("⚠️ No existing transaction found, attempting safe insert for {$checkoutRequestID}");
                $transaction = $this->safeInsertTransaction($merchantRequestID, $checkoutRequestID);
                if (!$transaction) {
                    $this->mpesa_debug("❌ Transaction could not be created — see DB error above");
                    return $this->failServerError("Transaction could not be created");
                }
            }

            if (!$transaction) {
                $this->mpesa_debug("❌ Could not create or retrieve transaction for {$checkoutRequestID}");
                return $this->failServerError('Transaction could not be created');
            }

            // Handle failed payments
            if ($resultCode != 0) {
                $this->mpesaTransactionModel->update($transaction['id'], [
                    'status'     => 'Failed',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $this->mpesa_debug("❌ Payment failed for {$checkoutRequestID} — {$resultDesc}");
                return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'], 200);
            }

            // Safely parse metadata
            $metadata = $callback['CallbackMetadata']['Item'] ?? [];
            $amount = $mpesaReceipt = $phone = $transactionDate = null;

            if (is_array($metadata)) {
                foreach ($metadata as $item) {
                    $name  = $item['Name'] ?? null;
                    $value = $item['Value'] ?? null;
                    if ($name) {
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
                                $transactionDate = $value
                                    ? date('Y-m-d H:i:s', strtotime($value))
                                    : null;
                                break;
                        }
                    }
                }
            }

            if (!$mpesaReceipt) {
                $this->mpesa_debug("❌ Missing MpesaReceiptNumber — aborting.");
                return $this->respond(['ResultCode' => 1, 'ResultDesc' => 'Missing MpesaReceiptNumber'], 400);
            }

            // Duplicate protection (check by mpesa_receipt_number)
            $exists = $this->paymentsModel->where('mpesa_receipt_number', $mpesaReceipt)->first();
            if ($exists) {
                $this->mpesa_debug("⚠️ Duplicate payment ignored for {$mpesaReceipt}");
                return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Duplicate ignored'], 200);
            }

            // Update transaction info safely
            $this->mpesaTransactionModel->update($transaction['id'], [
                'amount'                => $amount ?? $transaction['amount'],
                'phone_number'          => $phone ?? $transaction['phone_number'],
                'mpesa_receipt_number'  => $mpesaReceipt,
                'transaction_date'      => $transactionDate ?? $transaction['transaction_date'],
                'status'                => 'Success',
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);
            $this->mpesa_debug("💾 Transaction updated to Success — ID {$transaction['id']}");

            // Save payment safely
            $this->paymentsModel->insert([
                'mpesa_transaction_id' => $transaction['id'],
                'client_id'            => $transaction['client_id'] ?? null,
                'package_id'           => $transaction['package_id'] ?? null,
                'amount'               => $amount,
                'phone'                => $phone,
                'mpesa_receipt_number' => $mpesaReceipt,
                'transaction_date'     => $transactionDate,
                'created_at'           => date('Y-m-d H:i:s'),
            ]);
            $this->mpesa_debug("✅ Payment saved successfully for {$mpesaReceipt}");

            // Auto-activate package
            if (!empty($transaction['package_id']) && !empty($transaction['client_id'])) {
                $this->activateClientPackage(
                    $transaction['client_id'],
                    $transaction['package_id'],
                    $amount
                );
                $this->mpesa_debug("✅ Auto-activation completed for client #{$transaction['client_id']}");
            } else {
                $this->mpesa_debug("⚠️ Skipped auto-activation — missing client_id or package_id");
            }

            $this->mpesa_debug("✅ Payment success — {$mpesaReceipt} | {$phone} | {$amount}");
            return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'], 200);
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception in callback: " . $e->getMessage());
            return $this->failServerError($e->getMessage());
        }
    }

    private function safeInsertTransaction($merchantRequestID, $checkoutRequestID)
    {
        try {
            // Check if it already exists
            $existing = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if ($existing) {
                $this->mpesa_debug("ℹ️ Transaction already exists, returning existing record.");
                return $existing;
            }

            // Generate a fallback transaction_id if required by validation
            $fallbackTransactionId = 'TEMP_' . substr(md5($checkoutRequestID . microtime()), 0, 8);

            // Prepare minimal data (ensure required fields exist)
            $data = [
                'transaction_id'       => $fallbackTransactionId, // safe fallback
                'merchant_request_id'  => $merchantRequestID ?? 'N/A',
                'checkout_request_id'  => $checkoutRequestID ?? 'N/A',
                'amount'               => 0, // safe default until actual amount is parsed
                'status'               => 'Pending',
                'result_code'          => null,
                'result_desc'          => null,
                'mpesa_receipt_number' => null,
                'transaction_date'     => null,
                'phone_number'         => null,
                'created_at'           => date('Y-m-d H:i:s'),
            ];

            // Insert safely
            if (!$this->mpesaTransactionModel->insert($data)) {
                $dbError = $this->mpesaTransactionModel->errors();
                $errorMessage = json_encode($dbError ?: ['error' => 'Unknown DB insert error']);
                $this->mpesa_debug("💣 DB Insert Error => {$errorMessage}");
                return null;
            }

            // Retrieve inserted record
            $newTransaction = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if ($newTransaction) {
                $this->mpesa_debug("💾 Transaction safely created => ID {$newTransaction['id']}");
                return $newTransaction;
            }

            $this->mpesa_debug("⚠️ Insert did not throw error but record not found — possible DB issue.");
            return null;
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception in safeInsertTransaction: " . $e->getMessage());
            return null;
        }
    }


    /**
     * 🔹 3. Auto-activate client package after successful payment
     */
    private function activateClientPackage($clientId, $packageId, $amount)
    {
        try {
            // 1️⃣ Validate package
            $package = $this->db->table('packages')->where('id', $packageId)->get()->getRow();
            if (!$package) {
                $this->mpesa_debug("❌ Package not found for ID {$packageId}");
                return false;
            }

            // 2️⃣ Calculate duration based on unit
            $duration = (int)($package->duration_length ?? 1);
            $unit = strtolower($package->duration_unit ?? 'days');

            $now = new \DateTime();
            $startDate = $now->format('Y-m-d H:i:s');

            // Default duration
            $endDate = match ($unit) {
                'months'  => $now->modify("+{$duration} months")->format('Y-m-d H:i:s'),
                'hours'   => (new \DateTime())->modify("+{$duration} hours")->format('Y-m-d H:i:s'),
                'minutes' => (new \DateTime())->modify("+{$duration} minutes")->format('Y-m-d H:i:s'),
                default   => (new \DateTime())->modify("+{$duration} days")->format('Y-m-d H:i:s'),
            };

            // 3️⃣ Check if user already has an active package of same type
            $active = $this->db->table('client_packages')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->orderBy('end_date', 'DESC')
                ->get()
                ->getRow();

            if ($active) {
                // If same package, extend it
                if ($active->package_id == $packageId) {
                    $this->mpesa_debug("🔄 Extending existing package for Client #{$clientId}");
                    $newEnd = (new \DateTime($active->end_date))->modify("+{$duration} {$unit}")->format('Y-m-d H:i:s');
                    $this->db->table('client_packages')
                        ->where('id', $active->id)
                        ->update([
                            'end_date'   => $newEnd,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    $this->mpesa_debug("✅ Extended until {$newEnd}");
                    return true;
                }

                // Otherwise, mark old one expired
                $this->db->table('client_packages')
                    ->where('id', $active->id)
                    ->update(['status' => 'expired']);
                $this->mpesa_debug("🧹 Expired previous package (ID {$active->id})");
            }

            // 4️⃣ Create new active record
            $this->db->table('client_packages')->insert([
                'client_id'  => $clientId,
                'package_id' => $packageId,
                'amount'     => $amount,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 5️⃣ Reactivate client account if needed
            $this->db->table('clients')->where('id', $clientId)->update(['status' => 'active']);

            $this->mpesa_debug("✅ Package {$package->name} activated for Client #{$clientId}, valid until {$endDate}");
            return true;
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Error in activateClientPackage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🧠 Debug logger helper
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
