<?php

namespace App\Services;

use Config\Database;
use Throwable;

class RouterProvisioningService
{
    protected $db;
    protected LogService $logService;

    /**
     * Maximum number of provisioning attempts per job
     */
    const MAX_ATTEMPTS = 5;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->logService = new LogService();
    }

    /**
     * Run once (cron-safe).
     * Processes exactly ONE provisioning job.
     */
    public function runOnce(): void
    {
        $job = $this->claimJob();

        if (!$job) {
            return; // Nothing to process
        }

        try {
            $this->logService->info(
                'Router',
                'Provisioning started',
                ['provisioning_id' => $job['id']],
                $job['client_id']
            );

            // Dispatch provisioning
            $this->provision($job);

            // Mark success
            $this->markSuccess($job['id']);

            $this->logService->info(
                'Router',
                'Provisioning completed successfully',
                ['provisioning_id' => $job['id']],
                $job['client_id']
            );
        } catch (Throwable $e) {
            $this->markFailure($job['id'], $e->getMessage());

            $this->logService->error(
                'Router',
                'Provisioning failed',
                [
                    'provisioning_id' => $job['id'],
                    'error' => $e->getMessage()
                ],
                $job['client_id']
            );
        }
    }

    /**
     * Atomically claim ONE job for processing.
     */
    protected function claimJob(): ?array
    {
        $this->db->transStart();

        $job = $this->db->table('router_provisionings')
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts <', self::MAX_ATTEMPTS)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        if (!$job) {
            $this->db->transRollback();
            return null;
        }

        // Lock + increment attempts immediately (crash-safe)
        $this->db->table('router_provisionings')
            ->where('id', $job['id'])
            ->update([
                'status'     => 'processing',
                'attempts'   => $job['attempts'] + 1,
                'locked_at'  => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->db->transCommit();

        return $job;
    }

    /**
     * Dispatch provisioning based on service type.
     */
    protected function provision(array $job): void
    {
        switch ($job['service_type']) {
            case 'hotspot':
                $this->provisionHotspot($job);
                break;

            case 'pppoe':
                $this->provisionPPPoE($job);
                break;

            default:
                throw new \RuntimeException(
                    'Unsupported service type: ' . $job['service_type']
                );
        }
    }

    /**
     * Hotspot provisioning stub (NO router calls yet)
     */
    protected function provisionHotspot(array $job): void
    {
        $this->logService->debug(
            'Router',
            'Hotspot provisioning stub executed',
            [
                'router_id' => $job['router_id'],
                'username'  => $job['router_username'],
                'profile'   => $job['router_profile']
            ],
            $job['client_id']
        );

        // DB-level duplicate protection (already provisioned)
        if ($this->isDuplicateUser($job)) {
            throw new \RuntimeException('Router user already exists');
        }

        // STUB SUCCESS — real MikroTik API logic comes later
    }

    /**
     * PPPoE provisioning stub (NO router calls yet)
     */
    protected function provisionPPPoE(array $job): void
    {
        $this->logService->debug(
            'Router',
            'PPPoE provisioning stub executed',
            [
                'router_id' => $job['router_id'],
                'username'  => $job['router_username'],
                'profile'   => $job['router_profile']
            ],
            $job['client_id']
        );

        // STUB SUCCESS
    }

    /**
     * Mark provisioning as successful.
     */
    protected function markSuccess(int $id): void
    {
        $this->db->table('router_provisionings')
            ->where('id', $id)
            ->update([
                'status'     => 'completed',
                'last_error' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Mark provisioning as failed.
     */
    protected function markFailure(int $id, string $error): void
    {
        $this->db->table('router_provisionings')
            ->where('id', $id)
            ->update([
                'status'     => 'failed',
                'last_error' => $error,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Detect duplicate router user already provisioned successfully.
     */
    protected function isDuplicateUser(array $job): bool
    {
        return $this->db->table('router_provisionings')
            ->where('router_id', $job['router_id'])
            ->where('router_username', $job['router_username'])
            ->where('status', 'completed')
            ->countAllResults() > 0;
    }
}
