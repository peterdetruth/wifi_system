<?php

namespace App\Services;

use App\Models\MpesaTransactionModel;
use Config\Database;

class MpesaTransactionService
{
    protected MpesaTransactionModel $transactionModel;
    protected MpesaLogger $logger;
    protected $db;

    public function __construct(MpesaLogger $logger)
    {
        $this->transactionModel = new MpesaTransactionModel();
        $this->logger = $logger;
        $this->db = Database::connect();
    }

    public function createTransaction(array $data): bool
    {
        $existing = $this->transactionModel
            ->where('checkout_request_id', $data['checkout_request_id'])
            ->first();

        if ($existing) {
            $this->logger->debug("Transaction already exists", ['checkout_request_id' => $data['checkout_request_id']]);
            return true;
        }

        $data['status'] = 'Pending';
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->transactionModel->insert($data);
        $err = $this->db->error();

        if ($err['code'] !== 0) {
            $this->logger->error("DB Error inserting transaction", $err);
            return false;
        }

        $this->logger->debug("New mpesa_transaction inserted", ['insert_id' => $this->transactionModel->getInsertID()]);
        return true;
    }

    public function updateTransaction(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->transactionModel->update($id, $data);

        $err = $this->db->error();
        if ($err['code'] !== 0) {
            $this->logger->error("DB Error updating transaction", $err);
            return false;
        }

        $this->logger->debug("Transaction updated", ['id' => $id]);
        return true;
    }

    public function findByCheckoutRequestId(string $checkoutRequestId)
    {
        return $this->transactionModel
            ->where('checkout_request_id', $checkoutRequestId)
            ->first();
    }

    public function safeInsertTransaction(string $merchantRequestID, string $checkoutRequestID)
    {
        $existing = $this->findByCheckoutRequestId($checkoutRequestID);
        if ($existing) return $existing;

        $data = [
            'transaction_id'       => 'TEMP_' . substr(md5($checkoutRequestID . microtime()), 0, 8),
            'merchant_request_id'  => $merchantRequestID ?? 'N/A',
            'checkout_request_id'  => $checkoutRequestID ?? 'N/A',
            'amount'               => 0,
            'phone_number'         => null,
            'status'               => 'Pending',
            'result_code'          => null,
            'result_desc'          => null,
            'mpesa_receipt_number' => null,
            'transaction_date'     => null,
            'created_at'           => date('Y-m-d H:i:s')
        ];

        $this->transactionModel->insert($data);
        $err = $this->db->error();
        if ($err['code'] !== 0) {
            $this->logger->error("DB Error in safeInsertTransaction", $err);
            return null;
        }

        return $this->findByCheckoutRequestId($checkoutRequestID);
    }
}
