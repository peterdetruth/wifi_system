document.addEventListener('DOMContentLoaded', function() {
    const payButtons = document.querySelectorAll('.pay-now-btn');

    payButtons.forEach(button => {
        button.addEventListener('click', function() {
            const card = this.closest('.package-card');
            const form = card.querySelector('.pay-now-form');

            // Hide other forms
            document.querySelectorAll('.pay-now-form').forEach(f => {
                if (f !== form) f.style.display = 'none';
            });

            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    });

    // Handle AJAX form submission
    const forms = document.querySelectorAll('.pay-now-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const card = this.closest('.package-card');
            const statusDiv = card.querySelector('.payment-status');
            const formData = new FormData(this);

            statusDiv.style.display = 'block';
            statusDiv.innerHTML = 'Initiating payment... Please wait.';

            fetch(this.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.text())
            .then(html => {
                // Replace the form with the waiting page
                this.style.display = 'none';
                statusDiv.innerHTML = html;

                // Poll the M-PESA status
                const clientPackageId = html.match(/clientPackageId.*?(\d+)/);
                if (clientPackageId) {
                    pollPaymentStatus(clientPackageId[1], statusDiv);
                }
            })
            .catch(err => {
                statusDiv.innerHTML = 'Error initiating payment: ' + err;
            });
        });
    });

    function pollPaymentStatus(clientPackageId, statusDiv) {
        const interval = setInterval(() => {
            fetch('/client/payments/checkStatus', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.status === 'success') {
                    clearInterval(interval);
                    statusDiv.innerHTML = '✅ Payment successful! Subscription activated.';
                } else if (data.status === 'failed') {
                    clearInterval(interval);
                    statusDiv.innerHTML = '❌ Payment failed. Please try again.';
                } else {
                    statusDiv.innerHTML = '⏳ Waiting for M-PESA confirmation...';
                }
            })
            .catch(() => {
                statusDiv.innerHTML = '❌ Error checking payment status.';
            });
        }, 5000);
    }
});
