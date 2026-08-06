<?php
require_once __DIR__ . '/includes/functions.php';
$q = trim($_GET['q'] ?? ''); $category = (int) ($_GET['kategori'] ?? 0);
$categories = $pdo->query('SELECT * FROM kategori ORDER BY nama_kategori')->fetchAll();
$where = []; $params = [];
if ($q !== '') { $where[] = '(b.judul_buku LIKE ? OR b.penulis LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
if ($category > 0) { $where[] = 'b.id_kategori = ?'; $params[] = $category; }
$sql = 'SELECT b.*, k.nama_kategori FROM buku b JOIN kategori k ON k.id_kategori=b.id_kategori' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY b.judul_buku';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $books = $stmt->fetchAll();
$page_title = 'Koleksi Buku'; require __DIR__ . '/includes/header.php';
?>
<div class="section-heading"><div><div class="eyebrow">Temukan bacaanmu</div><h1>Koleksi Buku</h1></div><p><?= count($books) ?> buku ditemukan</p></div><section class="card search-panel"><form class="search-form" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Cari judul atau penulis..."><select name="kategori"><option value="0">Semua kategori</option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id_kategori'] ?>" <?= $category === (int) $cat['id_kategori'] ? 'selected' : '' ?>><?= e($cat['nama_kategori']) ?></option><?php endforeach; ?></select><button type="submit">Cari Buku</button></form></section>
<?php if ($books): ?><div class="book-grid"><?php foreach ($books as $book): ?><article class="book-card"><a href="<?= url('detail_buku.php?id=' . $book['id_buku']) ?>"><div class="book-cover"><?php if ($book['gambar']): ?><img src="<?= url($book['gambar']) ?>" alt="<?= e($book['judul_buku']) ?>"><?php else: ?><div class="fake-cover"><?= e($book['judul_buku']) ?></div><?php endif; ?></div></a><div class="book-card-body"><div class="helper"><?= e($book['nama_kategori']) ?></div><h3><a href="<?= url('detail_buku.php?id=' . $book['id_buku']) ?>"><?= e($book['judul_buku']) ?></a></h3><div class="author"><?= e($book['penulis']) ?></div><div class="card-actions"><span class="price"><?= rupiah($book['harga']) ?></span><span class="stock">Stok <?= $book['stok'] ?></span></div></div></article><?php endforeach; ?></div><?php else: ?><div class="empty"><span class="icon">🔎</span><h2>Buku tidak ditemukan</h2><p>Coba kata kunci atau kategori lain.</p></div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
