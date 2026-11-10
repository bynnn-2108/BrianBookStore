<?php
/** Pengelolaan profil, password, dan penghapusan akun customer. */
require_once __DIR__ . '/includes/functions.php';

require_user();

$userId = (int) $_SESSION['user_id'];
$user = current_user();

if (!$user) {
    unset($_SESSION['user_id'], $_SESSION['user_nama']);
    set_flash('warning', 'Akun tidak ditemukan. Silakan masuk kembali.');
    redirect('login.php');
}

$old = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $old = [
            'nama' => trim($_POST['nama'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'alamat' => trim($_POST['alamat'] ?? ''),
            'nomor_telepon' => trim($_POST['nomor_telepon'] ?? ''),
        ];
        $newPassword = $_POST['password_baru'] ?? '';
        $confirmPassword = $_POST['konfirmasi_password'] ?? '';

        if (in_array('', $old, true)) {
            $error = 'Nama, email, alamat, dan nomor telepon wajib diisi.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif ($newPassword !== '' && mb_strlen($newPassword) < 8) {
            $error = 'Password baru minimal 8 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi password baru tidak sama.';
        } else {
            $emailCheck = $pdo->prepare(
                'SELECT id_user
                 FROM `user`
                 WHERE email = ? AND id_user != ?'
            );
            $emailCheck->execute([$old['email'], $userId]);

            if ($emailCheck->fetch()) {
                $error = 'Email tersebut sudah digunakan oleh akun lain.';
            } else {
                if ($newPassword !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE `user`
                         SET nama = ?, email = ?, alamat = ?, nomor_telepon = ?, password = ?
                         WHERE id_user = ?'
                    );
                    $stmt->execute([
                        $old['nama'],
                        $old['email'],
                        $old['alamat'],
                        $old['nomor_telepon'],
                        password_hash($newPassword, PASSWORD_DEFAULT),
                        $userId,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE `user`
                         SET nama = ?, email = ?, alamat = ?, nomor_telepon = ?
                         WHERE id_user = ?'
                    );
                    $stmt->execute([
                        $old['nama'],
                        $old['email'],
                        $old['alamat'],
                        $old['nomor_telepon'],
                        $userId,
                    ]);
                }

                $_SESSION['user_nama'] = $old['nama'];
                set_flash('success', 'Data akun berhasil diperbarui.');
                redirect('kelola_akun.php');
            }
        }
    }

    if ($action === 'delete_account') {
        $confirmation = trim($_POST['konfirmasi_hapus'] ?? '');
        $password = $_POST['password_hapus'] ?? '';

        $passwordStmt = $pdo->prepare('SELECT password FROM `user` WHERE id_user = ?');
        $passwordStmt->execute([$userId]);
        $currentPasswordHash = $passwordStmt->fetchColumn();

        if ($confirmation !== 'HAPUS AKUN') {
            $deleteError = 'Ketik HAPUS AKUN sebagai konfirmasi penghapusan.';
        } elseif (!is_string($currentPasswordHash) || !password_verify($password, $currentPasswordHash)) {
            $deleteError = 'Password saat ini tidak sesuai.';
        } else {
            try {
                $deletedEmail = 'deleted-' . $userId . '-'
                    . bin2hex(random_bytes(6))
                    . '@example.invalid';
                $deletedPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $deleteCart = $pdo->prepare('DELETE FROM cart WHERE id_user = ?');
                $deleteCart->execute([$userId]);

                $deleteMessages = $pdo->prepare('DELETE FROM pesan WHERE id_user = ?');
                $deleteMessages->execute([$userId]);

                $anonymizeUser = $pdo->prepare(
                    'UPDATE `user`
                     SET nama = ?, email = ?, password = ?, alamat = ?, nomor_telepon = ?
                     WHERE id_user = ?'
                );
                $anonymizeUser->execute([
                    'Akun Dihapus',
                    $deletedEmail,
                    $deletedPassword,
                    '-',
                    '-',
                    $userId,
                ]);

                $pdo->commit();

                $_SESSION = [];
                set_flash(
                    'success',
                    'Akun Anda telah dihapus. Riwayat pesanan disimpan secara anonim '
                    . 'untuk pencatatan transaksi.'
                );
                redirect('login.php');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $deleteError = 'Akun tidak dapat dihapus saat ini. Silakan coba lagi.';
            }
        }
    }
}

$page_title = 'Kelola Akun';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Pengaturan akun</div>
    <h1>Kelola Akun</h1>
  </div>
  <a href="<?= url('pengaturan.php') ?>">← Kembali ke pengaturan</a>
</div>

<section class="card profile-summary">
  <div class="profile-avatar" aria-hidden="true">
    <?= e(strtoupper(mb_substr($user['nama'], 0, 1))) ?>
  </div>
  <div class="profile-summary-main">
    <div class="eyebrow">Profil pelanggan</div>
    <h2><?= e($user['nama']) ?></h2>
    <p class="helper"><?= e($user['email']) ?></p>
    <div class="profile-badges">
      <span class="badge badge-info">Akun aktif</span>
      <span class="helper">Data pengiriman tersimpan aman</span>
    </div>
  </div>
  <a class="button button-outline button-small" href="<?= url('pesanan.php') ?>">
    Lihat pesanan
  </a>
</section>

<div class="account-management-layout">
  <section class="card profile-form-card">
    <div class="card-section-heading">
      <div>
        <div class="eyebrow">Informasi utama</div>
        <h2>Data pribadi</h2>
      </div>
      <span class="card-heading-icon" aria-hidden="true">♙</span>
    </div>
    <p class="helper">Perbarui informasi yang digunakan untuk pengiriman pesanan.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">

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

      <hr class="receipt-rule">
      <h3>Ubah password <span class="helper">(opsional)</span></h3>

      <div class="form-row">
        <div class="form-group">
          <label for="password_baru">Password baru</label>
          <input id="password_baru" type="password" name="password_baru" minlength="8">
        </div>
        <div class="form-group">
          <label for="konfirmasi_password">Konfirmasi password baru</label>
          <input id="konfirmasi_password" type="password" name="konfirmasi_password" minlength="8">
        </div>
      </div>

      <button type="submit">Simpan perubahan</button>
    </form>
  </section>

  <aside class="card danger-zone">
    <div class="eyebrow">Tindakan permanen</div>
    <h2>Hapus akun</h2>
    <p>
      Tindakan ini tidak dapat dibatalkan. Data profil, keranjang, dan pesan Anda akan
      dihapus. Catatan pesanan tetap tersimpan secara anonim untuk keperluan transaksi.
    </p>

    <?php if (!empty($deleteError)): ?>
      <div class="alert alert-danger"><?= e($deleteError) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_account">

      <div class="form-group">
        <label for="password_hapus">Masukkan password saat ini</label>
        <input id="password_hapus" type="password" name="password_hapus" required>
      </div>

      <div class="form-group">
        <label for="konfirmasi_hapus">Ketik <b>HAPUS AKUN</b> untuk melanjutkan</label>
        <input
          id="konfirmasi_hapus"
          name="konfirmasi_hapus"
          autocomplete="off"
          required
        >
      </div>

      <button
        class="button-danger"
        type="submit"
        data-confirm="Akun akan dihapus dan Anda akan keluar. Lanjutkan?"
      >Hapus akun saya</button>
    </form>
  </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
