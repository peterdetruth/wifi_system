<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'UniWiPay') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/css/home.css') ?>">

    <!-- Font Awesome (optional) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

    <!-- Navbar (now matches client_layout) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?= base_url('/') ?>">UniWiPay</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">

                    <?php if (session()->get('client_id')): ?>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/dashboard') ? 'active' : '' ?>"
                               href="<?= base_url('client/dashboard') ?>">Dashboard</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/packages*') ? 'active' : '' ?>"
                               href="<?= base_url('client/packages') ?>">Packages</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/subscriptions*') ? 'active' : '' ?>"
                               href="<?= base_url('client/subscriptions') ?>">My Subscriptions</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/transactions*') ? 'active' : '' ?>"
                               href="<?= base_url('client/transactions') ?>">Transactions</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/vouchers/redeem*') ? 'active' : '' ?>"
                               href="<?= base_url('client/vouchers/redeem') ?>">Redeem</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/profile*') ? 'active' : '' ?>"
                               href="<?= base_url('client/profile') ?>">Profile</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link text-warning" href="<?= base_url('client/logout') ?>">Logout</a>
                        </li>

                    <?php else: ?>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/login') ? 'active' : '' ?>"
                               href="<?= base_url('/client/login') ?>">Login</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/register') ? 'active' : '' ?>"
                               href="<?= base_url('/client/register') ?>">Register</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/packages') ? 'active' : '' ?>"
                               href="<?= base_url('/client/packages') ?>">Packages</a>
                        </li>

                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container my-4">
        <?= $this->renderSection('content') ?>
    </main>

    <!-- Footer -->
    <footer class="bg-light py-4 border-top mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> UniWiPay. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="<?= base_url('assets/js/home.js') ?>"></script>

</body>
</html>
