<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\SubscriptionModel;
use App\Libraries\RouterService;

/**
 * Command: php spark subscriptions:check
 *
 * Automatically disables expired subscriptions in DB and RouterOS.
 */
class CheckExpiredSubscriptions extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'subscriptions:check';
    protected $description = 'Checks for expired subscriptions and disables them on the router.';

    public function run(array $params)
    {
        CLI::write("🔄 Checking for expired subscriptions...", 'yellow');

        $subscriptionModel = new SubscriptionModel();
        $routerService = new RouterService(simulate: false); // set to false for live mode

        // Get all active subscriptions that are past expiry
        $expired = $subscriptionModel
            ->where('status', 'active')
            ->where('expires_on <', date('Y-m-d H:i:s'))
            ->findAll();

        if (empty($expired)) {
            CLI::write("✅ No expired subscriptions found.", 'green');
            return;
        }

        CLI::write("⚠️ Found " . count($expired) . " expired subscriptions.", 'red');

        foreach ($expired as $sub) {
            try {
                // Mark subscription as expired
                $subscriptionModel->update($sub['id'], ['status' => 'expired']);

                // Attempt to disable client on router
                if (!empty($sub['router_id'])) {
                    $clientUsername = 'client_' . $sub['client_id']; // or fetch from client table
                    $success = $routerService->deactivateClient($sub['router_id'], $clientUsername);

                    if ($success) {
                        CLI::write("🔒 Disabled client {$clientUsername} on router ID {$sub['router_id']}.", 'light_red');
                    } else {
                        CLI::write("❌ Failed to disable {$clientUsername} on router ID {$sub['router_id']}.", 'red');
                    }
                } else {
                    CLI::write("⚠️ Subscription {$sub['id']} has no router assigned.", 'yellow');
                }
            } catch (\Throwable $e) {
                CLI::write("❌ Error processing subscription ID {$sub['id']}: {$e->getMessage()}", 'red');
            }
        }

        CLI::write("✅ Expiry check completed successfully.", 'green');
    }
}
