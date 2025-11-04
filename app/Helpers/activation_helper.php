<?php

use CodeIgniter\Database\Exceptions\DataException;

if (!function_exists('activateClientPackage')) {
    /**
     * Safely activates a client's package in a DB transaction.
     *
     * @param int    $clientId
     * @param int    $packageId
     * @param float  $amount
     * @return bool
     */
    function activateClientPackage(int $clientId, int $packageId, float $amount): bool
    {
        $db = \Config\Database::connect();
        $logger = service('logger');
        $db->transBegin();

        try {
            // ✅ Fetch the package details
            $package = $db->table('packages')->where('id', $packageId)->get()->getRow();
            if (!$package) {
                $logger->error("❌ Package not found for ID {$packageId}");
                $db->transRollback();
                return false;
            }

            $duration = (int)$package->duration_length;
            $unit = strtolower($package->duration_unit);

            $startDate = date('Y-m-d H:i:s');
            $endDate = match ($unit) {
                'months'  => date('Y-m-d H:i:s', strtotime("+{$duration} months")),
                'hours'   => date('Y-m-d H:i:s', strtotime("+{$duration} hours")),
                'minutes' => date('Y-m-d H:i:s', strtotime("+{$duration} minutes")),
                default   => date('Y-m-d H:i:s', strtotime("+{$duration} days")),
            };

            // ✅ Deactivate existing active packages for the client
            $db->table('client_packages')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            // ✅ Insert the new active package
            $db->table('client_packages')->insert([
                'client_id'  => $clientId,
                'package_id' => $packageId,
                'amount'     => $amount,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // ✅ Update client status to active
            $db->table('clients')->where('id', $clientId)->update(['status' => 'active']);

            // ✅ Commit transaction
            $db->transCommit();
            $logger->info("✅ Package {$package->name} activated for Client #{$clientId} until {$endDate}");
            return true;
        } catch (DataException $e) {
            $db->transRollback();
            $logger->error("🔥 DB Error during activation: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $db->transRollback();
            $logger->error("🔥 Exception during activation: " . $e->getMessage());
            return false;
        }
    }
}
