<?php
/** Form survey pengajuan pengembalian barang milik customer. */
require_once __DIR__ . '/includes/functions.php';

require_user();

try {
    // Menampilkan arahan yang ramah bila database lama belum menjalankan migrasi retur.
    $returnTableCheck = $pdo->query("SHOW TABLES LIKE 'pengajuan_pengembalian'");

    if (!$returnTableCheck->fetch()) {
        set_flash('warning', 'Fitur retur belum aktif. Jalankan migrasi database retur terlebih dahulu.');
        redirect('pesanan.php');
    }
} catch (PDOException $exception) {
    set_flash('danger', 'Tabel pengajuan retur belum tersedia.');
    redirect('pesanan.php');
}

$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? $_POST['id_order'] ?? 0);
$reasons = [
    'Buku rusak atau cacat',
    'Buku tidak sesuai pesanan',
    'Buku kurang atau salah jumlah',
    'Pesanan datang terlambat',
    'Alasan lainnya',
];
$refundMethods = ['Transfer bank', 'E-wallet', 'Tunai saat pengambilan'];

// Mengambil pesanan sekaligus memastikan halaman hanya dapat dibuka oleh pemiliknya.
$orderStmt = $pdo->prepare(
    'SELECT o.*, u.nama, u.email, u.nomor_telepon
     FROM orders o
     JOIN `user` u ON u.id_user = o.id_user
     WHERE o.id_order = ? AND o.id_user = ?'
);
$orderStmt->execute([$orderId, $userId]);
$order = $orderStmt->fetch();

if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}

// Mengambil pengajuan sebelumnya agar user dapat melihat progres verifikasi admin.
$requestStmt = $pdo->prepare(
    'SELECT *
     FROM pengajuan_pengembalian
     WHERE id_order = ? AND id_user = ?
     LIMIT 1'
);
$requestStmt->execute([$orderId, $userId]);
$returnRequest = $requestStmt->fetch() ?: null;

$form = [
    'nomor_telepon' => $returnRequest['nomor_telepon'] ?? $order['nomor_telepon'],
    'alasan' => $returnRequest['alasan'] ?? '',
    'detail_masalah' => $returnRequest['detail_masalah'] ?? '',
    'metode_pengembalian' => $returnRequest['metode_pengembalian'] ?? 'Transfer bank',
    'akun_pengembalian' => $returnRequest['akun_pengembalian'] ?? '',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Membaca data survey sebagai string agar input array atau tipe tidak valid ditolak.
    $phoneInput = $_POST['nomor_telepon'] ?? '';
    $reasonInput = $_POST['alasan'] ?? '';
    $detailInput = $_POST['detail_masalah'] ?? '';
    $methodInput = $_POST['metode_pengembalian'] ?? '';
    $accountInput = $_POST['akun_pengembalian'] ?? '';
    $form['nomor_telepon'] = is_scalar($phoneInput) ? trim((string) $phoneInput) : '';
    $form['alasan'] = is_scalar($reasonInput) ? trim((string) $reasonInput) : '';
    $form['detail_masalah'] = is_scalar($detailInput) ? trim((string) $detailInput) : '';
    $form['metode_pengembalian'] = is_scalar($methodInput) ? trim((string) $methodInput) : '';
    $form['akun_pengembalian'] = is_scalar($accountInput) ? trim((string) $accountInput) : '';

    if ($order['status'] !== 'Diterima') {
        $error = 'Pengajuan hanya dapat dibuat setelah pesanan berstatus Diterima.';
    } elseif (
        !preg_match('/^[0-9+()\-\s]{8,25}$/', $form['nomor_telepon'])
    ) {
        $error = 'Nomor telepon pengajuan belum valid.';
    } elseif (!in_array($form['alasan'], $reasons, true)) {
        $error = 'Pilih alasan pengembalian yang tersedia.';
    } elseif (mb_strlen($form['detail_masalah']) < 10) {
        $error = 'Jelaskan masalah barang minimal 10 karakter.';
    } elseif (mb_strlen($form['detail_masalah']) > 2000) {
        $error = 'Detail masalah terlalu panjang.';
    } elseif (!in_array($form['metode_pengembalian'], $refundMethods, true)) {
        $error = 'Metode pengembalian tidak valid.';
    } elseif (
        $form['metode_pengembalian'] !== 'Tunai saat pengambilan'
        && mb_strlen($form['akun_pengembalian']) < 4
    ) {
        $error = 'Isi nomor rekening atau akun e-wallet untuk pengembalian dana.';
    }

    $proof = null;

    if (!$error) {
        // Bukti retur dibatasi hanya foto agar survey mudah diverifikasi admin.
        [$uploaded, $uploadResult] = upload_file(
            $_FILES['bukti_foto'] ?? [],
            'uploads/returns',
            [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ]
        );

        if (!$uploaded || !$uploadResult) {
            $error = $uploadResult ?: 'Bukti foto wajib diunggah.';
        } else {
            $proof = $uploadResult;
        }
    }

    if (!$error) {
        $oldProof = null;

        try {
            // Mengunci order dan request agar survey tidak tertimpa proses admin.
            $pdo->beginTransaction();
            $lockOrder = $pdo->prepare(
                'SELECT status
                 FROM orders
                 WHERE id_order = ? AND id_user = ?
                 FOR UPDATE'
            );
            $lockOrder->execute([$orderId, $userId]);
            $lockedOrder = $lockOrder->fetch();

            if (!$lockedOrder || $lockedOrder['status'] !== 'Diterima') {
                throw new RuntimeException('Pesanan sudah berubah dan tidak dapat diajukan retur.');
            }

            $lockRequest = $pdo->prepare(
                'SELECT id_pengajuan, status, bukti_foto
                 FROM pengajuan_pengembalian
                 WHERE id_order = ? AND id_user = ?
                 FOR UPDATE'
            );
            $lockRequest->execute([$orderId, $userId]);
            $lockedRequest = $lockRequest->fetch();

            if ($lockedRequest && $lockedRequest['status'] !== 'Ditolak') {
                throw new RuntimeException('Pengajuan retur ini sedang diproses admin.');
            }

            if ($lockedRequest) {
                // Pengajuan yang ditolak dapat diperbaiki dan dikirim ulang oleh user.
                $oldProof = $lockedRequest['bukti_foto'];
                $saveRequest = $pdo->prepare(
                    'UPDATE pengajuan_pengembalian
                     SET nomor_telepon = ?,
                         alasan = ?,
                         detail_masalah = ?,
                         bukti_foto = ?,
                         metode_pengembalian = ?,
                         akun_pengembalian = ?,
                         status = \'Diajukan\',
                         jumlah_pengembalian = 0,
                         catatan_admin = NULL,
                         diverifikasi_pada = NULL,
                         diproses_pada = NULL,
                         diajukan_pada = NOW()
                     WHERE id_pengajuan = ?'
                );
                $saveRequest->execute([
                    $form['nomor_telepon'],
                    $form['alasan'],
                    $form['detail_masalah'],
                    $proof,
                    $form['metode_pengembalian'],
                    $form['akun_pengembalian'] ?: null,
                    $lockedRequest['id_pengajuan'],
                ]);
            } else {
                // Pengajuan pertama membuat survey baru yang terhubung satu order.
                $saveRequest = $pdo->prepare(
                    'INSERT INTO pengajuan_pengembalian (
                        id_order,
                        id_user,
                        nomor_telepon,
                        alasan,
                        detail_masalah,
                        bukti_foto,
                        metode_pengembalian,
                        akun_pengembalian,
                        jumlah_pengembalian
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)'
                );
                $saveRequest->execute([
                    $orderId,
                    $userId,
                    $form['nomor_telepon'],
                    $form['alasan'],
                    $form['detail_masalah'],
                    $proof,
                    $form['metode_pengembalian'],
                    $form['akun_pengembalian'] ?: null,
                ]);
            }

            // Status order berubah agar admin melihat pengajuan pada alur yang benar.
            $changeOrder = $pdo->prepare(
                'UPDATE orders
                 SET status = \'Pengajuan Pengembalian\'
                 WHERE id_order = ? AND id_user = ? AND status = \'Diterima\''
            );
            $changeOrder->execute([$orderId, $userId]);

            if ($changeOrder->rowCount() !== 1) {
                throw new RuntimeException('Status pesanan tidak berhasil diperbarui.');
            }

            $pdo->commit();

            // Bukti lama dibersihkan hanya setelah data baru berhasil tersimpan.
            if ($oldProof && $oldProof !== $proof) {
                delete_upload($oldProof);
            }

            set_flash('success', 'Pengajuan pengembalian berhasil dikirim ke admin.');
            redirect('pengembalian.php?order_id=' . $orderId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($proof) {
                delete_upload($proof);
            }

            $error = $exception->getMessage();
        }
    }
}

$page_title = 'Pengajuan Pengembalian';
require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Survey retur barang</div>
    <h1>Pengajuan Pengembalian</h1>
    <p class="section-lead">
      Ceritakan kendala pesanan agar admin dapat memverifikasi dan memproses pengembalian dana.
    </p>
  </div>
  <a class="button button-outline" href="<?= url('pesanan.php') ?>">← Kembali ke pesanan</a>
</div>

<div class="return-request-layout">
  <section class="card return-request-summary">
    <div class="card-section-heading">
      <div>
        <div class="eyebrow">Identitas transaksi</div>
        <h2><?= e(transaction_code($order)) ?></h2>
      </div>
      <span class="badge badge-<?= order_status_class($order['status']) ?>">
        <?= e($order['status']) ?>
      </span>
    </div>
    <div class="book-meta">
      <span>Total pesanan</span>
      <b><?= rupiah($order['total_harga']) ?></b>
      <span>Tanggal</span>
      <b><?= date('d M Y, H:i', strtotime($order['tanggal_order'])) ?></b>
      <span>Pemohon</span>
      <b><?= e($order['nama']) ?></b>
    </div>

    <?php if ($returnRequest): ?>
      <div class="return-status-panel">
        <strong>Status survey: <?= e($returnRequest['status']) ?></strong>
        <?php if ($returnRequest['catatan_admin']): ?>
          <p><?= nl2br(e($returnRequest['catatan_admin'])) ?></p>
        <?php elseif ($returnRequest['status'] === 'Diajukan'): ?>
          <p class="helper">Survey sedang menunggu verifikasi admin.</p>
        <?php elseif ($returnRequest['status'] === 'Disetujui'): ?>
          <p class="helper">
            Refund <?= rupiah($returnRequest['jumlah_pengembalian']) ?> melalui
            <?= e($returnRequest['metode_pengembalian']) ?> sudah disetujui dan pesanan masuk tahap diproses ulang.
          </p>
        <?php elseif ($returnRequest['status'] === 'Selesai'): ?>
          <p class="helper">Pengembalian dana dan proses ulang sudah ditutup.</p>
        <?php endif; ?>
        <a
          class="button button-outline button-small"
          href="<?= url('return_attachment.php?id=' . $returnRequest['id_pengajuan']) ?>"
          target="_blank"
          rel="noopener"
        >Lihat bukti foto</a>
      </div>
    <?php endif; ?>
  </section>

  <?php
  $canSubmit = $order['status'] === 'Diterima'
      && (!$returnRequest || $returnRequest['status'] === 'Ditolak');
  ?>
  <?php if ($canSubmit): ?>
    <section class="card">
      <div class="card-section-heading">
        <div>
          <div class="eyebrow">Form survey</div>
          <h2>Detail pengembalian</h2>
        </div>
        <span class="card-heading-icon" aria-hidden="true">↩</span>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id_order" value="<?= (int) $order['id_order'] ?>">

        <div class="form-group">
          <label for="transaction_id">ID transaksi</label>
          <input id="transaction_id" type="text" value="<?= e(transaction_code($order)) ?>" readonly>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="nomor_telepon">No. telepon aktif</label>
            <input id="nomor_telepon" name="nomor_telepon" value="<?= e($form['nomor_telepon']) ?>" required>
          </div>
          <div class="form-group">
            <label for="alasan">Alasan pengembalian</label>
            <select id="alasan" name="alasan" required>
              <option value="">Pilih alasan</option>
              <?php foreach ($reasons as $reason): ?>
                <option value="<?= e($reason) ?>" <?= $form['alasan'] === $reason ? 'selected' : '' ?>>
                  <?= e($reason) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="detail_masalah">Ceritakan kendala barang</label>
          <textarea
            id="detail_masalah"
            name="detail_masalah"
            minlength="10"
            maxlength="2000"
            required
          ><?= e($form['detail_masalah']) ?></textarea>
          <span class="helper">Minimal 10 karakter, jelaskan kondisi buku dan bagian yang bermasalah.</span>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="metode_pengembalian">Metode pengembalian dana</label>
            <select id="metode_pengembalian" name="metode_pengembalian" required>
              <?php foreach ($refundMethods as $refundMethod): ?>
                <option
                  value="<?= e($refundMethod) ?>"
                  <?= $form['metode_pengembalian'] === $refundMethod ? 'selected' : '' ?>
                >
                  <?= e($refundMethod) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="akun_pengembalian">Rekening / akun refund</label>
            <input
              id="akun_pengembalian"
              name="akun_pengembalian"
              value="<?= e($form['akun_pengembalian']) ?>"
              placeholder="Bank + nomor atau nama e-wallet"
            >
          </div>
        </div>

        <div class="form-group">
          <label for="bukti_foto">Bukti foto barang</label>
          <input id="bukti_foto" name="bukti_foto" type="file" accept="image/jpeg,image/png,image/webp" required>
          <span class="helper">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</span>
        </div>

        <button type="submit">Kirim Pengajuan Pengembalian</button>
      </form>
    </section>
  <?php else: ?>
    <section class="card empty">
      <span class="icon">✓</span>
      <h2>Survey sedang diproses</h2>
      <p>Anda belum dapat mengirim survey baru untuk status pesanan saat ini.</p>
      <a class="button" href="<?= url('pesanan.php') ?>">Lihat Status Pesanan</a>
    </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
