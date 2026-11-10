<?php
/** Template pembuka panel admin: guard admin, metadata HTML, dan sidebar navigasi. */
require_once __DIR__ . '/functions.php';

require_admin();

$page_title = $page_title ?? 'Admin';
$pendingReturnCount = 0;

try {
    // Badge navigasi memberi tahu admin jika ada survey retur yang belum diverifikasi.
    $pendingReturnCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM pengajuan_pengembalian WHERE status = 'Diajukan'"
    )->fetchColumn();
} catch (PDOException $exception) {
    // Panel admin tetap dapat dibuka pada database lama sebelum migrasi retur dijalankan.
    $pendingReturnCount = 0;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> | Admin BrianBookStore</title>
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
  <aside class="sidebar">
    <a class="brand light" href="<?= url('admin/dashboard.php') ?>">
      <span class="brand-mark">B</span>
      Brian<span>BookStore</span>
    </a>

    <p class="admin-label">ADMIN PANEL</p>

    <nav aria-label="Navigasi admin">
      <a href="<?= url('admin/dashboard.php') ?>">▦ Dashboard</a>
      <a href="<?= url('admin/kategori.php') ?>">▤ Kategori</a>
      <a href="<?= url('admin/buku.php') ?>">▣ Data Buku</a>
      <a href="<?= url('admin/user.php') ?>">♙ Data User</a>
      <a href="<?= url('admin/order.php') ?>">▧ Pesanan</a>
      <a href="<?= url('admin/pengembalian.php') ?>">
        <span>↩ Pengajuan Pengembalian</span>
        <?php if ($pendingReturnCount > 0): ?>
          <b class="sidebar-nav-badge"><?= $pendingReturnCount ?></b>
        <?php endif; ?>
      </a>
    </nav>

    <a class="logout-link" href="<?= url('admin/logout.php') ?>">↪ Keluar</a>
  </aside>

  <main class="admin-main">
    <div class="admin-mobile-head">
      <b>BrianBookStore Admin</b>
      <a href="<?= url('admin/logout.php') ?>">Keluar</a>
    </div>

    <div class="admin-content">
    <div class="admin-title">
      <div>
        <p>Panel Administrasi</p>
        <h1><?= e($page_title) ?></h1>
      </div>
      <div class="admin-title-actions">
        <!-- Toggle tema admin memakai penyimpanan preferensi yang sama dengan storefront. -->
        <button
          class="theme-toggle admin-theme-toggle"
          type="button"
          data-dark-mode-toggle
          aria-label="Aktifkan atau matikan mode gelap admin"
          aria-pressed="false"
        >
          <span class="theme-toggle-track" aria-hidden="true">
            <span class="theme-toggle-knob"></span>
          </span>
          <span class="theme-toggle-label" data-dark-mode-label>OFF</span>
        </button>
        <!-- Menampilkan identitas admin yang sedang aktif. -->
        <span>Halo, <?= e($_SESSION['admin_nama'] ?? 'Admin') ?></span>
      </div>
    </div>

      <?php display_flash(); ?>
