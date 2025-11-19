<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container my-5">
    <!-- How to purchase guide -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h4>How to Purchase</h4>
                <ol class="mb-0">
                    <li>Tap on your preferred package</li>
                    <li>Enter your phone number</li>
                    <li>Click <strong>PAY NOW</strong></li>
                    <li>Enter your M-PESA PIN and wait up to 30 seconds for authentication</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Packages display -->
    <div class="row">
        <?php foreach ($packages as $package) : ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><?= esc($package['name']) ?></h5>
                        <p class="card-text">
                            <strong>Price:</strong> $<?= esc($package['price']) ?><br>
                            <strong>Duration:</strong> <?= esc($package['duration_length'] . ' ' . $package['duration_unit']) ?><br>
                            <strong>Bandwidth:</strong> <?= esc($package['bandwidth_value'] . ' ' . $package['bandwidth_unit']) ?><br>
                            <strong>Plan Type:</strong> <?= esc($package['hotspot_plan_type'] ?? 'N/A') ?>
                        </p>

                        <button type="button" class="btn btn-primary toggle-form" data-package-id="<?= esc($package['id']) ?>">
                            Pay Now
                        </button>

                        <!-- Hidden form -->
                        <div id="package-form-<?= esc($package['id']) ?>" class="package-form mt-3 d-none">
                            <form class="needs-validation" novalidate>
                                <input type="hidden" name="package_id" value="<?= esc($package['id']) ?>">
                                <div class="mb-2">
                                    <label for="phone-<?= esc($package['id']) ?>" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone-<?= esc($package['id']) ?>" name="phone" required>
                                    <div class="invalid-feedback">Phone number is required.</div>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Pay Now</button>
                                <div class="status-message mt-2"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?= $this->endSection() ?>
