<?= $this->extend('layouts/client_layout') ?>

<?= $this->section('content') ?>
<div class="container mt-5" style="max-width:400px;">
  <h4>Client Login</h4>
  <?= view('templates/alerts') ?>

  <form action="<?= base_url('client/login-post') ?>" method="post">
    <div class="mb-3">
      <label>User Name</label>
      <input type="text" name="username" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary w-100">Login</button>
  </form>

  <p class="mt-3 text-center">
    Don’t have an account? <a href="<?= base_url('client/register') ?>">Register</a>
  </p>
</div>
<?= $this->endSection() ?>
