<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>My Subscriptions</h3>
  </div>

  <?= view('templates/alerts') ?>

  <?php if (!empty($subscriptions)): ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Package</th>
            <th>Router</th>
            <th>Account Type</th>
            <th>Package Type</th>
            <th>Price (Ksh)</th>
            <th>Start Date</th>
            <th>Expiry Date</th>
            <th>Validity</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subscriptions as $index => $sub): ?>
            <?php
            $isExpired = strtotime($sub['expires_on']) < time();
            $status = $isExpired ? 'Expired' : ucfirst($sub['status']);
            $statusClass = $isExpired ? 'text-danger fw-bold' : 'text-success fw-bold';
            // Correct numbering across pages
            $rowNumber = ($pager->getCurrentPage() - 1) * $perPage + $index + 1;
            ?>
            <tr>
              <td><?= $rowNumber ?></td>
              <td><?= esc($sub['package_name'] ?? 'N/A') ?></td>
              <td><?= esc($sub['router_name'] ?? 'N/A') ?></td>
              <td><?= esc($sub['package_account_type'] ?? 'N/A') ?></td>
              <td><?= esc(ucfirst($sub['package_type'] ?? 'N/A')) ?></td>
              <td><?= number_format($sub['price'] ?? 0, 2) ?></td>
              <td><?= date('d M Y H:i', strtotime($sub['start_date'])) ?></td>
              <td><?= date('d M Y H:i', strtotime($sub['expires_on'])) ?></td>
              <td class="<?= $isExpired ? 'text-danger' : 'text-success' ?>">
                <?= esc(remaining_time($sub['expires_on'])) ?>
              </td>
              <td class="<?= $statusClass ?>"><?= esc($status) ?></td>
              <td>
                <div class="btn-group btn-group-sm" role="group">
                  <a href="<?= base_url('client/subscriptions/view/' . $sub['id']) ?>" class="btn btn-primary">
                    <i class="bi bi-eye"></i> View
                  </a>

                  <?php if ($isExpired): ?>
                    <a href="<?= base_url('client/packages/view/' . $sub['package_id']) ?>" class="btn btn-success">
                      <i class="bi bi-arrow-repeat"></i> Renew
                    </a>
                  <?php elseif ($sub['status'] === 'active'): ?>
                    <a href="<?= base_url('client/subscriptions/reconnect/' . $sub['id']) ?>" class="btn btn-info">
                      <i class="bi bi-wifi"></i> Reconnect
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4">
      <?= $pager->links('default', 'bootstrap5') ?>
    </div>

  <?php else: ?>
    <div class="alert alert-warning text-center">
      You have no active or past subscriptions yet.
    </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>