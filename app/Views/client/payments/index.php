<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-5">
  <h3>Buy a Package</h3>
  <?= view('templates/alerts') ?>

  <?php if (empty($packages)): ?>
    <div class="alert alert-info mt-3">No packages available at the moment.</div>
  <?php else: ?>
    <div class="row mt-4">
      <?php foreach ($packages as $pkg): ?>
        <div class="col-md-4 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title"><?= esc($pkg['name']) ?></h5>
              <p class="card-text">
                Type: <?= esc($pkg['type']) ?><br>
                Duration: <?= esc($pkg['duration']) ?><br>
                Devices: <?= esc($pkg['devices']) ?><br>
                <strong>Price: Ksh <?= esc($pkg['price']) ?></strong>
              </p>
              <a href="<?= base_url('/client/payments/buy/' . $pkg['id']) ?>" class="btn btn-success">Buy Now</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
