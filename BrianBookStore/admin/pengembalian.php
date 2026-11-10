<?php
/** Panel admin untuk memverifikasi survey retur dan memproses pengembalian dana. */
require_once __DIR__ . '/../includes/functions.php';

require_admin();

try {
    // Menghindari error query saat admin membuka panel sebelum migrasi retur dijalankan.
    $returnTableCheck = $pdo->query("SHOW TABLES LIKE 'pengajuan_pengembalian'");

    if (!$returnTableCheck->fetch()) {
        set_flash('warning', 'Panel retur belum aktif. Jalankan migrasi database retur terlebih dahulu.');
        redirect('admin/order.php');
    }
} catch (PDOException $exception) {
    set_flash('danger', 'Tabel pengajuan retur belum tersedia.');
    redirect('admin/order.php');
}

$returnStatuses = ['Diajukan', 'Disetujui', 'Ditolak', 'Selesai'];
$refundMethods = ['Transfer bank', 'E-wallet', 'Tunai saat pengambilan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $requestId = (int) ($_POST['id_pengajuan'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($requestId < 1 || !in_array($action, ['approve', 'reject'], true)) {
        set_flash('danger', 'Aksi pengajuan pengembalian tidak valid.');
        redirect('admin/pengembalian.php');
    }

    $amountInput = $_POST['jumlah_pengembalian'] ?? '';
    $refundAmount = is_scalar($amountInput) ? (float) $amountInput : 0;
    $methodInput = $_POST['metode_pengembalian'] ?? '';
    $refundMethod = is_scalar($methodInput) ? trim((string) $methodInput) : '';
    $noteInput = $_POST['catatan_admin'] ?? '';
    $adminNote = is_scalar($noteInput) ? trim((string) $noteInput) : '';

    try {
        // Mengunci survey agar dua admin tidak memproses request yang sama.
        $pdo->beginTransaction();
        $requestStmt = $pdo->prepare(
            'SELECT pr.*, o.status AS order_status, o.total_harga,
                    u.nama, u.email
             FROM pengajuan_pengembalian pr
             JOIN orders o ON o.id_order = pr.id_order
             JOIN `user` u ON u.id_user = pr.id_user
             WHERE pr.id_pengajuan = ?
             FOR UPDATE'
        );
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch();

        if (!$request) {
            throw new RuntimeException('Pengajuan pengembalian tidak ditemukan.');
        }

        // Kunci header order secara eksplisit sebelum mengubah statusnya.
        $orderLock = $pdo->prepare(
            'SELECT status
             FROM orders
             WHERE id_order = ?
             FOR UPDATE'
        );
        $orderLock->execute([$request['id_order']]);
        $lockedOrder = $orderLock->fetch();

        if (!$lockedOrder) {
            throw new RuntimeException('Pesanan terkait pengajuan tidak ditemukan.');
        }

        if ($action === 'approve') {
            if (
                $request['status'] !== 'Diajukan'
                || $lockedOrder['status'] !== 'Pengajuan Pengembalian'
            ) {
                throw new RuntimeException('Pengajuan ini sudah diproses atau status order tidak sesuai.');
            }

            if ($refundAmount <= 0 || $refundAmount > (float) $request['total_harga']) {
                throw new RuntimeException('Jumlah pengembalian harus lebih dari 0 dan tidak melebihi total order.');
            }

            if (!in_array($refundMethod, $refundMethods, true)) {
                throw new RuntimeException('Metode pengembalian dana tidak valid.');
            }

            // Persetujuan admin sekaligus mencatat bahwa refund masuk tahap diproses.
            $approveStmt = $pdo->prepare(
                'UPDATE pengajuan_pengembalian
                 SET status = \'Disetujui\',
                     jumlah_pengembalian = ?,
                     metode_pengembalian = ?,
                     catatan_admin = ?,
                     diverifikasi_pada = NOW(),
                     diproses_pada = NOW()
                 WHERE id_pengajuan = ? AND status = \'Diajukan\''
            );
            $approveStmt->execute([
                $refundAmount,
                $refundMethod,
                $adminNote ?: null,
                $requestId,
            ]);

            $orderUpdate = $pdo->prepare(
                'UPDATE orders
                 SET status = \'Diproses Ulang\'
                 WHERE id_order = ? AND status = \'Pengajuan Pengembalian\''
            );
            $orderUpdate->execute([$request['id_order']]);

            if ($approveStmt->rowCount() !== 1 || $orderUpdate->rowCount() !== 1) {
                throw new RuntimeException('Persetujuan refund tidak berhasil disimpan.');
            }

            $message = 'Pengajuan disetujui. Refund dicatat dan order masuk status Diproses Ulang.';
        } else {
            if (
                $request['status'] !== 'Diajukan'
                || $lockedOrder['status'] !== 'Pengajuan Pengembalian'
            ) {
                throw new RuntimeException('Pengajuan ini sudah diproses atau status order tidak sesuai.');
            }

            if (mb_strlen($adminNote) < 5) {
                throw new RuntimeException('Tuliskan alasan penolakan minimal 5 karakter.');
            }

            // Penolakan mengembalikan order ke Diterima agar user dapat memperbaiki survey.
            $rejectStmt = $pdo->prepare(
                'UPDATE pengajuan_pengembalian
                 SET status = \'Ditolak\',
                     catatan_admin = ?,
                     diverifikasi_pada = NOW()
                 WHERE id_pengajuan = ? AND status = \'Diajukan\''
            );
            $rejectStmt->execute([$adminNote, $requestId]);

            $orderUpdate = $pdo->prepare(
                'UPDATE orders
                 SET status = \'Diterima\'
                 WHERE id_order = ? AND status = \'Pengajuan Pengembalian\''
            );
            $orderUpdate->execute([$request['id_order']]);

            if ($rejectStmt->rowCount() !== 1 || $orderUpdate->rowCount() !== 1) {
                throw new RuntimeException('Penolakan pengajuan tidak berhasil disimpan.');
            }

            $message = 'Pengajuan ditolak dan user dapat memperbaiki survey.';
        }

        $pdo->commit();
        set_flash('success', $message);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        set_flash('danger', $exception->getMessage());
    }

    redirect('admin/pengembalian.php?detail=' . $requestId);
}

$detailId = (int) ($_GET['detail'] ?? 0);

if (!$detailId && !empty($_GET['order_id'])) {
    $orderId = (int) $_GET['order_id'];
    $detailLookup = $pdo->prepare(
        'SELECT id_pengajuan
         FROM pengajuan_pengembalian
         WHERE id_order = ?'
    );
    $detailLookup->execute([$orderId]);
    $detailId = (int) $detailLookup->fetchColumn();
}

if ($detailId) {
    $detailStmt = $pdo->prepare(
        'SELECT pr.*, o.status AS order_status, o.total_harga,
                o.tanggal_order,
                u.nama, u.email
         FROM pengajuan_pengembalian pr
         JOIN orders o ON o.id_order = pr.id_order
         JOIN `user` u ON u.id_user = pr.id_user
         WHERE pr.id_pengajuan = ?'
    );
    $detailStmt->execute([$detailId]);
    $request = $detailStmt->fetch();

    if (!$request) {
        set_flash('danger', 'Pengajuan pengembalian tidak ditemukan.');
        redirect('admin/pengembalian.php');
    }

    $page_title = 'Detail Pengajuan Pengembalian';
    require __DIR__ . '/../includes/admin_header.php';
    ?>
    <div class="admin-order-detail return-admin-detail">
      <section class="card">
        <div class="section-heading">
          <div>
            <p>
              ID Transaksi <?= e(transaction_code($request)) ?> ·
              <a href="<?= url('admin/order.php?detail=' . $request['id_order']) ?>">Lihat order</a>
            </p>
            <h2>Survey Pengembalian</h2>
          </div>
          <span class="badge badge-<?= return_status_class($request['status']) ?>">
            <?= e($request['status']) ?>
          </span>
        </div>

        <div class="book-meta">
          <span>Pembeli</span>
          <b><?= e($request['nama']) ?></b>
          <span>Email</span>
          <b><?= e($request['email']) ?></b>
          <span>Telepon survey</span>
          <b><?= e($request['nomor_telepon']) ?></b>
          <span>Status order</span>
          <b><?= e($request['order_status']) ?></b>
          <span>Total order</span>
          <b><?= rupiah($request['total_harga']) ?></b>
          <span>Alasan</span>
          <b><?= e($request['alasan']) ?></b>
          <span>Detail masalah</span>
          <b><?= nl2br(e($request['detail_masalah'])) ?></b>
          <span>Metode refund</span>
          <b><?= e($request['metode_pengembalian']) ?></b>
          <span>Akun refund</span>
          <b><?= e($request['akun_pengembalian'] ?: '—') ?></b>
        </div>

        <div class="return-proof-panel">
          <div>
            <b>Bukti foto barang</b>
            <p class="helper">Periksa kondisi buku sebelum memutuskan pengajuan.</p>
          </div>
          <a
            class="button button-outline button-small"
            href="<?= url('return_attachment.php?id=' . $request['id_pengajuan']) ?>"
            target="_blank"
            rel="noopener"
          >Buka bukti foto</a>
        </div>

        <?php if ($request['catatan_admin']): ?>
          <div class="alert alert-info">
            <b>Catatan admin:</b><br>
            <?= nl2br(e($request['catatan_admin'])) ?>
          </div>
        <?php endif; ?>

        <?php if ($request['status'] === 'Diajukan'): ?>
          <div class="return-decision-grid">
            <form method="post" class="card return-decision-card">
              <?= csrf_field() ?>
              <input type="hidden" name="id_pengajuan" value="<?= (int) $request['id_pengajuan'] ?>">
              <input type="hidden" name="action" value="approve">
              <h3>Setujui & proses refund</h3>
              <div class="form-group">
                <label for="jumlah_pengembalian">Jumlah refund</label>
                <input
                  id="jumlah_pengembalian"
                  name="jumlah_pengembalian"
                  type="number"
                  min="1"
                  max="<?= e($request['total_harga']) ?>"
                  step="0.01"
                  value="<?= e($request['total_harga']) ?>"
                  required
                >
              </div>
              <div class="form-group">
                <label for="metode_pengembalian">Metode refund</label>
                <select id="metode_pengembalian" name="metode_pengembalian" required>
                  <?php foreach ($refundMethods as $refundMethod): ?>
                    <option
                      value="<?= e($refundMethod) ?>"
                      <?= $request['metode_pengembalian'] === $refundMethod ? 'selected' : '' ?>
                    >
                      <?= e($refundMethod) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="approve_note">Catatan admin</label>
                <textarea
                  id="approve_note"
                  name="catatan_admin"
                  placeholder="Catatan proses refund (opsional)"
                ></textarea>
              </div>
              <button class="button-success" type="submit" data-confirm="Setujui survey dan proses pengembalian dana?">
                Setujui & Proses Refund
              </button>
            </form>

            <form method="post" class="card return-decision-card return-reject-card">
              <?= csrf_field() ?>
              <input type="hidden" name="id_pengajuan" value="<?= (int) $request['id_pengajuan'] ?>">
              <input type="hidden" name="action" value="reject">
              <h3>Tolak survey</h3>
              <div class="form-group">
                <label for="reject_note">Alasan penolakan</label>
                <textarea
                  id="reject_note"
                  name="catatan_admin"
                  minlength="5"
                  required
                  placeholder="Jelaskan data atau bukti yang perlu diperbaiki."
                ></textarea>
              </div>
              <button class="button-danger" type="submit" data-confirm="Tolak pengajuan pengembalian ini?">
                Tolak Pengajuan
              </button>
            </form>
          </div>
        <?php elseif ($request['status'] === 'Disetujui'): ?>
          <div class="alert alert-success">
            Refund <?= rupiah($request['jumlah_pengembalian']) ?> dicatat sebagai diproses melalui
            <?= e($request['metode_pengembalian']) ?>. User akan mengonfirmasi setelah proses ulang selesai.
          </div>
        <?php endif; ?>

        <a class="button button-outline" href="<?= url('admin/pengembalian.php') ?>">Kembali ke daftar</a>
      </section>

      <section class="card">
        <div class="eyebrow">Alur status</div>
        <h2>Catatan verifikasi</h2>
        <ol class="return-timeline">
          <li class="is-current">User mengirim survey dan bukti foto.</li>
          <li>Admin memverifikasi kondisi dan menyetujui atau menolak.</li>
          <li>Persetujuan mengubah order menjadi Diproses Ulang.</li>
          <li>User menekan Proses Ulang Selesai setelah refund diterima.</li>
        </ol>
      </section>
    </div>
    <?php
    require __DIR__ . '/../includes/admin_footer.php';
    exit;
}

$statusFilter = trim($_GET['status'] ?? '');
$params = [];
$where = '';

if (in_array($statusFilter, $returnStatuses, true)) {
    $where = ' WHERE pr.status = ?';
    $params[] = $statusFilter;
}

$listStmt = $pdo->prepare(
    'SELECT pr.*, o.status AS order_status, o.total_harga,
            o.tanggal_order, u.nama, u.email
     FROM pengajuan_pengembalian pr
     JOIN orders o ON o.id_order = pr.id_order
     JOIN `user` u ON u.id_user = pr.id_user'
    . $where
    . ' ORDER BY pr.diajukan_pada DESC'
);
$listStmt->execute($params);
$requests = $listStmt->fetchAll();

$page_title = 'Pengajuan Pengembalian';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar">
  <p class="helper">Verifikasi survey retur, bukti foto, dan proses pengembalian dana customer.</p>
  <form class="search-inline" method="get">
    <select name="status" data-auto-submit>
      <option value="">Semua status</option>
      <?php foreach ($returnStatuses as $status): ?>
        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
          <?= e($status) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<section class="card table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>ID Transaksi</th>
        <th>Pembeli</th>
        <th>Alasan</th>
        <th>Total</th>
        <th>Status Survey</th>
        <th>Status Order</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($requests): ?>
        <?php foreach ($requests as $request): ?>
          <tr>
            <td><?= e(transaction_code($request)) ?></td>
            <td>
              <b><?= e($request['nama']) ?></b><br>
              <span class="helper"><?= e($request['email']) ?></span>
            </td>
            <td><?= e($request['alasan']) ?></td>
            <td><?= rupiah($request['total_harga']) ?></td>
            <td>
              <span class="badge badge-<?= return_status_class($request['status']) ?>">
                <?= e($request['status']) ?>
              </span>
            </td>
            <td>
              <span class="badge badge-<?= order_status_class($request['order_status']) ?>">
                <?= e($request['order_status']) ?>
              </span>
            </td>
            <td>
              <a
                class="button button-small"
                href="<?= url('admin/pengembalian.php?detail=' . $request['id_pengajuan']) ?>"
              >Tinjau Survey</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="helper">Belum ada pengajuan pengembalian.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
