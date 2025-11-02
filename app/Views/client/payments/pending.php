<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container text-center mt-5">
  <h2 class="text-warning mb-3">
    <i class="bi bi-hourglass-split"></i> Payment Pending...
  </h2>
  <p class="text-muted mb-4">
    We’ve sent an M-PESA STK push to your phone. Please enter your PIN to complete payment.
  </p>

  <div class="spinner-border text-primary mb-3" role="status">
    <span class="visually-hidden">Loading...</span>
  </div>

  <p>We’re checking your payment status automatically. This may take up to 1 minute.</p>

  <a href="<?= site_url('client/dashboard') ?>" class="btn btn-outline-secondary mt-3">
    Cancel and Return to Dashboard
  </a>
</div>

<script>
  // Auto-check payment status every 7 seconds
  const checkInterval = setInterval(() => {
    fetch("<?= site_url('client/payments/check-status') ?>")
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          clearInterval(checkInterval);
          window.location.href = "<?= site_url('client/payments/success/') ?>" + data.transaction_id;
        }
      })
      .catch(err => console.error('Status check failed:', err));
  }, 7000);
</script>

<?= $this->endSection() ?>
