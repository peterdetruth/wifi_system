<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Admin Login</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/login') ?>" method="post" class="mt-4">
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" required class="form-control">
  </div>

  <div class="mb-3">
    <label class="form-label">Password</label>
    <input type="password" name="password" required class="form-control">
  </div>

  <button type="submit" class="btn btn-success">Login</button>
</form>

<?= $this->endSection() ?>
