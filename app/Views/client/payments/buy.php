<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-5">
  <h3>Purchase Package</h3>
  <?= view('templates/alerts') ?>

  <div class="card col-md-6 mt-4">
    <div class="card-body">
      <h5><?= esc($package['name']) ?></h5>
      <p>
        Type: <?= esc($package['type']) ?><br>
        Duration: <?= esc($package['duration']) ?><br>
        Devices: <?= esc($package['devices']) ?><br>
        <strong>Price: Ksh <?= esc($package['price']) ?></strong>
      </p>

      <form action="<?= base_url('/client/payments/process') ?>" method="post">
        <input type="hidden" name="package_id" value="<?= $package['id'] ?>">

        <div class="mb-3">
          <label class="form-label">Payment Method</label>
          <select name="method" class="form-select" required>
            <option value="mpesa">M-Pesa</option>
            <option value="cash">Cash (Simulation)</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">Complete Payment</button>
        <a href="<?= base_url('/client/payments') ?>" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
