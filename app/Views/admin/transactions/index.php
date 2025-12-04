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
                <div class="table-responsive">
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
                            <?php $i = 1 + ($currentPage - 1) * $perPage; foreach ($transactions as $tx): 
                                $rowClass = 'table-light';
                                $status = strtolower($tx['status'] ?? 'pending');
                                if ($status === 'success') $rowClass = 'table-success';
                                elseif ($status === 'failed') $rowClass = 'table-danger';
                                elseif ($status === 'pending') $rowClass = 'table-warning';
                                
                                $createdOn = !empty($tx['created_at']) ? date('d M Y, H:i', strtotime($tx['created_at'])) : '-';
                                $expiresOn = !empty($tx['expires_on']) && $tx['expires_on'] !== '0000-00-00 00:00:00' 
                                             ? date('d M Y, H:i', strtotime($tx['expires_on'])) 
                                             : '-';
                                $packageLabel = !empty($tx['package_name']) ? $tx['package_name'] : ($tx['package_length'] ?? 'N/A');
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= $i++ ?></td>
                                <td><?= esc($tx['client_username'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($tx['package_id'])): ?>
                                        <a href="<?= base_url('admin/packages/view/' . $tx['package_id']) ?>">
                                            <?= esc($packageLabel) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= esc($packageLabel) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($tx['package_type'] ?? '-') ?></td>
                                <td>KES <?= number_format($tx['amount'] ?? 0, 2) ?></td>
                                <td><?= esc(ucfirst($tx['payment_method'] ?? 'mpesa')) ?></td>
                                <td><?= esc($tx['mpesa_receipt_number'] ?? '-') ?></td>
                                <td>
                                    <?php if ($status === 'success'): ?>
                                        <span class="badge bg-success">Success</span>
                                    <?php elseif ($status === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($status === 'failed'): ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= esc($tx['status'] ?? 'unknown') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($createdOn) ?></td>
                                <td><?= esc($expiresOn) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalTransactions > $perPage): ?>
                    <nav>
                        <ul class="pagination justify-content-center mt-3">
                            <?php
                            $totalPages = ceil($totalTransactions / $perPage);
                            for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                    <a href="<?= current_url() ?>?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <!-- Transaction Modal -->
                    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptLabel" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="receiptLabel">Transaction Details</h5>
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
            <p><strong>Client:</strong> ${cells[1]?.innerText || '-'}</p>
            <p><strong>Package:</strong> ${cells[2]?.innerText || '-'}</p>
            <p><strong>Type:</strong> ${cells[3]?.innerText || '-'}</p>
            <p><strong>Amount:</strong> ${cells[4]?.innerText || '-'}</p>
            <p><strong>Method:</strong> ${cells[5]?.innerText || '-'}</p>
            <p><strong>Mpesa Code:</strong> ${cells[6]?.innerText || '-'}</p>
            <p><strong>Status:</strong> ${cells[7]?.innerText || '-'}</p>
            <p><strong>Created On:</strong> ${cells[8]?.innerText || '-'}</p>
            <p><strong>Expires On:</strong> ${cells[9]?.innerText || '-'}</p>
        `;
        document.getElementById('receiptContent').innerHTML = receipt;
        const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
        modal.show();
    });
});
</script>

<?= $this->endSection() ?>
