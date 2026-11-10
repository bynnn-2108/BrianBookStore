<?php
/** Halaman hasil checkout yang menampilkan detail pesanan dan struk. */
require_once __DIR__ . '/includes/functions.php';

require_user();

$orderId = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT o.*, u.nama, u.email, u.alamat, u.nomor_telepon
     FROM orders o
     JOIN `user` u ON u.id_user = o.id_user
     WHERE o.id_order = ? AND o.id_user = ?'
);
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}

$detail = $pdo->prepare(
    'SELECT od.*, b.judul_buku
     FROM order_detail od
     JOIN buku b ON b.id_buku = od.id_buku
     WHERE od.id_order = ?'
);
$detail->execute([$orderId]);
$details = $detail->fetchAll();

// Mengutamakan snapshot alamat jika kolom migrasi tersedia pada order lama/baru.
if (!empty($order['nama_penerima'])) {
    $order['nama'] = $order['nama_penerima'];
}

if (!empty($order['alamat_pengiriman'])) {
    $order['alamat'] = $order['alamat_pengiriman'];
}

if (!empty($order['telepon_pengiriman'])) {
    $order['nomor_telepon'] = $order['telepon_pengiriman'];
}

$buyer = $order;
$page_title = 'Struk Pesanan';

require __DIR__ . '/includes/header.php';
?>
<section class="receipt-page">
  <div class="order-top">
    <div>
      <div class="eyebrow">Pesanan berhasil dibuat</div>
      <h1>Struk Pesanan</h1>
      <p class="helper">Simpan atau cetak struk ini sebagai bukti transaksi Anda.</p>
    </div>
    <span class="badge badge-<?= order_status_class($order['status']) ?>">
      <?= e($order['status']) ?>
    </span>
  </div>

  <?php if ($order['bukti_pembayaran']): ?>
    <div class="alert alert-info">
      Bukti pembayaran telah dikirim dan menunggu verifikasi admin.
      <a href="<?= url('order_attachment.php?id=' . $order['id_order']) ?>" target="_blank" rel="noopener">
        Lihat file yang diunggah
      </a>.
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/includes/receipt.php'; ?>

  <div class="receipt-actions">
    <button type="button" onclick="window.print()">🖨 Cetak Struk</button>
    <a class="button button-outline" href="<?= url('pesanan.php') ?>">Semua Pesanan</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
