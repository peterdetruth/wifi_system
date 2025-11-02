<?php
if (!function_exists('mpesa_log')) {
    function mpesa_log($message)
    {
        $logDir = WRITEPATH . 'logs/mpesa_logs/';
        if (!is_dir($logDir)) mkdir($logDir, 0777, true);
        $file = $logDir . 'mpesa_' . date('Y-m-d') . '.log';
        $timestamp = date('[Y-m-d H:i:s]');
        file_put_contents($file, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
    }
}
