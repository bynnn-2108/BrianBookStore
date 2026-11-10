<?php
/** Katalog publik: menerima pencarian/kategori lalu menampilkan buku yang cocok. */
require_once __DIR__ . '/includes/functions.php';

$query = trim($_GET['q'] ?? '');
$category = (int) ($_GET['kategori'] ?? 0);

$categories = $pdo->query(
    'SELECT *
     FROM kategori
     ORDER BY nama_kategori'
)->fetchAll();

$where = [];
$params = [];

if ($query !== '') {
    $where[] = '(b.judul_buku LIKE ? OR b.penulis LIKE ?)';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}

if ($category > 0) {
    $where[] = 'b.id_kategori = ?';
    $params[] = $category;
}

$sql = 'SELECT b.*, k.nama_kategori
        FROM buku b
        JOIN kategori k ON k.id_kategori = b.id_kategori';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY b.judul_buku';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$page_title = 'Koleksi Buku';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Temukan bacaanmu</div>
    <h1>Koleksi Buku</h1>
  </div>
  <p><?= count($books) ?> buku ditemukan</p>
</div>

<section class="card search-panel">
  <form class="search-form" method="get">
    <input name="q" value="<?= e($query) ?>" placeholder="Cari judul atau penulis...">

    <select name="kategori">
      <option value="0">Semua kategori</option>
      <?php foreach ($categories as $item): ?>
        <option
          value="<?= $item['id_kategori'] ?>"
          <?= $category === (int) $item['id_kategori'] ? 'selected' : '' ?>
        >
          <?= e($item['nama_kategori']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit">Cari Buku</button>
  </form>
</section>

<?php if ($books): ?>
  <div class="book-grid">
    <?php foreach ($books as $book): ?>
      <?php $returnTo = 'buku.php'; ?>
      <?php require __DIR__ . '/includes/book_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty">
    <span class="icon">🔎</span>
    <h2>Buku tidak ditemukan</h2>
    <p>Coba kata kunci atau kategori lain.</p>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
