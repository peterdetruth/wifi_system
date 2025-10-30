<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Create Access Code</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/vouchers/store') ?>" method="post">
  <div class="mb-3">
    <label class="form-label">Purpose</label>
    <select name="purpose" required class="form-control">
      <option value="compensation">Compensation</option>
      <option value="promotion">Promotion</option>
      <option value="new_customer">New Customer</option>
      <option value="loyalty_reward">Loyalty Reward</option>
      <option value="trial">Trial</option>
      <option value="gift">Gift</option>
      <option value="general">General</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Router</label>
    <select name="router_id" required class="form-control">
      <option value="">Select Router</option>
      <?php if (!empty($routers)): ?>
        <?php foreach ($routers as $router): ?>
          <option value="<?= $router['id'] ?>">
            <?= esc($router['name']) ?> (<?= esc($router['ip_address']) ?>)
          </option>
        <?php endforeach; ?>
      <?php else: ?>
        <option disabled>No routers found</option>
      <?php endif; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Package</label>
    <select name="package_id" required class="form-control">
      <option value="">Select Package</option>
      <?php if (!empty($packages)): ?>
        <?php foreach ($packages as $package): ?>
          <option value="<?= $package['id'] ?>">
            <?= esc($package['type']) ?> - <?= esc($package['duration']) ?> @ Ksh <?= esc($package['price']) ?>
          </option>
        <?php endforeach; ?>
      <?php else: ?>
        <option disabled>No packages found</option>
      <?php endif; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Client Phone</label>
    <input type="text" name="phone" required class="form-control">
  </div>

  <button type="submit" class="btn btn-success mt-3">Generate Voucher</button>
</form>

<?= $this->endSection() ?>
