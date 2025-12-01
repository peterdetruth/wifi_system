<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Transaction ID</th>
            <th>Package</th>
            <th>Amount</th>
            <th>Phone</th>
            <th>Transaction Date</th>
            <th>Status</th>
            <th>Created</th>
            <th>Mpesa Receipt</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($mpesaTransactions as $mpesa): ?>
            <tr>
                <td><?= esc($mpesa['id']) ?></td>
                <td><?= esc($mpesa['transaction_id']) ?></td>
                <td><?= esc($mpesa['package_name']) ?></td>
                <td><?= esc($mpesa['amount']) ?></td>
                <td><?= esc($mpesa['phone_number']) ?></td>
                <td><?= esc($mpesa['transaction_date']) ?></td>
                <td><?= esc(ucfirst($mpesa['status'])) ?></td>
                <td><?= esc($mpesa['created_at']) ?></td>
                <td><?= esc($mpesa['mpesa_receipt']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Pagination -->
<nav>
    <ul class="pagination" id="mpesaPagination">
        <?php
        $totalPages = ceil($mpesaTotal / $perPage);
        for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i == $mpesaPage ? 'active' : '' ?>">
                <a href="#" class="page-link" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
