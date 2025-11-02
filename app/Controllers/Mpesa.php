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
        $this->mpesaLogsModel = new MpesaLogsModel();
        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->paymentsModel = new PaymentsModel();
        $this->transactionModel = new TransactionModel();
        $this->subscriptionModel = new SubscriptionModel();
        $this->packageModel = new PackageModel();
        $this->clientModel = new ClientModel();
    }

    /**
     * ✅ M-PESA Callback Handler
     */
    public function callback()
    {
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);

        // Always log raw callback first
        $this->mpesaLogsModel->insert([
            'raw_callback' => $rawData,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Basic validation
        if (!$data || !isset($data['Body']['stkCallback'])) {
            return $this->respond([
                'ResultCode' => 1,
                'ResultDesc' => 'Invalid Callback'
            ], 400);
        }

        $callback          = $data['Body']['stkCallback'];
        $resultCode        = $callback['ResultCode'];
        $merchantRequestID = $callback['MerchantRequestID'] ?? null;
        $checkoutRequestID = $callback['CheckoutRequestID'] ?? null;
        $resultDesc        = $callback['ResultDesc'] ?? '';

        // Handle only successful transactions
        if ($resultCode == 0) {
            $items = $callback['CallbackMetadata']['Item'] ?? [];

            $amount         = 0;
            $mpesaReceipt   = null;
            $phone          = null;
            $transactionDate = date('Y-m-d H:i:s');

            foreach ($items as $item) {
                switch ($item['Name']) {
                    case 'Amount':
                        $amount = $item['Value'];
                        break;
                    case 'MpesaReceiptNumber':
                        $mpesaReceipt = $item['Value'];
                        break;
                    case 'PhoneNumber':
                        $phone = $item['Value'];
                        break;
                    case 'TransactionDate':
                        $transactionDate = date(
                            'Y-m-d H:i:s',
                            strtotime($item['Value'])
                        );
                        break;
                }
            }

            // 🔹 Find the pending M-PESA transaction by checkout ID
            $mpesaTx = $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->first();

            if ($mpesaTx) {
                // 🔹 Update existing M-PESA transaction
                $this->mpesaTransactionModel->update($mpesaTx['id'], [
                    'status'              => 'Success',
                    'amount'              => $amount,
                    'transaction_id'      => $mpesaReceipt,
                    'merchant_request_id' => $merchantRequestID,
                    'checkout_request_id' => $checkoutRequestID,
                    'callback_raw'        => $rawData,
                    'completed_at'        => date('Y-m-d H:i:s'),
                    'phone'               => $phone,
                ]);

                $clientId  = $mpesaTx['client_id'];
                $packageId = $mpesaTx['package_id'];
                $package   = $this->packageModel->find($packageId);
                $client    = $this->clientModel->find($clientId);

                if ($package && $client) {
                    // 🔹 Calculate expiry
                    $expiryDate = $this->calculateExpiry(
                        $package['duration_length'],
                        $package['duration_unit']
                    );

                    // 🔹 Insert payment record
                    $this->paymentsModel->insert([
                        'client_id'       => $clientId,
                        'package_id'      => $packageId,
                        'amount'          => $amount,
                        'method'          => 'mpesa',
                        'transaction_code'=> $mpesaReceipt,
                        'phone'           => $phone,
                        'created_at'      => date('Y-m-d H:i:s'),
                    ]);

                    // 🔹 Create subscription record
                    $this->subscriptionModel->insert([
                        'client_id'  => $clientId,
                        'package_id' => $packageId,
                        'router_id'  => $package['router_id'],
                        'start_date' => date('Y-m-d H:i:s'),
                        'expires_on' => $expiryDate,
                        'status'     => 'active',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    // 🔹 Add record to transactions table
                    $this->transactionModel->insert([
                        'client_id'      => $clientId,
                        'package_type'   => $package['type'],
                        'package_length' => $package['duration_length'] . ' ' . $package['duration_unit'],
                        'package_id'     => $packageId,
                        'created_on'     => date('Y-m-d H:i:s'),
                        'expires_on'     => $expiryDate,
                        'method'         => 'mpesa',
                        'mpesa_code'     => $mpesaReceipt,
                        'router_id'      => $package['router_id'],
                        'router_status'  => 'active',
                        'status'         => 'success',
                        'amount'         => $amount,
                    ]);
                }
            }
        } else {
            // Mark failed if result code != 0
            $this->mpesaTransactionModel
                ->where('checkout_request_id', $checkoutRequestID)
                ->set([
                    'status'       => 'Failed',
                    'callback_raw' => $rawData,
                    'completed_at' => date('Y-m-d H:i:s'),
                ])
                ->update();
        }

        return $this->respond([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback Received Successfully',
        ]);
    }

    /**
     * Helper to calculate expiry based on package duration
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
}
