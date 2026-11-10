<?php
/** Checkout: mengunci stok, menerima COD/transfer, dan membuat pesanan atomik. */
require_once __DIR__ . '/includes/functions.php';

require_user();

// Mengambil item keranjang untuk dihitung dan dikunci ulang saat checkout.
function cart_items_for_checkout(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.id_cart, c.id_buku, c.jumlah, b.judul_buku, b.harga, b.stok
         FROM cart c
         JOIN buku b ON b.id_buku = c.id_buku
         WHERE c.id_user = ?
         ORDER BY c.id_cart'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

$userId = (int) $_SESSION['user_id'];
$items = cart_items_for_checkout($pdo, $userId);

if (!$items) {
    set_flash('warning', 'Keranjang Anda masih kosong.');
    redirect('cart.php');
}

$user = current_user();
$method = 'COD';

// Menyimpan pilihan nominal tunai agar input tetap tampil ketika validasi checkout gagal.
$cashPaidInput = '';
// Menyimpan nominal uang yang dibayar setelah lolos validasi server.
$cashPaid = 0;
// Menyimpan nominal kembalian hasil pengurangan uang dibayar dan total pesanan.
$change = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $method = $_POST['metode_pembayaran'] ?? 'COD';
    // Mengambil input uang secara aman tanpa menganggap data POST selalu berupa string.
    $cashInputValue = $_POST['uang_dibayar'] ?? '';
    $cashPaidInput = is_scalar($cashInputValue) ? trim((string) $cashInputValue) : '';
    $proof = null;

    if (!in_array($method, ['COD', 'TRANSFER'], true)) {
        $error = 'Metode pembayaran tidak valid.';
    }

    if (!isset($error) && $method === 'TRANSFER') {
        $proofFile = $_FILES['bukti_pembayaran'] ?? [];
        [$uploaded, $result] = upload_file(
            $proofFile,
            'uploads/payment',
            [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
            ]
        );

        if (!$uploaded || !$result) {
            $error = $result ?: 'Bukti pembayaran wajib diunggah untuk transfer.';
        } else {
            $proof = $result;
        }
    }

    if (!isset($error)) {
        try {
            $pdo->beginTransaction();
            $items = cart_items_for_checkout($pdo, $userId);

            if (!$items) {
                throw new RuntimeException('Keranjang Anda sudah kosong.');
            }

            // Menyimpan harga yang sudah dikunci agar detail pesanan konsisten dengan total.
            $lockedItems = [];
            // Mengawali total dari nol sebelum seluruh item dihitung ulang di dalam transaksi.
            $total = 0;

            foreach ($items as $item) {
                $lock = $pdo->prepare(
                    'SELECT stok, harga
                     FROM buku
                     WHERE id_buku = ?
                     FOR UPDATE'
                );
                $lock->execute([$item['id_buku']]);
                $book = $lock->fetch();

                if (!$book || $book['stok'] < $item['jumlah']) {
                    throw new RuntimeException(
                        'Stok untuk "' . $item['judul_buku'] . '" tidak mencukupi.'
                    );
                }

                // Menggunakan harga terbaru yang ikut terkunci oleh transaksi database.
                $lockedPrice = (float) $book['harga'];
                // Menambahkan subtotal item ke total checkout yang menjadi sumber kembalian.
                $total += $lockedPrice * (int) $item['jumlah'];
                // Menyimpan item beserta harga terkunci untuk insert detail pesanan.
                $lockedItems[] = [
                    'item' => $item,
                    'harga' => $lockedPrice,
                ];
            }

            // COD wajib memiliki uang dibayar yang cukup untuk menghasilkan kembalian valid.
            if ($method === 'COD') {
                // Menolak input kosong atau non-numerik agar perhitungan tidak ambigu.
                if ($cashPaidInput === '' || !is_numeric($cashPaidInput)) {
                    throw new RuntimeException('Masukkan uang yang dibayarkan pembeli.');
                }

                // Membulatkan nominal ke dua angka desimal sesuai tipe DECIMAL database.
                $cashPaid = round((float) $cashPaidInput, 2);

                // Menolak nominal negatif, tak hingga, atau lebih kecil dari total pesanan.
                if (!is_finite($cashPaid) || $cashPaid < $total) {
                    throw new RuntimeException(
                        'Uang dibayar harus lebih besar atau sama dengan total pesanan.'
                    );
                }

                // Menghitung kembalian dan membulatkannya agar aman disimpan ke database.
                $change = round($cashPaid - $total, 2);
            } else {
                // Transfer tidak menerima uang tunai sehingga nilainya disimpan sebagai nol.
                $cashPaid = 0;
                // Transfer tidak memiliki kembalian sehingga nilainya juga disimpan sebagai nol.
                $change = 0;
            }

            $order = $pdo->prepare(
                'INSERT INTO orders (
                    id_user,
                    total_harga,
                    metode_pembayaran,
                    uang_dibayar,
                    uang_kembalian,
                    bukti_pembayaran,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $order->execute([
                $userId,
                $total,
                $method,
                $cashPaid,
                $change,
                $proof,
                'Menunggu Konfirmasi',
            ]);
            $orderId = (int) $pdo->lastInsertId();

            foreach ($lockedItems as $lockedItem) {
                // Mengambil data item dan harga yang sama dengan perhitungan total di atas.
                $item = $lockedItem['item'];
                // Menghitung subtotal dari harga terkunci agar struk tidak berbeda dari checkout.
                $subtotal = $lockedItem['harga'] * (int) $item['jumlah'];

                $detail = $pdo->prepare(
                    'INSERT INTO order_detail (id_order, id_buku, jumlah, subtotal)
                     VALUES (?, ?, ?, ?)'
                );
                $detail->execute([
                    $orderId,
                    $item['id_buku'],
                    $item['jumlah'],
                    $subtotal,
                ]);

                $stock = $pdo->prepare(
                    'UPDATE buku
                     SET stok = stok - ?
                     WHERE id_buku = ?'
                );
                $stock->execute([$item['jumlah'], $item['id_buku']]);
            }

            $clear = $pdo->prepare('DELETE FROM cart WHERE id_user = ?');
            $clear->execute([$userId]);

            $pdo->commit();
            redirect('order_success.php?id=' . $orderId);
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

$total = array_sum(
    array_map(
        fn(array $item) => $item['harga'] * $item['jumlah'],
        $items
    )
);
$page_title = 'Checkout';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Satu langkah lagi</div>
    <h1>Checkout</h1>
  </div>
  <a href="<?= url('cart.php') ?>">← Kembali ke keranjang</a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="cart-layout">
  <section class="card">
    <h2>Data Pengiriman</h2>

    <div class="form-group">
      <label>Nama penerima</label>
      <input disabled value="<?= e($user['nama']) ?>">
    </div>

    <div class="form-group">
      <label>Alamat</label>
      <textarea disabled><?= e($user['alamat']) ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Email</label>
        <input disabled value="<?= e($user['email']) ?>">
      </div>
      <div class="form-group">
        <label>Nomor telepon</label>
        <input disabled value="<?= e($user['nomor_telepon']) ?>">
      </div>
    </div>

    <p class="helper">Data pengiriman diambil dari akun Anda.</p>
    <hr class="receipt-rule">

    <!-- Form checkout membawa total ke JavaScript untuk menampilkan estimasi kembalian. -->
    <form
      method="post"
      enctype="multipart/form-data"
      data-payment-form
      data-order-total="<?= e((string) $total) ?>"
    >
      <?= csrf_field() ?>

      <h2>Metode Pembayaran</h2>
      <div class="payment-options">
        <label class="payment-option">
          <input
            type="radio"
            name="metode_pembayaran"
            value="COD"
            <?= $method === 'COD' ? 'checked' : '' ?>
          >
          <b>COD</b><br>
          <span class="helper">Bayar saat barang diterima.</span>
        </label>

        <label class="payment-option">
          <input
            type="radio"
            name="metode_pembayaran"
            value="TRANSFER"
            <?= $method === 'TRANSFER' ? 'checked' : '' ?>
          >
          <b>Transfer</b><br>
          <span class="helper">Bayar terlebih dahulu lalu unggah bukti.</span>
        </label>
      </div>

      <!-- Panel uang tunai hanya aktif untuk metode COD. -->
      <div
        class="cash-payment-details"
        data-cash-payment-details
        <?= $method === 'COD' ? '' : 'hidden' ?>
      >
        <div class="form-group">
          <!-- Label menjelaskan bahwa nominal diisi berdasarkan uang dari pembeli. -->
          <label for="uang_dibayar">Uang dibayar pembeli</label>
          <input
            id="uang_dibayar"
            type="number"
            name="uang_dibayar"
            min="<?= e((string) $total) ?>"
            step="0.01"
            inputmode="decimal"
            placeholder="Contoh: 100000"
            value="<?= e($cashPaidInput) ?>"
            data-cash-paid
            <?= $method === 'COD' ? 'required' : 'disabled' ?>
          >
          <div class="helper">
            Masukkan nominal tunai yang diserahkan pembeli saat barang diterima.
          </div>
        </div>

        <!-- Area ini memberi umpan balik cepat tanpa menggantikan validasi server. -->
        <div class="payment-change-preview" data-payment-change-preview aria-live="polite">
          Isi uang dibayar untuk melihat estimasi kembalian.
        </div>
      </div>

      <div
        class="transfer-details"
        data-transfer-details
        <?= $method === 'TRANSFER' ? '' : 'hidden' ?>
      >
        <div class="transfer-account">
          <span class="transfer-account-label">Nomor rekening transfer</span>
          <strong>082263872834</strong>
          <span>a.n. BrianBookStore</span>
          <button
            class="copy-account-button"
            type="button"
            data-copy-value="082263872834"
          >Salin nomor</button>
        </div>

        <ol class="payment-steps">
          <li>Transfer sesuai total pesanan ke rekening di atas.</li>
          <li>Simpan foto atau PDF bukti transfer Anda.</li>
          <li>Unggah bukti; admin akan memverifikasi pembayaran sebelum pesanan diproses.</li>
        </ol>

        <div class="form-group">
          <label for="bukti_pembayaran">Unggah bukti transfer</label>
          <input
            id="bukti_pembayaran"
            type="file"
            name="bukti_pembayaran"
            accept="image/jpeg,image/png,application/pdf"
            data-payment-proof
            <?= $method === 'TRANSFER' ? 'required' : 'disabled' ?>
          >
          <div class="helper">
            Unggah foto atau screenshot transfer (JPG/PNG, PDF juga diterima) maksimal 2 MB.
          </div>
          <div class="upload-file-name" data-upload-file-name aria-live="polite"></div>
        </div>
      </div>

      <button type="submit">Buat Pesanan</button>
    </form>
  </section>

  <aside class="card summary">
    <h3>Pesanan Anda</h3>
    <?php foreach ($items as $item): ?>
      <div class="summary-line">
        <span><?= e($item['judul_buku']) ?> × <?= $item['jumlah'] ?></span>
        <span><?= rupiah($item['harga'] * $item['jumlah']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="summary-line summary-total">
      <b>Total Pembayaran</b>
      <b class="price" data-order-total-display><?= rupiah($total) ?></b>
    </div>
  </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
