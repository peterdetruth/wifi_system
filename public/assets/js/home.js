document.addEventListener('DOMContentLoaded', function () {
    const packages = document.querySelectorAll('.package');

    packages.forEach(pkg => {
        const btn = pkg.querySelector('.buy-now-btn');
        const form = pkg.querySelector('.payment-form');

        btn.addEventListener('click', function () {
            // Hide any other open forms
            document.querySelectorAll('.payment-form').forEach(f => {
                if (f !== form) f.style.display = 'none';
            });

            // Toggle current form
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    });
});
