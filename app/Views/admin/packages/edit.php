<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">
  <h3 class="mb-4">Edit Package</h3>
  <?= view('templates/alerts') ?>

  <form action="<?= base_url('admin/packages/update/' . $package['id']) ?>" method="post">
    <?= csrf_field() ?>

    <!-- Basic Info -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Package Name</label>
        <input type="text" name="name" class="form-control" value="<?= esc($package['name']) ?>" required>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Package Type</label>
        <select name="type" id="packageType" class="form-select" required>
          <option value="">-- Select Type --</option>
          <option value="hotspot" <?= $package['type'] === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
          <option value="pppoe" <?= $package['type'] === 'pppoe' ? 'selected' : '' ?>>PPPoE</option>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label for="account_type" class="form-label">Account Type</label>
      <select name="account_type" id="account_type" class="form-select" required>
        <option value="personal" <?= $package['account_type'] === 'personal' ? 'selected' : '' ?>>Personal</option>
        <option value="business" <?= $package['account_type'] === 'business' ? 'selected' : '' ?>>Business</option>
      </select>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Duration Length</label>
        <input type="number" name="duration_length" class="form-control" min="1" value="<?= esc($package['duration_length']) ?>" required>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Duration Unit</label>
        <select name="duration_unit" class="form-select" required>
          <option value="minutes" <?= $package['duration_unit'] === 'minutes' ? 'selected' : '' ?>>Minutes</option>
          <option value="hours" <?= $package['duration_unit'] === 'hours' ? 'selected' : '' ?>>Hours</option>
          <option value="days" <?= $package['duration_unit'] === 'days' ? 'selected' : '' ?>>Days</option>
          <option value="months" <?= $package['duration_unit'] === 'months' ? 'selected' : '' ?>>Months</option>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Price (Ksh)</label>
        <input type="number" step="0.01" name="price" class="form-control" value="<?= esc($package['price']) ?>" required>
      </div>
    </div>

    <!-- Router -->
    <div class="mb-3">
      <label class="form-label">Router</label>
      <select name="router_id" class="form-select" required>
        <option value="">-- Select Router --</option>
        <?php foreach ($routers as $router): ?>
          <option value="<?= esc($router['id']) ?>" <?= $package['router_id'] == $router['id'] ? 'selected' : '' ?>>
            <?= esc($router['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Bandwidth Configuration -->
    <h5 class="mt-4">Bandwidth Configuration</h5>
    <div class="row align-items-center">
      <div class="col-md-3 mb-3">
        <input type="number" name="bandwidth_value" class="form-control"
               value="<?= esc($package['bandwidth_value'] ?? '') ?>" placeholder="Enter value">
      </div>
      <div class="col-md-3 mb-3">
        <select name="bandwidth_unit" class="form-select">
          <option value="Mbps" <?= ($package['bandwidth_unit'] ?? '') === 'Mbps' ? 'selected' : '' ?>>Mbps</option>
          <option value="Kbps" <?= ($package['bandwidth_unit'] ?? '') === 'Kbps' ? 'selected' : '' ?>>Kbps</option>
        </select>
      </div>
    </div>

    <!-- Burst Settings Toggle -->
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" id="burstToggle" name="burst_enabled" value="1"
             <?= !empty($package['burst_enabled']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="burstToggle">Enable Burst Settings</label>
    </div>

    <div id="burstSettings" style="display: <?= !empty($package['burst_enabled']) ? 'block' : 'none' ?>; border-left:3px solid #0d6efd; padding-left:1rem;">
      <div class="row mt-2">
        <h6>Upload Burst Rate</h6>
        <div class="col-md-3 mb-2">
          <input type="number" name="upload_burst_rate_value" class="form-control"
                 value="<?= esc($package['upload_burst_rate_value'] ?? '') ?>">
        </div>
        <div class="col-md-3 mb-2">
          <select name="upload_burst_rate_unit" class="form-select">
            <option value="Mbps" <?= ($package['upload_burst_rate_unit'] ?? '') === 'Mbps' ? 'selected' : '' ?>>Mbps</option>
            <option value="Kbps" <?= ($package['upload_burst_rate_unit'] ?? '') === 'Kbps' ? 'selected' : '' ?>>Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Download Burst Rate</h6>
        <div class="col-md-3 mb-2">
          <input type="number" name="download_burst_rate_value" class="form-control"
                 value="<?= esc($package['download_burst_rate_value'] ?? '') ?>">
        </div>
        <div class="col-md-3 mb-2">
          <select name="download_burst_rate_unit" class="form-select">
            <option value="Mbps" <?= ($package['download_burst_rate_unit'] ?? '') === 'Mbps' ? 'selected' : '' ?>>Mbps</option>
            <option value="Kbps" <?= ($package['download_burst_rate_unit'] ?? '') === 'Kbps' ? 'selected' : '' ?>>Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Upload Burst Threshold</h6>
        <div class="col-md-3 mb-2">
          <input type="number" name="upload_burst_threshold_value" class="form-control"
                 value="<?= esc($package['upload_burst_threshold_value'] ?? '') ?>">
        </div>
        <div class="col-md-3 mb-2">
          <select name="upload_burst_threshold_unit" class="form-select">
            <option value="Mbps" <?= ($package['upload_burst_threshold_unit'] ?? '') === 'Mbps' ? 'selected' : '' ?>>Mbps</option>
            <option value="Kbps" <?= ($package['upload_burst_threshold_unit'] ?? '') === 'Kbps' ? 'selected' : '' ?>>Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Download Burst Threshold</h6>
        <div class="col-md-3 mb-2">
          <input type="number" name="download_burst_threshold_value" class="form-control"
                 value="<?= esc($package['download_burst_threshold_value'] ?? '') ?>">
        </div>
        <div class="col-md-3 mb-2">
          <select name="download_burst_threshold_unit" class="form-select">
            <option value="Mbps" <?= ($package['download_burst_threshold_unit'] ?? '') === 'Mbps' ? 'selected' : '' ?>>Mbps</option>
            <option value="Kbps" <?= ($package['download_burst_threshold_unit'] ?? '') === 'Kbps' ? 'selected' : '' ?>>Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Upload Burst Time</h6>
        <div class="col-md-3 mb-2">
          <input type="number" name="upload_burst_time" class="form-control"
                 value="<?= esc($package['upload_burst_time'] ?? '') ?>" placeholder="Seconds">
        </div>
        <div class="col-md-3 mb-2"><span class="form-text">Seconds</span></div>
      </div>

      <div class="row">
        <h6>Download Burst Time</h6>
        <div class="col-md-3 mb-2">
          <input type="number" name="download_burst_time" class="form-control"
                 value="<?= esc($package['download_burst_time'] ?? '') ?>" placeholder="Seconds">
        </div>
        <div class="col-md-3 mb-2"><span class="form-text">Seconds</span></div>
      </div>
    </div>

    <!-- Hotspot Settings -->
    <div id="hotspotSettings" style="display: <?= $package['type'] === 'hotspot' ? 'block' : 'none' ?>; margin-top:1rem; border-left:3px solid orange; padding-left:1rem;">
      <h5>Hotspot Settings</h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Hotspot Plan Type</label>
          <select name="hotspot_plan_type" class="form-select">
            <option value="Unlimited" <?= ($package['hotspot_plan_type'] ?? '') === 'Unlimited' ? 'selected' : '' ?>>Unlimited</option>
            <option value="Data Plans" <?= ($package['hotspot_plan_type'] ?? '') === 'Data Plans' ? 'selected' : '' ?>>Data Plans</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Number of Devices</label>
          <input type="number" name="hotspot_devices" class="form-control"
                 value="<?= esc($package['hotspot_devices'] ?? '') ?>">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-success mt-4">Update Package</button>
    <a href="<?= site_url('admin/packages') ?>" class="btn btn-secondary mt-4">Cancel</a>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const burstToggle = document.getElementById('burstToggle');
  const burstSettings = document.getElementById('burstSettings');
  const packageType = document.getElementById('packageType');
  const hotspotSettings = document.getElementById('hotspotSettings');

  burstToggle.addEventListener('change', function () {
    burstSettings.style.display = this.checked ? 'block' : 'none';
  });

  packageType.addEventListener('change', function () {
    hotspotSettings.style.display = this.value === 'hotspot' ? 'block' : 'none';
  });
});
</script>

<?= $this->endSection() ?>
