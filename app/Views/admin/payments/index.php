<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>All Payments</h4>
    </div>

    <?= view('templates/alerts') ?>

    <?php if (empty($payments)): ?>
        <div class="alert alert-info">No payments found.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <form method="get" action="/admin/transactions/export" class="d-inline mb-2">
                    <input type="hidden" name="client_id" value="<?= $_GET['client_id'] ?? '' ?>">
                    <input type="hidden" name="package_id" value="<?= $_GET['package_id'] ?? '' ?>">
                    <input type="hidden" name="date_from" value="<?= $_GET['date_from'] ?? '' ?>">
                    <input type="hidden" name="date_to" value="<?= $_GET['date_to'] ?? '' ?>">
                    <button class="btn btn-success">Export CSV</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Amount</th>
                                <th>Mpesa Code</th>
                                <th>Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1 + ($currentPage - 1) * $perPage;
                            foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= esc($p['client_username'] ?? 'Unknown') ?></td>
                                    <td><?= esc($p['package_name'] ?? '-') ?></td>
                                    <td>KES <?= number_format($p['amount'] ?? 0, 2) ?></td>
                                    <td><?= esc($p['mpesa_code'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge 
                                            <?= ($p['status'] === 'completed') ? 'bg-success' : (($p['status'] === 'pending') ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                            <?= ucfirst($p['status'] ?? 'unknown') ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPayments > $perPage): ?>
                    <nav>
                        <ul class="pagination justify-content-center mt-3">
                            <?php
                            $totalPages = ceil($totalPayments / $perPage);
                            for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                    <a href="<?= current_url() ?>?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.table-hover tbody tr:hover { background-color: #f9fafb; transition: background 0.2s; }
.badge { font-size: .85rem; padding: .35em .5em; }
</style>

<?= $this->endSection() ?>
