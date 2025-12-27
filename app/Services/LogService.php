<?php

namespace App\Services;

use App\Models\LogModel;

class LogService
{
    protected LogModel $logModel;

    public function __construct()
    {
        $this->logModel = new LogModel();
    }

    /**
     * Add a log entry
     *
     * @param string $level debug|info|warning|error
     * @param string $type Payment|Subscription|Login|Voucher|Router|General
     * @param string $message Human-readable message
     * @param array|null $context Additional details (will be stored as JSON)
     * @param int|null $userId Related user ID
     * @param string|null $ip Optional IP address
     */
    public function log(string $level, string $type, string $message, ?array $context = null, ?int $userId = null, ?string $ip = null)
    {
        // Validate level and type
        $validLevels = ['debug', 'info', 'warning', 'error'];
        $validTypes  = ['Payment', 'Subscription', 'Login', 'Voucher', 'Router', 'mpesa', 'General'];

        if (!in_array($level, $validLevels)) $level = 'info';
        if (!in_array($type, $validTypes)) $type = 'General';

        // Ensure context is JSON
        $jsonContext = $context ? json_encode($context) : json_encode([]);

        // Auto-detect IP if not provided
        $ipAddress = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $this->logModel->insert([
            'level'      => $level,
            'type'       => $type,
            'message'    => $message,
            'context'    => $jsonContext,
            'user_id'    => $userId,
            'ip_address' => $ipAddress,
            'created_at' => $this->now(),
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

    /**
     * Get current timestamp in Y-m-d H:i:s
     */
    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
