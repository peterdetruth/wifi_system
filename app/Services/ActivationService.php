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

    /**
     * Activate a subscription for a client after payment
     * Option B: create a new subscription record per payment
     *
     * @param int $clientId
     * @param int $packageId
     * @param int|null $paymentId
     * @return bool
     */
    public function activate(int $clientId, int $packageId, ?int $paymentId = null): bool
    {
        $db = $this->db;
        $db->transStart();

        try {
            $package = $db->table('packages')->where('id', $packageId)->get()->getRowArray();
            if (!$package) {
                $this->logger->error("Package not found during activation", ['packageId' => $packageId]);
                $db->transRollback();
                return false;
            }

            $startDate = date('Y-m-d H:i:s');
            $expiresOn = date('Y-m-d H:i:s', strtotime("+{$package['duration_length']} {$package['duration_unit']}"));

            // Create new subscription record
            $subscriptionData = [
                'client_id' => $clientId,
                'package_id' => $packageId,
                'payment_id' => $paymentId,
                'router_id' => $package['router_id'] ?? null,
                'start_date' => $startDate,
                'end_date' => $expiresOn,
                'expires_on' => $expiresOn,
                'status' => 'active'
            ];

            $db->table('subscriptions')->insert($subscriptionData);
            $subscriptionId = $db->insertID();

            $this->logger->info("Created new subscription record", [
                'subscription_id' => $subscriptionId,
                'client_id' => $clientId,
                'package_id' => $packageId,
                'payment_id' => $paymentId
            ]);

            // Router activation placeholder
            if (!empty($package['router_id'])) {
                $this->logger->debug("Router activation placeholder called", [
                    'subscription_id' => $subscriptionId,
                    'router_id' => $package['router_id'],
                    'package_id' => $packageId,
                    'package_name' => $package['name'],
                    'package_type' => $package['type']
                ]);

                // TODO: Replace this with real MikroTik activation logic
                $this->logger->info("Router activation placeholder succeeded", [
                    'subscription_id' => $subscriptionId,
                    'router_id' => $package['router_id']
                ]);
            }

            $db->transCommit();
            return true;

        } catch (\Throwable $e) {
            $db->transRollback();
            $this->logger->error("Activation error", [
                'message' => $e->getMessage(),
                'client_id' => $clientId,
                'package_id' => $packageId,
                'payment_id' => $paymentId
            ]);
            return false;
        }
    }
}
