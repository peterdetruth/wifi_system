<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WiFi Packages</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/home.css') ?>">
</head>
<body>

    <section class="how-to-buy">
        <h2>How to Purchase</h2>
        <ol>
            <li>Tap on your preferred package</li>
            <li>Enter your phone number</li>
            <li>Click <strong>PAY NOW</strong></li>
            <li>Enter M-PESA PIN and wait for 30 seconds for M-PESA authentication</li>
        </ol>
    </section>

    <section class="packages">
        <h2>Available Packages</h2>
        <div class="package-list">
            <?php foreach ($packages as $package): ?>
                <div class="package" data-package-id="<?= $package['id'] ?>">
                    <h3><?= esc($package['name']) ?> - $<?= number_format($package['price'], 2) ?></h3>
                    <p><?= esc($package['duration_length'] . ' ' . $package['duration_unit']) ?> | Validity: <?= esc($package['validity_days']) ?> days</p>
                    <button class="buy-now-btn">Select Package</button>

                    <form class="payment-form" method="POST" action="<?= base_url('/client/payments/process') ?>" style="display:none;">
                        <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                        <label>Phone Number:
                            <input type="text" name="phone" placeholder="Enter your phone number" required>
                        </label>
                        <button type="submit">PAY NOW</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <script src="<?= base_url('assets/js/home.js') ?>"></script>
</body>
</html>
