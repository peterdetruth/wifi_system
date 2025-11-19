<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'WiFi System' ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom Home CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/css/home.css') ?>">

    <!-- Optional: Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= base_url('/') ?>">WiFi System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('/client/login') ?>">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('/client/packages') ?>">Packages</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mb-5">
        <?= $this->renderSection('content') ?>
    </main>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-auto border-top">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> WiFi System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="<?= base_url('assets/js/home.js') ?>"></script>
</body>
</html>
