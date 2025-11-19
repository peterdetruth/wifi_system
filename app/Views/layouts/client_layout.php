<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($title ?? 'Client Area') ?> | UniWiPay</title>

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

  <!-- Custom stylesheet (reuse or separate) -->
  <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <a class="navbar-brand" href="<?= base_url('/client/dashboard') ?>">UniWiPay</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <?php if (session()->get('client_id')): ?>
            <li class="nav-item">
              <a class="nav-link <?= url_is('client/dashboard') ? 'active' : '' ?>" href="<?= base_url('client/dashboard') ?>">Dashboard</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= url_is('client/packages*') ? 'active' : '' ?>" href="<?= base_url('client/packages') ?>">Packages</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= url_is('client/subscriptions*') ? 'active' : '' ?>" href="<?= base_url('client/subscriptions') ?>">My Subscriptions</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= url_is('client/transactions*') ? 'active' : '' ?>" href="<?= base_url('client/transactions') ?>">Transactions</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= url_is('client/vouchers/redeem*') ? 'active' : '' ?>" href="<?= base_url('client/vouchers/redeem') ?>">Redeem</a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= url_is('client/profile*') ? 'active' : '' ?>" href="<?= base_url('client/profile') ?>">Profile</a>
            </li>
            <li class="nav-item">
              <a class="nav-link text-warning" href="<?= base_url('client/logout') ?>">Logout</a>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= base_url('/client/login') ?>">Login</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= base_url('/client/register') ?>">Register</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Page Content -->
  <main class="container my-4">
    <?= $this->renderSection('content') ?>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
