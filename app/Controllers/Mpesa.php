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
     * Initiate a transaction record when an STK Push is started.
     */
    public function initiateTransaction(
        int $clientId,
        int $packageId,
        float $amount,
        string $phone,
        ?string $merchantRequestId = null,
        ?string $checkoutRequestId = null
    ) {
        // Call MpesaService with all required arguments
        $success = $this->mpesaService->initiateTransaction(
            $clientId,
            $packageId,
            $amount,
            $phone,
            $merchantRequestId,
            $checkoutRequestId
        );

        if ($success) {
            return $this->respond(['status' => 'ok'], 200);
        }

        return $this->failServerError('Failed to initiate transaction');
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

            // Delegate to MpesaService
            $response = $this->mpesaService->handleCallback($data);

            return $this->respond($response, 200);
        } catch (\Throwable $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}
