<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid mt-3">
    <h4>All Payments</h4>
    <?= view('templates/alerts') ?>

    <div id="paymentsTableWrapper">
        <?= view('admin/payments/partials/payments_table', [
            'payments' => $payments,
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'totalPayments' => $totalPayments
        ]) ?>
    </div>
</div>

<script>
function loadPayments(page = 1) {
    const params = new URLSearchParams(window.location.search);
    params.set('payments_page', page);
    fetch('<?= base_url("/admin/payments") ?>?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById('paymentsTableWrapper').innerHTML = html;
    });
}

document.addEventListener('click', function(e) {
    if (e.target.closest('#paymentsPagination a')) {
        e.preventDefault();
        const page = e.target.dataset.page;
        loadPayments(page);
    }
});
</script>

<?= $this->endSection() ?>
