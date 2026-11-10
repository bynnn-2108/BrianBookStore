<?php
/** Manajemen pesanan admin: detail buyer, lampiran, dan perubahan status. */
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$allowedStatuses = [
    'Menunggu Konfirmasi',
    'Diproses',
    'Dikirim',
    'Selesai',
    'Dibatalkan',
];

// Membatasi penghapusan hanya untuk pesanan yang sudah selesai atau dibatalkan.
$deletableStatuses = ['Selesai', 'Dibatalkan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $orderId = (int) ($_POST['id_order'] ?? 0);
    $action = $_POST['action'] ?? 'update';
    $status = $_POST['status'] ?? '';

    // Memproses penghapusan melalui endpoint yang sama agar tetap dilindungi CSRF.
    if ($action === 'delete') {
        $attachmentPath = null;

        try {
            // Mengunci pesanan selama validasi agar status tidak berubah saat dihapus.
            $pdo->beginTransaction();
            $findStmt = $pdo->prepare(
                'SELECT status, bukti_pembayaran
                 FROM orders
                 WHERE id_order = ?
                 FOR UPDATE'
            );
            $findStmt->execute([$orderId]);
            $targetOrder = $findStmt->fetch();

            if (!$targetOrder) {
                $pdo->rollBack();
                set_flash('warning', 'Pesanan tidak ditemukan.');
                redirect('admin/order.php');
            }

            // Validasi ulang status di server supaya tombol tidak dapat disalahgunakan.
            if (!in_array($targetOrder['status'], $deletableStatuses, true)) {
                $pdo->rollBack();
                set_flash(
                    'danger',
                    'Pesanan hanya dapat dihapus jika statusnya Selesai atau Dibatalkan.'
                );
                redirect('admin/order.php?detail=' . $orderId);
            }

            // Menyimpan path lampiran agar file ikut dibersihkan setelah transaksi database berhasil.
            $attachmentPath = $targetOrder['bukti_pembayaran'];
            $deleteStmt = $pdo->prepare('DELETE FROM orders WHERE id_order = ?');
            $deleteStmt->execute([$orderId]);

            // Menyelesaikan penghapusan database sebelum menghapus file fisik lampiran.
            $pdo->commit();
            delete_upload($attachmentPath);
            set_flash('success', 'Pesanan berhasil dihapus.');
        } catch (Throwable $exception) {
            // Membatalkan transaksi jika terjadi kegagalan agar data pesanan tetap aman.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            set_flash('danger', 'Pesanan gagal dihapus. Silakan coba lagi.');
        }

        redirect('admin/order.php');
    }

    // Memvalidasi status baru sebelum menyimpan perubahan pesanan.
    if (!in_array($status, $allowedStatuses, true)) {
        set_flash('danger', 'Status pesanan tidak valid.');
    } else {
        $stmt = $pdo->prepare(
            'UPDATE orders
             SET status = ?
             WHERE id_order = ?'
        );
        $stmt->execute([$status, $orderId]);

        if ($stmt->rowCount()) {
            set_flash('success', 'Status pesanan diperbarui.');
        } else {
            set_flash('warning', 'Pesanan tidak ditemukan atau status tidak berubah.');
        }
    }

    redirect('admin/order.php?detail=' . $orderId);
}

$detailId = (int) ($_GET['detail'] ?? 0);

if ($detailId) {
    $stmt = $pdo->prepare(
        'SELECT o.*, u.nama, u.email, u.alamat, u.nomor_telepon
         FROM orders o
         JOIN `user` u ON u.id_user = o.id_user
         WHERE o.id_order = ?'
    );
    $stmt->execute([$detailId]);
    $order = $stmt->fetch();

    if (!$order) {
        set_flash('danger', 'Pesanan tidak ditemukan.');
        redirect('admin/order.php');
    }

    $detailStmt = $pdo->prepare(
        'SELECT od.*, b.judul_buku
         FROM order_detail od
         JOIN buku b ON b.id_buku = od.id_buku
         WHERE od.id_order = ?'
    );
    $detailStmt->execute([$detailId]);
    $details = $detailStmt->fetchAll();
    $buyer = $order;
    $attachmentUrl = url('admin/order_attachment.php?id=' . $order['id_order']);
    $isPdf = str_ends_with((string) $order['bukti_pembayaran'], '.pdf');
    $page_title = 'Detail Pesanan';

    require __DIR__ . '/../includes/admin_header.php';
    ?>
    <div class="admin-order-detail">
      <section class="card">
        <div class="section-heading">
          <div>
            <!-- Menampilkan kode transaksi publik pada halaman detail admin. -->
            <p>ID Transaksi <?= e(transaction_code($order)) ?></p>
            <h2>Informasi Pesanan</h2>
          </div>
          <span class="badge badge-<?= order_status_class($order['status']) ?>">
            <?= e($order['status']) ?>
          </span>
        </div>

        <div class="book-meta">
          <span>Pembeli</span>
          <b><?= e($order['nama']) ?></b>
          <span>Email</span>
          <b><?= e($order['email']) ?></b>
          <span>Telepon</span>
          <b><?= e($order['nomor_telepon']) ?></b>
          <span>Alamat</span>
          <b><?= nl2br(e($order['alamat'])) ?></b>
          <span>Pembayaran</span>
          <b><?= e($order['metode_pembayaran']) ?></b>
        </div>

        <div class="form-group">
          <label>Lampiran checkout</label>

          <?php if ($order['bukti_pembayaran']): ?>
            <div class="payment-attachment">
              <?php if ($isPdf): ?>
                <span class="attachment-file-icon" aria-hidden="true">PDF</span>
                <div>
                  <b>Bukti pembayaran (PDF)</b>
                  <p class="helper">Klik untuk membuka file yang diunggah pembeli.</p>
                  <a
                    class="button button-outline button-small"
                    href="<?= $attachmentUrl ?>"
                    target="_blank"
                    rel="noopener"
                  >Buka PDF</a>
                </div>
              <?php else: ?>
                <a href="<?= $attachmentUrl ?>" target="_blank" rel="noopener">
                  <img
                    class="image-preview"
                    src="<?= $attachmentUrl ?>"
                    alt="Bukti pembayaran dari <?= e($order['nama']) ?>"
                  >
                </a>
                <div>
                  <b>Bukti pembayaran (gambar)</b>
                  <p class="helper">Klik gambar untuk melihat ukuran penuh.</p>
                </div>
              <?php endif; ?>
            </div>
          <?php elseif ($order['metode_pembayaran'] === 'TRANSFER'): ?>
            <p class="helper">Bukti pembayaran belum diunggah oleh pembeli.</p>
          <?php else: ?>
            <p class="helper">Pesanan COD tidak memerlukan bukti pembayaran.</p>
          <?php endif; ?>
        </div>

        <form method="post">
          <?= csrf_field() ?>
          <!-- Menandai form ini sebagai aksi perubahan status pesanan. -->
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id_order" value="<?= $order['id_order'] ?>">

          <div class="form-group">
            <label for="status">Status pesanan</label>
            <select id="status" name="status">
              <?php foreach ($allowedStatuses as $status): ?>
                <option <?= $status === $order['status'] ? 'selected' : '' ?>>
                  <?= e($status) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit">Simpan Status</button>
          <a class="button button-outline" href="<?= url('admin/order.php') ?>">Kembali</a>
        </form>

        <?php if (in_array($order['status'], $deletableStatuses, true)): ?>
          <!-- Tombol hapus hanya ditampilkan untuk status yang memang boleh dihapus. -->
          <form
            class="row-actions"
            method="post"
            onsubmit="return confirm('Hapus pesanan ini secara permanen? Data tidak dapat dipulihkan.');"
          >
            <?= csrf_field() ?>
            <!-- Aksi delete tetap diverifikasi ulang di server berdasarkan status terbaru. -->
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_order" value="<?= $order['id_order'] ?>">
            <button class="button-danger button-small" type="submit">Hapus Pesanan</button>
          </form>
        <?php endif; ?>
      </section>

      <section>
        <?php require __DIR__ . '/../includes/receipt.php'; ?>

        <div class="receipt-actions">
          <button type="button" onclick="window.print()">🖨 Cetak Struk</button>
        </div>
      </section>
    </div>
    <?php
    require __DIR__ . '/../includes/admin_footer.php';
    exit;
}

$statusFilter = trim($_GET['status'] ?? '');
$params = [];
$where = '';

if (in_array($statusFilter, $allowedStatuses, true)) {
    $where = ' WHERE o.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare(
    'SELECT o.*, u.nama, u.email
     FROM orders o
     JOIN `user` u ON u.id_user = o.id_user'
    . $where
    . ' ORDER BY o.tanggal_order DESC'
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$page_title = 'Manajemen Pesanan';

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar">
  <p class="helper">Tinjau detail pesanan, lampiran checkout, dan ubah status proses.</p>

  <form class="search-inline" method="get">
    <select name="status" data-auto-submit>
      <option value="">Semua status</option>
      <?php foreach ($allowedStatuses as $status): ?>
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
        <!-- Kolom ini menampilkan kode transaksi publik pada daftar admin. -->
        <th>ID Transaksi</th>
        <th>Pembeli</th>
        <th>Tanggal</th>
        <th>Metode</th>
        <th>Lampiran</th>
        <th>Total</th>
        <th>Status</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($orders): ?>
        <?php foreach ($orders as $order): ?>
          <?php
          $attachmentUrl = url('admin/order_attachment.php?id=' . $order['id_order']);
          $isPdf = str_ends_with((string) $order['bukti_pembayaran'], '.pdf');
          // Membuat kode transaksi sekali agar dapat dipakai pada tabel dan alt gambar.
          $transactionNumber = transaction_code($order);
          ?>
          <tr>
            <!-- Menampilkan kode transaksi publik pada daftar pesanan admin. -->
            <td><?= e($transactionNumber) ?></td>
            <td>
              <b><?= e($order['nama']) ?></b><br>
              <span class="helper"><?= e($order['email']) ?></span>
            </td>
            <td><?= date('d M Y H:i', strtotime($order['tanggal_order'])) ?></td>
            <td><?= e($order['metode_pembayaran']) ?></td>
            <td>
              <?php if ($order['bukti_pembayaran']): ?>
                <?php if ($isPdf): ?>
                  <a
                    class="button button-outline button-small"
                    href="<?= $attachmentUrl ?>"
                    target="_blank"
                    rel="noopener"
                  >Buka PDF</a>
                <?php else: ?>
                  <a
                    class="attachment-thumbnail"
                    href="<?= $attachmentUrl ?>"
                    target="_blank"
                    rel="noopener"
                  >
                    <img
                      src="<?= $attachmentUrl ?>"
                      alt="Lihat bukti pembayaran <?= e($transactionNumber) ?>"
                    >
                    <span>Lihat gambar</span>
                  </a>
                <?php endif; ?>
              <?php else: ?>
                <span class="helper">—</span>
              <?php endif; ?>
            </td>
            <td><?= rupiah($order['total_harga']) ?></td>
            <td>
              <span class="badge badge-<?= order_status_class($order['status']) ?>">
                <?= e($order['status']) ?>
              </span>
            </td>
            <td>
              <div class="row-actions">
                <a
                  class="button button-small"
                  href="<?= url('admin/order.php?detail=' . $order['id_order']) ?>"
                >Detail</a>

                <?php if (in_array($order['status'], $deletableStatuses, true)): ?>
                  <!-- Tombol delete tidak muncul untuk Menunggu Konfirmasi, Diproses, atau Dikirim. -->
                  <form
                    method="post"
                    onsubmit="return confirm('Hapus pesanan ini secara permanen? Data tidak dapat dipulihkan.');"
                  >
                    <?= csrf_field() ?>
                    <!-- Mengirim aksi delete dan ID pesanan yang dipilih ke handler POST. -->
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_order" value="<?= $order['id_order'] ?>">
                    <button class="button-danger button-small" type="submit">Hapus</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" class="helper">Belum ada pesanan.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
