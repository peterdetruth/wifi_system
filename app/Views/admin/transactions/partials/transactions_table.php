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
                    <?php $i = 1 + ($currentPage - 1) * $perPage; ?>
                    <?php foreach ($transactions as $tx): 
                        $status = strtolower($tx['status'] ?? 'pending');
                        $rowClass = $status === 'success' ? 'table-success' : ($status === 'failed' ? 'table-danger' : ($status === 'pending' ? 'table-warning' : 'table-light'));
                        $createdOn = !empty($tx['created_at']) ? date('d M Y, H:i', strtotime($tx['created_at'])) : '-';
                        $expiresOn = !empty($tx['expires_on']) && $tx['expires_on'] !== '0000-00-00 00:00:00' 
                                     ? date('d M Y, H:i', strtotime($tx['expires_on'])) : '-';
                        $packageLabel = $tx['package_name'] ?? ($tx['package_length'] ?? 'N/A');
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= $i++ ?></td>
                        <td><?= esc($tx['client_username'] ?? 'N/A') ?></td>
                        <td><?= esc($packageLabel) ?></td>
                        <td><?= esc($tx['package_type'] ?? '-') ?></td>
                        <td>KES <?= number_format($tx['amount'] ?? 0, 2) ?></td>
                        <td><?= esc(ucfirst($tx['payment_method'] ?? 'mpesa')) ?></td>
                        <td><?= esc($tx['mpesa_receipt_number'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $status === 'success' ? 'bg-success' : ($status === 'pending' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                <?= ucfirst($tx['status'] ?? 'unknown') ?>
                            </span>
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
                <ul class="pagination justify-content-center mt-3" id="transactionsPagination">
                    <?php $totalPages = ceil($totalTransactions / $perPage); ?>
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
