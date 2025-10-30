<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">My Transactions</h3>
        <a href="<?= base_url('client/packages') ?>" class="btn btn-outline-primary btn-sm">Browse Packages</a>
      </div>
      <p>Click on a row to open a modal with transaction details</p>

    <?= view('templates/alerts') ?>

    <?php if (empty($transactions)): ?>
        <div class="alert alert-info">No transactions found.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Package</th>
                                <th>Amount (KES)</th>
                                <th>Mpesa Code</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Expires On</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($transactions as $tx): 
                                $rowClass = 'table-light';
                                if (isset($tx['status'])) {
                                    if ($tx['status'] === 'failed') $rowClass = 'table-danger';
                                    elseif ($tx['status'] === 'pending') $rowClass = 'table-warning';
                                    elseif ($tx['status'] === 'success') $rowClass = 'table-success';
                                }
                                $createdOn = !empty($tx['created_on']) ? date('d M Y, H:i', strtotime($tx['created_on'])) : '-';
                                $expiresOn = !empty($tx['expires_on']) && $tx['expires_on'] !== '0000-00-00 00:00:00' ? date('d M Y, H:i', strtotime($tx['expires_on'])) : '-';
                                $packageLabel = !empty($tx['package_name']) ? $tx['package_name'] : ($tx['package_length'] ?? 'N/A');
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td><?= $i++ ?></td>

                                    <td>
                                        <?php if (!empty($tx['package_id'])): ?>
                                            <a href="<?= base_url('client/packages/view/' . $tx['package_id']) ?>">
                                                <?= esc($packageLabel) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= esc($packageLabel) ?>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= esc(number_format((float)($tx['amount'] ?? 0), 2)) ?></td>
                                    <td><?= esc($tx['mpesa_code'] ?? '-') ?></td>
                                    <td><?= esc(ucfirst($tx['method'] ?? 'mpesa')) ?></td>

                                    <td>
                                        <?php if (($tx['status'] ?? '') === 'success'): ?>
                                            <span class="badge bg-success">Success</span>
                                        <?php elseif (($tx['status'] ?? '') === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif (($tx['status'] ?? '') === 'failed'): ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= esc($tx['status'] ?? 'unknown') ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= esc($expiresOn) ?></td>
                                    <td><?= esc($createdOn) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <!-- Receipt Modal -->
                    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptLabel" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="receiptLabel">Transaction Receipt</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <div id="receiptContent" class="p-2 text-sm"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.table-hover tbody tr:hover {
    background-color: #f9fafb;
    transition: background 0.2s;
}
.badge { font-size: .85rem; padding: .35em .5em; }
</style>

<script>
document.querySelectorAll('tbody tr').forEach(row => {
  row.addEventListener('click', () => {
    const cells = row.querySelectorAll('td');
    const receipt = `
      <p><strong>Package:</strong> ${cells[1]?.innerText || '-'}</p>
      <p><strong>Amount:</strong> ${cells[2]?.innerText || '-'}</p>
      <p><strong>Mpesa Code:</strong> ${cells[3]?.innerText || '-'}</p>
      <p><strong>Method:</strong> ${cells[4]?.innerText || '-'}</p>
      <p><strong>Status:</strong> ${cells[5]?.innerText || '-'}</p>
      <p><strong>Expires On:</strong> ${cells[6]?.innerText || '-'}</p>
      <p><strong>Date:</strong> ${cells[7]?.innerText || '-'}</p>
    `;
    document.getElementById('receiptContent').innerHTML = receipt;
    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    modal.show();
  });
});
</script>

<?= $this->endSection() ?>
