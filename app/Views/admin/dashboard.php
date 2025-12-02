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
    <div class="container-fluid py-4">

        <h3 class="mb-4 text-primary">Admin Dashboard</h3>

        <!-- KPI Cards (unchanged) -->
        <div class="row g-3 mb-4">
            <?php 
            $kpis = [
                ['title' => 'Clients', 'value' => $totalClients, 'bg' => 'bg-primary text-white'],
                ['title' => 'Packages', 'value' => $totalPackages, 'bg' => 'bg-info text-white'],
                ['title' => 'Revenue (KES)', 'value' => number_format($totalRevenue,2), 'bg' => 'bg-success text-white'],
                ['title' => 'Active Subscriptions', 'value' => $activeSubscriptions, 'bg' => 'bg-warning text-dark']
            ];
            foreach ($kpis as $kpi):
            ?>
                <div class="col-md-3">
                    <div class="card shadow-sm <?= $kpi['bg'] ?> rounded-3">
                        <div class="card-body text-center">
                            <h6 class="mb-2"><?= esc($kpi['title']) ?></h6>
                            <h2 class="count" data-target="<?= esc($kpi['value']) ?>">0</h2>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Secondary KPIs (unchanged) -->
        <div class="row g-3 mb-4">
            <?php 
            $secondaryKpis = [
                ['title'=>'New Subs Today', 'value'=>$newSubscriptionsToday,'bg'=>'bg-light'],
                ['title'=>'New Subs Month', 'value'=>$newSubscriptionsMonth,'bg'=>'bg-light'],
                ['title'=>'Pending Payments','value'=>$pendingPayments,'bg'=>'bg-light'],
                ['title'=>'Inactive Users','value'=>$inactiveUsers,'bg'=>'bg-light']
            ];
            foreach($secondaryKpis as $kpi):
            ?>
                <div class="col-md-3">
                    <div class="card shadow-sm <?= $kpi['bg'] ?> rounded-3">
                        <div class="card-body text-center">
                            <h6 class="mb-1"><?= esc($kpi['title']) ?></h6>
                            <div class="h4"><?= esc($kpi['value']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts (unchanged) -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm rounded-3">
                    <div class="card-header bg-white border-bottom"><strong>Revenue (<?= esc($month) ?>/<?= esc($year) ?>)</strong></div>
                    <div class="card-body">
                        <canvas id="revenueChart" style="width:100%; height:250px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm rounded-3">
                    <div class="card-header bg-white border-bottom"><strong>Voucher Usage (<?= esc($month) ?>/<?= esc($year) ?>)</strong></div>
                    <div class="card-body">
                        <canvas id="voucherChart" style="width:100%; height:250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <?php 
        $tables = [
            ['title'=>'Expiring Within 24 Hours','data'=>$expiringSoon,'columns'=>['#','Client','Package','Expires On','Countdown'],'class'=>'table-danger'],
            ['title'=>'Recent Payments','data'=>$recentTransactions,'columns'=>['ID','Client','Package','Amount','Status'],'class'=>'table-striped'],
            ['title'=>'Recent Clients','data'=>$recentClients,'columns'=>['#','Username','Email','Joined'],'class'=>'table-striped'],
            ['title'=>'Recent Subscriptions','data'=>$recentSubscriptions,'columns'=>['#','Client','Package','Status','Created At'],'class'=>'table-striped'],
            ['title'=>'Recently Expired / Cancelled','data'=>$recentlyExpired,'columns'=>['#','Client','Package','Status','Expires On'],'class'=>'table-striped'],
        ];
        ?>

        <?php foreach($tables as $t): ?>
            <div class="card shadow-sm mb-4 rounded-3">
                <div class="card-header <?= $t['title']=='Expiring Within 24 Hours'?'bg-danger text-white':'bg-white border-bottom' ?>"><strong><?= $t['title'] ?></strong></div>
                <div class="card-body p-0">
                    <?php if(empty($t['data'])): ?>
                        <p class="p-3 text-muted">No records available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table <?= $t['class'] ?> table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <?php foreach($t['columns'] as $col): ?>
                                            <th><?= $col ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i=1; foreach($t['data'] as $row): ?>
                                        <tr>
                                            <?php
                                            // Dynamic row rendering based on table type
                                            if($t['title']=='Expiring Within 24 Hours'): ?>
                                                <td><?= $i++ ?></td>
                                                <td><?= esc($row['client_username']) ?></td>
                                                <td><?= esc($row['package_name']) ?></td>
                                                <td><?= date('d M Y, H:i', strtotime($row['expires_on'])) ?></td>
                                                <td><span class="countdown" data-expiry="<?= esc($row['expires_on']) ?>"></span></td>
                                            <?php elseif($t['title']=='Recent Payments'): ?>
                                                <td>#<?= esc($row['id']) ?></td>
                                                <td><?= esc($row['client_username']) ?></td>
                                                <td><?= esc($row['package_name']) ?></td>
                                                <td>KES <?= number_format($row['amount'],2) ?></td>
                                                <td>
                                                    <span class="badge <?= $row['status']==='completed'?'bg-success':($row['status']==='failed'?'bg-danger':'bg-warning text-dark') ?>">
                                                        <?= ucfirst(esc($row['status'])) ?>
                                                    </span>
                                                </td>
                                            <?php elseif($t['title']=='Recent Clients'): ?>
                                                <td><?= esc($row['id']) ?></td>
                                                <td><?= esc($row['username']) ?></td>
                                                <td><?= esc($row['email'] ?? '') ?></td>
                                                <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                            <?php elseif($t['title']=='Recent Subscriptions'): ?>
                                                <td><?= esc($row['id']) ?></td>
                                                <td><?= esc($row['client_username']) ?></td>
                                                <td><?= esc($row['package_name']) ?></td>
                                                <td><?= ucfirst(esc($row['status'])) ?></td>
                                                <td><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
                                            <?php elseif($t['title']=='Recently Expired / Cancelled'): ?>
                                                <td><?= esc($row['id']) ?></td>
                                                <td><?= esc($row['client_username']) ?></td>
                                                <td><?= esc($row['package_name']) ?></td>
                                                <td><?= ucfirst(esc($row['status'])) ?></td>
                                                <td><?= date('d M Y, H:i', strtotime($row['expires_on'])) ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</div>

<?= $this->endSection() ?>
