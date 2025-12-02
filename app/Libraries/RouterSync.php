<?php
namespace App\Libraries;

class RouterSync
{
    private $ip;
    private $username;
    private $password;
    private $port = 8728; // API port

    public function __construct($ip, $username = 'admin', $password = '')
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
     * Add Hotspot User
     */
    public function addHotspotUser($username, $password, $profile)
    {
        $api = $this->connect();
        if (! $api) return false;

        $api->comm('/ip/hotspot/user/add', [
            'name' => $username,
            'password' => $password,
            'profile' => $profile
        ]);

        $api->disconnect();
        return true;
    }

    /**
     * Remove Hotspot User
     */
    public function removeHotspotUser($username)
    {
        $api = $this->connect();
        if (! $api) return false;

        $user = $api->comm('/ip/hotspot/user/print', [
            '?name' => $username
        ]);

        if (isset($user[0]['.id'])) {
            $api->comm('/ip/hotspot/user/remove', [
                '.id' => $user[0]['.id']
            ]);
        }

        $api->disconnect();
        return true;
    }

    /**
     * Add PPPoE User
     */
    public function addPPPoEUser($username, $password, $profile)
    {
        $api = $this->connect();
        if (! $api) return false;

        $api->comm('/ppp/secret/add', [
            'name' => $username,
            'password' => $password,
            'service' => 'pppoe',
            'profile' => $profile
        ]);

        $api->disconnect();
        return true;
    }

    /**
     * Remove PPPoE user
     */
    public function removePPPoEUser($username)
    {
        $api = $this->connect();
        if (! $api) return false;

        $user = $api->comm('/ppp/secret/print', [
            '?name' => $username
        ]);

        if (isset($user[0]['.id'])) {
            $api->comm('/ppp/secret/remove', [
                '.id' => $user[0]['.id']
            ]);
        }

        $api->disconnect();
        return true;
    }
}
