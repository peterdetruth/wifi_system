<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Models\TransactionModel;
use App\Models\MpesaTransactionModel;
use App\Models\VoucherModel;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $clientModel = new ClientModel();
        $txnModel = new TransactionModel();
        $mpesaModel = new MpesaTransactionModel();
        $voucherModel = new VoucherModel();
        $packageModel = new PackageModel();
        $subscriptionModel = new SubscriptionModel();

        // Metrics
        $data['totalClients'] = $clientModel->countAllResults();
        $data['totalPackages'] = $packageModel->countAllResults();
        $data['totalVouchers'] = $voucherModel->countAllResults();
        $data['totalTransactions'] = $txnModel->countAllResults();

        // Active subscriptions
        $data['activeSubscriptions'] = $subscriptionModel->where('status', 'active')->countAllResults();

        // Recent data
        $data['recentTransactions'] = $txnModel->orderBy('created_on', 'DESC')->limit(5)->findAll();
        $data['recentClients'] = $clientModel->orderBy('created_at', 'DESC')->limit(5)->findAll();

        // Chart Data: Transactions per day (last 7 days)
        $chartData = $txnModel->select("DATE(created_on) as date, SUM(amount) as total")
            ->groupBy('DATE(created_on)')
            ->orderBy('DATE(created_on)', 'ASC')
            ->limit(7)
            ->findAll();

        $data['chartLabels'] = array_column($chartData, 'date');
        $data['chartValues'] = array_map('floatval', array_column($chartData, 'total'));

        // Voucher usage trends
        $voucherUsage = $voucherModel->select("DATE(used_at) as date, COUNT(id) as total")
            ->where('used_at IS NOT NULL')
            ->groupBy('DATE(used_at)')
            ->orderBy('DATE(used_at)', 'ASC')
            ->limit(7)
            ->findAll();

        $data['voucherLabels'] = array_column($voucherUsage, 'date');
        $data['voucherValues'] = array_map('intval', array_column($voucherUsage, 'total'));

        // Expiring subscriptions (next 24 hours)
        $data['expiringSoon'] = $subscriptionModel
            ->where('status', 'active')
            ->where('expires_on <=', date('Y-m-d H:i:s', strtotime('+24 hours')))
            ->findAll();

        return view('admin/dashboard', $data);
    }
}
