<?php
use Config\Services;

function mpesa_access_token()
{
    $key = getenv('mpesa.consumer_key');
    $secret = getenv('mpesa.consumer_secret');
    $env = getenv('mpesa.environment');

    $url = $env === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode("$key:$secret")]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    return $response->access_token ?? null;
}

function send_stk_push($phone, $amount, $accountReference, $description)
{
    $token = mpesa_access_token();
    if (!$token) return ['error' => 'Failed to get access token'];

    $shortcode = getenv('mpesa.shortcode');
    $passkey = getenv('mpesa.passkey');
    $callback = getenv('mpesa.callback_url');
    $env = getenv('mpesa.environment');
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);

    $url = $env === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $payload = [
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => $shortcode,
        'PhoneNumber' => $phone,
        'CallBackURL' => $callback,
        'AccountReference' => $accountReference,
        'TransactionDesc' => $description,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "Authorization: Bearer $token"
        ],
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response ?: ['error' => 'No response from Daraja'];
}

if (!function_exists('mpesa_debug')) {
    function mpesa_debug($message)
    {
        $logPath = WRITEPATH . 'logs/mpesa_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}

if (!function_exists('mpesa_log')) {
    function mpesa_log($message)
    {
        $logPath = WRITEPATH . 'logs/mpesa_activity.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}

