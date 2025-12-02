document.addEventListener("DOMContentLoaded", () => {

    /* ---------------------------------------------------
     *  TOGGLE PACKAGE PAYMENT FORM
     * --------------------------------------------------- */
    document.querySelectorAll(".toggle-form").forEach(btn => {
        btn.addEventListener("click", () => {
            const id = btn.dataset.packageId;
            const form = document.getElementById("package-form-" + id);

            if (form) {
                form.classList.toggle("d-none");
            }
        });
    });


    /* ---------------------------------------------------
     *  PACKAGE PAYMENT (STK PUSH)
     * --------------------------------------------------- */
    document.querySelectorAll(".ajax-package-form").forEach(form => {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();

            const packageId = form.dataset.packageId;
            const phone = form.querySelector('input[name="phone"]').value.trim();
            const statusDiv = document.getElementById("status-" + packageId);

            if (!phone) {
                statusDiv.innerHTML = `<span class="text-danger">Please enter your phone number.</span>`;
                return;
            }

            statusDiv.innerHTML = `<span class="text-info">Processing your payment…</span>`;

            try {
                const formData = new FormData();
                formData.append("package_id", packageId);
                formData.append("phone", phone);

                const response = await fetch("/client/payments/process", {
                    method: "POST",
                    body: formData
                });

                const html = await response.text();

                // Replace form area with waiting screen HTML
                form.parentElement.innerHTML = html;
                
                // After replacing HTML, start polling
                startPaymentPolling();

            } catch (error) {
                console.error(error);
                statusDiv.innerHTML = `<span class="text-danger">Payment initiation failed. Try again.</span>`;
            }
        });
    });


    /* ---------------------------------------------------
     *  POLLING: PAYMENT STATUS
     * --------------------------------------------------- */
    function startPaymentPolling() {
        const timer = setInterval(async () => {
            try {
                const res = await fetch("/client/payments/checkStatus");
                const data = await res.json();

                if (data.status === "success") {
                    clearInterval(timer);
                    window.location.href = "/client/payments/success/" + (data.transaction_id || 0);
                }

                if (data.status === "unauthorized") {
                    clearInterval(timer);
                    alert("You are not logged in.");
                }

            } catch (error) {
                console.error("Polling error:", error);
            }
        }, 3000);
    }


    /* ---------------------------------------------------
     *  VOUCHER REDEEM
     * --------------------------------------------------- */
    const voucherForm = document.getElementById("voucher-redeem-form");
    if (voucherForm) {
        const voucherStatus = document.getElementById("voucher-status");

        voucherForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            const code = document.getElementById("voucher").value.trim();
            if (!code) {
                voucherStatus.innerHTML = `<span class="text-danger">Enter a voucher code.</span>`;
                return;
            }

            voucherStatus.innerHTML = `<span class="text-info">Checking voucher…</span>`;

            try {
                const formData = new FormData();
                formData.append("voucher_code", code);

                const res = await fetch("/client/vouchers/redeem-post", {
                    method: "POST",
                    body: formData
                });

                const result = await res.json();

                if (result.status === "success") {
                    voucherStatus.innerHTML = `<span class="text-success">${result.message}</span>`;
                    setTimeout(() => window.location.href = "/client/subscriptions", 1500);
                } else {
                    voucherStatus.innerHTML = `<span class="text-danger">${result.message}</span>`;
                }

            } catch (error) {
                voucherStatus.innerHTML = `<span class="text-danger">Redeem failed. Try again.</span>`;
                console.error(error);
            }
        });
    }


    /* ---------------------------------------------------
     *  RECONNECT USING MPESA CODE
     * --------------------------------------------------- */
    const reconnectForm = document.getElementById("reconnect-form");
    if (reconnectForm) {
        const reconnectStatus = document.getElementById("reconnect-status");

        reconnectForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            const code = reconnectForm.querySelector("input[name='mpesa_code']").value.trim();

            if (!code) {
                reconnectStatus.innerHTML = `<span class="text-danger">Enter your M-PESA code.</span>`;
                return;
            }

            reconnectStatus.innerHTML = `<span class="text-info">Verifying code…</span>`;

            try {
                const formData = new FormData();
                formData.append("mpesa_code", code);

                const res = await fetch("/reconnect-mpesa", {
                    method: "POST",
                    body: formData
                });

                const result = await res.json();

                if (result.success) {
                    reconnectStatus.innerHTML = `<span class="text-success">${result.message}</span>`;
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    reconnectStatus.innerHTML = `<span class="text-danger">${result.message}</span>`;
                }

            } catch (error) {
                reconnectStatus.innerHTML = `<span class="text-danger">Reconnect failed. Try again.</span>`;
                console.error(error);
            }
        });
    }

});
