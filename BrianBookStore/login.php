<?php
require_once __DIR__ . '/includes/functions.php';
if (is_user_logged_in()) redirect('index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id_user, nama, password FROM `user` WHERE email = ? LIMIT 1'); $stmt->execute([$email]); $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) { session_regenerate_id(true); $_SESSION['user_id'] = $user['id_user']; $_SESSION['user_nama'] = $user['nama']; set_flash('success', 'Selamat datang, ' . $user['nama'] . '!'); redirect('index.php'); }
    $error = 'Email atau password tidak tepat.';
}
$page_title = 'Masuk'; require __DIR__ . '/includes/header.php';
?>
<section class="card narrow-card"><div class="auth-head"><a class="brand" href="<?= url('index.php') ?>"><span class="brand-mark">B</span>Brian<span>BookStore</span></a><h1>Selamat datang kembali</h1><p class="helper">Masuk untuk melanjutkan belanja buku favorit.</p></div><?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><div class="form-group"><label for="email">Email</label><input id="email" type="email" name="email" required value="<?= e($email ?? '') ?>"></div><div class="form-group"><label for="password">Password</label><input id="password" type="password" name="password" required></div><button class="button-block" type="submit">Masuk</button></form><p class="helper" style="text-align:center;margin:18px 0 0">Belum memiliki akun? <a href="<?= url('register.php') ?>">Daftar sekarang</a></p><p class="helper" style="text-align:center">Admin? <a href="<?= url('admin/login.php') ?>">Masuk ke panel admin</a></p></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
