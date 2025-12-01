<?php
// $subscriptions: array of subscriptions
// $subscriptionsPager: CI4 Pager instance
// $clientId: client id
?>
<table class="table table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>Payment ID</th>
      <th>Package</th>
      <th>Router</th>
      <th>Status</th>
      <th>Start Date</th>
      <th>Expiry Date</th>
      <th>Time Remaining</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($subscriptions as $sub): ?>
      <tr>
        <td><?= esc($sub['id']) ?></td>
        <td><?= esc($sub['payment_id']) ?></td>
        <td><?= esc($sub['package_name'] ?? 'N/A') ?></td>
        <td><?= esc($sub['router_name'] ?? 'N/A') ?></td>
        <td>
            <span class="badge <?= $sub['status']=='active'?'bg-success':'bg-secondary' ?>">
                <?= esc(ucfirst($sub['status'])) ?>
            </span>
        </td>
        <td><?= esc($sub['start_date']) ?></td>
        <td><?= esc($sub['expires_on']) ?></td>
        <td>
            <?php if ($sub['status'] == 'active' && strtotime($sub['expires_on']) > time()): ?>
                <span class="countdown" data-expires="<?= esc($sub['expires_on']) ?>"></span>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="mt-2 d-flex justify-content-center">
    <?= $subscriptionsPager->links('subscriptions', 'default_full') ?>
</div>

<script>
// Countdown Timer
document.querySelectorAll('.countdown').forEach(function(el) {
    function updateCountdown() {
        const expires = new Date(el.dataset.expires).getTime();
        const now = new Date().getTime();
        let diff = expires - now;

        if (diff <= 0) {
            el.innerText = 'Expired';
            return;
        }

        const days = Math.floor(diff / (1000*60*60*24));
        const hours = Math.floor((diff % (1000*60*60*24)) / (1000*60*60));
        const minutes = Math.floor((diff % (1000*60*60)) / (1000*60));
        const seconds = Math.floor((diff % (1000*60)) / 1000);

        el.innerText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>
