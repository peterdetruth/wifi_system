<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt #<?= esc($transaction['id']) ?></title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    h2 { color: #007bff; }
    .header { text-align: center; margin-bottom: 20px; }
    .details { margin: 15px 0; }
    .footer { text-align: center; font-size: 10px; margin-top: 40px; color: #777; }
  </style>
</head>
<body>
  <div class="header">
    <h2>Payment Receipt</h2>
    <p><strong>WiFi System</strong></p>
  </div>

  <div class="details">
    <p><strong>Client:</strong> <?= esc($client['username']) ?></p>
    <p><strong>Email:</strong> <?= esc($client['email']) ?></p>
    <p><strong>Package:</strong> <?= esc($package['name']) ?></p>
    <p><strong>Amount:</strong> KES <?= number_format($transaction['amount'], 2) ?></p>
    <p><strong>Method:</strong> <?= ucfirst($transaction['method']) ?></p>
    <p><strong>Transaction ID:</strong> <?= esc($transaction['mpesa_code']) ?></p>
    <p><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($transaction['created_at'])) ?></p>
  </div>

  <div class="footer">
    Thank you for choosing our service!
  </div>
</body>
</html>
