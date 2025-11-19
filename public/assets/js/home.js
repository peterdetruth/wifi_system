document.addEventListener('DOMContentLoaded', function () {
    // Toggle package forms when clicking "Pay Now"
    const toggleButtons = document.querySelectorAll('.toggle-form');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const packageId = btn.dataset.packageId;
            const form = document.getElementById('package-form-' + packageId);
            if (form) {
                form.classList.toggle('d-none');
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Handle package form submissions
    const forms = document.querySelectorAll('.package-form form');
    forms.forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const packageId = this.querySelector('input[name="package_id"]').value;
            const phoneInput = this.querySelector('input[name="phone"]');
            const phone = phoneInput.value.trim();

            // Status div
            let statusDiv = this.querySelector('.status-message');
            if (!statusDiv) {
                statusDiv = document.createElement('div');
                statusDiv.classList.add('status-message', 'mt-2');
                this.appendChild(statusDiv);
            }

            if (!phone) {
                statusDiv.innerHTML = '<span class="text-danger">Please enter your phone number.</span>';
                phoneInput.focus();
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
                // Replace the package card body with waiting view
                this.closest('.card-body').innerHTML = html;

                // Start polling payment status
                pollPaymentStatus(packageId);
            } catch (err) {
                console.error(err);
                statusDiv.innerHTML = '<span class="text-danger">Payment initiation failed.</span>';
            }
        });
    });

    // Polling function for M-PESA transaction status
    function pollPaymentStatus(clientPackageId) {
        const interval = setInterval(async () => {
            try {
                const res = await fetch('/client/payments/checkStatus');
                const data = await res.json();

                if (data.status === 'success') {
                    clearInterval(interval);
                    // redirect to success page
                    window.location.href = '/client/payments/success/' + data.transaction_id;
                }
            } catch (err) {
                console.error(err);
            }
        }, 3000); // every 3 seconds
    }

    // Bootstrap form validation
    const validationForms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(validationForms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
