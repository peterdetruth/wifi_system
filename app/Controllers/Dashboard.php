<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Models\PaymentsModel;
use App\Models\VoucherModel;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;
use Config\Services;

class Dashboard extends BaseController
{
    protected ClientModel $clientModel;
    protected PaymentsModel $paymentsModel;
    protected VoucherModel $voucherModel;
    protected PackageModel $packageModel;
    protected SubscriptionModel $subscriptionModel;

    public function __construct()
    {
        $this->clientModel = new ClientModel();
        $this->paymentsModel = new PaymentsModel();
        $this->voucherModel = new VoucherModel();
        $this->packageModel = new PackageModel();
        $this->subscriptionModel = new SubscriptionModel();
    }

    /**
     * Admin dashboard main page
     */
    public function index()
    {
        // Filters: month/year from GET, default to current
        $month = (int) ($this->request->getGet('month') ?? date('m'));
        $year  = (int) ($this->request->getGet('year') ?? date('Y'));

        // KPIs
        $totalClients = (int) $this->clientModel->countAllResults();
        $totalPackages = (int) $this->packageModel->countAllResults();

        // Active subscriptions
        $activeSubscriptions = (int) $this->subscriptionModel
            ->where('status', 'active')
            ->countAllResults();

        // New subscriptions: Today and This Month
        $today = date('Y-m-d');
        $newSubscriptionsToday = (int) $this->subscriptionModel
            ->where('DATE(created_at)', $today)
            ->countAllResults();

        $newSubscriptionsMonth = (int) $this->subscriptionModel
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->countAllResults();

        // Inactive users (expired subscriptions)
        $inactiveUsers = (int) $this->subscriptionModel
            ->where('status', 'expired')
            ->countAllResults();

        // Pending payments (not completed)
        $pendingPayments = (int) $this->paymentsModel
            ->where('status !=', 'completed')
            ->countAllResults();

        // Total Revenue (payments.status = 'completed') for selected month/year
        $totalRevenueRow = $this->paymentsModel
            ->selectSum('amount')
            ->where('status', 'completed')
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->first();
        $totalRevenue = (float) ($totalRevenueRow['amount'] ?? 0.00);

        // Revenue chart data (grouped by date)
        $revenueData = $this->paymentsModel
            ->select("DATE(created_at) as date, SUM(amount) as total")
            ->where('status', 'completed')
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->groupBy('DATE(created_at)')
            ->orderBy('DATE(created_at)', 'ASC')
            ->findAll();

        $chartLabels = array_column($revenueData, 'date');
        $chartValues = array_map('floatval', array_column($revenueData, 'total'));

        // Voucher usage trends
        $voucherUsage = $this->voucherModel
            ->select("DATE(used_at) as date, COUNT(id) as total")
            ->where('used_at IS NOT NULL')
            ->where('MONTH(used_at)', $month)
            ->where('YEAR(used_at)', $year)
            ->groupBy('DATE(used_at)')
            ->orderBy('DATE(used_at)', 'ASC')
            ->findAll();

        $voucherLabels = array_column($voucherUsage, 'date');
        $voucherValues = array_map('intval', array_column($voucherUsage, 'total'));

        // Recent transactions (from payments table, latest completed/pending)
        $recentTransactions = $this->paymentsModel
            ->orderBy('created_at', 'DESC')
            ->limit(8)
            ->findAll();

        // Recent clients
        $recentClients = $this->clientModel
            ->orderBy('created_at', 'DESC')
            ->limit(8)
            ->findAll();

        // Expiring subscriptions within next 24 hours
        $expiringSoon = $this->subscriptionModel
            ->where('status', 'active')
            ->where('expires_on <=', date('Y-m-d H:i:s', strtotime('+24 hours')))
            ->findAll();

        // Pass variables to view (we will embed them as data-* attributes in the view)
        return view('admin/dashboard', [
            'totalClients'           => $totalClients,
            'totalPackages'          => $totalPackages,
            'activeSubscriptions'    => $activeSubscriptions,
            'newSubscriptionsToday'  => $newSubscriptionsToday,
            'newSubscriptionsMonth'  => $newSubscriptionsMonth,
            'inactiveUsers'          => $inactiveUsers,
            'pendingPayments'        => $pendingPayments,
            'totalRevenue'           => $totalRevenue,
            'chartLabels'            => $chartLabels,
            'chartValues'            => $chartValues,
            'voucherLabels'          => $voucherLabels,
            'voucherValues'          => $voucherValues,
            'recentTransactions'     => $recentTransactions,
            'recentClients'          => $recentClients,
            'expiringSoon'           => $expiringSoon,
            'month'                  => $month,
            'year'                   => $year
        ]);
    }

    /**
     * Export revenue CSV for the selected month/year (uses payments table)
     */
    public function exportRevenueCSV()
    {
        $month = (int) ($this->request->getGet('month') ?? date('m'));
        $year  = (int) ($this->request->getGet('year') ?? date('Y'));

        $rows = $this->paymentsModel
            ->select('payments.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = payments.client_id', 'left')
            ->join('packages', 'packages.id = payments.package_id', 'left')
            ->where('status', 'completed')
            ->where('MONTH(payments.created_at)', $month)
            ->where('YEAR(payments.created_at)', $year)
            ->orderBy('payments.created_at', 'DESC')
            ->findAll();

        $filename = "revenue_{$year}{$month}.csv";
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename={$filename}");

        $out = fopen('php://output', 'w');

        // CSV header
        fputcsv($out, ['ID', 'Client', 'Package', 'Amount', 'Payment Method', 'Mpesa Receipt', 'Status', 'Created At']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['client_username'] ?? $r['client_id'],
                $r['package_name'] ?? $r['package_id'],
                number_format((float)$r['amount'], 2, '.', ''),
                $r['payment_method'] ?? 'mpesa',
                $r['mpesa_receipt_number'] ?? $r['mpesa_receipt'] ?? '',
                $r['status'] ?? '',
                $r['created_at'] ?? ''
            ]);
        }

        fclose($out);
        exit;
    }
}
