<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Pending</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
            text-align: center;
            padding-top: 80px;
        }
        .loader {
            border: 6px solid #f3f3f3;
            border-top: 6px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h2 { color: #333; }
        p { color: #666; }
    </style>
</head>
<body>
    <h2>Your payment is being processed...</h2>
    <div class="loader"></div>
    <p>We are waiting for M-PESA confirmation. Please do not close this page.</p>

    <script>
        const checkoutRequestID = "<?= esc($checkoutRequestID) ?>"; // Provided from controller
        const statusUrl = "<?= site_url('client/payments/status') ?>/" + checkoutRequestID;

        function checkPaymentStatus() {
            $.get(statusUrl, function (response) {
                console.log(response);

                if (response.status === "Success") {
                    window.location.href = "<?= site_url('client/payments/success') ?>";
                } else if (response.status === "Failed") {
                    alert("Your payment failed. Please try again.");
                    window.location.href = "<?= site_url('client/payments') ?>";
                } else if (response.status === "NotFound") {
                    console.warn("Transaction not found, will retry...");
                    setTimeout(checkPaymentStatus, 5000);
                } else {
                    setTimeout(checkPaymentStatus, 5000); // Keep checking
                }
            }).fail(() => {
                setTimeout(checkPaymentStatus, 5000);
            });
        }

        setTimeout(checkPaymentStatus, 3000); // start after short delay
    </script>
</body>
</html>
