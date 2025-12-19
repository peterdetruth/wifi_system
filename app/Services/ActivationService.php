<?php

namespace App\Services;

use Config\Database;

class ActivationService
{
    protected MpesaLogger $logger;
    protected LogService $logService;
    protected $db;

    public function __construct(
        ?MpesaLogger $logger = null,
        ?LogService $logService = null
    ) {
        $this->logger = $logger ?? new MpesaLogger();
        $this->logService = $logService ?? new LogService();
        $this->db = Database::connect();
    }

    /**
     * Activate a client after payment.
     * Queues router provisioning (no router calls here).
     */
    public function activate(
        int $clientId,
        int $packageId,
        ?int $paymentId = null,
        bool $createSubscription = false
    ): bool {
        $db = $this->db;
        $db->transStart();

        try {
            /* ===============================
             * Fetch package
             * =============================== */
            $package = $db->table('packages')->where('id', $packageId)->get()->getRowArray();

            if (!$package) {
                $this->logger->error('Package not found during activation', [
                    'package_id' => $packageId
                ]);

                $this->logService->error(
                    'Router',
                    'Package not found during activation',
                    ['package_id' => $packageId],
                    $clientId
                );

                $db->transRollback();
                return false;
            }

            /* ===============================
             * Optional subscription creation
             * =============================== */
            if ($createSubscription) {
                $startDate = date('Y-m-d H:i:s');
                $expiresOn = date(
                    'Y-m-d H:i:s',
                    strtotime("+{$package['duration_length']} {$package['duration_unit']}")
                );

                $db->table('subscriptions')->insert([
                    'client_id'  => $clientId,
                    'package_id' => $packageId,
                    'payment_id' => $paymentId,
                    'router_id'  => $package['router_id'] ?? null,
                    'start_date' => $startDate,
                    'end_date'   => $expiresOn,
                    'expires_on' => $expiresOn,
                    'status'     => 'active'
                ]);

                $subscriptionId = $db->insertID();

                $this->logger->info('Subscription created', [
                    'subscription_id' => $subscriptionId,
                    'client_id' => $clientId,
                    'package_id' => $packageId
                ]);

                $this->logService->info(
                    'Subscription',
                    'Subscription created after payment',
                    [
                        'subscription_id' => $subscriptionId,
                        'package_id' => $packageId
                    ],
                    $clientId
                );
            }

            /* ===============================
             * Queue router provisioning
             * =============================== */
            if (!empty($package['router_id'])) {

                $routerUsername = $this->getRouterUsername($clientId);

                $provisioningData = [
                    'client_id'       => $clientId,
                    'router_id'       => $package['router_id'],
                    'package_id'      => $packageId,
                    'service_type'    => $package['type'],
                    'router_username' => $routerUsername,
                    'router_password' => null,
                    'router_profile'  => $package['router_profile'],
                    'status'          => 'pending',
                    'last_error'      => null,
                ];

                $existingProvisioning = $db->table('router_provisionings')
                    ->where('router_id', $package['router_id'])
                    ->where('router_username', $routerUsername)
                    ->get()
                    ->getRowArray();

                if ($existingProvisioning) {

                    $this->logger->info('Router provisioning already exists', [
                        'provisioning_id' => $existingProvisioning['id'],
                        'router_id' => $package['router_id'],
                        'router_username' => $routerUsername
                    ]);

                    $this->logService->info(
                        'Router',
                        'Router provisioning already exists, skipping insert',
                        [
                            'provisioning_id' => $existingProvisioning['id'],
                            'router_id' => $package['router_id'],
                            'router_username' => $routerUsername
                        ],
                        $clientId
                    );
                } else {

                    $db->table('router_provisionings')->insert($provisioningData);
                    $provisioningId = $db->insertID();

                    if (!$provisioningId) {
                        $error = $db->error();

                        $this->logger->error('Router provisioning insert failed', $error);

                        $this->logService->error(
                            'Router',
                            'Failed to queue router provisioning',
                            $error,
                            $clientId
                        );

                        $db->transRollback();
                        return false;
                    }

                    $this->logger->info('Router provisioning queued', [
                        'provisioning_id' => $provisioningId,
                        'router_id' => $package['router_id']
                    ]);

                    $this->logService->info(
                        'Router',
                        'Router provisioning queued',
                        [
                            'provisioning_id' => $provisioningId,
                            'router_id' => $package['router_id'],
                            'router_profile' => $package['router_profile']
                        ],
                        $clientId
                    );
                }
            }

            $db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            $this->logger->error('Activation error', [
                'message' => $e->getMessage(),
                'client_id' => $clientId,
                'package_id' => $packageId,
                'payment_id' => $paymentId
            ]);

            $this->logService->error(
                'Router',
                'Activation failed due to exception',
                ['exception' => $e->getMessage()],
                $clientId
            );

            return false;
        }
    }

    /**
     * Router username = client.username
     */
    protected function getRouterUsername(int $clientId): string
    {
        $client = $this->db
            ->table('clients')
            ->select('username')
            ->where('id', $clientId)
            ->get()
            ->getRowArray();

        return $client['username'] ?? 'user_' . $clientId;
    }
}
