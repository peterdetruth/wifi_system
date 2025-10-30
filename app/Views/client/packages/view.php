<?= $this->extend('layouts/client_layout') ?>

<?= $this->section('content') ?>

<div class="container mt-4">
  <h3 class="mb-4"><?= esc($package['name']) ?> Package</h3>

  <?= view('templates/alerts') ?>

  <div class="card p-4 shadow-sm">
    <p><strong>Type:</strong> <?= esc(ucfirst($package['type'])) ?></p>
    <p><strong>Duration:</strong> <?= esc($package['duration_length']) . ' ' . esc(ucfirst($package['duration_unit'])) ?></p>
    <p><strong>Router:</strong> <?= esc($package['router_name'] ?? 'N/A') ?></p>
    <p><strong>Devices Allowed:</strong> <?= esc($package['hotspot_devices']) ?></p>
    <p><strong>Price:</strong> Ksh <?= number_format($package['price'], 2) ?></p>

    <div class="d-flex gap-2">
      <a href="<?= base_url('client/packages/subscribe/' . $package['id']) ?>" class="btn btn-success mt-3">
        Subscribe Now
      </a>
      <a href="<?= base_url('client/vouchers/redeem') ?>" class="btn btn-outline-primary mt-3">
        Redeem Voucher
      </a>
    </div>
  </div>

  <a href="<?= base_url('client/packages') ?>" class="btn btn-link mt-3">← Back to Packages</a>
</div>

<?= $this->endSection() ?>
