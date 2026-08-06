<?php
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_user(); verify_csrf(); $message = trim($_POST['pesan'] ?? '');
    if (mb_strlen($message) < 5) set_flash('danger', 'Pesan minimal terdiri dari 5 karakter.');
    else { $stmt = $pdo->prepare('INSERT INTO pesan (id_user, pesan) VALUES (?, ?)'); $stmt->execute([$_SESSION['user_id'], $message]); set_flash('success', 'Pesan Anda sudah terkirim ke admin.'); redirect('contact.php'); }
}
$page_title = 'Contact Admin'; require __DIR__ . '/includes/header.php';
?>
<div class="form-row"><section><div class="eyebrow">Kami siap membantu</div><h1>Hubungi BrianBookStore</h1><p>Memiliki pertanyaan mengenai buku atau pesanan? Kirim pesan melalui formulir berikut, dan admin akan meninjaunya.</p><div class="card"><b>BrianBookStore</b><p style="margin:8px 0 0">Jl. Buku Impian No. 10, Indonesia<br>0812-3456-7890<br>admin@brianbookstore.test</p></div></section><section class="card"><h2>Kirim Pesan</h2><?php if (!is_user_logged_in()): ?><p>Silakan <a href="<?= url('login.php') ?>">masuk</a> terlebih dahulu untuk mengirim pesan.</p><?php else: ?><form method="post"><?= csrf_field() ?><div class="form-group"><label for="pesan">Pesan Anda</label><textarea id="pesan" name="pesan" required placeholder="Tuliskan pesan Anda..."></textarea></div><button type="submit">Kirim Pesan</button></form><?php endif; ?></section></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
