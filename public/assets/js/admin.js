document.addEventListener('DOMContentLoaded', function() {
  const dash = document.getElementById('dashboard');
  if (!dash) return;

  // Utility to safely parse JSON stored in data-*
  const safeParse = (s) => {
    try {
      return JSON.parse(s);
    } catch (e) {
      return [];
    }
  };

  // Read KPI/data attributes
  const chartLabels = safeParse(dash.dataset.chartLabels || '[]');
  const chartValues = safeParse(dash.dataset.chartValues || '[]');
  const voucherLabels = safeParse(dash.dataset.voucherLabels || '[]');
  const voucherValues = safeParse(dash.dataset.voucherValues || '[]');

  // Count animation
  document.querySelectorAll('.count').forEach(el => {
    const targetAttr = el.getAttribute('data-target');
    const target = targetAttr ? parseFloat(targetAttr) : 0;
    const isFloat = (targetAttr && targetAttr.indexOf('.') !== -1);
    const steps = 60;
    let current = 0;
    if (target === 0) {
      el.innerText = '0';
      return;
    }
    const step = target / steps;
    const iv = setInterval(() => {
      current += step;
      if (current >= target) {
        clearInterval(iv);
        el.innerText = isFloat ? Number(target).toFixed(2) : Math.round(target);
      } else {
        el.innerText = isFloat ? Number(current).toFixed(2) : Math.ceil(current);
      }
    }, 20);
  });

  // Revenue chart
  try {
    const revCanvas = document.getElementById('revenueChart');
    if (revCanvas && typeof Chart !== 'undefined') {
      new Chart(revCanvas.getContext('2d'), {
        type: 'line',
        data: {
          labels: chartLabels,
          datasets: [{
            label: 'KES Collected',
            data: chartValues,
            fill: true,
            tension: 0.3,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0,123,255,0.1)'
          }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
      });
    }
  } catch (e) {
    console.error('Error rendering revenue chart', e);
  }

  // Voucher chart
  try {
    const vCanvas = document.getElementById('voucherChart');
    if (vCanvas && typeof Chart !== 'undefined') {
      new Chart(vCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: voucherLabels,
          datasets: [{
            label: 'Vouchers Redeemed',
            data: voucherValues,
            backgroundColor: '#17a2b8'
          }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
      });
    }
  } catch (e) {
    console.error('Error rendering voucher chart', e);
  }

  // Countdown timers for Expiring Within 24 Hours
  function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown');
    const now = Date.now();

    countdowns.forEach(el => {
      const expiry = new Date(el.dataset.expiry).getTime();
      if (isNaN(expiry)) {
        el.innerHTML = '<span class="text-muted fw-bold">Invalid Date</span>';
        return;
      }
      const diff = expiry - now;
      if (diff <= 0) {
        el.innerHTML = '<span class="text-danger fw-bold">Expired</span>';
        return;
      }

      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const secs = Math.floor((diff % (1000 * 60)) / 1000);

      const parts = [];
      if (days) parts.push(days + 'd');
      if (hours) parts.push(hours + 'h');
      if (mins) parts.push(mins + 'm');
      parts.push(secs + 's');

      el.innerHTML = `<span class="text-warning">${parts.join(' ')} left</span>`;
    });
  }

  updateCountdowns();
  setInterval(updateCountdowns, 1000); // update every second for live countdown
});
