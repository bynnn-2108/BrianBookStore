<?php
require_once __DIR__ . '/includes/functions.php'; require_user();
function cart_items_for_checkout(PDO $pdo, int $userId): array { $stmt = $pdo->prepare('SELECT c.id_cart,c.id_buku,c.jumlah,b.judul_buku,b.harga,b.stok FROM cart c JOIN buku b ON b.id_buku=c.id_buku WHERE c.id_user=? ORDER BY c.id_cart'); $stmt->execute([$userId]); return $stmt->fetchAll(); }
$items = cart_items_for_checkout($pdo, (int) $_SESSION['user_id']);
if (!$items) { set_flash('warning', 'Keranjang Anda masih kosong.'); redirect('cart.php'); }
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $method = $_POST['metode_pembayaran'] ?? 'COD'; $proof = null;
    if (!in_array($method, ['COD', 'TRANSFER'], true)) $error = 'Metode pembayaran tidak valid.';
    if (!isset($error) && $method === 'TRANSFER') {
        if (($_FILES['bukti_pembayaran']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) $error = 'Bukti pembayaran wajib diunggah untuk transfer.';
        else { [$ok, $result] = upload_file($_FILES['bukti_pembayaran'], 'uploads/payment', ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf']); if (!$ok) $error = $result; else $proof = $result; }
    }
    if (!isset($error)) {
        try {
            $pdo->beginTransaction(); $items = cart_items_for_checkout($pdo, (int) $_SESSION['user_id']);
            if (!$items) throw new RuntimeException('Keranjang Anda sudah kosong.'); $total = 0;
            foreach ($items as $item) { $lock = $pdo->prepare('SELECT stok, harga FROM buku WHERE id_buku=? FOR UPDATE'); $lock->execute([$item['id_buku']]); $book = $lock->fetch(); if (!$book || $book['stok'] < $item['jumlah']) throw new RuntimeException('Stok untuk "' . $item['judul_buku'] . '" tidak mencukupi.'); $total += $book['harga'] * $item['jumlah']; }
            $order = $pdo->prepare('INSERT INTO orders (id_user,total_harga,metode_pembayaran,bukti_pembayaran,status) VALUES (?,?,?,?,?)'); $order->execute([$_SESSION['user_id'], $total, $method, $proof, 'Menunggu Konfirmasi']); $orderId = (int) $pdo->lastInsertId();
            foreach ($items as $item) { $sub = $item['harga'] * $item['jumlah']; $detail = $pdo->prepare('INSERT INTO order_detail (id_order,id_buku,jumlah,subtotal) VALUES (?,?,?,?)'); $detail->execute([$orderId, $item['id_buku'], $item['jumlah'], $sub]); $stock = $pdo->prepare('UPDATE buku SET stok=stok-? WHERE id_buku=?'); $stock->execute([$item['jumlah'], $item['id_buku']]); }
            $clear = $pdo->prepare('DELETE FROM cart WHERE id_user=?'); $clear->execute([$_SESSION['user_id']]); $pdo->commit(); redirect('order_success.php?id=' . $orderId);
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); if ($proof) delete_upload($proof); $error = $exception->getMessage(); }
    }
}
$total = array_sum(array_map(fn($item) => $item['harga'] * $item['jumlah'], $items)); $page_title = 'Checkout'; require __DIR__ . '/includes/header.php';
?>
<div class="section-heading"><div><div class="eyebrow">Satu langkah lagi</div><h1>Checkout</h1></div><a href="<?= url('cart.php') ?>">← Kembali ke keranjang</a></div><?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><div class="cart-layout"><section class="card"><h2>Data Pengiriman</h2><div class="form-group"><label>Nama penerima</label><input disabled value="<?= e($user['nama']) ?>"></div><div class="form-group"><label>Alamat</label><textarea disabled><?= e($user['alamat']) ?></textarea></div><div class="form-row"><div class="form-group"><label>Email</label><input disabled value="<?= e($user['email']) ?>"></div><div class="form-group"><label>Nomor telepon</label><input disabled value="<?= e($user['nomor_telepon']) ?>"></div></div><p class="helper">Data pengiriman diambil dari akun Anda.</p><hr class="receipt-rule"><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><h2>Metode Pembayaran</h2><div class="payment-options"><label class="payment-option"><input type="radio" name="metode_pembayaran" value="COD" checked> <b>COD</b><br><span class="helper">Bayar saat barang diterima</span></label><label class="payment-option"><input type="radio" name="metode_pembayaran" value="TRANSFER"> <b>Transfer</b><br><span class="helper">Unggah bukti pembayaran</span></label></div><div class="form-group" style="margin-top:16px"><label for="bukti_pembayaran">Bukti pembayaran (wajib untuk transfer)</label><input id="bukti_pembayaran" type="file" name="bukti_pembayaran" accept="image/jpeg,image/png,application/pdf"><div class="helper">JPG, PNG, atau PDF maksimal 2 MB.</div></div><button type="submit">Buat Pesanan</button></form></section><aside class="card summary"><h3>Pesanan Anda</h3><?php foreach ($items as $item): ?><div class="summary-line"><span><?= e($item['judul_buku']) ?> × <?= $item['jumlah'] ?></span><span><?= rupiah($item['harga'] * $item['jumlah']) ?></span></div><?php endforeach; ?><div class="summary-line summary-total"><b>Total Pembayaran</b><b class="price"><?= rupiah($total) ?></b></div></aside></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
