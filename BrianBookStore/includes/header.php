<?php
/** Template header publik: cache-busting asset, brand, navigasi, dan flash message. */
require_once __DIR__ . '/functions.php';

$page_title = $page_title ?? 'BrianBookStore';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> | BrianBookStore</title>
  <link
    rel="stylesheet"
    href="<?= url('assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css')) ?>"
  >
</head>
<body>
<header class="site-header">
  <div class="container navbar">
    <a class="brand" href="<?= url('index.php') ?>">
      <span class="brand-mark">B</span>
      <span>Brian<span>BookStore</span></span>
    </a>

    <button
      class="nav-toggle"
      type="button"
      aria-label="Buka navigasi"
      aria-expanded="false"
    >☰</button>

    <nav class="main-nav" aria-label="Navigasi utama">
      <a href="<?= url('index.php') ?>">Beranda</a>
      <a href="<?= url('buku.php') ?>">Koleksi Buku</a>
      <a href="<?= url('about.php') ?>">About Us</a>
      <a href="<?= url('contact.php') ?>">Kontak</a>

      <?php if (is_user_logged_in()): ?>
        <a href="<?= url('pesanan.php') ?>">Pesanan</a>
        <a class="cart-link" href="<?= url('cart.php') ?>">
          Keranjang <b><?= cart_count() ?></b>
        </a>

        <details class="account-menu">
          <summary
            class="account-menu-toggle"
            aria-label="Buka Profil dan Pengaturan"
          >
            <span class="account-menu-hamburger" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </summary>

          <div class="account-menu-list">
            <a href="<?= url('kelola_akun.php') ?>">Profil</a>
            <a href="<?= url('pengaturan.php') ?>">Pengaturan</a>
            <a href="<?= url('logout.php') ?>">Keluar</a>
          </div>
        </details>
      <?php else: ?>
        <a href="<?= url('login.php') ?>">Masuk</a>
        <a class="button button-small" href="<?= url('register.php') ?>">Daftar</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="page-content page-transition">
  <div class="container">
    <?php display_flash(); ?>
