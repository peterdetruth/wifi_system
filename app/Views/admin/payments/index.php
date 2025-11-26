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
                        <?php $i = 1; foreach ($payments as $p): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= esc($p['client_username'] ?? 'Unknown') ?></td>
                            <td><?= esc($p['package_name'] ?? '-') ?></td>

                            <td>KES <?= number_format($p['amount'] ?? 0, 2) ?></td>

                            <td><?= esc($p['mpesa_code'] ?? '-') ?></td>

                            <td>
                                <span class="badge 
                                    <?= ($p['status'] === 'completed') ? 'bg-success' : 
                                        (($p['status'] === 'pending') ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                    <?= ucfirst($p['status'] ?? 'unknown') ?>
                                </span>
                            </td>

                            <td><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>

            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
