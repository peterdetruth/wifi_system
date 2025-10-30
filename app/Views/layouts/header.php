<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WiFi System Admin</title>
  <link href="<?= base_url('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body>

  <nav class="topnav">
    <div class="nav-left">
      <h2>WiFi Admin Panel</h2>
    </div>
    <div class="nav-right">
      <span>Welcome, <strong><?= esc(session()->get('username')) ?></strong> (<?= esc(session()->get('role')) ?>)</span>
      <a href="<?= base_url('/logout') ?>" class="logout-btn">Logout</a>
    </div>
  </nav>

  <div class="main-container">
