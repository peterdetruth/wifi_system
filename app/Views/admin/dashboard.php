 3<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid py-3">
  <h3 class="mb-4">Admin Dashboard</h3>

  <!-- Metric Cards -->
  <div class="row g-3 mb-4">
    <?php
      $metrics = [
        ['title' => 'Clients', 'value' => $totalClients, 'color' => 'primary'],
        ['title' => 'Packages', 'value' => $totalPackages, 'color' => 'success'],
        ['title' => 'Transactions', 'value' => $totalTransactions, 'color' => 'warning'],
        ['title' => 'Vouchers', 'value' => $totalVouchers, 'color' => 'info'],
      ];
    ?>
    <?php foreach ($metrics as $m): ?>
    <div class="col-md-3">
      <div class="card text-bg-<?= $m['color'] ?> shadow-sm text-center">
        <div class="card-body">
          <h6><?= $m['title'] ?></h6>
          <h2 class="count" data-target="<?= $m['value'] ?>">0</h2>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Row -->
  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Revenue (Last 7 Days)</strong></div>
        <div class="card-body"><canvas id="revenueChart"></canvas></div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Voucher Usage (Last 7 Days)</strong></div>
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
            <td><?= esc($s['client_id']) ?></td>
            <td><?= esc($s['package_id']) ?></td>
            <td><?= date('d M Y, H:i', strtotime($s['expires_on'])) ?></td>
            <td>
              <span class="countdown" data-expiry="<?= $s['expires_on'] ?>"></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="row g-4">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Recent Transactions</strong></div>
        <div class="card-body p-0">
          <?php if (empty($recentTransactions)): ?>
            <p class="p-3 text-muted">No transactions yet.</p>
          <?php else: ?>
          <table class="table table-striped mb-0">
            <thead>
              <tr><th>ID</th><th>Client</th><th>Amount</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recentTransactions as $t): ?>
              <tr>
                <td>#<?= $t['id'] ?></td>
                <td><?= esc($t['client_id']) ?></td>
                <td>KES <?= number_format($t['amount'], 2) ?></td>
                <td><span class="badge <?= ($t['status'] === 'success') ? 'bg-success' : (($t['status'] === 'failed') ? 'bg-danger' : 'bg-warning text-dark') ?>">
                    <?= ucfirst($t['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm">
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
                <td><?= $c['id'] ?></td>
                <td><?= esc($c['username']) ?></td>
                <td><?= esc($c['email']) ?></td>
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
</div>

<!-- JS -->
<script src="<?= base_url('assets/js/chart.js') ?>"></script>
<script>
// Count Animation
document.querySelectorAll('.count').forEach(el => {
  const target = +el.getAttribute('data-target');
  const step = target / 60;
  let count = 0;
  const interval = setInterval(() => {
    count += step;
    if (count >= target) { el.innerText = target; clearInterval(interval); }
    else el.innerText = Math.ceil(count);
  }, 20);
});

// Charts
const revCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'KES Collected',
      data: <?= json_encode($chartValues) ?>,
      borderColor: '#007bff',
      fill: true,
      tension: 0.4
    }]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

const vouchCtx = document.getElementById('voucherChart').getContext('2d');
new Chart(vouchCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($voucherLabels) ?>,
    datasets: [{
      label: 'Vouchers Redeemed',
      data: <?= json_encode($voucherValues) ?>,
      backgroundColor: '#17a2b8'
    }]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

// Countdown timers
function updateCountdowns() {
  document.querySelectorAll('.countdown').forEach(el => {
    const expiry = new Date(el.dataset.expiry).getTime();
    const now = new Date().getTime();
    const diff = expiry - now;

    if (diff <= 0) {
      el.innerHTML = '<span class="text-danger fw-bold">Expired</span>';
    } else {
      const h = Math.floor(diff / (1000*60*60));
      const m = Math.floor((diff % (1000*60*60)) / (1000*60));
      el.innerHTML = `<span class="text-warning">${h}h ${m}m left</span>`;
    }
  });
}
setInterval(updateCountdowns, 60000);
updateCountdowns();
</script>

<?= $this->endSection() ?>
