<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Edit Admin</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/admins/update/'.$admin['id']) ?>" method="post">
  <label>Username</label>
  <input type="text" name="username" value="<?= esc($admin['username']) ?>" class="form-control" required>

  <label>Email</label>
  <input type="email" name="email" value="<?= esc($admin['email']) ?>" class="form-control" required>

  <label>Role</label>
  <select name="role" class="form-control" required>
    <option value="admin" <?= $admin['role']=='admin'?'selected':'' ?>>Admin</option>
    <option value="superadmin" <?= $admin['role']=='superadmin'?'selected':'' ?>>Super Admin</option>
  </select>

  <button type="submit" class="btn btn-primary mt-3">Update</button>
</form>

<?= $this->endSection() ?>
