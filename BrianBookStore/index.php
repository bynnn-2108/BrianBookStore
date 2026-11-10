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

<?php require __DIR__ . '/includes/footer.php'; ?>
