<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<h3>Client: <?= esc($client['full_name']) ?> (<?= esc($client['username']) ?>)</h3>

<div class="mb-4">
    <h5>Subscriptions</h5>
    <div class="d-flex gap-2 mb-2 align-items-center">
        <label>Status:</label>
        <select id="subscriptionsStatus" class="form-select w-auto">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div id="subscriptionsTable">
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
                            <?php if ($sub['status'] != 'expired'): ?>
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
    </div>
</div>

<div class="mb-4">
    <h5>Mpesa Transactions</h5>
    <div class="d-flex gap-2 mb-2 align-items-center">
        <label>Status:</label>
        <select id="mpesaStatus" class="form-select w-auto">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
        </select>
    </div>
    <div id="mpesaTable">
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
    </div>
</div>

<!-- CSS & JS -->
<style>
    .countdown {
        font-weight: bold;
        color: #d9534f;
    }

    .pagination {
        display: flex;
        gap: 0.3rem;
    }

    .pagination li {
        cursor: pointer;
    }

    .pagination li.active a {
        background-color: #0d6efd;
        color: white;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(function(el) {
                let expiry = new Date(el.dataset.expiry);
                let now = new Date();
                let diff = expiry - now;
                if (diff <= 0) {
                    el.textContent = 'Expired';
                } else {
                    let days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    let hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
                    let mins = Math.floor((diff / (1000 * 60)) % 60);
                    let secs = Math.floor((diff / 1000) % 60);
                    el.textContent = days + 'd ' + hours + 'h ' + mins + 'm ' + secs + 's';
                }
            });
        }
        setInterval(updateCountdowns, 1000);
        updateCountdowns();

        // --- AJAX Pagination ---
        function loadTable(tableType, page, status) {
            const clientId = <?= $client['id'] ?>;
            const url = '<?= base_url("/admin/clients/view/" . $client["id"]) ?>';
            fetch(`${url}?${tableType}_page=${page}&${tableType}_status=${status}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.text())
                .then(html => {
                    if (tableType === 'subscriptions') {
                        document.getElementById('subscriptionsTable').innerHTML = html;
                    } else {
                        document.getElementById('mpesaTable').innerHTML = html;
                    }
                });
        }

        // Subscriptions Pagination Click
        document.addEventListener('click', function(e) {
            if (e.target.closest('#subscriptionsPagination a')) {
                e.preventDefault();
                const page = e.target.dataset.page;
                const status = document.getElementById('subscriptionsStatus').value;
                loadTable('subscriptions', page, status);
            }
            if (e.target.closest('#mpesaPagination a')) {
                e.preventDefault();
                const page = e.target.dataset.page;
                const status = document.getElementById('mpesaStatus').value;
                loadTable('mpesa', page, status);
            }
        });

        // Status Filters Change
        document.getElementById('subscriptionsStatus').addEventListener('change', function() {
            loadTable('subscriptions', 1, this.value);
        });
        document.getElementById('mpesaStatus').addEventListener('change', function() {
            loadTable('mpesa', 1, this.value);
        });

    });
</script>

<?= $this->endSection() ?>