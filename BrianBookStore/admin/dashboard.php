<?php
require_once __DIR__ . '/../includes/functions.php'; require_admin();
$totals = [
    'Buku' => (int) $pdo->query('SELECT COUNT(*) FROM buku')->fetchColumn(),
    'Kategori' => (int) $pdo->query('SELECT COUNT(*) FROM kategori')->fetchColumn(),
    'User' => (int) $pdo->query('SELECT COUNT(*) FROM `user`')->fetchColumn(),
    'Pesanan' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
];
$orders = $pdo->query('SELECT o.*,u.nama FROM orders o JOIN `user` u ON u.id_user=o.id_user ORDER BY o.tanggal_order DESC LIMIT 5')->fetchAll();
$messages = $pdo->query('SELECT p.*,u.nama FROM pesan p JOIN `user` u ON u.id_user=p.id_user ORDER BY p.tanggal DESC LIMIT 5')->fetchAll();
$page_title = 'Dashboard'; require __DIR__ . '/../includes/admin_header.php';
?>
<section class="stat-grid"><?php $icons=['Buku'=>'📚','Kategori'=>'▤','User'=>'♙','Pesanan'=>'🧾']; foreach ($totals as $name => $count): ?><article class="stat-card"><span class="stat-icon"><?= $icons[$name] ?></span><p>Total <?= $name ?></p><b><?= number_format($count, 0, ',', '.') ?></b></article><?php endforeach; ?></section>
<section class="card" style="margin-top:25px"><div class="section-heading"><div><h2>Pesanan Terbaru</h2><p>Transaksi yang baru masuk ke toko.</p></div><a href="<?= url('admin/order.php') ?>">Lihat semua →</a></div><?php if ($orders): ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Invoice</th><th>Pembeli</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#BBS-<?= str_pad((string)$order['id_order'],5,'0',STR_PAD_LEFT) ?></td><td><?= e($order['nama']) ?></td><td><?= rupiah($order['total_harga']) ?></td><td><span class="badge badge-<?= order_status_class($order['status']) ?>"><?= e($order['status']) ?></span></td><td><a href="<?= url('admin/order.php?detail=' . $order['id_order']) ?>">Detail</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="helper">Belum ada pesanan.</p><?php endif; ?></section>
<section class="card" style="margin-top:25px"><div class="section-heading"><div><h2>Pesan Terbaru</h2><p>Pesan dari pelanggan melalui halaman Contact Admin.</p></div></div><?php if ($messages): ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Pengirim</th><th>Pesan</th><th>Tanggal</th></tr></thead><tbody><?php foreach ($messages as $message): ?><tr><td><?= e($message['nama']) ?></td><td><?= e($message['pesan']) ?></td><td><?= date('d M Y H:i', strtotime($message['tanggal'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="helper">Belum ada pesan dari pelanggan.</p><?php endif; ?></section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
