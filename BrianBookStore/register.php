<?php
/** Pendaftaran customer baru dengan validasi input dan password hashing. */
require_once __DIR__ . '/includes/functions.php';

if (is_user_logged_in()) {
    redirect('index.php');
}

$old = [
    'nama' => '',
    'email' => '',
    'alamat' => '',
    'nomor_telepon' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['konfirmasi_password'] ?? '';

    if (in_array('', $old, true) || $password === '') {
        $error = 'Semua data wajib diisi.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (mb_strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        $check = $pdo->prepare('SELECT id_user FROM `user` WHERE email = ?');
        $check->execute([$old['email']]);

        if ($check->fetch()) {
            $error = 'Email sudah terdaftar. Silakan gunakan email lain atau masuk.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO `user` (nama, email, password, alamat, nomor_telepon)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['nama'],
                $old['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $old['alamat'],
                $old['nomor_telepon'],
            ]);

            // Menyalin alamat awal ke buku alamat jika migrasi alamat sudah tersedia.
            try {
                $newUserId = (int) $pdo->lastInsertId();
                $address = $pdo->prepare(
                    'INSERT INTO alamat_user (
                        id_user, label, nama_penerima, nomor_telepon, alamat, is_default
                     ) VALUES (?, ?, ?, ?, ?, 1)'
                );
                $address->execute([
                    $newUserId,
                    'Alamat utama',
                    $old['nama'],
                    $old['nomor_telepon'],
                    $old['alamat'],
                ]);
            } catch (PDOException $exception) {
                // Registrasi tetap berhasil pada database lama yang belum menjalankan migrasi alamat.
            }

            set_flash('success', 'Registrasi berhasil. Silakan masuk ke akun Anda.');
            redirect('login.php');
        }
    }
}

$page_title = 'Daftar';

require __DIR__ . '/includes/header.php';
?>
<section class="card narrow-card">
  <div class="auth-head">
    <a class="brand" href="<?= url('index.php') ?>">
      <span class="brand-mark">B</span>
      Brian<span>BookStore</span>
    </a>
    <h1>Buat akun baru</h1>
    <p class="helper">Mulai perjalanan membaca Anda bersama kami.</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="nama">Nama lengkap</label>
      <input id="nama" name="nama" required value="<?= e($old['nama']) ?>">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" required value="<?= e($old['email']) ?>">
      </div>
      <div class="form-group">
        <label for="nomor_telepon">Nomor telepon</label>
        <input
          id="nomor_telepon"
          name="nomor_telepon"
          required
          value="<?= e($old['nomor_telepon']) ?>"
        >
      </div>
    </div>

    <div class="form-group">
      <label for="alamat">Alamat lengkap</label>
      <textarea id="alamat" name="alamat" required><?= e($old['alamat']) ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" minlength="8" required>
      </div>
      <div class="form-group">
        <label for="konfirmasi_password">Konfirmasi password</label>
        <input
          id="konfirmasi_password"
          type="password"
          name="konfirmasi_password"
          minlength="8"
          required
        >
      </div>
    </div>

    <button class="button-block" type="submit">Daftar Sekarang</button>
  </form>

  <p class="helper" style="text-align:center;margin:18px 0 0">
    Sudah punya akun? <a href="<?= url('login.php') ?>">Masuk di sini</a>
  </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
