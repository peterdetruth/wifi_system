<?= $this->extend('layouts/client_layout') ?>

<?= $this->section('content') ?>

<div class="container mt-4">
  <h3 class="mb-4">Transaction Details</h3>

  <?= view('templates/alerts') ?>

  <div class="card shadow-sm p-4">
    <p><strong>Package Type:</strong> <?= esc(ucfirst($transaction['package_type'])) ?></p>
    <p><strong>Package Length:</strong> <?= esc($transaction['package_length']) ?></p>
    <p><strong>Amount:</strong> Ksh <?= number_format($transaction['amount'], 2) ?></p>
    <p><strong>Status:</strong>
      <?php if ($transaction['status'] === 'success'): ?>
        <span class="badge bg-success">Active</span>
      <?php elseif ($transaction['status'] === 'pending'): ?>
        <span class="badge bg-warning text-dark">Pending</span>
      <?php else: ?>
        <span class="badge bg-danger">Failed</span>
      <?php endif; ?>
    </p>
    <p><strong>Payment Method:</strong> <?= esc(ucfirst($transaction['method'])) ?></p>
    <p><strong>MPESA Code:</strong> <?= esc($transaction['mpesa_code'] ?? '-') ?></p>
    <p><strong>Created On:</strong> <?= esc($transaction['created_on']) ?></p>
    <p><strong>Expires On:</strong> <?= esc($transaction['expires_on'] ?? '-') ?></p>
  </div>

  <a href="<?= base_url('client/transactions') ?>" class="btn btn-link mt-3">← Back to Transactions</a>
</div>

<?= $this->endSection() ?>
