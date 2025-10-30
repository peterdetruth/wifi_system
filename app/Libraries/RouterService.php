<?php

namespace App\Libraries;

use App\Models\RouterModel;

class RouterService
{
    protected RouterModel $routerModel;
    protected bool $simulate;

    /**
     * @param bool $simulate true = simulate RouterOS API (default),
     *                       false = perform real API operations (requires composer package)
     */
    public function __construct(bool $simulate = true)
    {
        $this->routerModel = new RouterModel();
        $this->simulate = $simulate;
    }

    /**
     * Get a RouterOS client or return true (in simulation mode).
     *
     * @throws \RuntimeException if RouterOS classes missing in real mode.
     */
    protected function getConnection(array $router)
    {
        if ($this->simulate) {
            return true; // Simulation mode
        }

        $clientClass = '\\RouterOS\\Client';
        $queryClass  = '\\RouterOS\\Query';

        if (!class_exists($clientClass) || !class_exists($queryClass)) {
            throw new \RuntimeException(
                'RouterOS library not installed. Run: composer require evilfreelancer/routeros-api-php'
            );
        }

        return new $clientClass([
            'host'    => $router['ip_address'] ?? '127.0.0.1',
            'user'    => $router['username'] ?? 'admin',
            'pass'    => $router['password'] ?? '',
            'port'    => $router['api_port'] ?? 8728,
            'timeout' => 5,
        ]);
    }

    /**
     * ✅ Activate or create a client on the router (used after payment/voucher).
     */
    public function activateClient(?int $routerId, string $clientUsername, array $package = null): bool
    {
        if (empty($routerId)) {
            log_message('warning', "RouterService: No router_id provided for activation of {$clientUsername}");
            return false;
        }

        $router = $this->routerModel->find($routerId);
        if (!$router) {
            log_message('error', "RouterService: Router {$routerId} not found.");
            return false;
        }

        try {
            $conn = $this->getConnection($router);
            $password = $package['password'] ?? bin2hex(random_bytes(4));
            $profile  = $package['name'] ?? 'default';

            if ($this->simulate) {
                log_message('info', "SIMULATED: Activated '{$clientUsername}' (profile={$profile}) on router {$router['name']}");
                return true;
            }

            $queryClass = '\\RouterOS\\Query';
            $queryPrint = new $queryClass('/ppp/secret/print');
            $queryPrint->where('name', $clientUsername);
            $existing = $conn->query($queryPrint)->read();

            if (!empty($existing)) {
                // Update existing user & re-enable
                $id = $existing[0]['.id'];
                $update = new $queryClass('/ppp/secret/set');
                $update->equal('.id', $id)
                       ->equal('disabled', 'no')
                       ->equal('password', $password)
                       ->equal('profile', $profile);
                $conn->query($update)->read();
            } else {
                // Create new PPPoE secret
                $add = new $queryClass('/ppp/secret/add');
                $add->equal('name', $clientUsername)
                    ->equal('password', $password)
                    ->equal('profile', $profile)
                    ->equal('service', 'pppoe')
                    ->equal('comment', 'Auto-created by WiFi system');
                $conn->query($add)->read();
            }

            log_message('info', "RouterService: Activated {$clientUsername} on router {$router['name']}");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'RouterService activateClient error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Deactivate (disable) a client on the router (used on subscription cancel).
     */
    public function deactivateClient(?int $routerId, string $clientUsername): bool
    {
        if (empty($routerId)) {
            log_message('warning', "RouterService: No router_id provided for deactivation of {$clientUsername}");
            return false;
        }

        $router = $this->routerModel->find($routerId);
        if (!$router) {
            log_message('error', "RouterService: Router {$routerId} not found.");
            return false;
        }

        try {
            $conn = $this->getConnection($router);

            if ($this->simulate) {
                log_message('info', "SIMULATED: Deactivated '{$clientUsername}' on router {$router['name']}");
                return true;
            }

            $queryClass = '\\RouterOS\\Query';
            $query = new $queryClass('/ppp/secret/disable');
            $query->equal('name', $clientUsername);
            $conn->query($query)->read();

            log_message('info', "RouterService: Deactivated {$clientUsername} on router {$router['name']}");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'RouterService deactivateClient error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Reconnect (force disconnect + enable) a client on router.
     */
    public function reconnectClient(?int $routerId, string $clientUsername): bool
    {
        if (empty($routerId)) {
            log_message('warning', "RouterService: No router_id provided for reconnect of {$clientUsername}");
            return false;
        }

        $router = $this->routerModel->find($routerId);
        if (!$router) {
            log_message('error', "RouterService: Router {$routerId} not found.");
            return false;
        }

        try {
            $conn = $this->getConnection($router);

            if ($this->simulate) {
                log_message('info', "SIMULATED: Reconnected '{$clientUsername}' on router {$router['name']}");
                return true;
            }

            $queryClass = '\\RouterOS\\Query';

            // Kick active session
            $queryActive = new $queryClass('/ppp/active/print');
            $queryActive->where('name', $clientUsername);
            $active = $conn->query($queryActive)->read();

            if (!empty($active)) {
                $id = $active[0]['.id'];
                $remove = new $queryClass('/ppp/active/remove');
                $remove->equal('.id', $id);
                $conn->query($remove)->read();
            }

            // Re-enable the user
            $enable = new $queryClass('/ppp/secret/enable');
            $enable->equal('name', $clientUsername);
            $conn->query($enable)->read();

            log_message('info', "RouterService: Reconnected {$clientUsername} on router {$router['name']}");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'RouterService reconnectClient error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Optional: remove expired users from router (for housekeeping jobs)
     */
    public function cleanupExpired(?int $routerId): bool
    {
        if (empty($routerId)) {
            return false;
        }

        $router = $this->routerModel->find($routerId);
        if (!$router) {
            return false;
        }

        try {
            $conn = $this->getConnection($router);

            if ($this->simulate) {
                log_message('info', "SIMULATED: Cleaned up expired users on {$router['name']}");
                return true;
            }

            // Example placeholder: your logic here if needed
            log_message('info', "RouterService: Cleaned up expired users on router {$router['name']}");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'RouterService cleanupExpired error: ' . $e->getMessage());
            return false;
        }
    }
}
