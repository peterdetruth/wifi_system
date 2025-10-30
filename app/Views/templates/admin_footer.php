  </section>
</div>
  <footer>
    <p>&copy; <?= date('Y') ?> Beth WiFi</p>
  </footer>
</body>
</html>

<script src="<?= base_url('assets/js/bootstrap.bundle.min.js') ?>"></script>

<script>
  let $logo = document.querySelector('.logo');
  if ($logo) {
    $logo.addEventListener('mouseover', () => {
      $logo.style.cursor = 'pointer';
    });
    $logo.addEventListener('mouseout', () => {
      $logo.style.cursor = 'default';
    });
    const dashboardUrl = 'http://localhost:8080/admin/dashboard';
    $logo.addEventListener('click', function() {
      window.location.href = dashboardUrl;
    });
  }

  setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(el => {
          let alert = new bootstrap.Alert(el);
          alert.close();
      });
  }, 5000);
</script>