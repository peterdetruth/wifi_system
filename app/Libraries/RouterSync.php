<?php
namespace App\Libraries;

class RouterSync
{
    private $ip;
    private $username;
    private $password;
    private $port = 8728; // MikroTik API port

    public function __construct($ip = null, $username = 'admin', $password = '')
    {
        $this->ip = $ip;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Connect to MikroTik API
     */
    private function connect()
    {
        require_once(APPPATH . 'Libraries/RouterosAPI.php');
        $api = new \RouterosAPI();

        if ($api->connect($this->ip, $this->username, $this->password, $this->port)) {
            return $api;
        }

        return false;
    }

    /**
     * Add user to router based on package type
     * Accepts client and package arrays
     */
    public function addUserToRouter(array $client, array $package): array
    {
        // Determine router IP from package
        $routerIp = $package['router_id'] ?? null;
        if (!$routerIp) {
            return [
                'success' => false,
                'message' => 'No router assigned to this package.'
            ];
        }

        $this->ip = $routerIp;

        // Generate username and password
        $username = $client['username'] ?? 'user_' . $client['id'];
        $password = $this->generatePassword();

        $api = $this->connect();
        if (!$api) {
            return [
                'success' => false,
                'message' => 'Failed to connect to router.'
            ];
        }

        try {
            if ($package['type'] === 'hotspot') {
                $api->comm('/ip/hotspot/user/add', [
                    'name' => $username,
                    'password' => $password,
                    'profile' => $package['name']
                ]);
            } elseif ($package['type'] === 'pppoe') {
                $api->comm('/ppp/secret/add', [
                    'name' => $username,
                    'password' => $password,
                    'service' => 'pppoe',
                    'profile' => $package['name']
                ]);
            } else {
                return [
                    'success' => false,
                    'message' => 'Unknown package type.'
                ];
            }

            $api->disconnect();

            return [
                'success' => true,
                'username' => $username,
                'password' => $password,
                'message' => 'User provisioned successfully.'
            ];

        } catch (\Throwable $e) {
            $api->disconnect();
            return [
                'success' => false,
                'message' => 'Router provisioning failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove user from router based on package type
     */
    public function removeUserFromRouter(string $username, string $packageType = 'hotspot'): bool
    {
        $api = $this->connect();
        if (!$api) return false;

        try {
            if ($packageType === 'hotspot') {
                $user = $api->comm('/ip/hotspot/user/print', ['?name' => $username]);
                if (isset($user[0]['.id'])) {
                    $api->comm('/ip/hotspot/user/remove', ['.id' => $user[0]['.id']]);
                }
            } elseif ($packageType === 'pppoe') {
                $user = $api->comm('/ppp/secret/print', ['?name' => $username]);
                if (isset($user[0]['.id'])) {
                    $api->comm('/ppp/secret/remove', ['.id' => $user[0]['.id']]);
                }
            }

            $api->disconnect();
            return true;
        } catch (\Throwable $e) {
            $api->disconnect();
            return false;
        }
    }

    /**
     * Generate a secure random password
     */
    private function generatePassword(int $length = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
