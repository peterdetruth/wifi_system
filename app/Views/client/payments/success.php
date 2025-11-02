<?= $this->extend('client/layout') ?>
<?= $this->section('content') ?>

<div class="container mt-5">
  <div class="card shadow p-4 text-center">
    <h2 class="text-success mb-3"><i class="bi bi-check-circle-fill"></i> Payment Successful!</h2>
    <p class="text-muted mb-4">Thank you for your payment. Your subscription is now active.</p>

    <div class="border rounded p-3 mb-4 bg-light">
      <h5 class="fw-bold"><?= esc($package['name'] ?? 'Unknown Package') ?></h5>
      <p class="mb-1">Duration: <?= esc($package['duration_length'] . ' ' . $package['duration_unit']) ?></p>
      <p class="mb-1">Amount: KES <?= number_format($package['price'], 2) ?></p>
      <?php if (!empty($subscription['expires_on'])): ?>
        <p class="mb-1">Expiry: <?= date('d M Y, H:i', strtotime($subscription['expires_on'])) ?></p>
      <?php else: ?>
        <p class="mb-1 text-muted">Expiry date will appear once your subscription is activated.</p>
      <?php endif; ?>
    </div>

    <a href="<?= site_url('client/subscriptions') ?>" class="btn btn-primary">Go to My Subscriptions</a>
  </div>
</div>

<?= $this->endSection() ?>
