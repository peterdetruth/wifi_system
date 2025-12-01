<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Payment ID</th>
            <th>Package</th>
            <th>Router</th>
            <th>Status</th>
            <th>Start Date</th>
            <th>Expiry Date</th>
            <th>Time Remaining</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($subscriptions as $sub): ?>
            <tr>
                <td><?= esc($sub['id']) ?></td>
                <td><?= esc($sub['payment_id']) ?></td>
                <td><?= esc($sub['package_name']) ?></td>
                <td><?= esc($sub['router_name'] ?? '-') ?></td>
                <td><?= esc(ucfirst($sub['status'])) ?></td>
                <td><?= esc($sub['start_date']) ?></td>
                <td><?= esc($sub['expires_on']) ?></td>
                <td>
                    <?php if ($sub['status'] !== 'expired'): ?>
                        <span class="countdown" data-expiry="<?= esc($sub['expires_on']) ?>"></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Pagination -->
<nav>
    <ul class="pagination" id="subscriptionsPagination">
        <?php
        $totalPages = ceil($subscriptionsTotal / $perPage);
        for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i == $subscriptionsPage ? 'active' : '' ?>">
                <a href="#" class="page-link" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
