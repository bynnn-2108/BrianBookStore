<?php
require_once __DIR__ . '/includes/functions.php'; require_user(); $id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT o.*, u.nama,u.email,u.alamat,u.nomor_telepon FROM orders o JOIN `user` u ON u.id_user=o.id_user WHERE o.id_order=? AND o.id_user=?'); $stmt->execute([$id, $_SESSION['user_id']]); $order = $stmt->fetch();
if (!$order) { set_flash('danger', 'Pesanan tidak ditemukan.'); redirect('pesanan.php'); }
$detail = $pdo->prepare('SELECT od.*, b.judul_buku FROM order_detail od JOIN buku b ON b.id_buku=od.id_buku WHERE od.id_order=?'); $detail->execute([$id]); $details = $detail->fetchAll(); $buyer = $order; $page_title = 'Struk Pesanan'; require __DIR__ . '/includes/header.php';
?>
<section class="receipt-page"><div class="order-top"><div><div class="eyebrow">Pesanan berhasil dibuat</div><h1>Struk Pesanan</h1><p class="helper">Simpan atau cetak struk ini sebagai bukti transaksi Anda.</p></div><span class="badge badge-<?= order_status_class($order['status']) ?>"><?= e($order['status']) ?></span></div><?php if ($order['bukti_pembayaran']): ?><div class="alert alert-info">Bukti pembayaran: <a href="<?= url($order['bukti_pembayaran']) ?>" target="_blank">lihat file yang diunggah</a>.</div><?php endif; ?><?php require __DIR__ . '/includes/receipt.php'; ?><div class="receipt-actions"><button onclick="window.print()">🖨 Cetak Struk</button><a class="button button-outline" href="<?= url('pesanan.php') ?>">Semua Pesanan</a></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
