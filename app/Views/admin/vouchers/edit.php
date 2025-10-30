<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<h3>Edit Voucher</h3>
<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/vouchers/update/' . $voucher['id']) ?>" method="post">
  <div class="mb-3">
    <label class="form-label">Voucher Code</label>
    <input type="text" class="form-control" value="<?= esc($voucher['code']) ?>" readonly>
  </div>

  <div class="mb-3">
    <label class="form-label">Purpose</label>
    <select name="purpose" required class="form-control">
      <?php
        $purposes = ['compensation','promotion','new_customer','loyalty_reward','trial','gift','general'];
      ?>
      <?php foreach ($purposes as $p): ?>
        <option value="<?= $p ?>" <?= $voucher['purpose'] == $p ? 'selected' : '' ?>>
          <?= ucfirst(str_replace('_',' ', $p)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Router</label>
    <select name="router_id" class="form-control">
      <option value="">-- None --</option>
      <?php foreach ($routers as $router): ?>
        <option value="<?= $router['id'] ?>" <?= $voucher['router_id'] == $router['id'] ? 'selected' : '' ?>>
          <?= esc($router['name']) ?> (<?= esc($router['ip_address']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Package</label>
    <select name="package_id" required class="form-control">
      <option value="">Select Package</option>
      <?php foreach ($packages as $package): ?>
        <option value="<?= $package['id'] ?>" <?= $voucher['package_id'] == $package['id'] ? 'selected' : '' ?>>
          <?= esc($package['name']) ?> - Ksh <?= esc($package['price']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Client Phone</label>
    <input type="text" name="phone" value="<?= esc($voucher['phone']) ?>" class="form-control">
  </div>

  <div class="mb-3">
    <label class="form-label">Expiry Date</label>
    <input type="datetime-local" name="expires_on" value="<?= esc(date('Y-m-d\TH:i', strtotime($voucher['expires_on'] ?? ''))) ?>" class="form-control">
  </div>

  <div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-control">
      <option value="unused" <?= $voucher['status'] == 'unused' ? 'selected' : '' ?>>Unused</option>
      <option value="used" <?= $voucher['status'] == 'used' ? 'selected' : '' ?>>Used</option>
      <option value="inactive" <?= $voucher['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
      <option value="expired" <?= $voucher['status'] == 'expired' ? 'selected' : '' ?>>Expired</option>
    </select>
  </div>

  <button type="submit" class="btn btn-primary">Update Voucher</button>
  <a href="<?= base_url('/admin/vouchers') ?>" class="btn btn-secondary">Cancel</a>
</form>

<?= $this->endSection() ?>
