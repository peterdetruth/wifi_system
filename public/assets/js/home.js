document.addEventListener('DOMContentLoaded', function() {

    // Toggle package form
    document.querySelectorAll('.toggle-form').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.packageId;
            document.getElementById('package-form-' + id).classList.toggle('d-none');
        });
    });


    // Handle package payments
    document.querySelectorAll('.ajax-package-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const packageId = this.dataset.packageId;
            const phone = this.querySelector('input[name="phone"]').value.trim();
            const statusDiv = document.getElementById('status-' + packageId);

            if (!phone) {
                statusDiv.innerHTML = '<span class="text-danger">Please enter your phone number.</span>';
                return;
            }

            statusDiv.innerHTML = '<span class="text-info">Processing your payment...</span>';

            try {
                const formData = new FormData();
                formData.append('package_id', packageId);
                formData.append('phone', phone);

                const response = await fetch('/client/payments/process', {
                    method: 'POST',
                    body: formData
                });

                const html = await response.text();

                // Replace card area with waiting screen
                this.parentElement.innerHTML = html;

                // Start polling
                pollPaymentStatus(statusDiv);
                
            } catch (err) {
                console.error(err);
                statusDiv.innerHTML = '<span class="text-danger">Payment initiation failed.</span>';
            }
        });
    });



    // Payment polling
    function pollPaymentStatus(statusDiv) {
        const timer = setInterval(async () => {
            try {
                const res = await fetch('/client/payments/checkStatus');
                const data = await res.json();

                if (data.status === 'success') {
                    clearInterval(timer);
                    window.location.href = '/client/payments/success/' + data.transaction_id;
                }

            } catch (err) {
                console.error(err);
            }
        }, 3000);
    }


    // Voucher Redeem AJAX
    const voucherForm = document.getElementById('voucher-redeem-form');
    const voucherStatus = document.getElementById('voucher-status');

    voucherForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const code = document.getElementById('voucher').value.trim();
        if (!code) {
            voucherStatus.innerHTML = '<span class="text-danger">Enter a voucher code.</span>';
            return;
        }

        voucherStatus.innerHTML = '<span class="text-info">Checking voucher...</span>';

        try {
            const formData = new FormData();
            formData.append('voucher_code', code);

            const res = await fetch('/client/vouchers/redeem-post', {
                method: 'POST',
                body: formData
            });

            const result = await res.json();

            if (result.status === 'success') {
                voucherStatus.innerHTML = '<span class="text-success">' + result.message + '</span>';
                setTimeout(() => window.location.href = '/client/subscriptions', 1500);
            } else {
                voucherStatus.innerHTML = '<span class="text-danger">' + result.message + '</span>';
            }

        } catch (err) {
            console.error(err);
            voucherStatus.innerHTML = '<span class="text-danger">Redeem failed. Try again.</span>';
        }
    });

});
2