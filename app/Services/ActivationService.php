<?php

namespace App\Services;

use Config\Database;

class ActivationService
{
    protected MpesaLogger $logger;
    protected $db;

    public function __construct(?MpesaLogger $logger = null)
    {
        $this->logger = $logger ?? new MpesaLogger();
        $this->db = Database::connect();
    }

    public function activate(int $clientId, int $packageId, float $amount): bool
    {
        $db = $this->db;
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
            $this->logger->debug("Subscription activated", ['clientId' => $clientId, 'packageId' => $packageId]);
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            $this->logger->error("Activation error", ['message' => $e->getMessage()]);
            return false;
        }
    }
}
