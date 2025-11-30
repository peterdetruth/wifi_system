<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<?php
$chartLabelsJson = htmlspecialchars(json_encode($chartLabels ?? []), ENT_QUOTES, 'UTF-8');
$chartValuesJson = htmlspecialchars(json_encode($chartValues ?? []), ENT_QUOTES, 'UTF-8');
$voucherLabelsJson = htmlspecialchars(json_encode($voucherLabels ?? []), ENT_QUOTES, 'UTF-8');
$voucherValuesJson = htmlspecialchars(json_encode($voucherValues ?? []), ENT_QUOTES, 'UTF-8');
?>

<div id="dashboard"
     data-chart-labels='<?= $chartLabelsJson ?>'
     data-chart-values='<?= $chartValuesJson ?>'
     data-voucher-labels='<?= $voucherLabelsJson ?>'
     data-voucher-values='<?= $voucherValuesJson ?>'
     data-total-clients="<?= esc($totalClients ?? 0) ?>"
     data-total-packages="<?= esc($totalPackages ?? 0) ?>"
     data-total-revenue="<?= esc(number_format($totalRevenue ?? 0, 2)) ?>"
     data-active-subs="<?= esc($activeSubscriptions ?? 0) ?>"
     data-new-subs-today="<?= esc($newSubscriptionsToday ?? 0) ?>"
     data-new-subs-month="<?= esc($newSubscriptionsMonth ?? 0) ?>"
     data-pending-payments="<?= esc($pendingPayments ?? 0) ?>"
     data-inactive-users="<?= esc($inactiveUsers ?? 0) ?>"
     data-month="<?= esc($month ?? date('m')) ?>"
     data-year="<?= esc($year ?? date('Y')) ?>"
>
    <div class="container-fluid py-3">
        <h3 class="mb-4">Admin Dashboard</h3>

        <!-- KPI Cards (unchanged) -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body text-center">
                        <h6>Clients</h6>
                        <h2 class="count" data-target="<?= esc($totalClients ?? 0) ?>">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body text-center">
                        <h6>Packages</h6>
                        <h2 class="count" data-target="<?= esc($totalPackages ?? 0) ?>">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body text-center">
                        <h6>Revenue (KES)</h6>
                        <h2 class="count" data-target="<?= esc($totalRevenue ?? 0) ?>"><?= number_format($totalRevenue ?? 0, 2) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body text-center">
                        <h6>Active Subscriptions</h6>
                        <h2 class="count" data-target="<?= esc($activeSubscriptions ?? 0) ?>">0</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary KPIs (unchanged) -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card kpi-card-alt">
                    <div class="card-body text-center">
                        <h6>New Subscriptions (Today)</h6>
                        <div class="kpi-small"><?= esc($newSubscriptionsToday ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card-alt">
                    <div class="card-body text-center">
                        <h6>New Subscriptions (Month)</h6>
                        <div class="kpi-small"><?= esc($newSubscriptionsMonth ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card-alt">
                    <div class="card-body text-center">
                        <h6>Pending Payments</h6>
                        <div class="kpi-small"><?= esc($pendingPayments ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card-alt">
                    <div class="card-body text-center">
                        <h6>Inactive Users</h6>
                        <div class="kpi-small"><?= esc($inactiveUsers ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts (unchanged) -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light"><strong>Revenue (<?= esc($month ?? date('m')) ?>/<?= esc($year ?? date('Y')) ?>)</strong></div>
                    <div class="card-body"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light"><strong>Voucher Usage (<?= esc($month ?? date('m')) ?>/<?= esc($year ?? date('Y')) ?>)</strong></div>
                    <div class="card-body"><canvas id="voucherChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Expiring Subscriptions -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-danger text-white"><strong>Expiring Within 24 Hours</strong></div>
            <div class="card-body p-0">
                <?php if (empty($expiringSoon)): ?>
                    <p class="p-3 text-muted">No subscriptions expiring soon.</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <thead class="table-danger">
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Expires On</th>
                                <th>Countdown</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($expiringSoon as $s): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= esc($s['client_username']) ?></td>
                                    <td><?= esc($s['package_name']) ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($s['expires_on'])) ?></td>
                                    <td><span class="countdown" data-expiry="<?= esc($s['expires_on']) ?>"></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light"><strong>Recent Payments</strong></div>
                    <div class="card-body p-0">
                        <?php if (empty($recentTransactions)): ?>
                            <p class="p-3 text-muted">No payments yet.</p>
                        <?php else: ?>
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Package</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $t): ?>
                                        <tr>
                                            <td>#<?= esc($t['id']) ?></td>
                                            <td><?= esc($t['client_username']) ?></td>
                                            <td><?= esc($t['package_name']) ?></td>
                                            <td>KES <?= number_format($t['amount'], 2) ?></td>
                                            <td>
                                                <span class="badge <?= ($t['status'] === 'completed') ? 'bg-success' : (($t['status'] === 'failed') ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                                    <?= ucfirst(esc($t['status'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Clients -->
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light"><strong>Recent Clients</strong></div>
                    <div class="card-body p-0">
                        <?php if (empty($recentClients)): ?>
                            <p class="p-3 text-muted">No clients yet.</p>
                        <?php else: ?>
                            <table class="table table-striped mb-0">
                                <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Joined</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentClients as $c): ?>
                                        <tr>
                                            <td><?= esc($c['id']) ?></td>
                                            <td><?= esc($c['username']) ?></td>
                                            <td><?= esc($c['email'] ?? '') ?></td>
                                            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Subscriptions -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light"><strong>Recent Subscriptions</strong></div>
                    <div class="card-body p-0">
                        <?php if (empty($recentSubscriptions)): ?>
                            <p class="p-3 text-muted">No subscriptions yet.</p>
                        <?php else: ?>
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Client</th>
                                        <th>Package</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSubscriptions as $s): ?>
                                        <tr>
                                            <td><?= esc($s['id']) ?></td>
                                            <td><?= esc($s['client_username']) ?></td>
                                            <td><?= esc($s['package_name']) ?></td>
                                            <td><?= ucfirst(esc($s['status'])) ?></td>
                                            <td><?= date('d M Y, H:i', strtotime($s['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recently Expired / Cancelled Subscriptions -->
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light"><strong>Recently Expired / Cancelled</strong></div>
                    <div class="card-body p-0">
                        <?php if (empty($recentlyExpired)): ?>
                            <p class="p-3 text-muted">No recently expired or cancelled subscriptions.</p>
                        <?php else: ?>
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Client</th>
                                        <th>Package</th>
                                        <th>Status</th>
                                        <th>Expires On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentlyExpired as $s): ?>
                                        <tr>
                                            <td><?= esc($s['id']) ?></td>
                                            <td><?= esc($s['client_username']) ?></td>
                                            <td><?= esc($s['package_name']) ?></td>
                                            <td><?= ucfirst(esc($s['status'])) ?></td>
                                            <td><?= date('d M Y, H:i', strtotime($s['expires_on'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
