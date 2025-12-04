<div class="card shadow-sm">
    <div class="card-body p-0">
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
                    <?php $i = 1 + ($currentPage - 1) * $perPage; ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= esc($p['client_username'] ?? 'Unknown') ?></td>
                            <td><?= esc($p['package_name'] ?? '-') ?></td>
                            <td>KES <?= number_format($p['amount'] ?? 0, 2) ?></td>
                            <td><?= esc($p['mpesa_code'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= ($p['status'] === 'completed') ? 'bg-success' : (($p['status'] === 'pending') ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                    <?= ucfirst($p['status'] ?? 'unknown') ?>
                                </span>
                            </td>
                            <td><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPayments > $perPage): ?>
            <nav>
                <ul class="pagination justify-content-center mt-3" id="paymentsPagination">
                    <?php $totalPages = ceil($totalPayments / $perPage); ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p == $currentPage ? 'active' : '' ?>">
                            <a href="#" class="page-link" data-page="<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
