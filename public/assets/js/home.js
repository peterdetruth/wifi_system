document.addEventListener('DOMContentLoaded', () => {
    const packageCards = document.querySelectorAll('.package-card');

    packageCards.forEach(card => {
        card.addEventListener('click', (e) => {
            // Avoid toggling when clicking inside the form inputs
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;

            const form = card.querySelector('.payment-form');
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        });
    });
});
