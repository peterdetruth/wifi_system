<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-5">
  <div class="card shadow p-4 text-center">
    <h2 class="text-success mb-3"><i class="bi bi-check-circle-fill"></i> Payment Successful!</h2>
    <p class="text-muted mb-4">Thank you for your payment. Your subscription is now active.</p>

    <!-- Package Info -->
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

    <!-- Router Credentials -->
    <?php if (!empty($router_sync) && isset($router_sync['success']) && $router_sync['success']): ?>
      <div class="border rounded p-3 mb-4 bg-light">
        <h5 class="fw-bold text-primary">Your Router Credentials</h5>
        <p class="mb-1"><strong>Username:</strong> <?= esc($router_sync['username'] ?? '') ?></p>
        <p class="mb-1"><strong>Password:</strong> <?= esc($router_sync['password'] ?? '') ?></p>
        <p class="mb-1 text-muted">Use these credentials to connect to your Wi-Fi or PPPoE service.</p>
      </div>
    <?php elseif (!empty($router_sync) && isset($router_sync['success']) && !$router_sync['success']): ?>
      <div class="alert alert-warning mb-4">
        <strong>Notice:</strong> Router provisioning failed: <?= esc($router_sync['message'] ?? 'Unknown error') ?>.<br>
        Please contact support to get your credentials.
      </div>
    <?php endif; ?>

    <a href="<?= site_url('client/subscriptions') ?>" class="btn btn-primary">Go to My Subscriptions</a>
  </div>
</div>

<?= $this->endSection() ?>
