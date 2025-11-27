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

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">

            <!-- Brand -->
            <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= base_url('/') ?>">
                <i class="fa-solid fa-wifi me-2"></i> UniWiPay
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu -->
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">

                    <?php if (!session()->get('client_id')): ?> 
                        <!-- PUBLIC NAV LINKS -->

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('/') ? 'active' : '' ?>" href="<?= base_url('/') ?>">
                                <i class="fa-solid fa-house me-1"></i> Home
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('about') ? 'active' : '' ?>" href="<?= base_url('about') ?>">
                                <i class="fa-solid fa-circle-info me-1"></i> About
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('pricing') ? 'active' : '' ?>" href="<?= base_url('pricing') ?>">
                                <i class="fa-solid fa-tags me-1"></i> Pricing
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/packages') ? 'active' : '' ?>" href="<?= base_url('client/packages') ?>">
                                <i class="fa-solid fa-box-open me-1"></i> Packages
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('contact') ? 'active' : '' ?>" href="<?= base_url('contact') ?>">
                                <i class="fa-solid fa-envelope me-1"></i> Contact
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/login') ? 'active' : '' ?>" href="<?= base_url('client/login') ?>">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/register') ? 'active' : '' ?>" href="<?= base_url('client/register') ?>">
                                <i class="fa-solid fa-user-plus me-1"></i> Register
                            </a>
                        </li>

                    <?php else: ?>  
                        <!-- LOGGED-IN CLIENT NAV LINKS -->

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/dashboard') ? 'active' : '' ?>" href="<?= base_url('client/dashboard') ?>">
                                <i class="fa-solid fa-gauge me-1"></i> Dashboard
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/packages*') ? 'active' : '' ?>" href="<?= base_url('client/packages') ?>">
                                <i class="fa-solid fa-box-open me-1"></i> Packages
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/subscriptions*') ? 'active' : '' ?>" href="<?= base_url('client/subscriptions') ?>">
                                <i class="fa-solid fa-clipboard-list me-1"></i> My Subscriptions
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/transactions*') ? 'active' : '' ?>" href="<?= base_url('client/transactions') ?>">
                                <i class="fa-solid fa-money-bill-transfer me-1"></i> Transactions
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/vouchers/redeem') ? 'active' : '' ?>" href="<?= base_url('client/vouchers/redeem') ?>">
                                <i class="fa-solid fa-ticket me-1"></i> Redeem
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?= url_is('client/profile*') ? 'active' : '' ?>" href="<?= base_url('client/profile') ?>">
                                <i class="fa-solid fa-user me-1"></i> Profile
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link text-warning" href="<?= base_url('client/logout') ?>">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                            </a>
                        </li>

                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mt-4">
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
