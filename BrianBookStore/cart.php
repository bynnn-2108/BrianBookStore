<?php
/** Halaman keranjang: memperbarui jumlah, menghapus item, dan menghitung total. */
require_once __DIR__ . '/includes/functions.php';

require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $cartId = (int) ($_POST['id_cart'] ?? 0);
    $userId = (int) $_SESSION['user_id'];

    if ($action === 'update') {
        $quantity = max(1, (int) ($_POST['jumlah'] ?? 1));

        $stmt = $pdo->prepare(
            'SELECT c.id_cart, b.stok
             FROM cart c
             JOIN buku b ON b.id_buku = c.id_buku
             WHERE c.id_cart = ? AND c.id_user = ?'
        );
        $stmt->execute([$cartId, $userId]);
        $item = $stmt->fetch();

        if (!$item) {
            set_flash('danger', 'Item keranjang tidak ditemukan.');
        } elseif ($item['stok'] < 1) {
            $delete = $pdo->prepare(
                'DELETE FROM cart
                 WHERE id_cart = ? AND id_user = ?'
            );
            $delete->execute([$cartId, $userId]);
            set_flash('warning', 'Item dihapus karena stok telah habis.');
        } else {
            $quantity = min($quantity, (int) $item['stok']);
            $update = $pdo->prepare(
                'UPDATE cart
                 SET jumlah = ?
                 WHERE id_cart = ? AND id_user = ?'
            );
            $update->execute([$quantity, $cartId, $userId]);
            set_flash('success', 'Keranjang diperbarui.');
        }
    } elseif ($action === 'delete') {
        $delete = $pdo->prepare(
            'DELETE FROM cart
             WHERE id_cart = ? AND id_user = ?'
        );
        $delete->execute([$cartId, $userId]);
        set_flash('success', 'Buku dihapus dari keranjang.');
    }

    redirect('cart.php');
}

$stmt = $pdo->prepare(
    'SELECT c.*, b.judul_buku, b.harga, b.stok, b.gambar
     FROM cart c
     JOIN buku b ON b.id_buku = c.id_buku
     WHERE c.id_user = ?
     ORDER BY c.id_cart DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

$total = array_sum(
    array_map(
        fn(array $item) => $item['harga'] * $item['jumlah'],
        $items
    )
);

$page_title = 'Keranjang Belanja';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Belanja Anda</div>
    <h1>Keranjang Belanja</h1>
  </div>
  <a href="<?= url('buku.php') ?>">← Lanjut belanja</a>
</div>

<?php if (!$items): ?>
  <div class="card empty">
    <span class="icon">🛒</span>
    <h2>Keranjang masih kosong</h2>
    <p>Yuk, temukan buku yang ingin Anda baca.</p>
    <a class="button" href="<?= url('buku.php') ?>">Jelajahi Buku</a>
  </div>
<?php else: ?>
  <div class="cart-layout">
    <section class="card">
      <?php foreach ($items as $item): ?>
        <div class="cart-item">
          <div class="cart-thumb">
            <?php if ($item['gambar']): ?>
              <img
                src="<?= url($item['gambar']) ?>"
                alt=""
                style="width:100%;height:100%;object-fit:cover;border-radius:6px"
              >
            <?php else: ?>
              B
            <?php endif; ?>
          </div>

          <div>
            <b><?= e($item['judul_buku']) ?></b>
            <div class="stock">Stok tersedia: <?= $item['stok'] ?></div>
            <span class="cart-price price"><?= rupiah($item['harga']) ?></span>
          </div>

          <form method="post" style="display:flex;gap:5px;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_cart" value="<?= $item['id_cart'] ?>">
            <input
              class="qty-control"
              type="number"
              min="1"
              max="<?= $item['stok'] ?>"
              name="jumlah"
              value="<?= $item['jumlah'] ?>"
              data-auto-submit
            >
          </form>

          <b><?= rupiah($item['harga'] * $item['jumlah']) ?></b>

          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_cart" value="<?= $item['id_cart'] ?>">
            <button
              class="button-danger button-small"
              type="submit"
              data-confirm="Hapus buku ini dari keranjang?"
            >×</button>
          </form>
        </div>
      <?php endforeach; ?>
    </section>

    <aside class="card summary">
      <h3>Ringkasan Belanja</h3>
      <div class="summary-line">
        <span>Total item</span>
        <span><?= array_sum(array_column($items, 'jumlah')) ?> buku</span>
      </div>
      <div class="summary-line summary-total">
        <b>Total</b>
        <b class="price"><?= rupiah($total) ?></b>
      </div>
      <a class="button button-block" style="margin-top:20px" href="<?= url('checkout.php') ?>">
        Lanjut ke Checkout
      </a>
    </aside>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
