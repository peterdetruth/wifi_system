<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>All Transactions</h4>
  </div>

  <?= view('templates/alerts') ?>

  <?php if (empty($transactions)): ?>
    <div class="alert alert-info">No transactions found.</div>
  <?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <table class="table table-hover table-striped mb-0">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Client</th>
            <th>Package</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Mpesa Code</th>
            <th>Status</th>
            <th>Created On</th>
            <th>Expires On</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($transactions as $tx): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= esc($tx['client_username'] ?? 'N/A') ?></td>
            <td><?= esc($tx['package_name'] ?? '-') ?></td>
            <td><?= esc($tx['package_type'] ?? '-') ?></td>
            <td>KES <?= number_format($tx['amount'] ?? 0, 2) ?></td>
            <td><?= ucfirst($tx['method'] ?? 'mpesa') ?></td>
            <td><?= esc($tx['mpesa_code'] ?? '-') ?></td>
            <td>
              <span class="badge 
                <?= ($tx['status'] === 'success') ? 'bg-success' : (($tx['status'] === 'failed') ? 'bg-danger' : 'bg-warning text-dark') ?>">
                <?= ucfirst($tx['status'] ?? 'pending') ?>
              </span>
            </td>
            <td><?= date('d M Y, H:i', strtotime($tx['created_on'])) ?></td>
            <td><?= isset($tx['expires_on']) ? date('d M Y, H:i', strtotime($tx['expires_on'])) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
