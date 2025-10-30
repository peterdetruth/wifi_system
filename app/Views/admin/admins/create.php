<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Add New Admin</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/admins/store') ?>" method="post">
  <label>Username</label>
  <input type="text" name="username" class="form-control" required>

  <label>Email</label>
  <input type="email" name="email" class="form-control" required>

  <label>Password</label>
  <input type="password" name="password" class="form-control" required>

  <label>Role</label>
  <select name="role" class="form-control" required>
    <option value="admin">Admin</option>
    <option value="superadmin">Super Admin</option>
  </select>

  <button type="submit" class="btn btn-success mt-3">Save</button>
</form>

<?= $this->endSection() ?>
