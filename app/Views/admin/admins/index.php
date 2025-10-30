<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Admins</h3>

<?= view('templates/alerts') ?>

<a href="<?= base_url('/admin/admins/create') ?>" class="btn btn-primary mb-3">Add New Admin</a>

<table class="table table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>Username</th>
      <th>Email</th>
      <th>Role</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($admins as $admin): ?>
      <tr>
        <td><?= esc($admin['id']) ?></td>
        <td><?= esc($admin['username']) ?></td>
        <td><?= esc($admin['email']) ?></td>
        <td><?= esc($admin['role']) ?></td>
        <td>
          <a href="<?= base_url('/admin/admins/edit/'.$admin['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
          <a href="<?= base_url('/admin/admins/delete/'.$admin['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?= $this->endSection() ?>
