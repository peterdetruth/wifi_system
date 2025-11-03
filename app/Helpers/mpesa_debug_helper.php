<?php

if (!function_exists('mpesa_debug')) {
    function mpesa_debug($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] {$message}\n";

        // Log to file
        file_put_contents(WRITEPATH . 'logs/mpesa_debug.log', $entry, FILE_APPEND);

        // Optional browser/session trace
        $session = session();
        $trace = $session->get('mpesa_debug_trace') ?? [];
        $trace[] = $message;
        $session->set('mpesa_debug_trace', $trace);
    }
}

if (!function_exists('mpesa_debug_clear')) {
    function mpesa_debug_clear()
    {
        session()->remove('mpesa_debug_trace');
    }
}
