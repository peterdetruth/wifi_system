<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container my-5">

    <!-- How to Purchase -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h4>How to Purchase</h4>
                <ol class="mb-0">
                    <li>Tap on your preferred package</li>
                    <li>Enter your phone number</li>
                    <li>Click <strong>PAY NOW</strong></li>
                    <li>Enter your M-PESA PIN and wait up to 30 seconds</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Packages -->
    <div class="row">
        <?php foreach ($packages as $package) : ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><?= esc($package['name']) ?></h5>
                        <p class="card-text">
                            <strong>Price:</strong> KES <?= esc($package['price']) ?><br>
                            <strong>Duration:</strong> <?= esc($package['duration_length'] . ' ' . $package['duration_unit']) ?><br>
                            <strong>Bandwidth:</strong> <?= esc($package['bandwidth_value'] . ' ' . $package['bandwidth_unit']) ?><br>
                        </p>

                        <button type="button" class="btn btn-primary toggle-form"
                                data-package-id="<?= esc($package['id']) ?>">
                            Pay Now
                        </button>

                        <!-- Hidden AJAX form -->
                        <div id="package-form-<?= esc($package['id']) ?>" class="package-form mt-3 d-none">
                            <form class="ajax-package-form" data-package-id="<?= esc($package['id']) ?>">
                                <input type="hidden" name="package_id" value="<?= esc($package['id']) ?>">

                                <div class="mb-2">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" required>
                                </div>

                                <button type="submit" class="btn btn-success w-100">Pay Now</button>

                                <div id="status-<?= esc($package['id']) ?>" class="mt-2 text-center"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>


    <!-- Voucher Redeem Section -->
    <div class="row mt-5">
        <div class="col-md-6 offset-md-3">

            <div class="card shadow-sm">
                <div class="card-body">

                    <h4>Redeem Voucher</h4>
                    <p>Enter your voucher code below to activate your package instantly.</p>

                    <form id="voucher-redeem-form">
                        <div class="mb-3">
                            <label for="voucher" class="form-label">Voucher Code</label>
                            <input type="text" class="form-control" name="voucher_code" id="voucher" required>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            Redeem Voucher
                        </button>

                        <div id="voucher-status" class="mt-3 text-center"></div>
                    </form>

                </div>
            </div>

        </div>
    </div>

</div>

<?= $this->endSection() ?>
