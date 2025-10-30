<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Beth</title>
  <link href="<?= base_url('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>

<div class="sidebar">
  <h2 class="logo">Beth</h2>
  <ul>
    <li><a href="<?= base_url('/admin/admins') ?>">👤 Admins</a></li>
    <li><a href="<?= base_url('/admin/packages') ?>">📦 Packages</a></li>
    <li><a href="<?= base_url('/admin/clients') ?>">👥 Clients</a></li>
    <li><a href="<?= base_url('/admin/routers') ?>">📡 Routers</a></li>
    <li><a href="<?= base_url('/admin/vouchers') ?>">🎟️ Access Codes</a></li>
    <li><a href="<?= base_url('/admin/transactions') ?>">💳 Payment Transactions</a></li>
    <li><a href="<?= base_url('/admin/mpesa') ?>">📱 Mpesa Transactions</a></li>
  </ul>
</div>

<div class="main-content">
  <header>
    <h2>Admin Dashboard</h2>
    <a href="<?= base_url('/logout') ?>" class="logout-btn">Logout</a>
  </header>

  <section class="content">
