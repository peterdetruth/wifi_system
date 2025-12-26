<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<h3>Client: <?= esc($client['full_name']) ?> (<?= esc($client['username']) ?>)</h3>

<?= view('templates/alerts') ?>

<!-- Subscriptions Table -->
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
        <?= view('admin/clients/partials/subscriptions_table', [
            'subscriptions' => $subscriptions,
            'subscriptionsTotal' => $subscriptionsTotal,
            'subscriptionsPage' => $subscriptionsPage,
            'perPage' => $perPage
        ]) ?>
    </div>
</div>

<!-- Mpesa Transactions Table -->
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
        <?= view('admin/clients/partials/mpesa_table', [
            'mpesaTransactions' => $mpesaTransactions,
            'mpesaTotal' => $mpesaTotal,
            'mpesaPage' => $mpesaPage,
            'perPage' => $perPage
        ]) ?>
    </div>
</div>

<!-- Account Recharge Form -->
<div class="mb-4">
    <h5>Account Recharge</h5>
    <form method="POST" action="<?= base_url('/admin/clients/recharge/' . $client['id']) ?>" class="d-flex gap-2 align-items-center">
        <?= csrf_field() ?>

        <label for="package">Select Package:</label>
        <select name="package_id" id="package" class="form-select w-auto" required>
            <option value="">-- Choose Package --</option>
            <?php foreach ($db->table('packages')->get()->getResultArray() as $pkg): ?>
                <option value="<?= esc($pkg['id']) ?>"><?= esc($pkg['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-success">Auto Connect</button>
    </form>
</div>

<!-- Recharges Table -->
<div class="mb-4">
    <h5>Recharges History</h5>
    <div id="rechargesTable">
        <?= view('admin/clients/partials/recharges_table', [
            'recharges' => $recharges,
            'rechargesTotal' => $rechargesTotal,
            'rechargesPage' => $rechargesPage,
            'perPage' => $perPage
        ]) ?>
    </div>
</div>

<div class="mb-4">
    <h5>Connect with Username & Password</h5>

    <form method="POST" action="<?= base_url('/admin/clients/create-activation/' . $client['id']) ?>" class="row g-3">
        <?= csrf_field() ?>

        <div class="col-md-6">
            <label class="form-label">Select Package</label>
            <select name="package_id" class="form-select" required>
                <option value="">-- Choose Package --</option>
                <?php foreach ($db->table('packages')->get()->getResultArray() as $pkg): ?>
                    <option value="<?= esc($pkg['id']) ?>"><?= esc($pkg['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">
                Generate Username & Password
            </button>
        </div>
    </form>
</div>

<div class="mb-4">
    <h5>Username & Password Connections</h5>

    <?php if (empty($credentials)): ?>
        <div class="alert alert-info">No activation credentials created yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Start Date</th>
                        <th>Expires On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as $cred): ?>
                        <tr>
                            <td>
                                <strong><?= esc($cred['username']) ?></strong>
                            </td>

                            <td>
                                <?php if ($cred['status'] === 'unused'): ?>
                                    <code class="text-primary">
                                        <?= esc($cred['password_plain'] ?? '—') ?>
                                    </code>
                                    <br>
                                    <small class="text-muted">Copy & give to client</small>
                                <?php else: ?>
                                    <span class="text-muted">Hidden</span>
                                <?php endif; ?>
                            </td>

                            <td><?= esc($cred['package_name']) ?></td>

                            <td>
                                <?php if ($cred['status'] === 'used'): ?>
                                    <span class="badge bg-success">Used</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Unused</span>
                                <?php endif; ?>
                            </td>

                            <td><?= date('d M Y H:i', strtotime($cred['created_at'])) ?></td>

                            <td>
                                <?= $cred['start_date']
                                    ? date('d M Y H:i', strtotime($cred['start_date']))
                                    : '-' ?>
                            </td>

                            <td>
                                <?= $cred['expires_on']
                                    ? date('d M Y H:i', strtotime($cred['expires_on']))
                                    : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="alert alert-warning mt-2">
            <strong>Note:</strong> Passwords are only visible while unused.
            Once activated, they are hidden for security reasons.
        </div>
    <?php endif; ?>
</div>



<!-- CSS & Countdown -->
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

<!-- JS -->
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // Countdown updater (for subscriptions & recharges)
        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(function(el) {
                let expiry = new Date(el.dataset.expiry);
                let now = new Date();
                let diff = expiry - now;

                if (diff <= 0) {
                    el.textContent = 'Expired';
                    el.closest('tr').classList.add('expired-row'); // highlight row
                } else {
                    let days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    let hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
                    let mins = Math.floor((diff / (1000 * 60)) % 60);
                    let secs = Math.floor((diff / 1000) % 60);
                    el.textContent = days + 'd ' + hours + 'h ' + mins + 'm ' + secs + 's';
                    el.closest('tr').classList.remove('expired-row'); // remove highlight if not expired
                }
            });
        }
        setInterval(updateCountdowns, 1000);
        updateCountdowns();

        // AJAX table reload for subscriptions, mpesa, and recharges
        function loadTable(tableType, page, status) {
            const url = '<?= base_url("/admin/clients/view/" . $client["id"]) ?>';
            fetch(`${url}?table=${tableType}&${tableType}_page=${page}&${tableType}_status=${status || ''}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.text())
                .then(html => {
                    if (tableType === 'subscriptions') {
                        document.getElementById('subscriptionsTable').innerHTML = html;
                    } else if (tableType === 'mpesa') {
                        document.getElementById('mpesaTable').innerHTML = html;
                    } else if (tableType === 'recharges') {
                        document.getElementById('rechargesTable').innerHTML = html;
                    }
                });
        }

        // Pagination click handler
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
            if (e.target.closest('#rechargesPagination a')) {
                e.preventDefault();
                const page = e.target.dataset.page;
                loadTable('recharges', page, '');
            }
        });

        // Status filter change handler
        document.getElementById('subscriptionsStatus').addEventListener('change', function() {
            loadTable('subscriptions', 1, this.value);
        });
        document.getElementById('mpesaStatus').addEventListener('change', function() {
            loadTable('mpesa', 1, this.value);
        });

        // Future-proof hook for copy-to-clipboard / reveal password / regenerate
        console.log('Activation credentials section loaded');
    });
</script>

<style>
    /* Highlight expired rows */
    .expired-row {
        background-color: #f8d7da !important;
        /* light red */
        color: #721c24;
    }
</style>



<?= $this->endSection() ?>