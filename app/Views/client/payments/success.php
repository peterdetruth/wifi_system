<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-5">
  <div class="card shadow-lg border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Payment Successful</h4>
      <a href="<?= base_url('client/dashboard') ?>" class="btn btn-light btn-sm">Go to Dashboard</a>
    </div>

    <div class="card-body">
      <div class="text-center mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
        <h5 class="mt-3">Thank you! Your payment was processed successfully.</h5>
      </div>

      <?php if (isset($transaction)): ?>
      <div class="border rounded p-3 bg-light">
        <h6 class="mb-3 text-secondary">Transaction Summary</h6>
        <table class="table table-sm table-borderless mb-0">
          <tr><th>Package</th><td><?= esc($transaction['package_name'] ?? '-') ?></td></tr>
          <tr><th>Amount</th><td>KES <?= number_format($transaction['amount'] ?? 0, 2) ?></td></tr>
          <tr><th>Phone</th><td><?= esc($transaction['phone'] ?? '-') ?></td></tr>
          <tr><th>Method</th><td><?= ucfirst($transaction['method'] ?? 'Mpesa') ?></td></tr>
          <tr><th>Mpesa Code</th><td><?= esc($transaction['mpesa_code'] ?? '-') ?></td></tr>
          <tr><th>Date</th><td><?= date('d M Y, H:i', strtotime($transaction['created_on'] ?? 'now')) ?></td></tr>
          <tr><th>Expires On</th><td><?= isset($transaction['expires_on']) ? date('d M Y, H:i', strtotime($transaction['expires_on'])) : '-' ?></td></tr>
        </table>
      </div>
      <?php else: ?>
      <div class="alert alert-warning">No transaction details available.</div>
      <?php endif; ?>

      <div class="mt-4 d-flex justify-content-between">
        <a href="<?= base_url('client/transactions') ?>" class="btn btn-outline-secondary">View Transactions</a>
        <a href="<?= base_url('client/subscriptions') ?>" class="btn btn-success">Go to My Subscriptions</a>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
