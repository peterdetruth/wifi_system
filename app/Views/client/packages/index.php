<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Available Packages</h3>
<?= view('templates/alerts') ?>

<div class="mb-3">
  <a href="<?= base_url('client/vouchers/redeem') ?>" class="btn btn-outline-primary">
    <i class="bi bi-gift"></i> Redeem Voucher
  </a>
</div>

<div class="row">
  <?php if (!empty($packages)): ?>
    <?php foreach ($packages as $package): ?>
      <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><?= esc($package['name']) ?></h5>
            <p class="card-text">
              <strong>Type:</strong> <?= esc($package['type']) ?><br>
              <strong>Duration:</strong> <?= esc($package['duration_length']) . ' ' . esc($package['duration_unit']) ?><br>
              <strong>Router:</strong> <?= esc($package['router_name'] ?? 'N/A') ?><br>
              <strong>Devices:</strong> <?= esc($package['hotspot_devices']) ?><br>
              <strong>Price:</strong> <span class="text-success">Ksh <?= esc(number_format($package['price'], 2)) ?></span>
            </p>
            <a href="<?= base_url('client/packages/view/' . $package['id']) ?>" class="btn btn-success w-100">
              View Details
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p>No packages available at the moment.</p>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
