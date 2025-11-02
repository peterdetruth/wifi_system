<?php
namespace App\Libraries;

use App\Models\PackageModel;
use App\Models\ClientModel;
use App\Models\MpesaTransactionModel;
use App\Models\TransactionModel;
use App\Models\SubscriptionModel;
use App\Models\VoucherModel;
use App\Helpers\ReceiptHelper;

class PaymentFlow
{
    protected $packageModel;
    protected $clientModel;
    protected $mpesaTxModel;
    protected $txModel;
    protected $subModel;
    protected $voucherModel;

    public function __construct()
    {
        $this->packageModel   = new PackageModel();
        $this->clientModel    = new ClientModel();
        $this->mpesaTxModel   = new MpesaTransactionModel();
        $this->txModel        = new TransactionModel();
        $this->subModel       = new SubscriptionModel();
        $this->voucherModel   = new VoucherModel();
    }

    /**
     * Run full flow:
     * 1) Validate inputs
     * 2) If voucher -> redeem flow
     * 3) Else -> initiate STK push (or simulate), create mpesa_tx + tx record (pending)
     * 4) Return structured status
     *
     * $opts array expects:
     *  - client_id, package_id, phone, voucher_code (optional), simulate(boolean)
     */
    public function run(array $opts): array
    {
        try {
            // Step 0: basic input validation
            if (empty($opts['client_id']) || empty($opts['package_id'])) {
                return $this->fail('validation', 'Missing client_id or package_id');
            }

            $client = $this->clientModel->find($opts['client_id']);
            if (! $client) return $this->fail('validation', 'Client not found');

            $package = $this->packageModel->find($opts['package_id']);
            if (! $package) return $this->fail('validation', 'Package not found');

            $phone = trim($opts['phone'] ?? ($client['phone'] ?? ''));
            if (empty($phone) && empty($opts['voucher_code'])) {
                // phone only required for mpesa flow
                return $this->fail('validation', 'Phone number required for payments');
            }

            // Calculate expiry from package duration fields
            $expiry = $this->calculateExpiry($package['duration_length'], $package['duration_unit']);

            // Step 1: Voucher flow (immediate activation)
            if (!empty($opts['voucher_code'])) {
                $voucher = $this->voucherModel->isValidVoucher($opts['voucher_code']);
                if (! $voucher) return $this->fail('voucher', 'Invalid or expired voucher');

                // create subscription
                $subData = [
                    'client_id'  => $client['id'],
                    'package_id' => $package['id'],
                    'router_id'  => $package['router_id'],
                    'start_date' => date('Y-m-d H:i:s'),
                    'expires_on' => $expiry,
                    'status'     => 'active'
                ];
                $subId = $this->subModel->insert($subData);

                // mark voucher used
                $this->voucherModel->markAsUsed($opts['voucher_code'], $client['id']);

                // log transaction (voucher)
                $txId = $this->txModel->insert([
                    'client_id'      => $client['id'],
                    'package_type'   => $package['type'],
                    'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
                    'package_id'     => $package['id'],
                    'created_on'     => date('Y-m-d H:i:s'),
                    'expires_on'     => $expiry,
                    'method'         => 'voucher',
                    'mpesa_code'     => $opts['voucher_code'],
                    'router_id'      => $package['router_id'],
                    'status'         => 'success',
                    'amount'         => 0,
                ]);

                // optional: generate receipt (simulated)
                try {
                    $pdf = ReceiptHelper::generate($txId);
                    ReceiptHelper::sendEmail($client['id'], $pdf);
                } catch (\Throwable $e) {
                    // don't fail the whole flow for receipt problems; just log
                    log_message('warning', 'Receipt generation failed: ' . $e->getMessage());
                }

                return $this->success('voucher', 'Voucher redeemed and subscription activated', [
                    'subscription_id' => $subId,
                    'transaction_id'  => $txId,
                    'expires_on'      => $expiry,
                ]);
            }

            // Step 2: Payment (STK)
            // If simulate flag set, we will not call Daraja
            $simulate = !empty($opts['simulate']);

            // Create mpesa transaction record (pending) BEFORE initiating STK so we have an id
            $mpesaTxData = [
                'client_id'       => $client['id'],
                'client_username' => $client['username'] ?? null,
                'package_id'      => $package['id'],
                'package_length'  => $package['duration_length'] . ' ' . $package['duration_unit'],
                'amount'          => (float) $package['price'],
                'phone'           => $phone,
                'status'          => 'pending',
                'created_at'      => date('Y-m-d H:i:s'),
            ];
            $mpesaTxId = $this->mpesaTxModel->insert($mpesaTxData);

            // If simulation requested -> mark tx success immediately (for dev)
            if ($simulate) {
                // create transaction + subscription
                $mpesaCode = 'SIM' . strtoupper(bin2hex(random_bytes(4)));

                $this->mpesaTxModel->update($mpesaTxId, [
                    'transaction_id' => $mpesaCode,
                    'status'         => 'success',
                    'completed_at'   => date('Y-m-d H:i:s'),
                    'callback_raw'   => json_encode(['simulated' => true]),
                ]);

                $txId = $this->txModel->insert([
                    'client_id'      => $client['id'],
                    'package_type'   => $package['type'],
                    'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
                    'package_id'     => $package['id'],
                    'created_on'     => date('Y-m-d H:i:s'),
                    'expires_on'     => $expiry,
                    'method'         => 'mpesa',
                    'mpesa_code'     => $mpesaCode,
                    'router_id'      => $package['router_id'],
                    'status'         => 'success',
                    'amount'         => (float)$package['price'],
                ]);

                $subId = $this->subModel->insert([
                    'client_id'  => $client['id'],
                    'package_id' => $package['id'],
                    'router_id'  => $package['router_id'],
                    'start_date' => date('Y-m-d H:i:s'),
                    'expires_on' => $expiry,
                    'status'     => 'active'
                ]);

                return $this->success('simulate', 'Simulated payment completed', [
                    'mpesa_tx_id' => $mpesaTxId,
                    'transaction_id' => $txId,
                    'subscription_id' => $subId,
                ]);
            }

            // IMPORTANT: real STK initiation handled by caller (controller) because it needs Daraja config + redirect/wait UX.
            // But we supply the mpesaTxId so the controller can store CheckoutRequestID/MerchantRequestID against it.
            return $this->success('initiate_stk', 'Ready to initiate STK push', [
                'mpesa_tx_id' => $mpesaTxId,
                'amount'      => (int) round($package['price']), // ensure integer
                'phone'       => $phone,
                'package'     => $package,
                'client'      => $client,
                'expires_on'  => $expiry,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'PaymentFlow::run error: ' . $e->getMessage());
            return $this->fail('exception', 'Unexpected error: ' . $e->getMessage());
        }
    }

    protected function success($stage, $message, $data = [])
    {
        return [
            'success' => true,
            'stage'   => $stage,
            'message' => $message,
            'data'    => $data
        ];
    }

    protected function fail($stage, $message, $data = [])
    {
        return [
            'success' => false,
            'stage'   => $stage,
            'message' => $message,
            'data'    => $data
        ];
    }

    private function calculateExpiry($length, $unit)
    {
        $unit = strtolower(trim($unit));
        switch ($unit) {
            case 'minutes':
            case 'minute':
                $interval = "+$length minutes";
                break;
            case 'hours':
            case 'hour':
                $interval = "+$length hours";
                break;
            case 'days':
            case 'day':
                $interval = "+$length days";
                break;
            case 'months':
            case 'month':
                $interval = "+$length months";
                break;
            default:
                $interval = "+$length days";
        }

        return date('Y-m-d H:i:s', strtotime($interval));
    }
}
