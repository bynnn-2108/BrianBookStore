<?php
require_once __DIR__ . '/../includes/functions.php';
if (is_admin_logged_in()) redirect('admin/dashboard.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id_admin,nama,password FROM admin WHERE email=? LIMIT 1'); $stmt->execute([$email]); $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) { session_regenerate_id(true); $_SESSION['admin_id'] = $admin['id_admin']; $_SESSION['admin_nama'] = $admin['nama']; redirect('admin/dashboard.php'); }
    $error = 'Email atau password admin tidak tepat.';
}
$page_title = 'Login Admin'; require __DIR__ . '/../includes/header.php';
?>
<section class="card narrow-card"><div class="auth-head"><a class="brand" href="<?= url('index.php') ?>"><span class="brand-mark">B</span>Brian<span>BookStore</span></a><h1>Login Admin</h1><p class="helper">Masuk untuk mengelola BrianBookStore.</p></div><?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><div class="form-group"><label for="email">Email Admin</label><input id="email" type="email" name="email" required value="<?= e($email ?? '') ?>"></div><div class="form-group"><label for="password">Password</label><input id="password" type="password" name="password" required></div><button class="button-block" type="submit">Masuk ke Dashboard</button></form><p class="helper" style="text-align:center;margin:18px 0 0"><a href="<?= url('index.php') ?>">← Kembali ke website</a></p></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
