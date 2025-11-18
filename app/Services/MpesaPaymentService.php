<?php

namespace App\Services;

use App\Models\PaymentsModel;
use Config\Database;

class MpesaPaymentService
{
    protected PaymentsModel $paymentsModel;
    protected MpesaLogger $logger;
    protected $db;

    public function __construct(MpesaLogger $logger)
    {
        $this->paymentsModel = new PaymentsModel();
        $this->logger = $logger;
        $this->db = Database::connect();
    }

    public function createOrUpdatePayment(array $data): bool
    {
        $existing = null;
        if (!empty($data['mpesa_receipt_number'])) {
            $existing = $this->paymentsModel
                ->where('mpesa_receipt_number', $data['mpesa_receipt_number'])
                ->first();

            if ($existing) {
                $this->logger->debug("Duplicate payment detected", ['mpesa_receipt' => $data['mpesa_receipt_number']]);
                return true;
            }
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->paymentsModel->insert($data);
        $err = $this->db->error();
        if ($err['code'] !== 0) {
            $this->logger->error("DB Error inserting payment", $err);
            return false;
        }

        $this->logger->debug("Payment record inserted", ['insert_id' => $this->paymentsModel->getInsertID()]);
        return true;
    }

    public function updatePayment(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->paymentsModel->update($id, $data);
        $err = $this->db->error();
        if ($err['code'] !== 0) {
            $this->logger->error("DB Error updating payment", $err);
            return false;
        }

        $this->logger->debug("Payment updated", ['id' => $id]);
        return true;
    }

    public function findByTransactionId(int $transactionId)
    {
        return $this->paymentsModel->where('mpesa_transaction_id', $transactionId)->first();
    }
}
