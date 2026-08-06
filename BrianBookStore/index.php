<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Toko Buku Online';
$books = $pdo->query('SELECT b.*, k.nama_kategori FROM buku b JOIN kategori k ON k.id_kategori=b.id_kategori ORDER BY b.created_at DESC LIMIT 8')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="hero"><div class="hero-inner"><div class="eyebrow">Toko buku online pilihan</div><h1>Setiap buku membuka dunia baru.</h1><p>BrianBookStore menghadirkan koleksi bacaan bermakna untuk menemani rasa ingin tahu Anda.</p><a class="button" href="<?= url('buku.php') ?>">Jelajahi Koleksi →</a></div></section>
<section class="section"><div class="section-heading"><div><p>Koleksi pilihan</p><h2>Buku yang baru hadir</h2></div><a href="<?= url('buku.php') ?>">Lihat semua buku →</a></div>
<?php if ($books): ?><div class="book-grid"><?php foreach ($books as $book): ?><article class="book-card"><a href="<?= url('detail_buku.php?id=' . $book['id_buku']) ?>"><div class="book-cover"><?php if ($book['gambar']): ?><img src="<?= url($book['gambar']) ?>" alt="<?= e($book['judul_buku']) ?>"><?php else: ?><div class="fake-cover"><?= e($book['judul_buku']) ?></div><?php endif; ?></div></a><div class="book-card-body"><div class="helper"><?= e($book['nama_kategori']) ?></div><h3><a href="<?= url('detail_buku.php?id=' . $book['id_buku']) ?>"><?= e($book['judul_buku']) ?></a></h3><div class="author"><?= e($book['penulis']) ?></div><div class="card-actions"><span class="price"><?= rupiah($book['harga']) ?></span><a class="button button-small" href="<?= url('detail_buku.php?id=' . $book['id_buku']) ?>">Detail</a></div></div></article><?php endforeach; ?></div><?php else: ?><div class="empty"><span class="icon">📚</span>Belum ada buku yang ditampilkan.</div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
