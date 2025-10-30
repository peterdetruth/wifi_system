<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Add New Router</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/routers/store') ?>" method="post" class="mt-4">
  <div class="mb-3">
    <label class="form-label">Router Name</label>
    <input type="text" name="name" required class="form-select">
  </div>

  <div class="mb-3">
    <label class="form-label">IP Address</label>
    <input type="text" name="ip_address" required class="form-select">
  </div>

  <div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" required class="form-select">
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
  </div>

  <button type="submit" class="btn btn-success">Save</button>
</form>

<?= $this->endSection() ?>
