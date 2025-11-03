<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($title ?? 'Admin Dashboard') ?></title>
  <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap-icons.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
  <script src="<?= base_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
  <style>
    body {
      background-color: #f8f9fa;
    }

    /* Sidebar styling */
    #sidebar-wrapper {
      min-width: 240px;
      max-width: 240px;
      transition: all 0.3s ease;
    }
    #wrapper.toggled #sidebar-wrapper {
      margin-left: -240px;
    }

    .sidebar-heading {
      font-size: 1.3rem;
      letter-spacing: 0.5px;
    }

    .list-group-item {
      border: none;
      padding: 12px 20px;
      transition: background-color 0.2s, color 0.2s;
    }
    .list-group-item:hover,
    .list-group-item.active {
      background-color: #0d6efd !important;
      color: white !important;
    }

    .menu-section {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      opacity: 0.7;
      padding: 10px 20px 5px;
    }

    .navbar {
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
  </style>
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
        <div class="menu-section text-secondary">Subscribers</div>
        <a href="<?= base_url('admin/subscribers/active') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-wifi me-2"></i> Active
        </a>
        <a href="<?= base_url('admin/subscribers/expired') ?>" class="list-group-item bg-dark text-white">
          <i class="bi bi-person-x me-2"></i> Expired
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

  <script>
    const toggleBtn = document.getElementById("menu-toggle");
    const wrapper = document.getElementById("wrapper");
    toggleBtn.addEventListener("click", () => {
      wrapper.classList.toggle("toggled");
    });
  </script>
</body>
</html>
