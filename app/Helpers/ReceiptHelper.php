<?php

namespace App\Helpers;

use Dompdf\Dompdf;
use App\Models\ClientModel;
use App\Models\PackageModel;
use App\Models\TransactionModel;
use CodeIgniter\I18n\Time;

class ReceiptHelper
{
    /**
     * Generate a PDF receipt and save to /writable/receipts/
     */
    public static function generate($transactionId)
    {
        $transactionModel = new TransactionModel();
        $packageModel = new PackageModel();
        $clientModel = new ClientModel();

        $transaction = $transactionModel->find($transactionId);
        if (!$transaction) {
            return null;
        }

        $client = $clientModel->find($transaction['client_id']);
        $package = $packageModel->find($transaction['package_id']);

        // Prepare receipt HTML
        $html = view('templates/receipt_pdf', [
            'transaction' => $transaction,
            'client'      => $client,
            'package'     => $package,
            'date'        => Time::now()->toLocalizedString('MMM d, yyyy HH:mm')
        ]);

        // Generate PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save file
        $path = WRITEPATH . 'receipts/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $filename = 'receipt_' . $transactionId . '.pdf';
        file_put_contents($path . $filename, $dompdf->output());

        return $path . $filename;
    }

    /**
     * Send receipt to client via email
     */
    public static function sendEmail($clientId, $pdfPath)
    {
        $clientModel = new ClientModel();
        $client = $clientModel->find($clientId);

        if (!$client || empty($client['email'])) {
            return false;
        }

        $email = \Config\Services::email();
        $email->setTo($client['email']);
        $email->setFrom('no-reply@wifi-system.local', 'WiFi System');
        $email->setSubject('Your Payment Receipt');
        $email->setMessage('Thank you for your payment. Your receipt is attached.');

        if (file_exists($pdfPath)) {
            $email->attach($pdfPath);
        }

        return $email->send();
    }
}
