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
     * @param string $type Payment|Subscription|Login|Voucher|Router
     * @param string $message Human-readable message
     * @param array|null $context Additional details (will be stored as JSON)
     * @param int|null $userId Related user ID
     * @param string|null $ip Optional IP address
     */
    public function log(string $level, string $type, string $message, ?array $context = null, ?int $userId = null, ?string $ip = null)
    {
        $this->logModel->insert([
            'level' => $level,
            'type' => $type,
            'message' => $message,
            'context' => $context ? json_encode($context) : null,
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
}
