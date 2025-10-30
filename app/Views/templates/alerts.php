<?php if ($success = session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php if (is_array($success)): ?>
      <ul class="mb-0">
        <?php foreach ($success as $msg): ?>
          <li><?= esc($msg) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?= esc($success) ?>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if ($error = session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php if (is_array($error)): ?>
      <ul class="mb-0">
        <?php foreach ($error as $msg): ?>
          <li><?= esc($msg) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?= esc($error) ?>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if (isset($validation) && $validation->getErrors()): ?>
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    <ul class="mb-0">
      <?php foreach ($validation->getErrors() as $error): ?>
        <li><?= esc($error) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
