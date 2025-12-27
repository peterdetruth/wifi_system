<?php

namespace App\Services;

use App\Models\LogModel;

class LogService
{
    protected LogModel $logModel;

    // Allowed levels and types
    protected array $levels = ['debug', 'info', 'warning', 'error'];
    protected array $types = ['Payment', 'Subscription', 'Login', 'Voucher', 'Router', 'Admin', 'Client'];

    // Whether to pretty-print context in dev environment
    protected bool $prettyPrint = false;

    public function __construct()
    {
        $this->logModel = new LogModel();
        // Enable pretty print if environment is development
        $this->prettyPrint = ENVIRONMENT === 'development';
    }

    /**
     * Add a log entry
     *
     * @param string $level debug|info|warning|error
     * @param string $type Payment|Subscription|Login|Voucher|Router|Admin|Client
     * @param string $message Human-readable message
     * @param array|null $context Additional details (will be stored as JSON)
     * @param int|null $userId Related user ID
     * @param string|null $ip Optional IP address
     */
    public function log(string $level, string $type, string $message, ?array $context = null, ?int $userId = null, ?string $ip = null)
    {
        // Validate level
        if (!in_array($level, $this->levels)) {
            $level = 'info';
        }

        // Validate type
        if (!in_array($type, $this->types)) {
            $type = 'Admin';
        }

        // Auto-detect IP if not provided
        if (!$ip && isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Encode context
        $contextJson = null;
        if ($context) {
            $contextJson = $this->prettyPrint ? json_encode($context, JSON_PRETTY_PRINT) : json_encode($context);
        }

        // Insert into DB
        $this->logModel->insert([
            'level' => $level,
            'type' => $type,
            'message' => $message,
            'context' => $contextJson,
            'user_id' => $userId,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Convenience helpers
    public function debug(string $type, string $message, ?array $context = null, ?int $userId = null, ?string $ip = null)
    {
        $this->log('debug', $type, $message, $context, $userId, $ip);
    }

    public function info(string $type, string $message, ?array $context = null, ?int $userId = null, ?string $ip = null)
    {
        $this->log('info', $type, $message, $context, $userId, $ip);
    }

    public function warning(string $type, string $message, ?array $context = null, ?int $userId = null, ?string $ip = null)
    {
        $this->log('warning', $type, $message, $context, $userId, $ip);
    }

    public function error(string $type, string $message, ?array $context = null, ?int $userId = null, ?string $ip = null)
    {
        $this->log('error', $type, $message, $context, $userId, $ip);
    }

    // Admin vs Client convenience methods
    public function logAdmin(string $level, string $message, ?array $context = null, ?int $adminId = null, ?string $ip = null)
    {
        $this->log($level, 'Admin', $message, $context, $adminId, $ip);
    }

    public function logClient(string $level, string $message, ?array $context = null, ?int $clientId = null, ?string $ip = null)
    {
        $this->log($level, 'Client', $message, $context, $clientId, $ip);
    }
}
