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
     * Activate a subscription for a client after payment.
     * Optionally creates a subscription record (can be disabled if subscriptions are managed separately).
     *
     * @param int $clientId
     * @param int $packageId
     * @param int|null $paymentId
     * @param bool $createSubscription
     * @return bool
     */
    public function activate(int $clientId, int $packageId, ?int $paymentId = null, bool $createSubscription = false): bool
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

            $subscriptionId = null;
            if ($createSubscription) {
                // Optional subscription creation
                $subscriptionData = [
                    'client_id'  => $clientId,
                    'package_id' => $packageId,
                    'payment_id' => $paymentId,
                    'router_id'  => $package['router_id'] ?? null,
                    'start_date' => $startDate,
                    'end_date'   => $expiresOn,
                    'expires_on' => $expiresOn,
                    'status'     => 'active'
                ];

                $db->table('subscriptions')->insert($subscriptionData);
                $subscriptionId = $db->insertID();

                $this->logger->info("Created new subscription record", [
                    'subscription_id' => $subscriptionId,
                    'client_id' => $clientId,
                    'package_id' => $packageId,
                    'payment_id' => $paymentId
                ]);
            }

            // Queue router provisioning
            if (!empty($package['router_id'])) {
                $provisioningData = [
                    'client_id'       => $clientId,
                    'router_id'       => $package['router_id'],
                    'package_id'      => $packageId,
                    'service_type'    => $package['type'],
                    'router_username' => $this->getRouterUsername($clientId),
                    'router_password' => null, // Will be set during real provisioning
                    'router_profile'  => $package['router_profile'],
                    'status'          => 'pending',
                    'last_error'      => null,
                ];

                $provisioningInsert = $db->table('router_provisionings')->insert($provisioningData);
                $provisioningId = $db->insertID();

                if ($provisioningInsert && $provisioningId) {
                    $this->logger->info("Router provisioning queued", [
                        'provisioning_id' => $provisioningId,
                        'client_id'       => $clientId,
                        'package_id'      => $packageId,
                        'router_id'       => $package['router_id'],
                        'router_profile'  => $package['router_profile']
                    ]);
                } else {
                    $this->logger->error("Failed to queue router provisioning", [
                        'client_id' => $clientId,
                        'package_id' => $packageId,
                        'router_id' => $package['router_id']
                    ]);
                }
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

    /**
     * Generate router username based on client ID or other logic.
     *
     * @param int $clientId
     * @return string
     */
    protected function getRouterUsername(int $clientId): string
    {
        // Assuming client username is used for router username
        $client = $this->db->table('clients')->where('id', $clientId)->get()->getRowArray();
        return $client['username'] ?? 'user_' . $clientId;
    }
}
