<?= $this->extend('layouts/client_layout') ?>

<?= $this->section('content') ?>
<div class="container mt-5" style="max-width:400px;">
  <h4>Client Registration</h4>
  <?= view('templates/alerts') ?>

  <form action="<?= base_url('client/register-post') ?>" method="post">
    <div class="mb-3">
      <label>Full Name</label>
      <input type="text" name="fullname" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Phone</label>
      <input type="text" name="phone" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-success w-100">Register</button>
  </form>

  <p class="mt-3 text-center">
    Already have an account? <a href="<?= base_url('client/login') ?>">Login</a>
  </p>
</div>
<?= $this->endSection() ?>
