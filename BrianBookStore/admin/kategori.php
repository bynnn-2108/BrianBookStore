<?php
require_once __DIR__ . '/../includes/functions.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $action = $_POST['action'] ?? ''; $name = trim($_POST['nama_kategori'] ?? ''); $id = (int) ($_POST['id_kategori'] ?? 0);
    try {
        if (($action === 'add' || $action === 'edit') && $name === '') throw new RuntimeException('Nama kategori wajib diisi.');
        if ($action === 'add') { $stmt = $pdo->prepare('INSERT INTO kategori (nama_kategori) VALUES (?)'); $stmt->execute([$name]); set_flash('success', 'Kategori berhasil ditambahkan.'); }
        elseif ($action === 'edit') { $stmt = $pdo->prepare('UPDATE kategori SET nama_kategori=? WHERE id_kategori=?'); $stmt->execute([$name, $id]); set_flash('success', 'Kategori berhasil diubah.'); }
        elseif ($action === 'delete') { $stmt = $pdo->prepare('DELETE FROM kategori WHERE id_kategori=?'); $stmt->execute([$id]); set_flash('success', 'Kategori berhasil dihapus.'); }
    } catch (Throwable $e) { set_flash('danger', str_contains($e->getMessage(), 'Integrity') ? 'Kategori masih dipakai oleh buku dan tidak dapat dihapus.' : $e->getMessage()); }
    redirect('admin/kategori.php');
}
$edit = null; if (isset($_GET['edit'])) { $stmt = $pdo->prepare('SELECT * FROM kategori WHERE id_kategori=?'); $stmt->execute([(int)$_GET['edit']]); $edit = $stmt->fetch(); }
$categories = $pdo->query('SELECT k.*, COUNT(b.id_buku) AS total_buku FROM kategori k LEFT JOIN buku b ON b.id_kategori=k.id_kategori GROUP BY k.id_kategori ORDER BY k.nama_kategori')->fetchAll();
$page_title = 'Manajemen Kategori'; require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-grid"><section class="card"><h2><?= $edit ? 'Ubah Kategori' : 'Tambah Kategori' ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>"><?php if ($edit): ?><input type="hidden" name="id_kategori" value="<?= $edit['id_kategori'] ?>"><?php endif; ?><div class="form-group"><label for="nama_kategori">Nama kategori</label><input id="nama_kategori" name="nama_kategori" required value="<?= e($edit['nama_kategori'] ?? '') ?>" placeholder="Contoh: Novel"></div><button type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Kategori' ?></button><?php if ($edit): ?><a class="button button-outline" href="<?= url('admin/kategori.php') ?>">Batal</a><?php endif; ?></form></section><section class="card table-wrap"><h2>Daftar Kategori</h2><table class="data-table"><thead><tr><th>Kategori</th><th>Total Buku</th><th>Aksi</th></tr></thead><tbody><?php foreach ($categories as $cat): ?><tr><td><?= e($cat['nama_kategori']) ?></td><td><?= $cat['total_buku'] ?></td><td><div class="row-actions"><a class="button button-small button-outline" href="<?= url('admin/kategori.php?edit=' . $cat['id_kategori']) ?>">Ubah</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id_kategori" value="<?= $cat['id_kategori'] ?>"><button class="button-danger button-small" data-confirm="Hapus kategori ini?" type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody></table></section></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
