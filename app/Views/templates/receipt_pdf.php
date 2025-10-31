<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; font-size: 13px; color: #333; }
    .header { text-align: center; margin-bottom: 20px; }
    .title { font-size: 18px; font-weight: bold; }
    .details { margin-bottom: 15px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    td, th { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f4f4f4; }
    .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
  </style>
</head>
<body>
  <div class="header">
    <div class="title">WiFi System Payment Receipt</div>
    <small><?= esc($date) ?></small>
  </div>

  <div class="details">
    <strong>Client:</strong> <?= esc($client['username'] ?? 'N/A') ?><br>
    <strong>Email:</strong> <?= esc($client['email'] ?? 'N/A') ?><br>
    <strong>Phone:</strong> <?= esc($client['phone'] ?? 'N/A') ?><br>
  </div>

  <table>
    <tr>
      <th>Package</th>
      <td><?= esc($package['name'] ?? 'N/A') ?></td>
    </tr>
    <tr>
      <th>Amount</th>
      <td>KES <?= number_format($transaction['amount'] ?? 0, 2) ?></td>
    </tr>
    <tr>
      <th>Payment Method</th>
      <td><?= strtoupper(esc($transaction['method'] ?? '')) ?></td>
    </tr>
    <tr>
      <th>Transaction Code</th>
      <td><?= esc($transaction['mpesa_code'] ?? '-') ?></td>
    </tr>
    <tr>
      <th>Status</th>
      <td><?= ucfirst(esc($transaction['status'] ?? 'pending')) ?></td>
    </tr>
    <tr>
      <th>Expires On</th>
      <td><?= date('d M Y, H:i', strtotime($transaction['expires_on'])) ?></td>
    </tr>
  </table>

  <div class="footer">
    Thank you for using our WiFi services.<br>
    <em>This is an auto-generated receipt.</em>
  </div>
</body>
</html>
