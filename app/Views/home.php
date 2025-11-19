<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="container">
    <h1>How to Purchase</h1>
    <ol>
        <li>Tap on your preferred package</li>
        <li>Enter your phone number</li>
        <li>Click <strong>PAY NOW</strong></li>
        <li>Enter M-PESA PIN, wait ~30 sec for authentication</li>
    </ol>

    <h2>Available Packages</h2>
    <div class="packages-grid">
        <?php foreach ($packages as $pkg): ?>
            <div class="package-card" data-package-id="<?= esc($pkg['id']) ?>">
                <h3><?= esc($pkg['name']) ?></h3>
                <p>Price: $<?= esc($pkg['price']) ?></p>
                <p>Duration: <?= esc($pkg['duration_length']) ?> <?= esc($pkg['duration_unit']) ?></p>
                <p>Bandwidth: <?= esc($pkg['bandwidth_value']) ?> <?= esc($pkg['bandwidth_unit']) ?></p>
                <p>Devices: <?= esc($pkg['hotspot_devices']) ?></p>
                <?php if ($pkg['burst_enabled']): ?>
                    <p>Burst: Enabled (Upload <?= esc($pkg['upload_burst_rate_value']) ?> <?= esc($pkg['upload_burst_rate_unit']) ?> / Download <?= esc($pkg['download_burst_rate_value']) ?> <?= esc($pkg['download_burst_rate_unit']) ?>)</p>
                <?php else: ?>
                    <p>Burst: Disabled</p>
                <?php endif; ?>

                <!-- Payment Form (hidden initially) -->
                <form class="payment-form" style="display:none;" method="POST" action="<?= site_url('payments/process') ?>">
                    <input type="hidden" name="package_id" value="<?= esc($pkg['id']) ?>">
                    <label>Phone Number:</label>
                    <input type="text" name="phone" required placeholder="2547XXXXXXXX">
                    <button type="submit" class="pay-now-btn">PAY NOW</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?= $this->endSection() ?>
