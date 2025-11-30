<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Models\PaymentsModel;
use App\Models\VoucherModel;
use App\Models\PackageModel;
use App\Models\SubscriptionModel;

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

    public function index()
    {
        $month = (int) ($this->request->getGet('month') ?? date('m'));
        $year  = (int) ($this->request->getGet('year') ?? date('Y'));

        // KPIs
        $totalClients = (int) $this->clientModel->countAllResults();
        $totalPackages = (int) $this->packageModel->countAllResults();

        $activeSubscriptions = (int) $this->subscriptionModel
            ->where('subscriptions.status', 'active')
            ->countAllResults();

        $today = date('Y-m-d');
        $newSubscriptionsToday = (int) $this->subscriptionModel
            ->where('DATE(created_at)', $today)
            ->countAllResults();

        $newSubscriptionsMonth = (int) $this->subscriptionModel
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->countAllResults();

        $inactiveUsers = (int) $this->subscriptionModel
            ->where('subscriptions.status', 'expired')
            ->countAllResults();

        $pendingPayments = (int) $this->paymentsModel
            ->where('payments.status !=', 'completed')
            ->countAllResults();

        $totalRevenueRow = $this->paymentsModel
            ->selectSum('amount')
            ->where('payments.status', 'completed')
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->first();
        $totalRevenue = (float) ($totalRevenueRow['amount'] ?? 0.00);

        $revenueData = $this->paymentsModel
            ->select("DATE(created_at) as date, SUM(amount) as total")
            ->where('payments.status', 'completed')
            ->where('MONTH(created_at)', $month)
            ->where('YEAR(created_at)', $year)
            ->groupBy('DATE(created_at)')
            ->orderBy('DATE(created_at)', 'ASC')
            ->findAll();

        $chartLabels = array_column($revenueData, 'date');
        $chartValues = array_map('floatval', array_column($revenueData, 'total'));

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

        // Recent Payments with client username & package name
        $recentTransactions = $this->paymentsModel
            ->select('payments.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = payments.client_id', 'left')
            ->join('packages', 'packages.id = payments.package_id', 'left')
            ->orderBy('payments.created_at', 'DESC')
            ->limit(8)
            ->findAll();

        // Recent Clients
        $recentClients = $this->clientModel
            ->orderBy('created_at', 'DESC')
            ->limit(8)
            ->findAll();

        // Expiring Subscriptions within 24 hours (active)
        $expiringSoon = $this->subscriptionModel
            ->select('subscriptions.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = subscriptions.client_id', 'left')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->where('subscriptions.status', 'active')
            ->where('expires_on <=', date('Y-m-d H:i:s', strtotime('+24 hours')))
            ->orderBy('expires_on', 'ASC')
            ->findAll();

        // Recent Subscriptions
        $recentSubscriptions = $this->subscriptionModel
            ->select('subscriptions.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = subscriptions.client_id', 'left')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->orderBy('subscriptions.created_at', 'DESC')
            ->limit(8)
            ->findAll();

        // Recently Expired/Cancelled Subscriptions
        $recentlyExpired = $this->subscriptionModel
            ->select('subscriptions.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = subscriptions.client_id', 'left')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->whereIn('subscriptions.status', ['expired','cancelled'])
            ->orderBy('subscriptions.expires_on', 'DESC')
            ->limit(8)
            ->findAll();

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
            'recentSubscriptions'    => $recentSubscriptions,
            'recentlyExpired'        => $recentlyExpired,
            'month'                  => $month,
            'year'                   => $year
        ]);
    }
}
