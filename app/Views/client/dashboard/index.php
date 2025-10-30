<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Welcome, <?= esc(session()->get('client_username')) ?></h3>
<?= view('templates/alerts') ?>

<!-- ====== Redeem Voucher Form ====== -->
<div class="card mt-3 mb-4">
    <div class="card-body">
        <h5>Redeem a Voucher</h5>
        <form action="<?= base_url('client/vouchers/redeem-post') ?>" method="post" class="row g-2">
            <div class="col-md-8">
                <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-success w-100">Redeem</button>
            </div>
        </form>
    </div>
</div>

<!-- ====== Active Subscriptions ====== -->
<div class="card mt-3">
    <div class="card-body">
        <h5>Your Subscriptions</h5>

        <?php if (!empty($subscriptions)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Router</th>
                        <th>Start Date</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): 
                        $expiryTimestamp = strtotime($sub['expires_on']);
                        $isExpired = $expiryTimestamp < time();
                        $statusClass = $isExpired ? 'text-danger' : 'text-success';
                        $status = $isExpired ? 'Expired' : ucfirst($sub['status']);
                        $timeLeft = $expiryTimestamp - time();
                        $highlightWarning = (!$isExpired && $timeLeft <= 24 * 60 * 60);
                    ?>
                    <tr id="subscription-row-<?= $sub['id'] ?>" class="<?= $highlightWarning ? 'table-warning' : '' ?>">
                        <td><?= esc($sub['package_name']) ?></td>
                        <td><?= esc($sub['router_name'] ?? 'N/A') ?></td>
                        <td><?= date('d M Y H:i', strtotime($sub['start_date'])) ?></td>
                        <td>
                            <?= date('d M Y H:i', $expiryTimestamp) ?>
                            <?php if (!$isExpired): ?>
                                <br><small id="countdown-<?= $sub['id'] ?>" class="text-primary"></small>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $statusClass ?>"><?= $status ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?= base_url('client/subscriptions/view/' . $sub['id']) ?>" class="btn btn-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <?php if ($isExpired): ?>
                                    <a href="<?= base_url('client/packages/view/' . $sub['package_id']) ?>" class="btn btn-success">
                                        <i class="bi bi-arrow-repeat"></i> Renew
                                    </a>
                                <?php elseif ($sub['status'] === 'active'): ?>
                                    <a href="<?= base_url('client/subscriptions/reconnect/' . $sub['id']) ?>" class="btn btn-info">
                                        <i class="bi bi-wifi"></i> Reconnect
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted">You have no active subscriptions.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ====== Redeemed Vouchers History ====== -->
<div class="card mt-4">
    <div class="card-body">
        <h5>Your Redeemed Vouchers</h5>

        <?php if (!empty($vouchers)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Redeemed On</th>
                        <th>Expires On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vouchers as $voucher):
                        $expired = $voucher['expires_on'] && strtotime($voucher['expires_on']) < time();
                    ?>
                    <tr class="<?= $expired ? 'table-danger' : '' ?>">
                        <td><?= esc($voucher['code']) ?></td>
                        <td><?= esc($voucher['package_name'] ?? 'N/A') ?></td>
                        <td><?= ucfirst($voucher['status']) ?></td>
                        <td><?= $voucher['used_at'] ? date('d M Y H:i', strtotime($voucher['used_at'])) : '-' ?></td>
                        <td><?= $voucher['expires_on'] ? date('d M Y H:i', strtotime($voucher['expires_on'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted">You have not redeemed any vouchers yet.</p>
        <?php endif; ?>
    </div>
</div>

<script>
<?php foreach ($subscriptions as $sub): 
    $expiryTimestamp = strtotime($sub['expires_on']);
    if ($expiryTimestamp > time()):
        $timeLeft = $expiryTimestamp - time();
        $highlightWarning = $timeLeft <= 24 * 60 * 60;
?>
(function() {
    const row = document.querySelector('#subscription-row-<?= $sub['id'] ?>');
    const countdownEl = document.getElementById('countdown-<?= $sub['id'] ?>');
    const expiry = new Date("<?= date('Y-m-d H:i:s', $expiryTimestamp) ?>").getTime();

    <?php if ($highlightWarning): ?>
    row.classList.add('table-warning');
    <?php endif; ?>

    const interval = setInterval(function() {
        const now = new Date().getTime();
        const distance = expiry - now;

        if (distance <= 0) {
            countdownEl.innerHTML = 'Expired';
            countdownEl.classList.remove('text-primary');
            countdownEl.classList.add('text-danger');
            row.classList.remove('table-warning');
            clearInterval(interval);
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        countdownEl.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s left`;
    }, 1000);
})();
<?php endif; endforeach; ?>
</script>

<?= $this->endSection() ?>
