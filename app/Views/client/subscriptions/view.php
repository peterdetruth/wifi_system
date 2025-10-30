<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Subscription Details</h3>
    <a href="<?= base_url('client/subscriptions') ?>" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <?= view('templates/alerts') ?>

  <?php if (!empty($subscription)): ?>
    <?php
      $isExpired = strtotime($subscription['expires_on']) < time();
      $status = $isExpired ? 'Expired' : ucfirst($subscription['status']);
      $statusClass = $isExpired ? 'text-danger fw-bold' : 'text-success fw-bold';
    ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title mb-3"><?= esc($subscription['package_name'] ?? 'Unknown Package') ?></h5>
        
        <div class="row mb-2">
          <div class="col-md-6">
            <strong>Router:</strong> <?= esc($subscription['router_name'] ?? 'N/A') ?>
          </div>
          <div class="col-md-6">
            <strong>Status:</strong> <span class="<?= $statusClass ?>"><?= esc($status) ?></span>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-6">
            <strong>Start Date:</strong> <?= date('d M Y H:i', strtotime($subscription['start_date'])) ?>
          </div>
          <div class="col-md-6">
            <strong>Expiry Date:</strong> <?= date('d M Y H:i', strtotime($subscription['expires_on'])) ?>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-6">
            <strong>Account Type:</strong> <?= esc($subscription['package_account_type'] ?? 'N/A') ?>
          </div>
          <div class="col-md-6">
            <strong>Package Type:</strong> <?= esc(ucfirst($subscription['package_type'] ?? 'N/A')) ?>
          </div>
        </div>

        <?php if (!empty($subscription['price'])): ?>
          <div class="row mb-2">
            <div class="col-md-6">
              <strong>Price:</strong> Ksh <?= number_format($subscription['price'], 2) ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="row mb-2">
          <div class="col-md-6">
            <strong>Created On:</strong> <?= date('d M Y H:i', strtotime($subscription['created_at'])) ?>
          </div>
        </div>

        <?php if (!empty($subscription['bandwidth_value'])): ?>
          <hr>
          <h6 class="fw-bold">Bandwidth Configuration</h6>
          <p>
            <?= esc($subscription['bandwidth_value']) . ' ' . esc($subscription['bandwidth_unit']) ?>
          </p>
        <?php endif; ?>

        <?php if (!empty($subscription['burst_enabled']) && $subscription['burst_enabled'] == 1): ?>
          <hr>
          <h6 class="fw-bold">Burst Settings</h6>
          <ul>
            <li>Upload Burst Rate: <?= esc($subscription['upload_burst_rate_value']) . ' ' . esc($subscription['upload_burst_rate_unit']) ?></li>
            <li>Download Burst Rate: <?= esc($subscription['download_burst_rate_value']) . ' ' . esc($subscription['download_burst_rate_unit']) ?></li>
            <li>Upload Burst Threshold: <?= esc($subscription['upload_burst_threshold_value']) . ' ' . esc($subscription['upload_burst_threshold_unit']) ?></li>
            <li>Download Burst Threshold: <?= esc($subscription['download_burst_threshold_value']) . ' ' . esc($subscription['download_burst_threshold_unit']) ?></li>
            <li>Upload Burst Time: <?= esc($subscription['upload_burst_time_value']) . ' Seconds' ?></li>
            <li>Download Burst Time: <?= esc($subscription['download_burst_time_value']) . ' Seconds' ?></li>
          </ul>
        <?php endif; ?>

        <hr>
        <div class="mt-3 d-flex gap-2">
          <?php if ($isExpired): ?>
            <a href="<?= base_url('client/packages/view/' . $subscription['package_id']) ?>" class="btn btn-success">
              <i class="bi bi-arrow-repeat"></i> Renew Subscription
            </a>
          <?php elseif ($subscription['status'] === 'active'): ?>
            <a href="<?= base_url('client/subscriptions/reconnect/' . $subscription['id']) ?>" class="btn btn-info">
              <i class="bi bi-wifi"></i> Reconnect
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

  <?php else: ?>
    <div class="alert alert-warning text-center">Subscription details not found.</div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
