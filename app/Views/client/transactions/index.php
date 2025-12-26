<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<?php
/**
 * Build sortable table header links safely
 */
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

    <p class="text-muted">Click a row to view transaction receipt</p>

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
                            $startIndex = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1;
                            ?>

                            <?php foreach ($transactions as $i => $tx):
                                $status = strtolower($tx['status'] ?? '');

                                $rowClass = match ($status) {
                                    'failed'  => 'table-danger',
                                    'pending' => 'table-warning',
                                    'success' => 'table-success',
                                    default   => 'table-light',
                                };

                                $createdAt = !empty($tx['created_at'])
                                    ? date('d M Y, H:i', strtotime($tx['created_at']))
                                    : '-';
                            ?>
                                <tr class="<?= $rowClass ?>" data-receipt='<?= esc(json_encode([
                                                                                'Package' => $tx['package_name'] ?? 'N/A',
                                                                                'Amount'  => number_format((float)$tx['amount'], 2),
                                                                                'Mpesa Code' => $tx['mpesa_receipt_number'] ?? '-',
                                                                                'Status'  => ucfirst($status ?: 'unknown'),
                                                                                'Date'    => $createdAt,
                                                                            ])) ?>'>
                                    <td><?= $startIndex + $i ?></td>
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
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="receiptLabel">Transaction Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receiptContent"></div>
            </div>
        </div>
    </div>
</div>

<style>
    .table-hover tbody tr:hover {
        background-color: #f9fafb;
        cursor: pointer;
    }

    .badge {
        font-size: .85rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('receiptModal');
        const modal = new bootstrap.Modal(modalEl);
        const content = document.getElementById('receiptContent');

        document.querySelectorAll('tbody tr[data-receipt]').forEach(row => {
            row.addEventListener('click', () => {
                const data = JSON.parse(row.dataset.receipt);
                let html = '';

                for (const [key, value] of Object.entries(data)) {
                    html += `<p><strong>${key}:</strong> ${value}</p>`;
                }

                content.innerHTML = html;
                modal.show();
            });
        });
    });
</script>

<?= $this->endSection() ?>