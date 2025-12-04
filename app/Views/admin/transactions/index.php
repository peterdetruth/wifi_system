<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>All Transactions</h4>
    </div>

    <?= view('templates/alerts') ?>

    <div id="transactionsTableWrapper">
        <?= view('admin/transactions/partials/transactions_table', [
            'transactions' => $transactions,
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'totalTransactions' => $totalTransactions
        ]) ?>
    </div>
</div>

<script>
function loadTransactions(page = 1) {
    const params = new URLSearchParams(window.location.search);
    params.set('transactions_page', page);
    fetch('<?= base_url("/admin/transactions") ?>?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById('transactionsTableWrapper').innerHTML = html;
    });
}

document.addEventListener('click', function(e) {
    if (e.target.closest('#transactionsPagination a')) {
        e.preventDefault();
        const page = e.target.dataset.page;
        loadTransactions(page);
    }
});
</script>

<?= $this->endSection() ?>
