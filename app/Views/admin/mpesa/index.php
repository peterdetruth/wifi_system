<?= $this->extend('layouts/admin_layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>MPESA Transactions</h4>
  </div>

  <?= view('templates/alerts') ?>

  <?php if (empty($mpesa_transactions)): ?>
    <div class="alert alert-info">No MPESA transactions found.</div>
  <?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <table class="table table-hover table-striped mb-0 align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Client</th>
            <th>Phone</th>
            <th>Amount</th>
            <th>Mpesa Code</th>
            <th>Transaction ID</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($mpesa_transactions as $m): ?>
          <?php $status = $m['txn_status'] ?? 'pending'; ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= esc($m['client_username'] ?? 'N/A') ?></td>
            <td><?= esc($m['phone'] ?? '-') ?></td>
            <td>KES <?= number_format($m['txn_amount'] ?? $m['amount'] ?? 0, 2) ?></td>
            <td><?= esc($m['transaction_id'] ?? '-') ?></td>
            <td><?= esc($m['mpesa_code'] ?? $m['transaction_id'] ?? '-') ?></td>
            <td>
              <span class="badge <?= ($status === 'success') ? 'bg-success' : (($status === 'failed') ? 'bg-danger' : 'bg-warning text-dark') ?>">
                <?= ucfirst($status) ?>
              </span>
            </td>
            <td><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" 
                      data-bs-toggle="modal" 
                      data-bs-target="#receiptModal<?= $m['id'] ?>">View Receipt</button>
            </td>
          </tr>

          <!-- 🧾 Receipt Modal -->
          <div class="modal fade" id="receiptModal<?= $m['id'] ?>" tabindex="-1" aria-labelledby="receiptLabel<?= $m['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title" id="receiptLabel<?= $m['id'] ?>">Payment Receipt</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="p-2">
                    <p><strong>Client:</strong> <?= esc($m['client_username'] ?? 'N/A') ?></p>
                    <p><strong>Phone:</strong> <?= esc($m['phone'] ?? '-') ?></p>
                    <p><strong>Amount:</strong> KES <?= number_format($m['txn_amount'] ?? $m['amount'] ?? 0, 2) ?></p>
                    <p><strong>M-PESA Code:</strong> <?= esc($m['transaction_id'] ?? '-') ?></p>
                    <p><strong>Status:</strong> 
                      <span class="badge <?= ($status === 'success') ? 'bg-success' : (($status === 'failed') ? 'bg-danger' : 'bg-warning text-dark') ?>">
                        <?= ucfirst($status) ?>
                      </span>
                    </p>
                    <p><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($m['created_at'])) ?></p>
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
