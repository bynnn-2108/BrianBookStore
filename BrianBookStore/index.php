<?php
/** Beranda storefront dengan hero brand dan koleksi buku terbaru. */
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Toko Buku Online';
$books = $pdo->query(
    'SELECT b.*, k.nama_kategori
     FROM buku b
     JOIN kategori k ON k.id_kategori = b.id_kategori
     ORDER BY b.created_at DESC
    LIMIT 8'
)->fetchAll();

// Mengurutkan best seller berdasarkan jumlah unit pada pesanan yang tidak dibatalkan.
$bestSellers = $pdo->query(
    'SELECT b.*, k.nama_kategori,
            COALESCE((
                SELECT SUM(od.jumlah)
                FROM order_detail od
                JOIN orders o ON o.id_order = od.id_order
                WHERE od.id_buku = b.id_buku
                  AND o.status <> \'Dibatalkan\'
            ), 0) AS total_terjual
     FROM buku b
     JOIN kategori k ON k.id_kategori = b.id_kategori
     ORDER BY total_terjual DESC, b.created_at DESC
     LIMIT 4'
)->fetchAll();

// Rekomendasi memakai koleksi terbaru sebagai pilihan tambahan setelah best seller.
$recommendations = $pdo->query(
    'SELECT b.*, k.nama_kategori
     FROM buku b
     JOIN kategori k ON k.id_kategori = b.id_kategori
     ORDER BY b.created_at DESC, b.judul_buku
     LIMIT 4 OFFSET 4'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-inner">
    <div class="eyebrow">Toko buku online pilihan</div>
    <h1>Setiap buku membuka dunia baru.</h1>
    <p>
      BrianBookStore menghadirkan koleksi bacaan bermakna untuk menemani
      rasa ingin tahu Anda.
    </p>
    <a class="button" href="<?= url('buku.php') ?>">Jelajahi Koleksi →</a>
  </div>
</section>

<!-- Elemen layanan singkat membuat beranda terasa lebih informatif dan hidup. -->
<section class="home-highlights" aria-label="Keunggulan BrianBookStore">
  <div class="home-highlight">
    <span class="home-highlight-icon" aria-hidden="true">✦</span>
    <div>
      <b>Kurasi bermakna</b>
      <span>Buku dipilih untuk menemani rasa ingin tahu.</span>
    </div>
  </div>
  <div class="home-highlight">
    <span class="home-highlight-icon" aria-hidden="true">⌁</span>
    <div>
      <b>Stok transparan</b>
      <span>Lihat jumlah stok sebelum menambahkan ke keranjang.</span>
    </div>
  </div>
  <div class="home-highlight">
    <span class="home-highlight-icon" aria-hidden="true">↗</span>
    <div>
      <b>Checkout lebih cepat</b>
      <span>Simpan beberapa alamat untuk tujuan pengiriman berbeda.</span>
    </div>
  </div>
</section>

<?php if ($bestSellers): ?>
  <section class="section">
    <div class="section-heading">
      <div>
        <p>Paling banyak dibeli</p>
        <h2>Best seller minggu ini</h2>
      </div>
      <a href="<?= url('buku.php') ?>">Temukan lebih banyak →</a>
    </div>

    <div class="book-grid">
      <?php foreach ($bestSellers as $book): ?>
        <?php $returnTo = 'index.php'; ?>
        <?php $bookBadge = 'Best Seller'; ?>
        <?php require __DIR__ . '/includes/book_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="section">
  <div class="section-heading">
    <div>
      <p>Koleksi pilihan</p>
      <h2>Buku yang baru hadir</h2>
    </div>
    <a href="<?= url('buku.php') ?>">Lihat semua buku →</a>
  </div>

  <?php if ($books): ?>
    <div class="book-grid">
      <?php foreach ($books as $book): ?>
        <?php $returnTo = 'index.php'; ?>
        <?php $bookBadge = ''; ?>
        <?php require __DIR__ . '/includes/book_card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty">
      <span class="icon">📚</span>
      Belum ada buku yang ditampilkan.
    </div>
  <?php endif; ?>
</section>

<?php if ($recommendations): ?>
  <section class="section">
    <div class="section-heading">
      <div>
        <p>Mungkin kamu suka</p>
        <h2>Rekomendasi untuk dibaca</h2>
      </div>
      <a href="<?= url('buku.php') ?>">Lihat katalog →</a>
    </div>

    <div class="book-grid">
      <?php foreach ($recommendations as $book): ?>
        <?php $returnTo = 'index.php'; ?>
        <?php $bookBadge = 'Rekomendasi'; ?>
        <?php require __DIR__ . '/includes/book_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
