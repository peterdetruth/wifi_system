<?php if (session()->getFlashdata('error')): ?>
  <p class="error"><?= session()->getFlashdata('error') ?></p>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
  <p class="success"><?= session()->getFlashdata('success') ?></p>
<?php endif; ?>