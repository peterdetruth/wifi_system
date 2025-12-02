<?php

namespace App\Controllers;

use App\Services\MpesaService;
use App\Services\MpesaLogger;
use App\Models\MpesaTransactionModel;
use App\Models\PaymentsModel;
use App\Models\PackageModel;
use App\Models\ClientModel;
use CodeIgniter\API\ResponseTrait;

class Mpesa extends BaseController
{
    use ResponseTrait;

    protected MpesaService $mpesaService;
    protected MpesaTransactionModel $mpesaTransactionModel;
    protected PaymentsModel $paymentsModel;
    protected PackageModel $packageModel;
    protected ClientModel $clientModel;

    public function __construct()
    {
        // Initialize logger
        $logger = new MpesaLogger();

        // Inject logger into service
        $this->mpesaService = new MpesaService($logger);

        // Models
        $this->mpesaTransactionModel = new MpesaTransactionModel();
        $this->paymentsModel         = new PaymentsModel();
        $this->packageModel          = new PackageModel();
        $this->clientModel           = new ClientModel();
    }

    /**
     * Callback endpoint for M-PESA STK push results
     */
    public function callback()
    {
        try {
            /** @var \CodeIgniter\HTTP\IncomingRequest $request */
            $request = $this->request;
            $data = $request->getJSON(true);

            if (!$data) {
                log_message('error', 'Mpesa callback received empty or invalid JSON.');
                return $this->fail('Invalid callback data', 400);
            }

            // Delegate to MpesaService for processing
            $response = $this->mpesaService->handleCallback($data);

            // Ensure callback failures do not break the system
            if (!isset($response['success']) || !$response['success']) {
                log_message('error', 'Mpesa callback handling failed: ' . json_encode($data));
                return $this->respond([
                    'status'  => 'error',
                    'message' => 'Callback processed partially. Manual check may be required.'
                ], 200);
            }

            return $this->respond($response, 200);
        } catch (\Throwable $e) {
            log_message('error', 'Mpesa callback exception: ' . $e->getMessage());

            // Do not throw error to M-PESA, return 200 with error info to avoid retries
            return $this->respond([
                'status'  => 'error',
                'message' => 'Callback processing error. Admin should verify the transaction.'
            ], 200);
        }
    }
}
