<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= esc($title ?? 'Client Portal') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap 5 -->
  <link href="<?= base_url('assets/css/bootstrap.min.css') ?>" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="<?= base_url('assets/css/bootstrap-icons.css') ?>" rel="stylesheet">

  <style>
    body {
      background: #f8f9fa;
      font-family: "Poppins", sans-serif;
    }
    .navbar-brand {
      font-weight: 600;
      color: #0d6efd !important;
    }
    footer {
      margin-top: 60px;
      padding: 20px 0;
      background: #fff;
      border-top: 1px solid #eee;
      text-align: center;
      color: #777;
      font-size: 0.9rem;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="<?= site_url('client/dashboard') ?>">WiFi System</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a href="<?= site_url('client/dashboard') ?>" class="nav-link">Dashboard</a></li>
        <li class="nav-item"><a href="<?= site_url('client/subscriptions') ?>" class="nav-link">Subscriptions</a></li>
        <li class="nav-item"><a href="<?= site_url('client/transactions') ?>" class="nav-link">Transactions</a></li>
        <li class="nav-item"><a href="<?= site_url('client/logout') ?>" class="nav-link text-danger">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Main Content -->
<main class="container py-4">
  <?= $this->renderSection('content') ?>
</main>

<!-- Footer -->
<footer>
  &copy; <?= date('Y') ?> WiFi Management System. All rights reserved.
</footer>

<script src="<?= base_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
