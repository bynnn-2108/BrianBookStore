<?php
require_once __DIR__ . '/../includes/functions.php'; require_admin();
$q = trim($_GET['q'] ?? ''); $stmt = $pdo->prepare('SELECT id_user,nama,email,alamat,nomor_telepon,created_at FROM `user` WHERE nama LIKE ? OR email LIKE ? ORDER BY created_at DESC'); $stmt->execute(['%' . $q . '%','%' . $q . '%']); $users = $stmt->fetchAll();
$page_title = 'Data User'; require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar"><p class="helper">Daftar pelanggan yang telah melakukan registrasi.</p><form class="search-inline" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Cari nama atau email"><button type="submit">Cari</button></form></div><section class="card table-wrap"><table class="data-table"><thead><tr><th>Nama</th><th>Email</th><th>Telepon</th><th>Alamat</th><th>Terdaftar</th></tr></thead><tbody><?php if ($users): ?><?php foreach ($users as $user): ?><tr><td><b><?= e($user['nama']) ?></b></td><td><?= e($user['email']) ?></td><td><?= e($user['nomor_telepon']) ?></td><td style="white-space:normal;min-width:180px"><?= e($user['alamat']) ?></td><td><?= date('d M Y', strtotime($user['created_at'])) ?></td></tr><?php endforeach; ?><?php else: ?><tr><td colspan="5" class="helper">Tidak ada user yang ditemukan.</td></tr><?php endif; ?></tbody></table></section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
