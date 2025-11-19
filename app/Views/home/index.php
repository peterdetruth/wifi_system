<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Purchase Packages</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/home.css') ?>">
</head>

<body>
    <div class="container">
        <header>
            <h1>Purchase Your Hotspot Package</h1>
            <p>Follow the steps below to complete your purchase:</p>
            <ol class="purchase-guide">
                <li>Tap on your preferred package</li>
                <li>Enter your phone number</li>
                <li>Click <strong>PAY NOW</strong></li>
                <li>Enter M-PESA PIN and wait ~30 seconds for authentication</li>
            </ol>
        </header>

        <section class="packages">
            <?php if (!empty($packages)) : ?>
                <?php foreach ($packages as $package) : ?>
                    <div class="package-card" data-package-id="<?= esc($package['id']) ?>">
                        <h2><?= esc($package['name']) ?></h2>
                        <p class="package-price">$<?= number_format($package['price'], 2) ?></p>
                        <p class="package-details">
                            Duration: <?= esc($package['duration_length'] . ' ' . $package['duration_unit']) ?><br>
                            Validity: <?= esc($package['validity_days']) ?> days<br>
                            Bandwidth: <?= esc($package['bandwidth_value']) . ' ' . esc($package['bandwidth_unit']) ?><br>
                            <?= esc($package['hotspot_plan_type']) ?> plan
                        </p>
                        <button class="pay-now-btn">PAY NOW</button>

                        <form class="pay-now-form" action="<?= site_url('/client/payments/process') ?>" method="POST" style="display: none;">
                            <input type="hidden" name="package_id" value="<?= esc($package['id']) ?>">
                            <label>Phone Number:
                                <input type="text" name="phone" placeholder="Enter your phone number" required>
                            </label>
                            <button type="submit">Submit Payment</button>
                        </form>

                        <div class="payment-status" style="display:none;"></div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p>No packages available at the moment.</p>
            <?php endif; ?>
        </section>
    </div>

    <script src="<?= base_url('assets/js/home.js') ?>"></script>
</body>

</html>