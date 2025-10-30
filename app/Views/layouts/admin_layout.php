<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($title ?? 'Admin Dashboard') ?></title>
  <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
  <script src="<?= base_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
</head>
<body>

  <!-- Sidebar -->
  <div class="d-flex" id="wrapper">
    <div class="bg-dark border-right text-white" id="sidebar-wrapper">
      <div class="sidebar-heading text-center py-4 fw-bold border-bottom">
        WiFi Admin
      </div>
      <div class="list-group list-group-flush">
        <a href="<?= base_url('admin/dashboard') ?>" class="list-group-item list-group-item-action bg-dark text-white">Dashboard</a>
        <a href="<?= base_url('admin/admins') ?>" class="list-group-item list-group-item-action bg-dark text-white">Admins</a>
        <a href="<?= base_url('admin/packages') ?>" class="list-group-item list-group-item-action bg-dark text-white">Packages</a>
        <a href="<?= base_url('admin/clients') ?>" class="list-group-item list-group-item-action bg-dark text-white">Clients</a>
        <a href="<?= base_url('admin/routers') ?>" class="list-group-item list-group-item-action bg-dark text-white">Routers</a>
        <a href="<?= base_url('admin/vouchers') ?>" class="list-group-item list-group-item-action bg-dark text-white">Access Codes</a>
        <a href="<?= base_url('admin/transactions') ?>" class="list-group-item list-group-item-action bg-dark text-white">Payment Transactions</a>
        <a href="<?= base_url('admin/mpesa') ?>" class="list-group-item list-group-item-action bg-dark text-white">M-Pesa Transactions</a>
        <a href="<?= base_url('/logout') ?>" class="list-group-item list-group-item-action bg-danger text-white mt-3">Logout</a>
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
    // Toggle Sidebar
    const toggleBtn = document.getElementById("menu-toggle");
    const wrapper = document.getElementById("wrapper");
    toggleBtn.addEventListener("click", () => {
      wrapper.classList.toggle("toggled");
    });
  </script>

</body>
</html>
