<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Redeem Voucher</h3>
<?= view('templates/alerts') ?>

<div class="card mt-3">
  <div class="card-body">
    <form action="<?= base_url('client/vouchers/redeem-post') ?>" method="post">
      <div class="mb-3">
        <label for="voucher_code" class="form-label">Enter Voucher Code</label>
        <input type="text" name="voucher_code" id="voucher_code" class="form-control" placeholder="Enter your voucher code" required>
      </div>

      <button type="submit" class="btn btn-success">Redeem</button>
      <a href="<?= base_url('client/subscriptions') ?>" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
