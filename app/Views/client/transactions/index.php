<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<?php
function sortLink(string $label, string $column, string $currentSort, string $currentOrder): string
{
    $order = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    $icon  = '';

    if ($currentSort === $column) {
        $icon = $currentOrder === 'asc' ? ' ▲' : ' ▼';
    }

    $query = http_build_query(array_merge($_GET, [
        'sort'  => $column,
        'order' => $order
    ]));

    return '<a href="?' . $query . '" class="text-white text-decoration-none">'
        . esc($label) . $icon .
        '</a>';
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">My Transactions</h3>
        <a href="<?= base_url('client/packages') ?>" class="btn btn-outline-primary btn-sm">
            Browse Packages
        </a>
    </div>

    <!-- Status Filter -->
    <form method="get" class="mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="fw-bold">Status:</label>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $statusFilter === 'failed'  ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>

            <!-- Preserve sorting -->
            <input type="hidden" name="sort" value="<?= esc($sort) ?>">
            <input type="hidden" name="order" value="<?= esc($order) ?>">
        </div>
    </form>

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
                                <th><?= sortLink('Package', 'package', $sort, $order) ?></th>
                                <th><?= sortLink('Amount (KES)', 'amount', $sort, $order) ?></th>
                                <th>Mpesa Code</th>
                                <th><?= sortLink('Status', 'status', $sort, $order) ?></th>
                                <th><?= sortLink('Date', 'date', $sort, $order) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $start = ($pager->getCurrentPage() - 1) * $pager->getPerPage();
                            ?>

                            <?php foreach ($transactions as $i => $tx):
                                $status = strtolower($tx['status'] ?? '');

                                $rowClass = match ($status) {
                                    'success' => 'table-success',
                                    'pending' => 'table-warning',
                                    'failed'  => 'table-danger',
                                    default   => 'table-light',
                                };

                                $createdAt = !empty($tx['created_at'])
                                    ? date('d M Y, H:i', strtotime($tx['created_at']))
                                    : '-';
                            ?>
                                <tr class="<?= $rowClass ?>" data-receipt='<?= esc(json_encode([
                                                                                'Package'    => $tx['package_name'] ?? 'N/A',
                                                                                'Amount'     => number_format((float)$tx['amount'], 2),
                                                                                'Mpesa Code' => $tx['mpesa_receipt_number'] ?? '-',
                                                                                'Status'     => ucfirst($status ?: 'unknown'),
                                                                                'Date'       => $createdAt,
                                                                            ])) ?>'>
                                    <td><?= $start + $i + 1 ?></td>
                                    <td><?= esc($tx['package_name'] ?? 'N/A') ?></td>
                                    <td><?= number_format((float)$tx['amount'], 2) ?></td>
                                    <td><?= esc($tx['mpesa_receipt_number'] ?? '-') ?></td>
                                    <td><?= ucfirst($status) ?></td>
                                    <td><?= esc($createdAt) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center mt-4">
            <?= $pager->links('default', 'bootstrap5') ?>
        </div>
    <?php endif; ?>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Transaction Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receiptContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
        const content = document.getElementById('receiptContent');

        document.querySelectorAll('tbody tr[data-receipt]').forEach(row => {
            row.addEventListener('click', () => {
                const data = JSON.parse(row.dataset.receipt);
                let html = '';

                Object.entries(data).forEach(([k, v]) => {
                    html += `<p><strong>${k}:</strong> ${v}</p>`;
                });

                content.innerHTML = html;
                modal.show();
            });
        });
    });
</script>

<?= $this->endSection() ?>