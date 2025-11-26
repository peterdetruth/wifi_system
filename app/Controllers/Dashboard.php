<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Models\MpesaTransactionModel;
use App\Models\VoucherModel;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $clientModel = new ClientModel();
        $mpesaModel = new MpesaTransactionModel();
        $voucherModel = new VoucherModel();
        $packageModel = new PackageModel();
        $subscriptionModel = new SubscriptionModel();

        // Filters: month/year from GET, default to current
        $month = (int) ($this->request->getGet('month') ?? date('m'));
        $year  = (int) ($this->request->getGet('year') ?? date('Y'));

        // Metrics
        $totalClients = $clientModel->countAllResults();
        $totalPackages = $packageModel->countAllResults();
        $activeSubscriptions = $subscriptionModel->where('status', 'active')->countAllResults();

        // Total Revenue (only successful M-PESA transactions)
        $totalRevenueRow = $mpesaModel
            ->selectSum('amount')
            ->where('result_code', 0)
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->first();
        $totalRevenue = (float) ($totalRevenueRow['amount'] ?? 0);

        // Revenue chart data
        $revenueData = $mpesaModel
            ->select("DATE(created_at) as date, SUM(amount) as total")
            ->where('result_code', 0)
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->groupBy('DATE(created_at)')
            ->orderBy('DATE(created_at)', 'ASC')
            ->findAll();
        $chartLabels = array_column($revenueData, 'date');
        $chartValues = array_map('floatval', array_column($revenueData, 'total'));

        // Voucher usage trends
        $voucherUsage = $voucherModel
            ->select("DATE(used_at) as date, COUNT(id) as total")
            ->where('used_at IS NOT NULL')
            ->where('MONTH(used_at)', $month)
            ->where('YEAR(used_at)', $year)
            ->groupBy('DATE(used_at)')
            ->orderBy('DATE(used_at)', 'ASC')
            ->findAll();
        $voucherLabels = array_column($voucherUsage, 'date');
        $voucherValues = array_map('intval', array_column($voucherUsage, 'total'));

        // Recent transactions & clients
        $recentTransactions = $mpesaModel->orderBy('created_at', 'DESC')->limit(5)->findAll();
        $recentClients = $clientModel->orderBy('created_at', 'DESC')->limit(5)->findAll();

        // Expiring subscriptions
        $expiringSoon = $subscriptionModel
            ->where('status', 'active')
            ->where('expires_on <=', date('Y-m-d H:i:s', strtotime('+24 hours')))
            ->findAll();

        // Pass all variables to the view
        return view('admin/dashboard', [
            'totalClients'        => $totalClients,
            'totalPackages'       => $totalPackages,
            'totalRevenue'        => $totalRevenue,
            'activeSubscriptions' => $activeSubscriptions,
            'chartLabels'         => $chartLabels,
            'chartValues'         => $chartValues,
            'voucherLabels'       => $voucherLabels,
            'voucherValues'       => $voucherValues,
            'recentTransactions'  => $recentTransactions,
            'recentClients'       => $recentClients,
            'expiringSoon'        => $expiringSoon,
            'month'               => $month,
            'year'                => $year
        ]);
    }
}
