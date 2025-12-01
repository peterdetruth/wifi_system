<?php
// $mpesaTransactions: array of mpesa transactions
// $mpesaPager: CI4 Pager instance
// $clientId: client id
?>
<table class="table table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>Package</th>
      <th>Amount</th>
      <th>Phone Number</th>
      <th>Transaction Date</th>
      <th>Status</th>
      <th>Created At</th>
      <th>Mpesa Code</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($mpesaTransactions as $txn): ?>
      <tr>
        <td><?= esc($txn['id']) ?></td>
        <td><?= esc($txn['package_name'] ?? 'N/A') ?></td>
        <td><?= esc($txn['amount']) ?></td>
        <td><?= esc($txn['phone_number']) ?></td>
        <td><?= esc($txn['transaction_date']) ?></td>
        <td>
            <span class="badge <?= $txn['status']=='success'?'bg-success':($txn['status']=='pending'?'bg-warning':'bg-danger') ?>">
                <?= esc(ucfirst($txn['status'])) ?>
            </span>
        </td>
        <td><?= esc($txn['created_at']) ?></td>
        <td><?= esc($txn['mpesa_receipt']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="mt-2 d-flex justify-content-center">
    <?= $mpesaPager->links('mpesa', 'default_full') ?>
</div>
