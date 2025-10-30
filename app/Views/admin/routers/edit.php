<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Edit Router</h3>

<?= view('templates/alerts') ?>

<form action="<?= base_url('/admin/routers/update/' . $router['id']) ?>" method="post" class="mt-4">
  <div class="mb-3">
    <label class="form-label">Router Name</label>
    <input type="text" name="name" value="<?= esc($router['name']) ?>" required class="form-select">
  </div>

  <div class="mb-3">
    <label class="form-label">IP Address</label>
    <input type="text" name="ip_address" value="<?= esc($router['ip_address']) ?>" required class="form-select">
  </div>

  <div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" required class="form-select">
      <option value="active" <?= $router['status']=='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $router['status']=='inactive'?'selected':'' ?>>Inactive</option>
    </select>
  </div>

  <button type="submit" class="btn btn-primary">Update</button>
</form>

<?= $this->endSection() ?>
