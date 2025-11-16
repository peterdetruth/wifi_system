<?php

namespace App\Controllers;

use App\Services\ActivationService;
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
     * Initiate a transaction record when an STK Push is started.
     * Ensures column names use phone_number for mpesa_transactions.
     */
    public function initiateTransaction($clientId, $packageId, $amount, $phone, $merchantRequestId, $checkoutRequestId)
    {
        try {
            // Check duplicates
            $existing = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestId)
                ->first();

            $this->mpesa_debug("🔎 initiateTransaction() checking existing transaction", [
                'checkout_request_id' => $checkoutRequestId,
                'found' => $existing ? true : false
            ]);

            if ($existing) {
                return true;
            }

            $data = [
                'client_id'           => $clientId,
                'package_id'          => $packageId,
                'amount'              => $amount,
                'phone_number'        => $phone,
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'status'              => 'Pending',
                'created_at'          => date('Y-m-d H:i:s'),
            ];

            $this->mpesa_debug("📝 initiateTransaction() insert data", $data);

            $inserted = $this->mpesaTransactionModel->insert($data);
            $dbErr = $this->db->error();

            if ($dbErr['code'] !== 0) {
                $this->mpesa_debug("❌ DB Error inserting mpesa_transaction", $dbErr);
                return false;
            }

            $this->mpesa_debug("🧾 New mpesa_transaction inserted", [
                'insert_id' => $this->mpesaTransactionModel->getInsertID(),
                'checkout_request_id' => $checkoutRequestId
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception in initiateTransaction()", [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);
            return false;
        }
    }
    /**
     * Callback endpoint for STK Push results from Safaricom.
     * Handles success/failure, updates mpesa_transactions, inserts payments,
     * and triggers ActivationService for auto-activation.
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
                'merchant_request_id' => $merchantRequestID,
                'checkout_request_id' => $checkoutRequestID,
                'result_code'         => $resultCode,
            ]);

            // Find existing mpesa_transaction by checkout_request_id
            $transaction = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if (!$transaction) {
                $this->mpesa_debug("⚠️ No transaction found for checkout_request_id, attempting safe insert", [
                    'merchant_request_id' => $merchantRequestID,
                    'checkout_request_id' => $checkoutRequestID
                ]);

                $transaction = $this->safeInsertTransaction($merchantRequestID, $checkoutRequestID);

                if (!$transaction) {
                    $this->mpesa_debug("❌ safeInsertTransaction failed - cannot proceed");
                    return $this->failServerError("Transaction could not be created");
                }
            }

            // If result code indicates failure from M-PESA -> mark transaction failed
            if ((int)$resultCode !== 0) {
                $updateData = [
                    'status'     => 'Failed',
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $this->mpesaTransactionModel->update($transaction['id'], $updateData);
                $dbErr = $this->db->error();
                if ($dbErr['code'] !== 0) {
                    $this->mpesa_debug("❌ DB Error updating mpesa_transaction (Failed)", $dbErr);
                } else {
                    $this->mpesa_debug("❌ Payment marked Failed for transaction", [
                        'transaction_id' => $transaction['id'],
                        'checkout_request_id' => $checkoutRequestID,
                        'result_desc' => $resultDesc
                    ]);
                }

                return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged failure'], 200);
            }

            // Parse callback metadata carefully
            $metadata = $callback['CallbackMetadata']['Item'] ?? [];
            $amount = null;
            $mpesaReceipt = null;
            $phone = null;
            $transactionDate = null;

            if (is_array($metadata)) {
                foreach ($metadata as $item) {
                    $name  = $item['Name'] ?? null;
                    $value = $item['Value'] ?? null;
                    if (!$name) continue;

                    $this->mpesa_debug("🔸 Metadata item", [$name => $value]);

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
                            // Safely parse numeric transaction date (e.g., 20251116185233)
                            if ($value) {
                                // If it's an integer-like timestamp returned by Safaricom, parse accordingly
                                $transactionDate = $this->parseMpesaDate($value);
                            }
                            break;
                    }
                }
            }

            $this->mpesa_debug("🔎 Parsed metadata", [
                'amount' => $amount,
                'mpesa_receipt' => $mpesaReceipt,
                'phone' => $phone,
                'transaction_date' => $transactionDate
            ]);

            if (empty($mpesaReceipt)) {
                $this->mpesa_debug("❌ Missing MpesaReceiptNumber in callback - aborting");
                return $this->respond(['ResultCode' => 1, 'ResultDesc' => 'Missing MpesaReceiptNumber'], 400);
            }

            // Duplicate protection: payments table
            $existingPayment = $this->paymentsModel->where('mpesa_receipt_number', $mpesaReceipt)->first();
            if ($existingPayment) {
                $this->mpesa_debug("⚠️ Duplicate payment detected - ignoring", ['mpesa_receipt' => $mpesaReceipt]);
                return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Duplicate ignored'], 200);
            }

            // Try to resolve client & package mapping by phone
            $mapping = $this->resolveClientPackageMapping($phone);
            $clientId = $mapping['client_id'] ?? ($transaction['client_id'] ?? null);
            $packageId = $mapping['package_id'] ?? ($transaction['package_id'] ?? null);

            // Strong defensive copy: if transaction row still has null client/package but mapping is present, use mapping.
            if (empty($clientId) && !empty($transaction['client_id'])) {
                $clientId = $transaction['client_id'];
            }
            if (empty($packageId) && !empty($transaction['package_id'])) {
                $packageId = $transaction['package_id'];
            }

            // Update the mpesa_transactions row
            $updateTx = [
                'amount'               => $amount ?? $transaction['amount'],
                'phone_number'         => $phone ?? $transaction['phone_number'],
                'mpesa_receipt_number' => $mpesaReceipt,
                'transaction_date'     => $transactionDate ?? $transaction['transaction_date'],
                'client_id'            => $clientId,
                'package_id'           => $packageId,
                'status'               => 'Success',
                'result_code'          => 0,
                'result_desc'          => $resultDesc,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            $this->mpesa_debug("📝 Updating mpesa_transaction to success", [
                'transaction_id' => $transaction['id'],
                'update' => $updateTx
            ]);

            $this->mpesaTransactionModel->update($transaction['id'], $updateTx);
            $dbErr = $this->db->error();
            if ($dbErr['code'] !== 0) {
                $this->mpesa_debug("❌ DB Error updating mpesa_transaction (success)", $dbErr);
                // don't abort yet — try to still insert payment record (helps recovery)
            } else {
                $this->mpesa_debug("💾 mpesa_transaction updated", ['transaction_id' => $transaction['id']]);
            }

            // Insert into payments table
            $paymentData = [
                'mpesa_transaction_id' => $transaction['id'],
                'client_id'            => $clientId,
                'package_id'           => $packageId,
                'amount'               => $amount,
                'phone'                => $phone,
                'mpesa_receipt_number' => $mpesaReceipt,
                'transaction_date'     => $transactionDate,
                'created_at'           => date('Y-m-d H:i:s'),
            ];

            $this->mpesa_debug("📝 Inserting payments record", $paymentData);
            $this->paymentsModel->insert($paymentData);
            $dbErr = $this->db->error();
            if ($dbErr['code'] !== 0) {
                $this->mpesa_debug("❌ DB Error inserting payment record", $dbErr);
                // Still respond 200 to Safaricom to avoid retries; but flag internally.
            } else {
                $this->mpesa_debug("✅ Payment record saved", ['mpesa_receipt' => $mpesaReceipt]);
            }

            // Auto-activate using ActivationService (centralized)
            if (!empty($clientId) && !empty($packageId)) {
                try {
                    $this->mpesa_debug("⚡ Triggering ActivationService", [
                        'clientId'  => $clientId,
                        'packageId' => $packageId,
                        'amount'    => $amount
                    ]);
                    $activationService = new ActivationService();
                    $activated = $activationService->activate((int)$clientId, (int)$packageId, (float)$amount);

                    if ($activated) {
                        $this->mpesa_debug("✅ ActivationService succeeded", ['clientId' => $clientId, 'packageId' => $packageId]);
                    } else {
                        $this->mpesa_debug("⚠️ ActivationService returned false", ['clientId' => $clientId, 'packageId' => $packageId]);
                    }
                } catch (\Throwable $e) {
                    $this->mpesa_debug("🔥 ActivationService exception", [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }
            } else {
                $this->mpesa_debug("⚠️ Skipping activation - missing clientId or packageId", [
                    'clientId' => $clientId,
                    'packageId' => $packageId
                ]);
            }

            $this->mpesa_debug("✅ Callback processing finished successfully", [
                'mpesa_receipt' => $mpesaReceipt,
                'checkout_request_id' => $checkoutRequestID
            ]);

            return $this->respond(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully'], 200);
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception in callback()", [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * If a transaction doesn't exist for a callback, create a safe placeholder.
     * Ensures we use phone_number field for mpesa_transactions and logs DB errors.
     */
    private function safeInsertTransaction($merchantRequestID, $checkoutRequestID)
    {
        try {
            $existing = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if ($existing) {
                $this->mpesa_debug("ℹ️ safeInsertTransaction found existing", ['checkout_request_id' => $checkoutRequestID]);
                return $existing;
            }

            $fallbackTransactionId = 'TEMP_' . substr(md5($checkoutRequestID . microtime()), 0, 8);

            $data = [
                'transaction_id'       => $fallbackTransactionId,
                'merchant_request_id'  => $merchantRequestID ?? 'N/A',
                'checkout_request_id'  => $checkoutRequestID ?? 'N/A',
                'amount'               => 0,
                'phone_number'         => null,
                'status'               => 'Pending',
                'result_code'          => null,
                'result_desc'          => null,
                'mpesa_receipt_number' => null,
                'transaction_date'     => null,
                'created_at'           => date('Y-m-d H:i:s'),
            ];

            $this->mpesa_debug("📝 safeInsertTransaction inserting", $data);
            $this->mpesaTransactionModel->insert($data);
            $dbErr = $this->db->error();

            if ($dbErr['code'] !== 0) {
                $this->mpesa_debug("❌ DB Error in safeInsertTransaction", $dbErr);
                return null;
            }

            $new = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if ($new) {
                $this->mpesa_debug("💾 safeInsertTransaction created record", ['id' => $new['id']]);
                return $new;
            }

            $this->mpesa_debug("⚠️ safeInsertTransaction insert did not return a record (unexpected)");
            return null;
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception in safeInsertTransaction()", [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * Resolve client and default package from phone number (if possible)
     * Returns ['client_id' => int|null, 'package_id' => int|null] or null
     */
    private function resolveClientPackageMapping($phone): ?array
    {
        try {
            if (empty($phone)) {
                $this->mpesa_debug("ℹ️ resolveClientPackageMapping: no phone provided");
                return null;
            }

            $this->mpesa_debug("🔎 resolveClientPackageMapping checking DB for phone", ['phone' => $phone]);

            $client = $this->clientModel
                ->select('id, default_package_id')
                ->where('phone', $phone)
                ->first();

            if (!$client) {
                $this->mpesa_debug("ℹ️ No client found for phone", ['phone' => $phone]);
                return null;
            }

            $this->mpesa_debug("✅ resolveClientPackageMapping found client", $client);
            return [
                'client_id' => $client['id'] ?? null,
                'package_id' => $client['default_package_id'] ?? null
            ];
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception in resolveClientPackageMapping()", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * Parse Safaricom transaction date (various formats)
     * Accepts either an integer like 20251116185233 or a normal timestamp string.
     */
    private function parseMpesaDate($val)
    {
        try {
            // If integer-looking value like 20251116185233
            if (is_numeric($val) && strlen((string)$val) >= 14) {
                // Format: YYYYMMDDhhmmss
                $s = (string)$val;
                $dt = \DateTime::createFromFormat('YmdHis', substr($s, 0, 14));
                if ($dt !== false) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }

            // Fallback: try strtotime
            $ts = @strtotime($val);
            if ($ts !== false) {
                return date('Y-m-d H:i:s', $ts);
            }

            return null;
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 parseMpesaDate exception", ['value' => $val, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Debug logger helper
     * Writes to CI logger and to writable/logs/mpesa_debug.log for quick file debugging.
     */
    private function mpesa_debug($label, $data = null)
    {
        $payload = $data !== null ? (is_array($data) ? json_encode($data) : $data) : '';
        $msg = '[' . date('Y-m-d H:i:s') . "] $label" . ($payload ? " => $payload" : '');
        // CI logger
        log_message('info', $msg);
        // Extra file
        file_put_contents(WRITEPATH . 'logs/mpesa_debug.log', $msg . PHP_EOL, FILE_APPEND);
    }

    /**
     * Safely activate a client’s package after successful payment
     * ----------------------------------------------------------
     * Handles:
     *   - Transaction wrapping (commit/rollback)
     *   - Validation of client + package existence
     *   - Insertion into client_packages (or update if already active)
     *   - Logging all steps
     */
    private function safeActivateClientPackage(int $clientId, int $packageId, float $amount): bool
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $builder = $db->table('subscriptions');
            $existing = $builder->where('client_id', $clientId)
                ->where('package_id', $packageId)
                ->get()
                ->getRowArray();

            if ($existing) {
                $builder->where('id', $existing['id'])
                    ->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                $builder->insert([
                    'client_id' => $clientId,
                    'package_id' => $packageId,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            $this->mpesa_debug("💣 Activation error: " . $e->getMessage());
            return false;
        }
    }


    /**
     * 🔹 Auto-activate client package after successful payment
     *     - Fully modernized
     *     - Logs every query + bindings
     *     - Logs DB errors
     */
    private function activateClientPackage($clientId, $packageId, $amount)
    {
        $this->mpesa_debug("⚙️ activateClientPackage() called", [
            'clientId'  => $clientId,
            'packageId' => $packageId,
            'amount'    => $amount
        ]);

        try {

            /* ---------------------------------------------------------
         * 1️⃣ Fetch package
         * --------------------------------------------------------- */
            $builder = $this->db->table('packages')->where('id', $packageId);

            $this->mpesa_debug("📥 SQL (Fetch package):", [
                'query'    => $builder->getCompiledSelect(),
                'bindings' => [$packageId]
            ]);

            $package = $builder->get()->getRow();

            if (!$package) {
                $this->mpesa_debug("❌ Package not found", ['packageId' => $packageId]);
                return false;
            }

            /* ---------------------------------------------------------
         * 2️⃣ Compute subscription duration
         * --------------------------------------------------------- */
            $duration = (int)($package->duration_length ?? 1);
            $unit     = strtolower($package->duration_unit ?? 'days');

            $now       = new \DateTime();
            $startDate = $now->format('Y-m-d H:i:s');

            $endDate = match ($unit) {
                'months'  => (clone $now)->modify("+{$duration} months")->format('Y-m-d H:i:s'),
                'hours'   => (clone $now)->modify("+{$duration} hours")->format('Y-m-d H:i:s'),
                'minutes' => (clone $now)->modify("+{$duration} minutes")->format('Y-m-d H:i:s'),
                default   => (clone $now)->modify("+{$duration} days")->format('Y-m-d H:i:s'),
            };

            /* ---------------------------------------------------------
         * 3️⃣ Check for current active subscription
         * --------------------------------------------------------- */
            $builder = $this->db->table('client_packages')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->orderBy('end_date', 'DESC');

            $this->mpesa_debug("📥 SQL (Check active package):", [
                'query'    => $builder->getCompiledSelect(),
                'bindings' => [$clientId]
            ]);

            $active = $builder->get()->getRow();

            /* ---------------------------------------------------------
         * 4️⃣ If active package exists
         * --------------------------------------------------------- */
            if ($active) {

                // Extend same package
                if ($active->package_id == $packageId) {

                    $this->mpesa_debug("🔄 Extending existing package", [
                        'existing_end' => $active->end_date,
                        'unit'         => $unit,
                        'duration'     => $duration
                    ]);

                    $newEnd = (new \DateTime($active->end_date))
                        ->modify("+{$duration} {$unit}")
                        ->format('Y-m-d H:i:s');

                    $updateBuilder = $this->db->table('client_packages')
                        ->where('id', $active->id);

                    $this->mpesa_debug("📝 SQL (Extend package):", [
                        'query'    => $updateBuilder->getCompiledUpdate(),
                        'bindings' => [$active->id]
                    ]);

                    $updateBuilder->update([
                        'end_date'   => $newEnd,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    $err = $this->db->error();
                    if ($err['code'] != 0) {
                        $this->mpesa_debug("❌ DB ERROR (extend package)", $err);
                        return false;
                    }

                    $this->mpesa_debug("✅ Package extended", ['new_end' => $newEnd]);
                    return true;
                }

                // Else expire the old package
                $expireBuilder = $this->db->table('client_packages')
                    ->where('id', $active->id);

                $this->mpesa_debug("📝 SQL (Expire old package):", [
                    'query'    => $expireBuilder->getCompiledUpdate(),
                    'bindings' => [$active->id]
                ]);

                $expireBuilder->update(['status' => 'expired']);

                $err = $this->db->error();
                if ($err['code'] != 0) {
                    $this->mpesa_debug("❌ DB ERROR (expire old package)", $err);
                    return false;
                }

                $this->mpesa_debug("🧹 Previous package expired", ['expired_id' => $active->id]);
            }

            /* ---------------------------------------------------------
         * 5️⃣ Insert new active package
         * --------------------------------------------------------- */
            $insertData = [
                'client_id'  => $clientId,
                'package_id' => $packageId,
                'amount'     => $amount,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $insertBuilder = $this->db->table('client_packages');

            $this->mpesa_debug("📝 SQL (Insert new package):", [
                'query'    => $insertBuilder->getCompiledInsert($insertData),
                'bindings' => $insertData
            ]);

            $insertBuilder->insert($insertData);

            $err = $this->db->error();
            if ($err['code'] != 0) {
                $this->mpesa_debug("❌ DB ERROR (insert package)", $err);
                return false;
            }

            /* ---------------------------------------------------------
         * 6️⃣ Reactivate client account
         * --------------------------------------------------------- */
            $clientBuilder = $this->db->table('clients')->where('id', $clientId);

            $this->mpesa_debug("📝 SQL (Reactivate client):", [
                'query'    => $clientBuilder->getCompiledUpdate(),
                'bindings' => [$clientId]
            ]);

            $clientBuilder->update(['status' => 'active']);

            $err = $this->db->error();
            if ($err['code'] != 0) {
                $this->mpesa_debug("❌ DB ERROR (reactivate client)", $err);
                return false;
            }

            /* ---------------------------------------------------------
         * FINAL SUCCESS
         * --------------------------------------------------------- */
            $this->mpesa_debug("🎉 Package activated successfully", [
                'clientId'  => $clientId,
                'package'   => $package->name,
                'valid_to'  => $endDate
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->mpesa_debug("🔥 Exception in activateClientPackage()", [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile()
            ]);
            return false;
        }
    }
}
