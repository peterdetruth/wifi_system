<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('content') ?>

<h3>Clients</h3>

<?= view('templates/alerts') ?>

<!-- Filters + Search -->
<form method="GET" class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <div class="d-flex align-items-center gap-2">
        <label>Status:</label>
        <select name="status" class="form-select w-auto" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="active" <?= ($status=='active')?'selected':'' ?>>Active</option>
            <option value="inactive" <?= ($status=='inactive')?'selected':'' ?>>Inactive</option>
        </select>
    </div>
    <div class="d-flex align-items-center gap-2">
        <label>Search:</label>
        <input type="text" name="search" class="form-control" value="<?= esc($search ?? '') ?>" placeholder="Name, username, email..." />
        <button type="submit" class="btn btn-primary">Go</button>
    </div>
</form>

<a href="<?= base_url('/admin/clients/create') ?>" class="btn btn-primary mb-3">Add New Client</a>

<table class="table table-striped table-hover">
  <thead class="table-dark">
    <tr>
      <th>ID</th>
      <th>Full Name</th>
      <th>Username</th>
      <th>Email</th>
      <th>Phone</th>
      <th>Status</th>
      <th>Subscriptions</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!empty($clients)): ?>
        <?php foreach ($clients as $client): ?>
        <tr>
            <td><?= esc($client['id']) ?></td>
            <td><?= esc($client['full_name']) ?></td>
            <td><?= esc($client['username']) ?></td>
            <td><?= esc($client['email']) ?></td>
            <td><?= esc($client['phone']) ?></td>
            <td>
                <span class="badge <?= $client['status']=='active'?'bg-success':'bg-secondary' ?>">
                    <?= esc(ucfirst($client['status'])) ?>
                </span>
            </td>
            <td><?= esc($client['subscriptions_count'] ?? 0) ?></td>
            <td>
                <a href="<?= base_url('/admin/clients/view/'.$client['id']) ?>" class="btn btn-sm btn-info">View</a>
                <a href="<?= base_url('/admin/clients/edit/'.$client['id']) ?>" class="btn btn-sm btn-warning">Edit</a>
                <a href="<?= base_url('/admin/clients/delete/'.$client['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="8" class="text-center">No clients found.</td>
        </tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Pagination -->
<div class="mt-3 d-flex justify-content-center">
    <?= $pager->links('default', 'default_full') ?>
</div>

<!-- Inline CSS -->
<style>
/* Pagination Styles */
.pagination {
    display: flex;
    list-style: none;
    gap: 0.3rem;
    padding-left: 0;
}
.pagination li {
    display: inline-block;
}
.pagination li a,
.pagination li span {
    display: block;
    padding: 0.5rem 0.8rem;
    border: 1px solid #dee2e6;
    color: #0d6efd;
    text-decoration: none;
    border-radius: 0.25rem;
}
.pagination li a:hover {
    background-color: #e9ecef;
}
.pagination li.active span {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}
.pagination li.disabled span {
    color: #6c757d;
    pointer-events: none;
}
</style>

<!-- Inline JS (optional enhancements) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Optional: press Enter in search box triggers form submit
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', e => {
            if (e.key === 'Enter') e.preventDefault(), searchInput.form.submit();
        });
    }
});
</script>

<?= $this->endSection() ?>
