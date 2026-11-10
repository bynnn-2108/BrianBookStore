<?php
/** Buku alamat customer: tambah, ubah, pilih utama, dan hapus alamat pengiriman. */
require_once __DIR__ . '/includes/functions.php';

require_user();

$userId = (int) $_SESSION['user_id'];
$user = current_user();
$returnTo = ($_GET['return_to'] ?? $_POST['return_to'] ?? '') === 'checkout.php'
    ? 'checkout.php'
    : 'alamat.php';
$addresses = [];
$error = '';
$oldAddress = [
    'id_alamat' => 0,
    'label' => 'Rumah',
    'nama_penerima' => $user['nama'] ?? '',
    'nomor_telepon' => $user['nomor_telepon'] ?? '',
    'alamat' => $user['alamat'] ?? '',
    'is_default' => 0,
];

try {
    // Mengonversi alamat lama pada profil menjadi alamat tersimpan pertama kali.
    seed_default_address($userId, $user ?: []);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        $action = $_POST['action'] ?? '';
        $addressId = (int) ($_POST['id_alamat'] ?? 0);

        if ($action === 'delete' || $action === 'set_default') {
            // Memastikan alamat yang dimanipulasi benar-benar milik user aktif.
            $find = $pdo->prepare(
                'SELECT id_alamat, is_default
                 FROM alamat_user
                 WHERE id_alamat = ? AND id_user = ?'
            );
            $find->execute([$addressId, $userId]);
            $target = $find->fetch();

            if (!$target) {
                throw new RuntimeException('Alamat tidak ditemukan.');
            }

            $pdo->beginTransaction();

            if ($action === 'set_default') {
                // Hanya satu alamat yang boleh menjadi alamat utama customer.
                $reset = $pdo->prepare(
                    'UPDATE alamat_user
                     SET is_default = 0
                     WHERE id_user = ?'
                );
                $reset->execute([$userId]);

                $makeDefault = $pdo->prepare(
                    'UPDATE alamat_user
                     SET is_default = 1
                     WHERE id_alamat = ? AND id_user = ?'
                );
                $makeDefault->execute([$addressId, $userId]);
                $pdo->commit();
                set_flash('success', 'Alamat utama berhasil diganti.');
            } else {
                // Menghapus alamat melalui kepemilikan user agar alamat user lain tidak tersentuh.
                $delete = $pdo->prepare(
                    'DELETE FROM alamat_user
                     WHERE id_alamat = ? AND id_user = ?'
                );
                $delete->execute([$addressId, $userId]);

                if ((int) $target['is_default'] === 1) {
                    // Mempromosikan alamat terbaru bila alamat utama dihapus.
                    $promote = $pdo->prepare(
                        'UPDATE alamat_user
                         SET is_default = 1
                         WHERE id_user = ?
                         ORDER BY updated_at DESC, id_alamat DESC
                         LIMIT 1'
                    );
                    $promote->execute([$userId]);
                }

                $pdo->commit();
                set_flash('success', 'Alamat berhasil dihapus.');
            }

            redirect($returnTo);
        }

        if ($action === 'save') {
            // Menyimpan nilai form agar input tidak hilang ketika validasi gagal.
            $oldAddress = [
                'id_alamat' => $addressId,
                'label' => trim((string) ($_POST['label'] ?? '')),
                'nama_penerima' => trim((string) ($_POST['nama_penerima'] ?? '')),
                'nomor_telepon' => trim((string) ($_POST['nomor_telepon'] ?? '')),
                'alamat' => trim((string) ($_POST['alamat'] ?? '')),
                'is_default' => isset($_POST['is_default']) ? 1 : 0,
            ];

            if (
                $oldAddress['label'] === ''
                || $oldAddress['nama_penerima'] === ''
                || $oldAddress['nomor_telepon'] === ''
                || $oldAddress['alamat'] === ''
            ) {
                $error = 'Label, nama penerima, nomor telepon, dan alamat wajib diisi.';
            } else {
                $pdo->beginTransaction();

                if ($oldAddress['is_default'] === 1) {
                    // Menonaktifkan alamat utama lain sebelum menyimpan pilihan baru.
                    $reset = $pdo->prepare(
                        'UPDATE alamat_user
                         SET is_default = 0
                         WHERE id_user = ?'
                    );
                    $reset->execute([$userId]);
                }

                if ($addressId > 0) {
                    // Update dibatasi dengan id_user agar ID dari user lain tidak dapat diubah.
                    $update = $pdo->prepare(
                        'UPDATE alamat_user
                         SET label = ?, nama_penerima = ?, nomor_telepon = ?,
                             alamat = ?, is_default = ?
                         WHERE id_alamat = ? AND id_user = ?'
                    );
                    $update->execute([
                        $oldAddress['label'],
                        $oldAddress['nama_penerima'],
                        $oldAddress['nomor_telepon'],
                        $oldAddress['alamat'],
                        $oldAddress['is_default'],
                        $addressId,
                        $userId,
                    ]);
                } else {
                    // Alamat baru otomatis menjadi utama jika ini alamat pertama customer.
                    $count = $pdo->prepare(
                        'SELECT COUNT(*) FROM alamat_user WHERE id_user = ?'
                    );
                    $count->execute([$userId]);
                    $isDefault = $oldAddress['is_default'] || (int) $count->fetchColumn() === 0;

                    $insert = $pdo->prepare(
                        'INSERT INTO alamat_user (
                            id_user, label, nama_penerima, nomor_telepon, alamat, is_default
                         ) VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $userId,
                        $oldAddress['label'],
                        $oldAddress['nama_penerima'],
                        $oldAddress['nomor_telepon'],
                        $oldAddress['alamat'],
                        $isDefault ? 1 : 0,
                    ]);
                }

                $pdo->commit();
                set_flash('success', 'Alamat berhasil disimpan.');
                redirect($returnTo);
            }
        }
    }

    // Mengisi form edit bila customer membuka tombol ubah pada salah satu alamat.
    $editId = (int) ($_GET['edit'] ?? 0);

    if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $edit = $pdo->prepare(
            'SELECT id_alamat, label, nama_penerima, nomor_telepon, alamat, is_default
             FROM alamat_user
             WHERE id_alamat = ? AND id_user = ?'
        );
        $edit->execute([$editId, $userId]);
        $found = $edit->fetch();

        if ($found) {
            $oldAddress = $found;
        } else {
            $error = 'Alamat yang ingin diubah tidak ditemukan.';
        }
    }

    $addresses = user_addresses($userId);
} catch (Throwable $exception) {
    // Pesan ramah membantu instalasi lama mengetahui bahwa migrasi alamat perlu dijalankan.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = 'Fitur alamat belum siap. Jalankan migrasi database alamat_user terlebih dahulu.';
}

$page_title = 'Alamat Pengiriman';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Akun &amp; checkout</div>
    <h1>Alamat Pengiriman</h1>
  </div>
  <a href="<?= url($returnTo === 'checkout.php' ? 'checkout.php' : 'pengaturan.php') ?>">
    ← Kembali
  </a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="address-book-layout">
  <section class="card address-list-panel">
    <div class="card-section-heading">
      <div>
        <div class="eyebrow">Buku alamat</div>
        <h2>Alamat tersimpan</h2>
      </div>
      <span class="card-heading-icon" aria-hidden="true">⌖</span>
    </div>

    <p class="helper">Pilih alamat utama untuk mempercepat checkout berikutnya.</p>

    <?php if ($addresses): ?>
      <div class="address-list">
        <?php foreach ($addresses as $address): ?>
          <article class="address-card <?= $address['is_default'] ? 'is-default' : '' ?>">
            <div class="address-card-heading">
              <div>
                <span class="address-label"><?= e($address['label']) ?></span>
                <?php if ($address['is_default']): ?>
                  <span class="badge badge-success">Alamat utama</span>
                <?php endif; ?>
              </div>
              <a href="<?= url('alamat.php?edit=' . $address['id_alamat'] . '&return_to=' . $returnTo) ?>">
                Ubah
              </a>
            </div>
            <strong><?= e($address['nama_penerima']) ?></strong>
            <span><?= e($address['nomor_telepon']) ?></span>
            <p><?= nl2br(e($address['alamat'])) ?></p>
            <div class="address-card-actions">
              <?php if (!$address['is_default']): ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="set_default">
                  <input type="hidden" name="id_alamat" value="<?= $address['id_alamat'] ?>">
                  <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                  <button class="button-outline button-small" type="submit">Jadikan utama</button>
                </form>
              <?php endif; ?>
              <form
                method="post"
                onsubmit="return confirm('Hapus alamat ini dari buku alamat?');"
              >
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_alamat" value="<?= $address['id_alamat'] ?>">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <button class="button-danger button-small" type="submit">Hapus</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty address-empty">
        <span class="icon">⌖</span>
        <p>Belum ada alamat tersimpan.</p>
      </div>
    <?php endif; ?>
  </section>

  <section class="card address-form-panel">
    <div class="eyebrow"><?= $oldAddress['id_alamat'] ? 'Perbarui alamat' : 'Alamat baru' ?></div>
    <h2><?= $oldAddress['id_alamat'] ? 'Ubah alamat' : 'Tambah alamat' ?></h2>
    <p class="helper">Simpan alamat rumah, kantor, atau alamat tujuan lain.</p>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id_alamat" value="<?= $oldAddress['id_alamat'] ?>">
      <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

      <div class="form-group">
        <label for="label">Label alamat</label>
        <input id="label" name="label" required value="<?= e($oldAddress['label']) ?>" placeholder="Rumah atau Kantor">
      </div>

      <div class="form-group">
        <label for="nama_penerima">Nama penerima</label>
        <input id="nama_penerima" name="nama_penerima" required value="<?= e($oldAddress['nama_penerima']) ?>">
      </div>

      <div class="form-group">
        <label for="nomor_telepon">Nomor telepon</label>
        <input id="nomor_telepon" name="nomor_telepon" required value="<?= e($oldAddress['nomor_telepon']) ?>">
      </div>

      <div class="form-group">
        <label for="alamat">Alamat lengkap</label>
        <textarea id="alamat" name="alamat" required><?= e($oldAddress['alamat']) ?></textarea>
      </div>

      <label class="checkbox-field">
        <input type="checkbox" name="is_default" value="1" <?= $oldAddress['is_default'] ? 'checked' : '' ?>>
        <span>Jadikan alamat utama</span>
      </label>

      <button type="submit">Simpan alamat</button>
      <?php if ($oldAddress['id_alamat']): ?>
        <a class="button button-outline" href="<?= url('alamat.php?return_to=' . $returnTo) ?>">
          Batal ubah
        </a>
      <?php endif; ?>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
