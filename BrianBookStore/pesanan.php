<?php
/** Riwayat pesanan customer beserta status, bukti, dan tautan struk. */
require_once __DIR__ . '/includes/functions.php';

require_user();

$stmt = $pdo->prepare(
    'SELECT *
     FROM orders
     WHERE id_user = ?
     ORDER BY tanggal_order DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
$page_title = 'Pesanan Saya';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Riwayat transaksi</div>
    <h1>Pesanan Saya</h1>
  </div>
</div>

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
          <th></th>
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
              <a class="button button-small" href="<?= url('order_success.php?id=' . $order['id_order']) ?>">
                Lihat Struk
              </a>
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
