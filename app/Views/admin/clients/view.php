<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Client Details: <?= esc($client['full_name']) ?></h3>

<?= view('templates/alerts') ?>

<!-- Client Info -->
<div class="card mb-4">
    <div class="card-body">
        <p><strong>Username:</strong> <?= esc($client['username']) ?></p>
        <p><strong>Email:</strong> <?= esc($client['email']) ?></p>
        <p><strong>Phone:</strong> <?= esc($client['phone']) ?></p>
    </div>
</div>

<!-- Subscriptions Table -->
<h4>Subscriptions</h4>
<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>Payment ID</th>
            <th>Package</th>
            <th>Router</th>
            <th>Status</th>
            <th>Start Date</th>
            <th>Expires On</th>
            <th>Time Remaining</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($subscriptions)): ?>
            <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td><?= esc($sub['id']) ?></td>
                    <td><?= esc($sub['payment_id']) ?></td>
                    <td><?= esc($sub['package_name'] ?? 'N/A') ?></td>
                    <td><?= esc($sub['router_name'] ?? 'N/A') ?></td>
                    <td>
                        <span class="badge <?= $sub['status']=='active'?'bg-success':($sub['status']=='expired'?'bg-secondary':'bg-warning') ?>">
                            <?= esc(ucfirst($sub['status'])) ?>
                        </span>
                    </td>
                    <td><?= esc($sub['start_date']) ?></td>
                    <td><?= esc($sub['expires_on']) ?></td>
                    <td>
                        <?php if($sub['status']=='active'): ?>
                            <span class="countdown" data-expire="<?= esc($sub['expires_on']) ?>"></span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" class="text-center">No subscriptions found</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Mpesa Transactions Table -->
<h4 class="mt-4">Mpesa Transactions</h4>
<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>Transaction ID</th>
            <th>Package</th>
            <th>Amount</th>
            <th>Phone Number</th>
            <th>Transaction Date</th>
            <th>Status</th>
            <th>Created</th>
            <th>Mpesa Receipt</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($mpesa_transactions)): ?>
            <?php foreach ($mpesa_transactions as $txn): ?>
                <tr>
                    <td><?= esc($txn['transaction_id']) ?></td>
                    <td><?= esc($txn['package_name'] ?? 'N/A') ?></td>
                    <td><?= esc(number_format($txn['amount'], 2)) ?></td>
                    <td><?= esc($txn['phone_number']) ?></td>
                    <td><?= esc($txn['transaction_date']) ?></td>
                    <td>
                        <span class="badge <?= $txn['status']=='success'?'bg-success':($txn['status']=='failed'?'bg-danger':'bg-warning') ?>">
                            <?= esc(ucfirst($txn['status'])) ?>
                        </span>
                    </td>
                    <td><?= esc($txn['created_at']) ?></td>
                    <td><?= esc($txn['mpesa_receipt']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" class="text-center">No transactions found</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Countdown JS -->
<script>
document.addEventListener("DOMContentLoaded", function(){
    function updateCountdowns(){
        const countdowns = document.querySelectorAll('.countdown');
        countdowns.forEach(function(span){
            const expireTime = new Date(span.dataset.expire).getTime();
            const now = new Date().getTime();
            let distance = expireTime - now;

            if(distance <= 0){
                span.innerHTML = 'Expired';
                span.classList.remove('text-success');
                span.classList.add('text-secondary');
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000*60*60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000*60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            span.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
        });
    }

    updateCountdowns();
    setInterval(updateCountdowns, 1000);
});
</script>

<style>
.table-bordered th, .table-bordered td { border: 1px solid #dee2e6; }
.countdown { font-weight: bold; color: green; }
</style>

<?= $this->endSection() ?>
