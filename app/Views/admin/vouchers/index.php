<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<h3 class="mb-4"><i class="bi bi-ticket-perforated"></i> Voucher Management</h3>

<?= view('templates/alerts') ?>

<div class="mb-3">
  <a href="<?= base_url('admin/vouchers/create') ?>" class="btn btn-success">
    <i class="bi bi-plus-circle"></i> Create New Voucher
  </a>
</div>

<div class="table-responsive shadow-sm">
  <table class="table table-bordered table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Code</th>
        <th>Purpose</th>
        <th>Router</th>
        <th>Package</th>
        <th>Phone</th>
        <th>Status</th>
        <th>Expires On</th>
        <th>Remaining Validity</th>
        <th>Used By</th>
        <th>Created</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($vouchers)): ?>
        <?php foreach ($vouchers as $i => $v): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= esc($v['code']) ?></strong></td>
            <td><?= ucfirst(str_replace('_', ' ', $v['purpose'])) ?></td>
            <td><?= esc($v['router_name'] ?? '—') ?></td>
            <td><?= esc($v['package_type'] ?? '—') ?> (Ksh <?= esc($v['package_price'] ?? '-') ?>)</td>
            <td><?= esc($v['phone']) ?></td>
            <td>
              <span class="badge 
                <?= $v['status'] == 'used' ? 'bg-danger' : ($v['status'] == 'expired' ? 'bg-secondary' : 'bg-success') ?>">
                <?= ucfirst($v['status']) ?>
              </span>
            </td>
            <td><?= date('d M Y', strtotime($v['expires_on'])) ?></td>
            <td class="<?= strtotime($v['expires_on']) < time() ? 'text-danger' : 'text-success' ?>"><?= esc(remaining_time($v['expires_on'])) ?></td>
            <td><?= esc($v['used_by_client_id'] ?? '—') ?></td>
            <td><?= date('d M Y', strtotime($v['created_at'])) ?></td>
            <td>
              <a href="<?= base_url('admin/vouchers/edit/' . $v['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Edit</a>
              <form action="<?= base_url('admin/vouchers/delete/' . $v['id']) ?>" method="post" onsubmit="return confirm('Delete this voucher?');">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-trash">Delete</i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="11" class="text-center text-muted">No vouchers found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
