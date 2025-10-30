<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Add New Client</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/clients/store') ?>" method="post">
  <label>Full Name</label>
  <input type="text" name="full_name" class="form-control" required>

  <label>Username</label>
  <input type="text" name="username" class="form-control" required>

  <label>Email</label>
  <input type="email" name="email" class="form-control">

  <label>Password</label>
  <input type="password" name="password" class="form-control" required>

  <label>Phone</label>
  <input type="text" name="phone" class="form-control">

  <label>Status</label>
  <select name="status" class="form-control">
    <option value="active">Active</option>
    <option value="inactive">Inactive</option>
  </select>

  <button type="submit" class="btn btn-success mt-3">Save</button>
</form>

<?= $this->endSection() ?>