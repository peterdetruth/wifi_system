<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Edit Client</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/clients/update/'.$client['id']) ?>" method="post">
  <label>Full Name</label>
  <input type="text" name="full_name" value="<?= esc($client['full_name']) ?>" class="form-control" required>

  <label>Username</label>
  <input type="text" name="username" value="<?= esc($client['username']) ?>" class="form-control" required>

  <label>Email</label>
  <input type="email" name="email" value="<?= esc($client['email']) ?>" class="form-control">

  <label>Phone</label>
  <input type="text" name="phone" value="<?= esc($client['phone']) ?>" class="form-control">

  <label>Status</label>
  <select name="status" class="form-control">
    <option value="active" <?= $client['status']=='active'?'selected':'' ?>>Active</option>
    <option value="inactive" <?= $client['status']=='inactive'?'selected':'' ?>>Inactive</option>
  </select>

  <button type="submit" class="btn btn-primary mt-3">Update</button>
</form>

<?= $this->endSection() ?>

