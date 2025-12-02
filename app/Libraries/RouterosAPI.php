<?php

/**
 * Lightweight MikroTik RouterOS API Class
 * Fully compatible with PHP 7+ and CodeIgniter 4
 *
 * Supports:
 * - connect()
 * - comm()
 * - read()
 * - write()
 * - disconnect()
 */

class RouterosAPI
{
    public $debug = false;
    public $connected = false;
    public $port = 8728;

    private $socket;
    private $timeout = 3;

    /**
     * Connect to RouterOS
     */
    public function connect($ip, $username, $password, $port = 8728)
    {
        $this->port = $port;

        $this->socket = @fsockopen($ip, $port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            return false;
        }

        // Login
        $this->write('/login');
        $response = $this->read(false);

        if (!isset($response[0]) || $response[0] !== '!done') {
            return false;
        }

        $cookie = substr($response[1], 5);

        $this->write('/login', false);
        $this->write('=name=' . $username, false);
        $this->write('=password=' . md5($password . $cookie), true);

        $final = $this->read(false);

        if (isset($final[0]) && $final[0] === '!done') {
            $this->connected = true;
            return true;
        }

        return false;
    }

    /**
     * Disconnect from router
     */
    public function disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
        }
        $this->connected = false;
    }

    /**
     * Write command to RouterOS
     */
    public function write($command, $final = true)
    {
        $length = strlen($command);
        $encoded = $this->encodeLength($length);

        fwrite($this->socket, $encoded . $command);

        if ($final) {
            fwrite($this->socket, "\x00");
        }
    }

    /**
     * Read response from RouterOS
     */
    public function read($parse = true)
    {
        $response = [];

        while (true) {
            $length = $this->readLength();

            if ($length === 0) {
                break;
            }

            $data = fread($this->socket, $length);
            $response[] = $data;
        }

        if ($parse) {
            return $this->parseResponse($response);
        }

        return $response;
    }

    /**
     * Execute a RouterOS command
     */
    public function comm($command, $params = [])
    {
        $this->write($command, false);

        foreach ($params as $k => $v) {
            $this->write('=' . $k . '=' . $v, false);
        }

        $this->write("\x00");

        return $this->read();
    }

    /**
     * --------------------
     * Internal low-level
     * --------------------
     */

    private function encodeLength($length)
    {
        if ($length < 0x80) {
            return chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            return chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            return chr(($length >> 16) & 0xFF)
                . chr(($length >> 8) & 0xFF)
                . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            return chr(($length >> 24) & 0xFF)
                . chr(($length >> 16) & 0xFF)
                . chr(($length >> 8) & 0xFF)
                . chr($length & 0xFF);
        } else {
            return chr(0xF0)
                . chr(($length >> 24) & 0xFF)
                . chr(($length >> 16) & 0xFF)
                . chr(($length >> 8) & 0xFF)
                . chr($length & 0xFF);
        }
    }

    private function readLength()
    {
        $c = ord(fread($this->socket, 1));

        if ($c < 0x80) {
            return $c;
        } elseif (($c & 0xC0) == 0x80) {
            $c &= ~0xC0;
            $len = ($c << 8) + ord(fread($this->socket, 1));
            return $len;
        } elseif (($c & 0xE0) == 0xC0) {
            $c &= ~0xE0;
            $len = ($c << 16)
                + (ord(fread($this->socket, 1)) << 8)
                + ord(fread($this->socket, 1));
            return $len;
        } elseif (($c & 0xF0) == 0xE0) {
            $c &= ~0xF0;
            $len = ($c << 24)
                + (ord(fread($this->socket, 1)) << 16)
                + (ord(fread($this->socket, 1)) << 8)
                + ord(fread($this->socket, 1));
            return $len;
        } elseif ($c == 0xF0) {
            $len = (ord(fread($this->socket, 1)) << 24)
                + (ord(fread($this->socket, 1)) << 16)
                + (ord(fread($this->socket, 1)) << 8)
                + ord(fread($this->socket, 1));
            return $len;
        }

        return 0;
    }

    private function parseResponse($response)
    {
        $parsed = [];
        $current = [];

        foreach ($response as $line) {
            if ($line === '!done') {
                $parsed[] = $current;
                $current = [];
            } elseif (strpos($line, '=') === 0) {
                $parts = explode('=', substr($line, 1), 2);
                $current[$parts[0]] = $parts[1] ?? '';
            }
        }

        return $parsed;
    }
}
