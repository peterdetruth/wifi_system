<table class="table table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>Package</th>
            <th>Router</th>
            <th>Start Date</th>
            <th>Expires On</th>
            <th>Created At</th>
            <th>Time Left</th> <!-- New column -->
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($recharges)): ?>
            <?php foreach ($recharges as $recharge): ?>
                <tr>
                    <td><?= esc($recharge['id']) ?></td>
                    <td><?= esc($recharge['package_name']) ?></td>
                    <td><?= esc($recharge['router_name'] ?? '-') ?></td>
                    <td><?= esc($recharge['start_date']) ?></td>
                    <td><?= esc($recharge['expires_on']) ?></td>
                    <td><?= esc($recharge['created_at']) ?></td>
                    <td>
                        <span class="countdown" data-expiry="<?= esc($recharge['expires_on']) ?>"></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-center">No recharges found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<?php if ($rechargesTotal > $perPage): ?>
    <nav>
        <ul class="pagination" id="rechargesPagination">
            <?php
            $totalPages = ceil($rechargesTotal / $perPage);
            for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $rechargesPage ? 'active' : '' ?>">
                    <a href="#" class="page-link" data-page="<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>


