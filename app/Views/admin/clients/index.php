<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Clients</h3>

<?= view('templates/alerts') ?>

<a href="<?= base_url('/admin/clients/create') ?>" class="btn btn-primary mb-3">Add New Client</a>

<table class="table table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>Full Name</th>
      <th>Username</th>
      <th>Email</th>
      <th>Phone</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($clients as $client): ?>
      <tr>
        <td><?= esc($client['id']) ?></td>
        <td><?= esc($client['full_name']) ?></td>
        <td><?= esc($client['username']) ?></td>
        <td><?= esc($client['email']) ?></td>
        <td><?= esc($client['phone']) ?></td>
        <td><?= esc($client['status']) ?></td>
        <td>
          <a href="<?= base_url('/admin/clients/edit/'.$client['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
          <a href="<?= base_url('/admin/clients/delete/'.$client['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?= $this->endSection() ?>
