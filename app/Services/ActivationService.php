<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ActivationService
{
    protected BaseConnection $db;
    protected $logger;

    public function __construct()
    {
        $this->db     = \Config\Database::connect();
        $this->logger = service('logger');   // CI4 logger
    }

    /* ---------------------------------------------------------------
     * 🔹 Unified debug logger
     * ---------------------------------------------------------------- */
    private function log($message, $context = [])
    {
        if (!is_array($context)) {
            $context = ['value' => $context];
        }

        $this->logger->info("[ActivationService] {$message} " . json_encode($context, JSON_PRETTY_PRINT));
    }

    /* ---------------------------------------------------------------
     * 🔹 Helper: Log SQL safely
     * ---------------------------------------------------------------- */
    private function logQuery($action, $builder, $data = null)
    {
        $this->log($action, [
            'sql'      => $builder->getCompiledInsert($data ?? []) ??
                          $builder->getCompiledUpdate($data ?? []) ??
                          $builder->getCompiledSelect(),
            'bindings' => $data,
        ]);
    }

    /* ---------------------------------------------------------------
     * 🔹 Public method that controllers call
     * ---------------------------------------------------------------- */
    public function activate(int $clientId, int $packageId, float $amount): bool
    {
        $this->log("⚡ Activation requested", [
            'clientId'  => $clientId,
            'packageId' => $packageId,
            'amount'    => $amount
        ]);

        // Atomic DB transaction
        $this->db->transStart();

        try {
            // 1️⃣ Fetch the package
            $package = $this->fetchPackage($packageId);
            if (!$package) {
                $this->log("❌ Activation aborted — package not found", $packageId);
                return $this->fail();
            }

            // 2️⃣ Compute subscription validity dates
            [$startDate, $endDate] = $this->computeDuration($package);

            // 3️⃣ Check existing active subscription
            $active = $this->fetchActivePackage($clientId);

            if ($active) {
                if ((int)$active->package_id === $packageId) {
                    // Extend package
                    $this->extendPackage($active->id, $active->end_date, $package);
                    return $this->success();
                }

                // Otherwise expire old one
                $this->expirePackage($active->id);
            }

            // 4️⃣ Insert new subscription
            $this->insertNewPackage($clientId, $packageId, $amount, $startDate, $endDate);

            // 5️⃣ Reactivate client account
            $this->reactivateClient($clientId);

            return $this->success();

        } catch (\Throwable $e) {

            $this->log("🔥 Exception inside ActivationService", [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);

            return $this->fail();
        }
    }


    /* ========================================================================
     *  INTERNAL COMPONENTS — small, testable, reusable sub-operations
     * ======================================================================== */

    private function fetchPackage(int $packageId)
    {
        $builder = $this->db->table('packages')->where('id', $packageId);

        $this->logQuery("📥 SQL (Fetch package)", $builder);
        $row = $builder->get()->getRow();

        if (!$row) {
            $this->log("❌ Package not found", ['package_id' => $packageId]);
        }

        return $row;
    }


    private function computeDuration($package): array
    {
        $duration = (int)($package->duration_length ?? 1);
        $unit     = strtolower($package->duration_unit ?? 'days');

        $now       = new \DateTime();
        $startDate = $now->format('Y-m-d H:i:s');

        $endDate = match ($unit) {
            'months'  => (clone $now)->modify("+{$duration} months")->format('Y-m-d H:i:s'),
            'hours'   => (clone $now)->modify("+{$duration} hours")->format('Y-m-d H:i:s'),
            'minutes' => (clone $now)->modify("+{$duration} minutes")->format('Y-m-d H:i:s'),
            default   => (clone $now)->modify("+{$duration} days")->format('Y-m-d H:i:s'),
        };

        $this->log("⏳ Duration computed", [
            'start' => $startDate,
            'end'   => $endDate,
            'unit'  => $unit,
            'len'   => $duration
        ]);

        return [$startDate, $endDate];
    }


    private function fetchActivePackage(int $clientId)
    {
        $builder = $this->db->table('client_packages')
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->orderBy('end_date', 'DESC');

        $this->logQuery("📥 SQL (Fetch active package)", $builder, [$clientId]);

        return $builder->get()->getRow();
    }


    private function extendPackage(int $id, string $oldEnd, $package)
    {
        $duration = (int)$package->duration_length;
        $unit     = strtolower($package->duration_unit);

        $newEnd = (new \DateTime($oldEnd))
            ->modify("+{$duration} {$unit}")
            ->format('Y-m-d H:i:s');

        $builder = $this->db->table('client_packages')->where('id', $id);

        $this->logQuery("📝 SQL (Extend existing package)", $builder, [
            'end_date'   => $newEnd,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $builder->update([
            'end_date'   => $newEnd,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $this->dbErrorCheck("extend package");
        $this->log("🔄 Package extended", ['new_end' => $newEnd]);
    }


    private function expirePackage(int $id)
    {
        $builder = $this->db->table('client_packages')->where('id', $id);

        $this->logQuery("📝 SQL (Expire previous package)", $builder, ['status' => 'expired']);

        $builder->update(['status' => 'expired']);
        $this->dbErrorCheck("expire package");

        $this->log("🧹 Previous package expired", ['id' => $id]);
    }


    private function insertNewPackage($clientId, $packageId, $amount, $startDate, $endDate)
    {
        $data = [
            'client_id'  => $clientId,
            'package_id' => $packageId,
            'amount'     => $amount,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $builder = $this->db->table('client_packages');

        $this->logQuery("📝 SQL (Insert new package)", $builder, $data);
        $builder->insert($data);

        $this->dbErrorCheck("insert new active package");
        $this->log("📦 New package activated", $data);
    }


    private function reactivateClient(int $clientId)
    {
        $builder = $this->db->table('clients')->where('id', $clientId);

        $this->logQuery("📝 SQL (Reactivate client)", $builder, ['status' => 'active']);
        $builder->update(['status' => 'active']);

        $this->dbErrorCheck("reactivate client");
        $this->log("👤 Client reactivated", ['client_id' => $clientId]);
    }


    /* ========================================================================
     *  Transaction wrappers
     * ======================================================================== */

    private function success(): bool
    {
        if ($this->db->transStatus() === false) {
            $this->db->transRollback();
            $this->log("❌ Transaction rolled back (internal failure)");
            return false;
        }

        $this->db->transCommit();
        $this->log("🎉 Transaction committed — activation complete");
        return true;
    }

    private function fail(): bool
    {
        $this->db->transRollback();
        $this->log("❌ Activation aborted — transaction rolled back");
        return false;
    }


    /* ----------------------------------------------------------------------
     * 🔍 DB Error Checker with detailed logging
     * ---------------------------------------------------------------------- */
    private function dbErrorCheck(string $stage)
    {
        $err = $this->db->error();

        if ($err['code'] != 0) {
            $this->log("🔥 DB ERROR during {$stage}", $err);
            throw new \RuntimeException("DB ERROR ({$stage}): {$err['message']}");
        }
    }
}
