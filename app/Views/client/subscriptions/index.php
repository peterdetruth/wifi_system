<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>My Subscriptions</h3>
  </div>

  <?= view('templates/alerts') ?>

  <?php
  /**
   * Generate sortable column links
   */
  function sort_link(string $label, string $field, string $currentSort, string $currentOrder)
  {
    $order = ($currentSort === $field && $currentOrder === 'asc') ? 'desc' : 'asc';
    $icon  = '';

    if ($currentSort === $field) {
      $icon = $currentOrder === 'asc'
        ? ' <i class="bi bi-arrow-up"></i>'
        : ' <i class="bi bi-arrow-down"></i>';
    }

    $query = array_merge($_GET, [
      'sort'  => $field,
      'order' => $order,
    ]);

    $url = current_url() . '?' . http_build_query($query);

    return '<a href="' . esc($url) . '" class="text-white text-decoration-none">'
      . esc($label) . $icon . '</a>';
  }
  ?>

  <?php if (!empty($subscriptions)): ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th><?= sort_link('Package', 'package_name', $sort, $order) ?></th>
            <th>Router</th>
            <th>Account Type</th>
            <th>Package Type</th>
            <th>Price (Ksh)</th>
            <th>Start Date</th>
            <th><?= sort_link('Expiry Date', 'expires_on', $sort, $order) ?></th>
            <th>Validity</th>
            <th><?= sort_link('Status', 'status', $sort, $order) ?></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subscriptions as $index => $sub): ?>
            <?php
            $isExpired = strtotime($sub['expires_on']) < time();
            $status = $isExpired ? 'Expired' : ucfirst($sub['status']);
            $statusClass = $isExpired ? 'text-danger fw-bold' : 'text-success fw-bold';
            ?>
            <tr>
              <td><?= $index + 1 ?></td>
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