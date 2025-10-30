<?= $this->extend('layouts/client_layout') ?>

<?= $this->section('content') ?>

<div class="container mt-4">
  <h3 class="mb-4">My Profile</h3>

  <?= view('templates/alerts') ?>

  <form action="<?= base_url('client/profile/update') ?>" method="post" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="full_name" class="form-label">Full Name</label>
      <input type="text" id="full_name" name="full_name" value="<?= esc($client['full_name'] ?? '') ?>" class="form-control" required>
    </div>

    <div class="mb-3">
      <label for="email" class="form-label">Email</label>
      <input type="email" id="email" name="email" value="<?= esc($client['email'] ?? '') ?>" class="form-control" required>
    </div>

    <div class="mb-3">
      <label for="phone" class="form-label">Phone</label>
      <input type="text" id="phone" name="phone" value="<?= esc($client['phone'] ?? '') ?>" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary mt-2">Update Profile</button>
  </form>
</div>

<?= $this->endSection() ?>
