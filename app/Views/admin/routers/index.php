<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Routers</h3>

<?= view('templates/alerts') ?>

<a href="<?= base_url('/admin/routers/create') ?>" class="btn btn-primary mb-3">Add New Router</a>

<table class="table table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>IP Address</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($routers as $router): ?>
      <tr>
        <td><?= esc($router['id']) ?></td>
        <td><?= esc($router['name']) ?></td>
        <td><?= esc($router['ip_address']) ?></td>
        <td><?= esc($router['status']) ?></td>
        <td>
          <a href="<?= base_url('/admin/routers/edit/'.$router['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
          <a href="<?= base_url('/admin/routers/delete/'.$router['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?= $this->endSection() ?>
