<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($title ?? 'Admin Dashboard') ?></title>

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <!-- Admin styles -->
  <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">

</head>

<body>
  <div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-dark text-white" id="sidebar-wrapper">
      <div class="sidebar-heading text-center py-4 fw-bold border-bottom border-secondary">
        <i class="bi bi-wifi"></i> WiFi Admin
      </div>

      <div class="list-group list-group-flush">

        <!-- MAIN -->
        <div class="menu-section text-secondary">Main</div>
        <a href="<?= base_url('admin/dashboard') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="<?= base_url('admin/admins') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-person-gear me-2"></i> Admins
        </a>

        <!-- MANAGEMENT -->
        <div class="menu-section text-secondary">Management</div>
        <a href="<?= base_url('admin/packages') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-box me-2"></i> Packages
        </a>
        <a href="<?= base_url('admin/clients') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-people me-2"></i> Clients
        </a>
        <a href="<?= base_url('admin/routers') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-router me-2"></i> Routers
        </a>
        <a href="<?= base_url('admin/vouchers') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-ticket-perforated me-2"></i> Access Codes
        </a>

        <!-- PAYMENTS -->
        <div class="menu-section text-secondary">Payments</div>
        <a href="<?= base_url('admin/transactions') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-receipt me-2"></i> Transactions
        </a>
        <a href="<?= base_url('admin/payments') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-cash-coin me-2"></i> Payments
        </a>
        <a href="<?= base_url('admin/mpesa-logs') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-phone-vibrate me-2"></i> M-PESA Logs
        </a>

        <!-- SUBSCRIBERS -->
        <div class="menu-section text-secondary">Features</div>
        <a href="<?= base_url('admin/features') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-list-check me-2"></i> Features
        </a>

        <!-- LOGOUT -->
        <a href="<?= base_url('/logout') ?>" class="list-group-item bg-danger text-white mt-3">
          <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
        <br><br>
      </div>
    </div>

    <!-- Page Content -->
    <div id="page-content-wrapper" class="w-100">
      <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <div class="container-fluid">
          <button class="btn btn-outline-secondary" id="menu-toggle">☰</button>
          <span class="navbar-text ms-3 fw-bold"><?= esc($title ?? 'Admin Panel') ?></span>
        </div>
      </nav>

      <div class="container-fluid mt-4">
        <?= $this->renderSection('content') ?>
      </div>
    </div>
  </div>

  <!-- Dependencies -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js: you may replace with your local copy if you have one -->
  <script src="<?= base_url('assets/js/chart.js') ?>"></script>

  <!-- Admin script -->
  <script src="<?= base_url('assets/js/admin.js') ?>"></script>

  <!-- Sidebar toggle -->
  <script>
    (function() {
      const toggleBtn = document.getElementById("menu-toggle");
      const wrapper = document.getElementById("wrapper");
      if (toggleBtn && wrapper) {
        toggleBtn.addEventListener("click", () => wrapper.classList.toggle("toggled"));
      }
    })();
  </script>
</body>
</html>
