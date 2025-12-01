<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<h3>Client: <?= esc($client['full_name']) ?> (<?= esc($client['username']) ?>)</h3>

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

<!-- CSS & Countdown -->
<style>
    .countdown { font-weight: bold; color: #d9534f; }
    .pagination { display: flex; gap: 0.3rem; }
    .pagination li { cursor: pointer; }
    .pagination li.active a { background-color: #0d6efd; color: white; }
</style>

<!-- JS -->
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
                let days = Math.floor(diff / (1000*60*60*24));
                let hours = Math.floor((diff / (1000*60*60)) % 24);
                let mins = Math.floor((diff / (1000*60)) % 60);
                let secs = Math.floor((diff / 1000) % 60);
                el.textContent = days+'d '+hours+'h '+mins+'m '+secs+'s';
            }
        });
    }
    setInterval(updateCountdowns, 1000);
    updateCountdowns();

    // AJAX table reload
    function loadTable(tableType, page, status) {
        const url = '<?= base_url("/admin/clients/view/" . $client["id"]) ?>';
        fetch(`${url}?table=${tableType}&${tableType}_page=${page}&${tableType}_status=${status}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
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

    // Pagination click
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

    // Status filter change
    document.getElementById('subscriptionsStatus').addEventListener('change', function() {
        loadTable('subscriptions', 1, this.value);
    });
    document.getElementById('mpesaStatus').addEventListener('change', function() {
        loadTable('mpesa', 1, this.value);
    });

});
</script>

<?= $this->endSection() ?>
