<?php
namespace App\Libraries;

use Config\Services;
use CodeIgniter\HTTP\CURLRequest;

class MpesaDaraja
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $shortcode;
    protected $passkey;
    protected $environment; // 'sandbox' or 'production'
    protected $baseUrl;
    protected $callbackUrl;

    public function __construct(array $config = [])
    {
        // Prefer pulled from env, but allow passing in constructor
        $this->consumerKey    = $config['consumer_key']    ?? getenv('mpesa.consumer_key');
        $this->consumerSecret = $config['consumer_secret'] ?? getenv('mpesa.consumer_secret');
        $this->shortcode      = $config['shortcode']       ?? getenv('mpesa.shortcode'); // shortcode
        $this->passkey        = $config['passkey']         ?? getenv('mpesa.passkey'); // passkey
        $this->environment    = $config['environment']     ?? getenv('mpesa.environment') ?? 'sandbox';
        $this->callbackUrl    = $config['callback_url']    ?? getenv('mpesa.callback_url');

        if ($this->environment === 'production') {
            $this->baseUrl = 'https://api.safaricom.co.ke';
        } else {
            $this->baseUrl = 'https://sandbox.safaricom.co.ke';
        }
    }

    /**
     * Get OAuth token
     */
    public function getAccessToken(): ?string
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        $auth = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $client = Services::curlrequest([
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Accept' => 'application/json'
            ],
            'verify' => false,
            'timeout' => 10
        ]);

        $resp = $client->get($url);
        if ($resp->getStatusCode() !== 200) {
            log_message('error', 'MpesaDaraja::getAccessToken - HTTP ' . $resp->getStatusCode() . ' - ' . $resp->getBody());
            return null;
        }

        $json = json_decode($resp->getBody(), true);
        return $json['access_token'] ?? null;
    }

    /**
     * Initiate STK Push (Lipa Na Mpesa)
     *
     * @param string $phone E.g. 2547xxxxxxxx
     * @param float|int $amount
     * @param string $accountReference (optional)
     * @param string $transactionDesc
     * @return array|null contains 'CheckoutRequestID' and raw response
     */
    public function stkPush(string $phone, $amount, string $accountReference = 'wifi', string $transactionDesc = 'Payment'): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) return null;

        // timestamp required
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round($amount),
            'PartyA' => $this->formatPhone($phone),     // customer MSISDN
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $this->formatPhone($phone),
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc
        ];

        $url = $this->baseUrl . '/mpesa/stkpush/v1/processrequest';

        $client = Services::curlrequest([
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'verify' => false,
            'timeout' => 30
        ]);

        $resp = $client->post($url, ['json' => $payload]);
        $body = $resp->getBody();
        $code = $resp->getStatusCode();

        log_message('info', "MpesaDaraja::stkPush resp code=$code body={$body}");

        $json = json_decode($body, true);
        if ($json === null) return null;

        return $json; // contains ResponseCode, ResponseDescription, CheckoutRequestID (if accepted)
    }

    protected function formatPhone(string $phone): string
    {
        // Expect local numbers like 07XXXXXXXX or international like 2547XXXXXXXX
        $p = preg_replace('/\D+/', '', $phone);
        if (strlen($p) === 9 && substr($p,0,1) === '7') {
            return '254' . $p; // 7xxxxxxxx -> 2547xxxxxxxx
        }
        if (strlen($p) === 10 && substr($p,0,1) === '0') {
            return '254' . substr($p,1);
        }
        return $p;
    }
}
