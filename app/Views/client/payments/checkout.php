<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Checkout - <?= esc($package['name']) ?></h3>
<?= view('templates/alerts') ?>

<div class="card mt-3">
  <div class="card-body">
    <p><strong>Package:</strong> <?= esc($package['name']) ?></p>
    <p><strong>Price:</strong> KES <?= esc(number_format($package['price'],2)) ?></p>
    <p><strong>Duration:</strong> <?= esc($package['duration_length'] . ' ' . $package['duration_unit']) ?></p>

    <form action="<?= base_url('client/payments/process') ?>" method="post">
      <input type="hidden" name="package_id" value="<?= esc($package['id']) ?>">

      <div class="mb-3">
        <label for="voucher_code" class="form-label">Voucher Code (optional)</label>
        <input type="text" name="voucher_code" id="voucher_code" class="form-control" placeholder="Enter voucher code">
      </div>

      <div class="mb-3">
        <label for="phone" class="form-label">Phone Number</label>
        <input type="text" name="phone" id="phone" class="form-control" placeholder="07XXXXXXXX" value="<?= esc(session()->get('phone')) ?>">
      </div>

      <button type="submit" class="btn btn-success">Subscribe / Pay</button>
      <a href="<?= base_url('client/packages') ?>" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
