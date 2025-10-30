<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">
  <h3 class="mb-4">Create Package</h3>
  <?= view('templates/alerts') ?>

  <form action="<?= base_url('admin/packages/store') ?>" method="post">
    <?= csrf_field() ?>

    <!-- Basic Info -->
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Package Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Package Type</label>
        <select name="type" id="packageType" class="form-select" required>
          <option value="">-- Select Type --</option>
          <option value="hotspot">Hotspot</option>
          <option value="pppoe">PPPoE</option>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label for="account_type" class="form-label">Account Type</label>
      <select name="account_type" id="account_type" class="form-select" required>
        <option value="personal">Personal</option>
        <option value="business">Business</option>
      </select>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Duration Length</label>
        <input type="number" name="duration_length" class="form-control" min="1" required>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Duration Unit</label>
        <select name="duration_unit" class="form-select" required>
          <option value="minutes">Minutes</option>
          <option value="hours">Hours</option>
          <option value="days">Days</option>
          <option value="months">Months</option>
        </select>
      </div>

      <div class="col-md-4 mb-3">
        <label class="form-label">Price (Ksh)</label>
        <input type="number" step="0.01" name="price" class="form-control" required>
      </div>
    </div>

    <!-- Router Selection -->
    <div class="mb-3">
      <label class="form-label">Router</label>
      <select name="router_id" class="form-select" required>
        <option value="">-- Select Router --</option>
        <?php foreach ($routers as $router): ?>
          <option value="<?= esc($router['id']) ?>"><?= esc($router['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Bandwidth Configuration -->
    <h5 class="mt-4">Bandwidth Configuration</h5>
    <div class="row align-items-center">
      <div class="col-md-3 mb-3">
        <input type="number" name="bandwidth_value" class="form-control" placeholder="Enter value">
      </div>
      <div class="col-md-3 mb-3">
        <select name="bandwidth_unit" class="form-select">
          <option value="Mbps">Mbps</option>
          <option value="Kbps">Kbps</option>
        </select>
      </div>
    </div>

    <!-- Burst Settings -->
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" id="burstToggle" name="burst_enabled" value="1">
      <label class="form-check-label" for="burstToggle">Enable Burst Settings</label>
    </div>

    <div id="burstSettings" style="display:none; border-left:3px solid #0d6efd; padding-left:1rem;">
      <div class="row mt-2">
        <h6>Upload Burst Rate</h6>
        <div class="col-md-3 mb-2"><input type="number" name="upload_burst_rate_value" class="form-control"></div>
        <div class="col-md-3 mb-2">
          <select name="upload_burst_rate_unit" class="form-select">
            <option value="Mbps">Mbps</option>
            <option value="Kbps">Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Download Burst Rate</h6>
        <div class="col-md-3 mb-2"><input type="number" name="download_burst_rate_value" class="form-control"></div>
        <div class="col-md-3 mb-2">
          <select name="download_burst_rate_unit" class="form-select">
            <option value="Mbps">Mbps</option>
            <option value="Kbps">Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Upload Burst Threshold</h6>
        <div class="col-md-3 mb-2"><input type="number" name="upload_burst_threshold_value" class="form-control"></div>
        <div class="col-md-3 mb-2">
          <select name="upload_burst_threshold_unit" class="form-select">
            <option value="Mbps">Mbps</option>
            <option value="Kbps">Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Download Burst Threshold</h6>
        <div class="col-md-3 mb-2"><input type="number" name="download_burst_threshold_value" class="form-control"></div>
        <div class="col-md-3 mb-2">
          <select name="download_burst_threshold_unit" class="form-select">
            <option value="Mbps">Mbps</option>
            <option value="Kbps">Kbps</option>
          </select>
        </div>
      </div>

      <div class="row">
        <h6>Upload Burst Time</h6>
        <div class="col-md-3 mb-2"><input type="number" name="upload_burst_time" class="form-control" placeholder="Seconds"></div>
        <div class="col-md-3 mb-2"><span class="form-text">Seconds</span></div>
      </div>

      <div class="row">
        <h6>Download Burst Time</h6>
        <div class="col-md-3 mb-2"><input type="number" name="download_burst_time" class="form-control" placeholder="Seconds"></div>
        <div class="col-md-3 mb-2"><span class="form-text">Seconds</span></div>
      </div>
    </div>

    <!-- Hotspot Settings -->
    <div id="hotspotSettings" style="display:none; margin-top:1rem; border-left:3px solid orange; padding-left:1rem;">
      <h5>Hotspot Settings</h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Hotspot Plan Type</label>
          <select name="hotspot_plan_type" class="form-select">
            <option value="Unlimited">Unlimited</option>
            <option value="Data Plans">Data Plans</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Number of Devices</label>
          <input type="number" name="hotspot_devices" class="form-control">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary mt-4">Save Package</button>
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
