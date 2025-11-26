<?php

namespace App\Services;

use App\Models\PackageModel;
use Config\Database;

/**
 * ActivationService
 *
 * Responsible for activating subscriptions after payment.
 * - Creates a new subscription record for each payment (Option B).
 * - Computes start_date, end_date, expires_on based on package duration.
 * - Marks any existing active subscription for the same client+package as expired (keeps history).
 * - Contains a placeholder for router activation (MikroTik) so you can plug in real router logic later.
 */
class ActivationService
{
    protected MpesaLogger $logger;
    protected $db;
    protected PackageModel $packageModel;

    /**
     * Constructor accepts an optional MpesaLogger.
     */
    public function __construct(?MpesaLogger $logger = null)
    {
        $this->logger = $logger ?? new MpesaLogger();
        $this->db = Database::connect();
        $this->packageModel = new PackageModel();
    }

    /**
     * Activate a subscription for a client and package (called after payment).
     *
     * @param int $clientId
     * @param int $packageId
     * @param float $amount   (kept for compatibility, not required for activation computation)
     * @return bool true on success, false on failure
     */
    public function activate(int $clientId, int $packageId, float $amount): bool
    {
        $now = new \DateTime('now');

        // Load package to determine duration
        $package = $this->packageModel->find($packageId);
        if (!$package) {
            $this->logger->error("Activation failed - package not found", ['package_id' => $packageId, 'client_id' => $clientId]);
            return false;
        }

        // Determine duration values (defaults if missing)
        $durationLength = (int)($package['duration_length'] ?? 0);
        $durationUnit = $package['duration_unit'] ?? 'days';

        if ($durationLength <= 0) {
            // If no duration is set, default to 30 days (sensible fallback) but log it
            $this->logger->info("Package duration missing or zero; defaulting to 30 days", ['package_id' => $packageId]);
            $durationLength = 30;
            $durationUnit = 'days';
        }

        // Compute end_date and expires_on
        $startDate = $now->format('Y-m-d H:i:s');

        // Create DateInterval spec by mapping duration_unit to period spec
        $intervalSpec = $this->buildIntervalSpec($durationLength, $durationUnit);
        if ($intervalSpec === null) {
            $this->logger->error("Invalid duration_unit for package", ['package_id' => $packageId, 'duration_unit' => $durationUnit]);
            return false;
        }

        try {
            $endDt = (clone $now)->add(new \DateInterval($intervalSpec));
        } catch (\Throwable $e) {
            $this->logger->error("Failed to compute end date interval", ['exception' => $e->getMessage(), 'package_id' => $packageId]);
            return false;
        }

        $endDate = $endDt->format('Y-m-d H:i:s');
        $expiresOn = $endDate; // keep semantics consistent with your table

        $routerId = $package['router_id'] ?? null;

        // Start DB transaction
        $this->db->transStart();
        try {
            // 1) Expire any existing active subscriptions for this client+package
            //    (keeps history but ensures a single active subscription)
            $subscriptionsTable = $this->db->table('subscriptions');
            $existingActive = $subscriptionsTable
                ->where('client_id', $clientId)
                ->where('package_id', $packageId)
                ->where('status', 'active')
                ->get()
                ->getResultArray();

            if (!empty($existingActive)) {
                foreach ($existingActive as $sub) {
                    $subscriptionsTable->where('id', $sub['id'])
                        ->update([
                            'status' => 'expired',
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                }
                $this->logger->info("Expired existing active subscriptions for client+package", ['client_id' => $clientId, 'package_id' => $packageId, 'count' => count($existingActive)]);
            }

            // 2) Insert new subscription record (history preserved)
            $newSubData = [
                'client_id'   => $clientId,
                'package_id'  => $packageId,
                'router_id'   => $routerId,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'expires_on'  => $expiresOn,
                'status'      => 'active',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ];

            $insertId = $subscriptionsTable->insert($newSubData);
            $dbErr = $this->db->error();
            if ($dbErr['code'] !== 0) {
                // DB error during insert
                $this->db->transRollback();
                $this->logger->error("DB error inserting new subscription", $dbErr);
                return false;
            }

            $subscriptionId = is_numeric($insertId) ? (int)$insertId : $this->db->insertID();

            $this->logger->info("Created new subscription record", [
                'subscription_id' => $subscriptionId,
                'client_id' => $clientId,
                'package_id' => $packageId,
                'start_date' => $startDate,
                'expires_on' => $expiresOn
            ]);

            // 3) Placeholder: Attempt to activate on router (MikroTik)
            //    This currently logs and returns true. Replace or extend activateOnRouter()
            //    with your router integration (API, RouterOS, etc.) when ready.
            if ($routerId) {
                try {
                    $routerActivated = $this->activateOnRouter($subscriptionId, $routerId, $package);
                    if ($routerActivated) {
                        $this->logger->info("Router activation placeholder succeeded", ['subscription_id' => $subscriptionId, 'router_id' => $routerId]);
                    } else {
                        // Router activation failed but subscription is still created.
                        // We log and continue (admin may reconcile).
                        $this->logger->error("Router activation placeholder failed", ['subscription_id' => $subscriptionId, 'router_id' => $routerId]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("Exception while attempting router activation", ['exception' => $e->getMessage()]);
                }
            } else {
                $this->logger->info("No router assigned for package (skipping router activation)", ['package_id' => $packageId]);
            }

            $this->db->transComplete();
            return true;

        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->logger->error("Exception during activation transaction", ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Build a DateInterval spec string from duration length and unit.
     * Returns interval spec (e.g. 'P3D' or 'PT5H') or null on invalid unit.
     *
     * Supported units: minutes, hours, days, months
     */
    protected function buildIntervalSpec(int $length, string $unit): ?string
    {
        $length = max(1, $length);
        $unit = strtolower(trim($unit));

        switch ($unit) {
            case 'minutes':
                // PT{n}M
                return 'PT' . $length . 'M';
            case 'hours':
                // PT{n}H
                return 'PT' . $length . 'H';
            case 'days':
                // P{n}D
                return 'P' . $length . 'D';
            case 'months':
                // P{n}M (months supported by DateInterval 'P{n}M')
                return 'P' . $length . 'M';
            default:
                return null;
        }
    }

    /**
     * Placeholder router activation method.
     *
     * Replace this with real router integration (MikroTik API / RouterOS / RouterOS-API).
     *
     * Expected behavior when implementing:
     *  - Create/enable PPPoE or Hotspot user on the router
     *  - Apply bandwidth/limits from the package
     *  - Map the router response (success/failure) back to the subscription
     *
     * For now, this method:
     *  - Logs what would be done
     *  - Returns true (simulating success)
     *
     * @param int $subscriptionId
     * @param int $routerId
     * @param array $package
     * @return bool
     */
    protected function activateOnRouter(int $subscriptionId, int $routerId, array $package): bool
    {
        // TODO: Implement real MikroTik/router activation here.
        // Example steps you might take:
        //  - Fetch router credentials from routers table
        //  - Connect via RouterOS API or SSH or REST (vendor-specific)
        //  - Create user with username derived from client record or subscription
        //  - Set bandwidth and burst rules from $package fields
        //  - Store router-side identifier on the subscription (extend subscriptions table if needed)
        //
        // For now, just log everything and pretend it succeeded.

        $this->logger->debug("Router activation placeholder called", [
            'subscription_id' => $subscriptionId,
            'router_id' => $routerId,
            'package_id' => $package['id'] ?? null,
            'package_name' => $package['name'] ?? null,
            'package_type' => $package['type'] ?? null
        ]);

        // Simulate a small delay if desired (avoid sleep on production)
        // usleep(100000);

        // Return true to indicate router activation succeeded (so subscription activation flow continues)
        return true;
    }
}
