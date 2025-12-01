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

    /**
     * Dashboard index
     *
     * - Auto-detects DB/server timezone offset and applies it when grouping by date
     * - Falls back to no conversion if detection fails
     */
    public function index()
    {
        // Requested month/year for charts (defaults)
        $month = (int) ($this->request->getGet('month') ?? date('m'));
        $year  = (int) ($this->request->getGet('year') ?? date('Y'));

        // --- determine DB/server offset (seconds) and build an offset string like "+03:00" or "-05:30"
        $db = \Config\Database::connect();
        $tzOffset = $this->detectDbTimezoneOffset($db); // returns like "+03:00" or "0" on failure

        // helper closure to return a DATE(...) expression with CONVERT_TZ if we have an offset
        $dateExpr = function (string $col = 'created_at') use ($tzOffset) {
            if ($tzOffset === '0') {
                // no conversion
                return "DATE(`$col`)";
            }
            // use numeric offset which is safe even if tz tables not loaded
            return "DATE(CONVERT_TZ(`$col`, '+00:00', '{$tzOffset}'))";
        };

        // --- KPIs & Counts ---
        $totalClients = (int) $this->clientModel->countAllResults();
        $totalPackages = (int) $this->packageModel->countAllResults();

        $activeSubscriptions = (int) $this->subscriptionModel
            ->where('subscriptions.status', 'active')
            ->countAllResults();

        // New subscriptions counts (today / month)
        $today = date('Y-m-d');

        // For DATE(created_at) comparisons we must use the same conversion expression
        $subsBuilderToday = $this->subscriptionModel->builder();
        $subsBuilderToday->where("{$dateExpr('created_at')} = '{$today}'", null, false);
        $newSubscriptionsToday = (int) $subsBuilderToday->countAllResults(false);

        $subsBuilderMonth = $this->subscriptionModel->builder();
        // Use MONTH/YEAR on converted date (we'll wrap the DATE expression into MONTH()/YEAR() via SQL)
        if ($tzOffset === '0') {
            $subsBuilderMonth->where('MONTH(created_at)', $month)->where('YEAR(created_at)', $year);
        } else {
            $subsBuilderMonth->where("MONTH(CONVERT_TZ(created_at, '+00:00', '{$tzOffset}')) = {$month}", null, false)
                              ->where("YEAR(CONVERT_TZ(created_at, '+00:00', '{$tzOffset}')) = {$year}", null, false);
        }
        $newSubscriptionsMonth = (int) $subsBuilderMonth->countAllResults(false);

        $inactiveUsers = (int) $this->subscriptionModel
            ->where('subscriptions.status', 'expired')
            ->countAllResults();

        $pendingPayments = (int) $this->paymentsModel
            ->where('payments.status !=', 'completed')
            ->countAllResults();

        // --- Total Revenue for selected month/year (payments.status = completed) ---
        $paymentsBuilder = $this->paymentsModel->builder();
        $paymentsBuilder->selectSum('amount');

        if ($tzOffset === '0') {
            $paymentsBuilder->where('payments.status', 'completed')
                            ->where('MONTH(created_at)', $month)
                            ->where('YEAR(created_at)', $year);
        } else {
            $paymentsBuilder->where('payments.status', 'completed')
                            ->where("MONTH(CONVERT_TZ(created_at, '+00:00', '{$tzOffset}')) = {$month}", null, false)
                            ->where("YEAR(CONVERT_TZ(created_at, '+00:00', '{$tzOffset}')) = {$year}", null, false);
        }
        $totalRevenueRow = $paymentsBuilder->get()->getRowArray();
        $totalRevenue = (float) ($totalRevenueRow['amount'] ?? 0.00);

        // --- Revenue chart data (group by date, using converted date when available) ---
        $paymentsChartBuilder = $this->paymentsModel->builder();
        $dateColumnExpr = $dateExpr('created_at');
        $paymentsChartBuilder->select("{$dateColumnExpr} AS date, SUM(amount) AS total", false)
            ->where('payments.status', 'completed');

        if ($tzOffset === '0') {
            $paymentsChartBuilder->where('MONTH(created_at)', $month)->where('YEAR(created_at)', $year);
        } else {
            $paymentsChartBuilder->where("MONTH(CONVERT_TZ(created_at, '+00:00', '{$tzOffset}')) = {$month}", null, false)
                                 ->where("YEAR(CONVERT_TZ(created_at, '+00:00', '{$tzOffset}')) = {$year}", null, false);
        }

        $paymentsChartBuilder->groupBy('date')->orderBy('date', 'ASC');

        $revenueData = $paymentsChartBuilder->get()->getResultArray();
        $chartLabels = array_column($revenueData, 'date');
        $chartValues = array_map('floatval', array_column($revenueData, 'total'));

        // --- Voucher usage chart (used_at) ---
        $voucherBuilder = $this->voucherModel->builder();
        $voucherDateExpr = $tzOffset === '0' ? "DATE(used_at)" : "DATE(CONVERT_TZ(used_at, '+00:00', '{$tzOffset}'))";
        $voucherBuilder->select("{$voucherDateExpr} AS date, COUNT(id) AS total", false)
            ->where('used_at IS NOT NULL');

        if ($tzOffset === '0') {
            $voucherBuilder->where('MONTH(used_at)', $month)->where('YEAR(used_at)', $year);
        } else {
            $voucherBuilder->where("MONTH(CONVERT_TZ(used_at, '+00:00', '{$tzOffset}')) = {$month}", null, false)
                           ->where("YEAR(CONVERT_TZ(used_at, '+00:00', '{$tzOffset}')) = {$year}", null, false);
        }

        $voucherBuilder->groupBy('date')->orderBy('date', 'ASC');
        $voucherUsage = $voucherBuilder->get()->getResultArray();

        $voucherLabels = array_column($voucherUsage, 'date');
        $voucherValues = array_map('intval', array_column($voucherUsage, 'total'));

        // --- Recent Payments (with client username & package name) ---
        $recentTransactions = $this->paymentsModel
            ->select('payments.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = payments.client_id', 'left')
            ->join('packages', 'packages.id = payments.package_id', 'left')
            ->orderBy('payments.created_at', 'DESC')
            ->limit(8)
            ->findAll();

        // --- Recent Clients ---
        $recentClients = $this->clientModel
            ->orderBy('created_at', 'DESC')
            ->limit(8)
            ->findAll();

        // --- Expiring subscriptions within 24 hours (active) ---
        $expiringBuilder = $this->subscriptionModel->builder();
        $expiringBuilder->select('subscriptions.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = subscriptions.client_id', 'left')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->where('subscriptions.status', 'active');

        if ($tzOffset === '0') {
            // compare expires_on in DB timezone (assumed server timezone)
            $expiringBuilder->where('expires_on <=', date('Y-m-d H:i:s', strtotime('+24 hours')));
        } else {
            // convert expires_on (assumed stored in UTC) to local offset for comparison
            // but since expires_on is a DATETIME stored in DB, comparing with server NOW() is simpler:
            // We'll compute the server's local 'now' in UTC then convert as numeric offset isn't needed for <= comparison.
            // Simpler approach: compare raw expires_on to UTC NOW + offsetSeconds — but for readability use DB function:
            $expiringBuilder->where("CONVERT_TZ(expires_on, '+00:00', '{$tzOffset}') <= CONVERT_TZ(NOW(), @@session.time_zone, '{$tzOffset}') + INTERVAL 24 HOUR", null, false);
            // However some MySQL versions don't allow arithmetic like that; fallback to comparing with PHP computed UTC time:
            // We'll instead compute the target datetime in PHP and use it:
            $target = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $expiringBuilder->where('expires_on <=', $target);
        }

        $expiringBuilder->orderBy('expires_on', 'ASC');
        $expiringSoon = $expiringBuilder->get()->getResultArray();

        // --- Recent Subscriptions & Recently Expired/Cancelled ---
        $recentSubscriptions = $this->subscriptionModel
            ->select('subscriptions.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = subscriptions.client_id', 'left')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->orderBy('subscriptions.created_at', 'DESC')
            ->limit(8)
            ->findAll();

        $recentlyExpired = $this->subscriptionModel
            ->select('subscriptions.*, clients.username AS client_username, packages.name AS package_name')
            ->join('clients', 'clients.id = subscriptions.client_id', 'left')
            ->join('packages', 'packages.id = subscriptions.package_id', 'left')
            ->whereIn('subscriptions.status', ['expired','cancelled'])
            ->orderBy('subscriptions.expires_on', 'DESC')
            ->limit(8)
            ->findAll();

        // Pass variables to view (same keys as previously expected)
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

    /**
     * Detect DB/server timezone offset relative to UTC in "+HH:MM" or "-HH:MM" format.
     * Returns '0' on failure (meaning "don't apply CONVERT_TZ").
     */
    protected function detectDbTimezoneOffset($db)
    {
        try {
            // TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) gives offset in seconds between UTC and server time
            $row = $db->query("SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS diff_seconds")->getRow();
            if (! $row || ! isset($row->diff_seconds)) {
                return '0';
            }

            $diffSec = (int) $row->diff_seconds;

            // Build sign +HH:MM
            $sign = $diffSec < 0 ? '-' : '+';
            $diffSec = abs($diffSec);
            $hours = floor($diffSec / 3600);
            $minutes = floor(($diffSec % 3600) / 60);

            // format as "+03:00" or "-05:30"
            return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
        } catch (\Throwable $e) {
            // If anything fails, return '0' (no conversion)
            return '0';
        }
    }
}
