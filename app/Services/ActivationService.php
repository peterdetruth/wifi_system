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
     * Create a router provisioning request after successful payment
     *
     * @param int $clientId
     * @param int $packageId
     * @param int|null $paymentId
     * @return bool
     */
    public function activate(int $clientId, int $packageId, ?int $paymentId = null): bool
    {
        $this->db->transStart();

        try {
            // 1️⃣ Load package
            $package = $this->db->table('packages')
                ->where('id', $packageId)
                ->get()
                ->getRowArray();

            if (!$package) {
                $this->logger->error('Activation failed: Package not found', [
                    'package_id' => $packageId
                ]);
                $this->db->transRollback();
                return false;
            }

            // 2️⃣ Validate router requirement
            if (empty($package['router_id'])) {
                $this->logger->error('Activation failed: Package has no router assigned', [
                    'package_id' => $packageId
                ]);
                $this->db->transRollback();
                return false;
            }

            // 3️⃣ Create router provisioning request
            $provisioningData = [
                'client_id'      => $clientId,
                'package_id'     => $packageId,
                'router_id'      => $package['router_id'],
                'router_profile' => $package['router_profile'],
                'payment_id'     => $paymentId,
                'status'         => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ];

            $this->db->table('router_provisionings')->insert($provisioningData);

            $provisioningId = $this->db->insertID();

            $this->logger->info('Router provisioning queued', [
                'provisioning_id' => $provisioningId,
                'client_id'       => $clientId,
                'package_id'      => $packageId,
                'router_id'       => $package['router_id'],
                'router_profile'  => $package['router_profile']
            ]);

            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();

            $this->logger->error('Activation error', [
                'message'     => $e->getMessage(),
                'client_id'   => $clientId,
                'package_id'  => $packageId,
                'payment_id'  => $paymentId
            ]);

            return false;
        }
    }
}
