<?php
/** Riwayat pesanan customer beserta status, bukti, dan tautan struk. */
require_once __DIR__ . '/includes/functions.php';

require_user();

// Memproses konfirmasi penerimaan atau pembatalan pesanan dari customer.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Menolak request tanpa token CSRF yang valid.
    verify_csrf();

    // Membaca target pesanan dan aksi yang dikirim melalui form.
    $orderId = (int) ($_POST['id_order'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    // Membatasi transisi agar customer tidak dapat melompati alur status admin.
    $transitions = [
        'accept' => [
            'from' => 'Dikirim',
            'to' => 'Diterima',
            'success' => 'Pesanan ditandai sudah diterima. Anda dapat menyelesaikan atau mengajukan pengembalian.',
        ],
        'finish' => [
            'from' => 'Diterima',
            'to' => 'Selesai',
            'success' => 'Pesanan berhasil diselesaikan.',
        ],
        'cancel' => [
            'from' => 'Menunggu Konfirmasi',
            'to' => 'Dibatalkan',
            'success' => 'Pesanan berhasil dibatalkan dan stok dikembalikan.',
        ],
        'complete_reprocess' => [
            'from' => 'Diproses Ulang',
            'to' => 'Selesai',
            'success' => 'Proses ulang dan pengembalian dana telah Anda konfirmasi selesai.',
        ],
    ];

    if ($orderId < 1 || !isset($transitions[$action])) {
        set_flash('danger', 'Aksi pesanan tidak valid.');
        redirect('pesanan.php');
    }

    $transition = $transitions[$action];

    try {
        // Mengunci pesanan sampai transaksi selesai agar stok tidak dikembalikan dua kali.
        $pdo->beginTransaction();
        $findOrder = $pdo->prepare(
            'SELECT id_order, status
             FROM orders
             WHERE id_order = ? AND id_user = ?
             FOR UPDATE'
        );
        $findOrder->execute([$orderId, $_SESSION['user_id']]);
        $order = $findOrder->fetch();

        if (!$order) {
            $pdo->rollBack();
            set_flash('warning', 'Pesanan tidak ditemukan.');
            redirect('pesanan.php');
        }

        if ($order['status'] !== $transition['from']) {
            $pdo->rollBack();
            set_flash(
                'warning',
                'Pesanan tidak dapat diubah karena statusnya sudah diperbarui.'
            );
            redirect('pesanan.php');
        }

        // Stok dikembalikan hanya saat customer membatalkan pesanan yang belum diproses.
        if ($action === 'cancel') {
            $details = $pdo->prepare(
                'SELECT id_buku, jumlah
                 FROM order_detail
                 WHERE id_order = ?'
            );
            $details->execute([$orderId]);
            $restoreStock = $pdo->prepare(
                'UPDATE buku
                 SET stok = stok + ?
                 WHERE id_buku = ?'
            );

            foreach ($details->fetchAll() as $detail) {
                $restoreStock->execute([
                    (int) $detail['jumlah'],
                    (int) $detail['id_buku'],
                ]);
            }
        }

        // Conditional update menjadi lapisan pengaman kedua setelah row lock.
        $updateOrder = $pdo->prepare(
            'UPDATE orders
             SET status = ?
             WHERE id_order = ? AND id_user = ? AND status = ?'
        );
        $updateOrder->execute([
            $transition['to'],
            $orderId,
            $_SESSION['user_id'],
            $transition['from'],
        ]);

        if ($updateOrder->rowCount() !== 1) {
            throw new RuntimeException('Status pesanan tidak berhasil diperbarui.');
        }

        if ($action === 'complete_reprocess') {
            // Menutup survey retur setelah user menerima proses ulang/refund.
            $completeReturn = $pdo->prepare(
                'UPDATE pengajuan_pengembalian
                 SET status = \'Selesai\',
                     updated_at = NOW()
                 WHERE id_order = ? AND id_user = ? AND status = \'Disetujui\''
            );
            $completeReturn->execute([$orderId, $_SESSION['user_id']]);

            if ($completeReturn->rowCount() !== 1) {
                throw new RuntimeException('Pengajuan pengembalian belum disetujui admin.');
            }
        }

        $pdo->commit();
        set_flash('success', $transition['success']);
    } catch (Throwable $exception) {
        // Membatalkan seluruh perubahan bila update status atau stok gagal.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        set_flash('danger', 'Pesanan belum dapat diperbarui. Silakan coba lagi.');
    }

    redirect('pesanan.php');
}

$stmt = $pdo->prepare(
    'SELECT *
     FROM orders
     WHERE id_user = ?
     ORDER BY tanggal_order DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
$statusCounts = array_count_values(array_column($orders, 'status'));
$page_title = 'Pesanan Saya';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Riwayat transaksi</div>
    <h1>Pesanan Saya</h1>
    <p class="section-lead">Pantau perjalanan pesanan, konfirmasi penerimaan, atau batalkan pesanan yang belum diproses.</p>
  </div>
</div>

<section class="order-overview-strip" aria-label="Ringkasan pesanan">
  <div class="order-overview-item">
    <span class="order-overview-icon">📦</span>
    <span>
      <b><?= count($orders) ?></b>
      <small>Total pesanan</small>
    </span>
  </div>
  <div class="order-overview-item">
    <span class="order-overview-icon">🚚</span>
    <span>
      <b><?= (int) ($statusCounts['Dikirim'] ?? 0) ?></b>
      <small>Siap diterima</small>
    </span>
  </div>
  <div class="order-overview-item">
    <span class="order-overview-icon">↩</span>
    <span>
      <b><?= (int) ($statusCounts['Diterima'] ?? 0) ?></b>
      <small>Perlu keputusan</small>
    </span>
  </div>
  <div class="order-overview-item">
    <span class="order-overview-icon">✓</span>
    <span>
      <b><?= (int) ($statusCounts['Selesai'] ?? 0) ?></b>
      <small>Transaksi selesai</small>
    </span>
  </div>
</section>

<section class="card table-wrap">
  <?php if ($orders): ?>
    <table class="data-table">
      <thead>
        <tr>
          <!-- Kolom ini menyimpan kode transaksi publik yang ditampilkan customer. -->
          <th>ID Transaksi</th>
          <th>Tanggal</th>
          <th>Metode</th>
          <th>Bukti</th>
          <th>Total</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <!-- Menampilkan kode transaksi ramah pengguna pada riwayat customer. -->
            <td><?= e(transaction_code($order)) ?></td>
            <td><?= date('d M Y, H:i', strtotime($order['tanggal_order'])) ?></td>
            <td><?= e($order['metode_pembayaran']) ?></td>
            <td>
              <?php if ($order['bukti_pembayaran']): ?>
                <a
                  class="button button-outline button-small"
                  href="<?= url('order_attachment.php?id=' . $order['id_order']) ?>"
                  target="_blank"
                  rel="noopener"
                >Lihat file</a>
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
              <div class="order-actions">
                <a class="button button-small" href="<?= url('order_success.php?id=' . $order['id_order']) ?>">
                  Lihat Struk
                </a>

                <?php if ($order['status'] === 'Dikirim'): ?>
                  <form method="post" class="order-action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_order" value="<?= (int) $order['id_order'] ?>">
                    <input type="hidden" name="action" value="accept">
                    <button
                      class="button button-success button-small"
                      type="submit"
                      data-confirm="Konfirmasi bahwa pesanan ini sudah Anda terima?"
                    >Terima</button>
                  </form>
                <?php elseif ($order['status'] === 'Diterima'): ?>
                  <form method="post" class="order-action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_order" value="<?= (int) $order['id_order'] ?>">
                    <input type="hidden" name="action" value="finish">
                    <button
                      class="button button-success button-small"
                      type="submit"
                      data-confirm="Tandai pesanan ini sebagai selesai tanpa pengembalian?"
                    >Selesaikan</button>
                  </form>
                  <a
                    class="button button-warning button-small"
                    href="<?= url('pengembalian.php?order_id=' . $order['id_order']) ?>"
                  >Ajukan Pengembalian</a>
                <?php elseif ($order['status'] === 'Menunggu Konfirmasi'): ?>
                  <form method="post" class="order-action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_order" value="<?= (int) $order['id_order'] ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button
                      class="button button-danger button-small"
                      type="submit"
                      data-confirm="Batalkan pesanan ini? Stok buku akan dikembalikan."
                    >Batalkan</button>
                  </form>
                <?php elseif ($order['status'] === 'Pengajuan Pengembalian'): ?>
                  <a
                    class="button button-outline button-small"
                    href="<?= url('pengembalian.php?order_id=' . $order['id_order']) ?>"
                  >Lihat Pengajuan</a>
                <?php elseif ($order['status'] === 'Diproses Ulang'): ?>
                  <form method="post" class="order-action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_order" value="<?= (int) $order['id_order'] ?>">
                    <input type="hidden" name="action" value="complete_reprocess">
                    <button
                      class="button button-success button-small"
                      type="submit"
                      data-confirm="Konfirmasi bahwa proses ulang dan pengembalian dana sudah selesai?"
                    >Proses Ulang Selesai</button>
                  </form>
                <?php else: ?>
                  <span class="helper order-action-note">Tidak ada aksi</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty">
      <span class="icon">🧾</span>
      <h2>Belum ada pesanan</h2>
      <p>Pesanan yang Anda buat akan muncul di sini.</p>
      <a class="button" href="<?= url('buku.php') ?>">Belanja Buku</a>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
