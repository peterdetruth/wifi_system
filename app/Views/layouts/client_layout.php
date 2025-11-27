<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($title ?? 'Client Area') ?> | UniWiPay</title>

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <!-- Custom stylesheet -->
  <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">

      <!-- NEW: Home link -->
      <a class="navbar-brand d-flex align-items-center" href="<?= base_url('/') ?>">
        <i class="bi bi-house-door-fill me-2"></i> UniWiPay
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">

          <?php if (session()->get('client_id')): ?>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center <?= url_is('client/dashboard') ? 'active' : '' ?>"
                 href="<?= base_url('client/dashboard') ?>">
                 <i class="bi bi-speedometer2 me-1"></i> Dashboard
              </a>
            </li>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center <?= url_is('client/packages*') ? 'active' : '' ?>"
                 href="<?= base_url('client/packages') ?>">
                 <i class="bi bi-box-seam me-1"></i> Packages
              </a>
            </li>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center <?= url_is('client/subscriptions*') ? 'active' : '' ?>"
                 href="<?= base_url('client/subscriptions') ?>">
                 <i class="bi bi-card-checklist me-1"></i> My Subscriptions
              </a>
            </li>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center <?= url_is('client/transactions*') ? 'active' : '' ?>"
                 href="<?= base_url('client/transactions') ?>">
                 <i class="bi bi-credit-card-2-front me-1"></i> Transactions
              </a>
            </li>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center <?= url_is('client/vouchers/redeem*') ? 'active' : '' ?>"
                 href="<?= base_url('client/vouchers/redeem') ?>">
                 <i class="bi bi-ticket-perforated me-1"></i> Redeem
              </a>
            </li>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center <?= url_is('client/profile*') ? 'active' : '' ?>"
                 href="<?= base_url('client/profile') ?>">
                 <i class="bi bi-person-circle me-1"></i> Profile
              </a>
            </li>

            <li class="nav-item">
              <a class="nav-link text-warning d-flex align-items-center"
                 href="<?= base_url('client/logout') ?>">
                 <i class="bi bi-box-arrow-right me-1"></i> Logout
              </a>
            </li>

          <?php else: ?>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="<?= base_url('/client/login') ?>">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
              </a>
            </li>

            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" href="<?= base_url('/client/register') ?>">
                <i class="bi bi-pencil-square me-1"></i> Register
              </a>
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
