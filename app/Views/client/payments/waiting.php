<!-- app/Views/client/payments/waiting.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Processing Payment...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            padding: 40px;
            text-align: center;
        }
        .box {
            max-width: 450px;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .loader {
            border: 5px solid #ddd;
            border-top: 5px solid #1e90ff;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        p {
            margin-top: 15px;
            font-size: 16px;
            color: #555;
        }
        .highlight {
            font-weight: bold;
            color: #111;
        }
    </style>

    <script>
        const checkoutRequestId = '<?= esc($checkoutRequestId) ?>';

        function pollPayment() {
            fetch("<?= site_url('client/payments/status/') ?>" + checkoutRequestId)
                .then(res => res.json())
                .then(data => {
                    if (data.status.toLowerCase() === 'success') {
                        window.location.href = "<?= site_url('client/payments/success/') ?>" + checkoutRequestId;
                    } else {
                        setTimeout(pollPayment, 3000); // poll every 3 seconds
                    }
                })
                .catch(err => console.error("Status check error:", err));
        }

        pollPayment();
    </script>
</head>
<body>

<div class="box">
    <h2>Processing Your Payment</h2>

    <div class="loader"></div>

    <p>
        We sent an M-PESA STK Push request to:<br>
        <span class="highlight"><?= esc($phone ?? 'your phone') ?></span>
    </p>

    <p>Please enter your M-PESA PIN to complete the payment.</p>

    <p><em>Do not close this page. We are checking the payment status automatically…</em></p>
</div>

</body>
</html>
