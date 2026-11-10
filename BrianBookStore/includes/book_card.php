<?php
/** Komponen kartu buku reusable untuk gambar, stok, harga, jumlah, dan keranjang. */
$returnTo = $returnTo ?? 'buku.php';
$bookId = (int) $book['id_buku'];
$stock = max(0, (int) $book['stok']);
$quantityInputId = 'jumlah-buku-' . $bookId;
$detailUrl = url('detail_buku.php?id=' . $bookId);
$bookBadge = trim((string) ($bookBadge ?? ''));
$bookCover = book_cover_path($book);
?>
<!-- Wrapper kartu yang dapat dipakai ulang di beranda dan katalog. -->
<article class="book-card">
  <a href="<?= $detailUrl ?>">
    <div class="book-cover">
      <?php if ($bookBadge !== ''): ?>
        <!-- Badge konteks menandai kartu best seller atau rekomendasi. -->
        <span class="book-card-badge"><?= e($bookBadge) ?></span>
      <?php endif; ?>
      <?php if ($bookCover): ?>
        <img src="<?= url($bookCover) ?>" alt="<?= e($book['judul_buku']) ?>">
      <?php else: ?>
        <div class="fake-cover"><?= e($book['judul_buku']) ?></div>
      <?php endif; ?>

      <!-- Overlay hover menampilkan sinopsis singkat tanpa memenuhi layout kartu. -->
      <span class="book-card-description">
        <span class="book-card-description-label">Sinopsis singkat</span>
        <span><?= e($book['deskripsi']) ?></span>
      </span>
    </div>
  </a>

  <!-- Metadata buku dan kontrol pembelian berada di bagian body kartu. -->
  <div class="book-card-body">
    <div class="helper"><?= e($book['nama_kategori']) ?></div>
    <h3><a href="<?= $detailUrl ?>"><?= e($book['judul_buku']) ?></a></h3>
    <div class="author"><?= e($book['penulis']) ?></div>
    <span class="price"><?= rupiah($book['harga']) ?></span>
    <span class="stock book-card-stock">Stok tersedia: <?= $stock ?></span>

    <!-- Tombol pembelian hanya aktif jika stok masih tersedia. -->
    <?php if ($stock > 0): ?>
      <form class="book-card-cart-form" action="<?= url('cart_add.php') ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id_buku" value="<?= $bookId ?>">
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <label class="sr-only" for="<?= $quantityInputId ?>">
          Jumlah <?= e($book['judul_buku']) ?>
        </label>
        <input
          class="qty-control"
          id="<?= $quantityInputId ?>"
          type="number"
          min="1"
          max="<?= $stock ?>"
          name="jumlah"
          value="1"
          required
        >
        <button class="button" type="submit">+ Keranjang</button>
      </form>
    <?php else: ?>
      <button class="button button-small" type="button" disabled>Stok habis</button>
    <?php endif; ?>

    <div class="book-card-links">
      <a href="<?= $detailUrl ?>">Lihat detail →</a>
    </div>
  </div>
</article>
