<?php
namespace App\Services;

class MpesaLogger
{
    protected string $logFile;

    public function __construct()
    {
        $this->logFile = WRITEPATH . 'logs/mpesa_debug.log';
    }

    public function debug(string $label, $data = null): void
    {
        $this->log('DEBUG', $label, $data);
    }

    public function info(string $label, $data = null): void
    {
        $this->log('INFO', $label, $data);
    }

    public function error(string $label, $data = null): void
    {
        $this->log('ERROR', $label, $data);
    }

    // ✅ Changed from protected to public
    public function log(string $level, string $label, $data = null): void
    {
        $payload = $data !== null ? (is_array($data) ? json_encode($data) : $data) : '';
        $msg = '[' . date('Y-m-d H:i:s') . "] [$level] $label" . ($payload ? " => $payload" : '');
        log_message('info', $msg);
        file_put_contents($this->logFile, $msg . PHP_EOL, FILE_APPEND);
    }
}
