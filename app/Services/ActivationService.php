<?php

namespace App\Services;

use Config\Database;

class ActivationService
{
    protected $db;
    protected MpesaLogger $logger;
    protected LogService $logService;

    public function __construct(
        ?MpesaLogger $logger = null,
        ?LogService $logService = null
    ) {
        $this->db = Database::connect();
        $this->logger = $logger ?? new MpesaLogger();
        $this->logService = $logService ?? new LogService();
    }

    /* =====================================================
     * PAYMENT / MPESA ACTIVATION
     * ===================================================== */
    public function activate(
        int $clientId,
        int $packageId,
        ?int $paymentId = null,
        bool $createSubscription = false
    ): bool {
        $db = $this->db;
        $db->transStart();

        try {
            /* -------------------------------
             * Fetch package
             * ------------------------------- */
            $package = $db->table('packages')
                ->where('id', $packageId)
                ->get()
                ->getRowArray();

            if (!$package) {
                $this->logService->error(
                    'Activation',
                    'Package not found during activation',
                    ['package_id' => $packageId],
                    $clientId
                );

                $db->transRollback();
                return false;
            }

            /* -------------------------------
             * Optional subscription creation
             * ------------------------------- */
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
                    'status'     => 'active',
                    'created_at' => $startDate,
                    'updated_at' => $startDate
                ]);
            }

            /* -------------------------------
             * Queue router provisioning
             * ------------------------------- */
            if (!empty($package['router_id'])) {

                $routerUsername = $this->getRouterUsername($clientId);

                $exists = $db->table('router_provisionings')
                    ->where([
                        'router_id'       => $package['router_id'],
                        'router_username' => $routerUsername
                    ])
                    ->get()
                    ->getRowArray();

                if (!$exists) {
                    $db->table('router_provisionings')->insert([
                        'client_id'       => $clientId,
                        'router_id'       => $package['router_id'],
                        'package_id'      => $packageId,
                        'service_type'    => $package['type'],
                        'router_username' => $routerUsername,
                        'router_password' => null,
                        'router_profile'  => $package['router_profile'],
                        'status'          => 'pending',
                        'created_at'      => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            $this->logger->error('Activation exception', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /* =====================================================
     * USERNAME + PASSWORD ACTIVATION (ADMIN ISSUED)
     * ===================================================== */
    public function activateUsingUsername(string $username, string $password): array
    {
        $db = $this->db;
        $db->transStart();

        try {
            /* -------------------------------
             * Fetch unused credentials
             * ------------------------------- */
            $cred = $db->table('client_activation_credentials')
                ->where([
                    'username'       => $username,
                    'password_plain' => $password,
                    'status'         => 'unused'
                ])
                ->get()
                ->getRowArray();

            if (!$cred) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password.'
                ];
            }

            /* -------------------------------
             * Fetch package
             * ------------------------------- */
            $package = $db->table('packages')
                ->where('id', $cred['package_id'])
                ->get()
                ->getRowArray();

            if (!$package) {
                return [
                    'success' => false,
                    'message' => 'Package not found.'
                ];
            }

            $now = date('Y-m-d H:i:s');
            $expiresOn = date(
                'Y-m-d H:i:s',
                strtotime("+{$package['duration_length']} {$package['duration_unit']}")
            );

            /* -------------------------------
             * Create subscription
             * ------------------------------- */
            $db->table('subscriptions')->insert([
                'client_id'  => $cred['client_id'],
                'package_id' => $cred['package_id'],
                'router_id'  => $package['router_id'] ?? null,
                'status'     => 'active',
                'start_date' => $now,
                'expires_on' => $expiresOn,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            /* -------------------------------
             * Mark credential as used
             * ------------------------------- */
            $db->table('client_activation_credentials')
                ->where('id', $cred['id'])
                ->update([
                    'status'     => 'used',
                    'start_date' => $now,
                    'expires_on' => $expiresOn,
                    'updated_at' => $now
                ]);

            /* -------------------------------
             * Queue router provisioning
             * ------------------------------- */
            if (!empty($package['router_id'])) {
                $db->table('router_provisionings')->insert([
                    'client_id'       => $cred['client_id'],
                    'router_id'       => $package['router_id'],
                    'package_id'      => $cred['package_id'],
                    'service_type'    => $package['type'],
                    'router_username' => $this->getRouterUsername($cred['client_id']),
                    'router_profile'  => $package['router_profile'],
                    'status'          => 'pending',
                    'created_at'      => $now
                ]);
            }

            $db->transCommit();

            $this->logService->info(
                'Activation',
                'Activated using username/password',
                ['package_id' => $cred['package_id']],
                $cred['client_id']
            );

            return [
                'success'    => true,
                'message'    => 'Activation successful!',
                'expires_on' => $expiresOn
            ];
        } catch (\Throwable $e) {
            $db->transRollback();

            $this->logger->error('Username activation failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Activation failed. Please try again.'
            ];
        }
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */
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
