<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between mb-3">
    <h3>Packages</h3>
    <?= view('templates/alerts') ?>
    <a href="<?= site_url('admin/packages/create') ?>" class="btn btn-primary">Add Package</a>
  </div>

  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>#</th>
        <th>Package Name</th>
        <th>Type</th>
        <th>Bandwidth</th>
        <th>Duration</th>
        <th>Price</th>
        <th>Devices</th>
        <th>Router</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($packages as $index => $package): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td><?= esc($package['name']) ?></td>
          <td><?= esc($package['type']) ?></td>
          <td><?= esc($package['bandwidth_value']) . ' ' . esc($package['bandwidth_unit']) ?></td>
          <td><?= esc($package['duration_length']) . ' ' . esc($package['duration_unit']) ?></td>
          <td><?= esc($package['price']) ?></td>
          <td><?= esc($package['hotspot_devices']) ?></td>
          <td><?= esc($routers[$package['router_id']] ?? 'N/A') ?></td>
          <td>
            <a href="<?= site_url('admin/packages/edit/' . $package['id']) ?>" class="btn btn-sm btn-primary">Edit</a>
            <a href="<?= site_url('admin/packages/delete/' . $package['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
