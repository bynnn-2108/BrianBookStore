<?php
/** Dashboard pengaturan customer untuk tampilan, pembelian, dan akun. */
require_once __DIR__ . '/includes/functions.php';

require_user();

$page_title = 'Pengaturan';

require __DIR__ . '/includes/header.php';
?>
<div class="settings-page-heading">
  <div class="settings-heading-copy">
    <div class="eyebrow">Akun &amp; preferensi</div>
    <h1>Pengaturan</h1>
    <p class="helper">Atur pengalaman belanja dan akses akun Anda dari satu tempat.</p>
  </div>
  <a class="button button-outline button-small" href="<?= url('kelola_akun.php') ?>">
    ← Profil saya
  </a>
</div>

<div class="settings-layout">
  <aside class="card settings-overview">
    <div class="settings-overview-icon" aria-hidden="true">⚙</div>
    <div class="eyebrow">Pusat preferensi</div>
    <h2>Atur pengalamanmu</h2>
    <p class="helper">Semua pilihan akun dan belanja tersusun rapi dalam satu tempat.</p>
    <div class="settings-overview-list">
      <div><span>01</span> Tampilan personal</div>
      <div><span>02</span> Riwayat pembelian</div>
      <div><span>03</span> Data dan keamanan akun</div>
    </div>
  </aside>

  <section class="card settings-panel">
  <div class="settings-category">
    <div class="settings-category-heading">
      <span class="settings-category-icon" aria-hidden="true">◐</span>
      <div>
        <p class="eyebrow">Tampilan</p>
        <h2>Pengalaman aplikasi</h2>
      </div>
    </div>

    <div class="settings-item">
      <span class="settings-item-icon" aria-hidden="true">☾</span>
      <div class="settings-item-main">
        <b>Mode gelap</b>
        <p class="helper">Mengurangi silau dengan tema gelap. Pilihan disimpan di perangkat ini.</p>
      </div>
      <button
        class="theme-toggle"
        type="button"
        data-dark-mode-toggle
        aria-label="Aktifkan atau matikan mode gelap"
        aria-pressed="false"
      >
        <span class="theme-toggle-track" aria-hidden="true">
          <span class="theme-toggle-knob"></span>
        </span>
        <span class="theme-toggle-label" data-dark-mode-label>OFF</span>
      </button>
    </div>
  </div>

  <div class="settings-category">
    <div class="settings-category-heading">
      <span class="settings-category-icon" aria-hidden="true">▣</span>
      <div>
        <p class="eyebrow">Pembelian</p>
        <h2>Pesanan dan transaksi</h2>
      </div>
    </div>

    <a class="settings-item settings-link" href="<?= url('pesanan.php') ?>">
      <span class="settings-item-icon" aria-hidden="true">▤</span>
      <div class="settings-item-main">
        <b>Log transaksi</b>
        <p class="helper">Lihat seluruh pesanan, total, status, dan struk transaksi Anda.</p>
      </div>
      <span aria-hidden="true">›</span>
    </a>
  </div>

  <div class="settings-category">
    <div class="settings-category-heading">
      <span class="settings-category-icon" aria-hidden="true">♙</span>
      <div>
        <p class="eyebrow">Akun</p>
        <h2>Data dan pembayaran</h2>
      </div>
    </div>

    <!-- Tautan buku alamat membuka daftar alamat yang dapat dipilih saat checkout. -->
    <a class="settings-item settings-link" href="<?= url('alamat.php') ?>">
      <span class="settings-item-icon" aria-hidden="true">⌖</span>
      <div class="settings-item-main">
        <b>Alamat pengiriman</b>
        <p class="helper">Tambah, ubah, dan atur alamat utama untuk pesanan Anda.</p>
      </div>
      <span aria-hidden="true">›</span>
    </a>

    <a class="settings-item settings-link" href="<?= url('metode_pembayaran.php') ?>">
      <span class="settings-item-icon" aria-hidden="true">▤</span>
      <div class="settings-item-main">
        <b>Metode pembayaran</b>
        <p class="helper">Lihat rekening transfer dan ketentuan konfirmasi pembayaran.</p>
      </div>
      <span aria-hidden="true">›</span>
    </a>

    <a class="settings-item settings-link" href="<?= url('kelola_akun.php') ?>">
      <span class="settings-item-icon" aria-hidden="true">♙</span>
      <div class="settings-item-main">
        <b>Kelola akun</b>
        <p class="helper">Perbarui data profil, password, atau hapus akun secara permanen.</p>
      </div>
      <span aria-hidden="true">›</span>
    </a>
  </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
