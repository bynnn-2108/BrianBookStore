<?php
/** CRUD buku admin, termasuk upload gambar dan validasi stok/harga. */
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Mengambil dan menormalisasi seluruh field buku dari form admin.
function validate_book_input(): array
{
    return [
        (int) ($_POST['id_kategori'] ?? 0),
        trim($_POST['judul_buku'] ?? ''),
        trim($_POST['penulis'] ?? ''),
        trim($_POST['penerbit'] ?? ''),
        (int) ($_POST['tahun_terbit'] ?? 0),
        (float) ($_POST['harga'] ?? -1),
        (int) ($_POST['stok'] ?? -1),
        trim($_POST['deskripsi'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $bookId = (int) ($_POST['id_buku'] ?? 0);
    $uploadedImage = null;

    try {
        if ($action === 'delete') {
            $find = $pdo->prepare('SELECT gambar FROM buku WHERE id_buku = ?');
            $find->execute([$bookId]);
            $oldBook = $find->fetch();

            $delete = $pdo->prepare('DELETE FROM buku WHERE id_buku = ?');
            $delete->execute([$bookId]);

            if ($delete->rowCount()) {
                delete_upload($oldBook['gambar'] ?? null);
                set_flash('success', 'Buku berhasil dihapus.');
            } else {
                set_flash('warning', 'Buku tidak ditemukan.');
            }

            redirect('admin/buku.php');
        }

        if (!in_array($action, ['add', 'edit'], true)) {
            throw new RuntimeException('Aksi buku tidak valid.');
        }

        [
            $category,
            $title,
            $author,
            $publisher,
            $year,
            $price,
            $stock,
            $description,
        ] = validate_book_input();

        if (
            !$category
            || $title === ''
            || $author === ''
            || $publisher === ''
            || $year < 1000
            || $year > (int) date('Y') + 1
            || $price < 0
            || $stock < 0
            || $description === ''
        ) {
            throw new RuntimeException('Mohon isi seluruh data buku dengan benar.');
        }

        $upload = $_FILES['gambar'] ?? [];

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$uploaded, $result] = upload_file(
                $upload,
                'uploads/books',
                [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ]
            );

            if (!$uploaded) {
                throw new RuntimeException($result);
            }

            $uploadedImage = $result;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare(
                'INSERT INTO buku (
                    id_kategori,
                    judul_buku,
                    penulis,
                    penerbit,
                    tahun_terbit,
                    harga,
                    stok,
                    deskripsi,
                    gambar
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $category,
                $title,
                $author,
                $publisher,
                $year,
                $price,
                $stock,
                $description,
                $uploadedImage,
            ]);

            set_flash('success', 'Buku berhasil ditambahkan.');
        } else {
            $oldStmt = $pdo->prepare('SELECT gambar FROM buku WHERE id_buku = ?');
            $oldStmt->execute([$bookId]);
            $oldBook = $oldStmt->fetch();

            if (!$oldBook) {
                throw new RuntimeException('Data buku tidak ditemukan.');
            }

            $image = $uploadedImage ?: $oldBook['gambar'];

            $stmt = $pdo->prepare(
                'UPDATE buku
                 SET id_kategori = ?,
                     judul_buku = ?,
                     penulis = ?,
                     penerbit = ?,
                     tahun_terbit = ?,
                     harga = ?,
                     stok = ?,
                     deskripsi = ?,
                     gambar = ?
                 WHERE id_buku = ?'
            );
            $stmt->execute([
                $category,
                $title,
                $author,
                $publisher,
                $year,
                $price,
                $stock,
                $description,
                $image,
                $bookId,
            ]);

            if ($uploadedImage && $oldBook['gambar']) {
                delete_upload($oldBook['gambar']);
            }

            set_flash('success', 'Data buku berhasil diubah.');
        }
    } catch (Throwable $exception) {
        if ($uploadedImage) {
            delete_upload($uploadedImage);
        }

        set_flash('danger', $exception->getMessage());
    }

    redirect('admin/buku.php');
}

$categories = $pdo->query(
    'SELECT *
     FROM kategori
     ORDER BY nama_kategori'
)->fetchAll();

$edit = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM buku WHERE id_buku = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$query = trim($_GET['q'] ?? '');

$stmt = $pdo->prepare(
    'SELECT b.*, k.nama_kategori
     FROM buku b
     JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE b.judul_buku LIKE ? OR b.penulis LIKE ?
     ORDER BY b.created_at DESC'
);
$stmt->execute(['%' . $query . '%', '%' . $query . '%']);
$books = $stmt->fetchAll();

$page_title = 'Manajemen Buku';

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar">
  <div>
    <p class="helper">Tambah, ubah, atau hapus koleksi toko.</p>
  </div>

  <form class="search-inline" method="get">
    <input name="q" value="<?= e($query) ?>" placeholder="Cari judul atau penulis">
    <button type="submit">Cari</button>
  </form>
</div>

<div class="admin-grid">
  <section class="card">
    <h2><?= $edit ? 'Ubah Buku' : 'Tambah Buku' ?></h2>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">

      <?php if ($edit): ?>
        <input type="hidden" name="id_buku" value="<?= $edit['id_buku'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label for="id_kategori">Kategori</label>
        <select id="id_kategori" name="id_kategori" required>
          <option value="">Pilih kategori</option>
          <?php foreach ($categories as $category): ?>
            <option
              value="<?= $category['id_kategori'] ?>"
              <?= (int) ($edit['id_kategori'] ?? 0) === (int) $category['id_kategori'] ? 'selected' : '' ?>
            >
              <?= e($category['nama_kategori']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="judul_buku">Judul buku</label>
        <input
          id="judul_buku"
          name="judul_buku"
          required
          value="<?= e($edit['judul_buku'] ?? '') ?>"
        >
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="penulis">Penulis</label>
          <input id="penulis" name="penulis" required value="<?= e($edit['penulis'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="penerbit">Penerbit</label>
          <input id="penerbit" name="penerbit" required value="<?= e($edit['penerbit'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="tahun_terbit">Tahun terbit</label>
          <input
            id="tahun_terbit"
            type="number"
            name="tahun_terbit"
            min="1000"
            max="<?= date('Y') + 1 ?>"
            required
            value="<?= e($edit['tahun_terbit'] ?? date('Y')) ?>"
          >
        </div>
        <div class="form-group">
          <label for="harga">Harga</label>
          <input
            id="harga"
            type="number"
            name="harga"
            min="0"
            required
            value="<?= e($edit['harga'] ?? '') ?>"
          >
        </div>
      </div>

      <div class="form-group">
        <label for="stok">Stok</label>
        <input
          id="stok"
          type="number"
          name="stok"
          min="0"
          required
          value="<?= e($edit['stok'] ?? 0) ?>"
        >
      </div>

      <div class="form-group">
        <label for="deskripsi">Deskripsi</label>
        <textarea id="deskripsi" name="deskripsi" required><?= e($edit['deskripsi'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label for="gambar">Gambar buku (opsional)</label>
        <input id="gambar" type="file" name="gambar" accept="image/jpeg,image/png,image/webp">
        <span class="helper">JPG, PNG, atau WEBP maksimal 2 MB.</span>
      </div>

      <button type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Buku' ?></button>

      <?php if ($edit): ?>
        <a class="button button-outline" href="<?= url('admin/buku.php') ?>">Batal</a>
      <?php endif; ?>
    </form>
  </section>

  <section class="card table-wrap">
    <h2>Daftar Buku</h2>

    <table class="data-table">
      <thead>
        <tr>
          <th>Buku</th>
          <th>Kategori</th>
          <th>Harga</th>
          <th>Stok</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($books as $book): ?>
          <tr>
            <td>
              <b><?= e($book['judul_buku']) ?></b><br>
              <span class="helper"><?= e($book['penulis']) ?></span>
            </td>
            <td><?= e($book['nama_kategori']) ?></td>
            <td><?= rupiah($book['harga']) ?></td>
            <td><?= $book['stok'] ?></td>
            <td>
              <div class="row-actions">
                <a
                  class="button button-small button-outline"
                  href="<?= url('admin/buku.php?edit=' . $book['id_buku']) ?>"
                >Ubah</a>

                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id_buku" value="<?= $book['id_buku'] ?>">
                  <button
                    class="button-danger button-small"
                    type="submit"
                    data-confirm="Hapus buku ini?"
                  >Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
