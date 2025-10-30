<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\SubscriptionModel;
use App\Models\TransactionModel;
use App\Libraries\RouterService;

// PS C:\xampp\htdocs\wifi_system> php spark subscriptions:expire >> C:/xampp/htdocs/wifi_system/writable/logs/subscriptions_expiry.log 2>&1

class ExpireSubscriptions extends BaseCommand
{
    protected $group       = 'Subscriptions';
    protected $name        = 'subscriptions:expire';
    protected $description = 'Check for expired subscriptions and deactivate them on routers.';

    public function run(array $params = [])
    {
        CLI::write('Starting subscription expiry check...', 'green');

        $subModel = new SubscriptionModel();
        $txModel = new TransactionModel();
        // Use RouterService in simulation mode by default; pass false for real routers
        $routerService = new RouterService(true);

        // Find active subscriptions that have expired
        $now = date('Y-m-d H:i:s');
        $expired = $subModel
            ->where('status', 'active')
            ->where('expires_on <', $now)
            ->findAll();

        if (empty($expired)) {
            CLI::write('No expired subscriptions found.', 'yellow');
            return;
        }

        foreach ($expired as $sub) {
            try {
                // Mark as expired
                $subModel->update($sub['id'], ['status' => 'expired']);

                // Attempt to deactivate on router (best-effort)
                $clientUsername = $sub['client_username'] ?? ('client_' . $sub['client_id']);
                $routerId = $sub['router_id'] ?? null;

                $deactivated = $routerService->deactivateClient($routerId, $clientUsername);

                // Optional: insert a transaction or log record
                $txModel->insert([
                    'client_id' => $sub['client_id'],
                    'package_id' => $sub['package_id'],
                    'amount' => 0,
                    'status' => 'expired',
                    'method' => 'system',
                    'mpesa_code' => null,
                    'created_on' => date('Y-m-d H:i:s'),
                ]);

                CLI::write("Expired subscription id={$sub['id']} (client {$sub['client_id']}) - router deactivated: " . ($deactivated ? 'yes' : 'no'));
            } catch (\Throwable $e) {
                CLI::write("Failed to expire subscription id={$sub['id']}: " . $e->getMessage(), 'red');
            }
        }

        CLI::write('Expiry check completed.', 'green');
    }
}
