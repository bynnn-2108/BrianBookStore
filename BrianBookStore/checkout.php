<?php
/** Checkout: mengunci stok, menerima transfer, dan membuat pesanan atomik. */
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
$method = 'TRANSFER';

// Mendeteksi kolom snapshot agar checkout tetap kompatibel dengan database lama.
$hasAddressSnapshot = false;
$hasUserOrderSequence = false;

try {
    $columnCheck = $pdo->query(
        "SHOW COLUMNS FROM orders LIKE 'alamat_pengiriman'"
    );
    $hasAddressSnapshot = (bool) $columnCheck->fetch();
} catch (PDOException $exception) {
    $hasAddressSnapshot = false;
}

try {
    // Mendeteksi kolom urutan agar checkout lama tetap kompatibel sebelum migrasi dijalankan.
    $sequenceColumnCheck = $pdo->query(
        "SHOW COLUMNS FROM orders LIKE 'urutan_user'"
    );
    $hasUserOrderSequence = (bool) $sequenceColumnCheck->fetch();
} catch (PDOException $exception) {
    $hasUserOrderSequence = false;
}

// Mengambil buku alamat agar customer dapat memilih tujuan kirim saat checkout.
$addresses = [];

try {
    // User lama otomatis memperoleh alamat utama dari field profil sebelumnya.
    seed_default_address($userId, $user ?: []);
    $addresses = user_addresses($userId);
} catch (Throwable $exception) {
    // Fallback ini menjaga checkout tetap dapat dipakai sebelum migrasi dijalankan.
    $addresses = [];
}

if (!$addresses && $user) {
    // Alamat legacy dipakai sementara bila tabel buku alamat belum berisi data.
    $addresses[] = [
        'id_alamat' => 0,
        'label' => 'Alamat akun',
        'nama_penerima' => $user['nama'],
        'nomor_telepon' => $user['nomor_telepon'],
        'alamat' => $user['alamat'],
        'is_default' => 1,
    ];
}

// Menentukan alamat utama yang terpilih sebelum customer mengirim checkout.
$selectedAddress = $addresses[0] ?? null;
$selectedAddressId = $selectedAddress ? (int) $selectedAddress['id_alamat'] : 0;

foreach ($addresses as $address) {
    if ((int) ($address['is_default'] ?? 0) === 1) {
        $selectedAddressId = (int) $address['id_alamat'];
        $selectedAddress = $address;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Checkout baru hanya menerima transfer agar setiap pesanan memiliki bukti pembayaran.
    $requestedMethod = $_POST['metode_pembayaran'] ?? 'TRANSFER';
    $method = 'TRANSFER';
    $requestedAddressId = (int) ($_POST['id_alamat'] ?? 0);

    // Memastikan alamat yang dipilih berasal dari buku alamat user yang sedang login.
    $selectedAddress = null;
    foreach ($addresses as $address) {
        if ((int) $address['id_alamat'] === $requestedAddressId) {
            $selectedAddressId = $requestedAddressId;
            $selectedAddress = $address;
            break;
        }
    }

    if (!$selectedAddress) {
        $error = 'Pilih alamat pengiriman terlebih dahulu.';
    }

    $proof = null;

    if ($requestedMethod !== 'TRANSFER') {
        $error = 'Metode COD sudah tidak tersedia. Pilih transfer untuk melanjutkan.';
    }

    if (!isset($error)) {
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

            // Mengunci baris user agar dua checkout bersamaan tidak mendapat urutan yang sama.
            $userOrderSequence = null;

            if ($hasUserOrderSequence) {
                $userLock = $pdo->prepare(
                    'SELECT id_user
                     FROM `user`
                     WHERE id_user = ?
                     FOR UPDATE'
                );
                $userLock->execute([$userId]);

                if (!$userLock->fetch()) {
                    throw new RuntimeException('Akun customer tidak ditemukan.');
                }

                // Urutan dimulai dari 001 untuk setiap user dan tidak dipengaruhi user lain.
                $sequence = $pdo->prepare(
                    'SELECT COALESCE(MAX(urutan_user), 0) + 1
                     FROM orders
                     WHERE id_user = ?'
                );
                $sequence->execute([$userId]);
                $userOrderSequence = (int) $sequence->fetchColumn();
            }

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
                // Menambahkan subtotal item ke total checkout yang disimpan pada order.
                $total += $lockedPrice * (int) $item['jumlah'];
                // Menyimpan item beserta harga terkunci untuk insert detail pesanan.
                $lockedItems[] = [
                    'item' => $item,
                    'harga' => $lockedPrice,
                ];
            }

            // Menyusun kolom order secara dinamis untuk mendukung database sebelum migrasi.
            $orderColumns = ['id_user'];
            $orderPlaceholders = ['?'];
            $orderValues = [$userId];

            if ($hasUserOrderSequence) {
                // Menyimpan nomor urut per user agar kode transaksi tidak bergantung user lain.
                $orderColumns[] = 'urutan_user';
                $orderPlaceholders[] = '?';
                $orderValues[] = $userOrderSequence;
            }

            if ($hasAddressSnapshot) {
                // Snapshot alamat terpilih menjaga struk tidak berubah setelah profil diedit.
                $orderColumns = array_merge(
                    $orderColumns,
                    ['nama_penerima', 'telepon_pengiriman', 'alamat_pengiriman']
                );
                $orderPlaceholders = array_merge($orderPlaceholders, ['?', '?', '?']);
                $orderValues = array_merge(
                    $orderValues,
                    [
                        $selectedAddress['nama_penerima'],
                        $selectedAddress['nomor_telepon'],
                        $selectedAddress['alamat'],
                    ]
                );
            }

            // Kolom pembayaran dan status selalu tersedia pada skema aplikasi.
            $orderColumns = array_merge(
                $orderColumns,
                [
                    'total_harga',
                    'metode_pembayaran',
                    'bukti_pembayaran',
                    'status',
                ]
            );
            $orderPlaceholders = array_merge(
                $orderPlaceholders,
                ['?', '?', '?', '?']
            );
            $orderValues = array_merge(
                $orderValues,
                [$total, $method, $proof, 'Menunggu Konfirmasi']
            );

            $order = $pdo->prepare(
                'INSERT INTO orders (' . implode(', ', $orderColumns) . ')
                 VALUES (' . implode(', ', $orderPlaceholders) . ')'
            );
            $order->execute($orderValues);
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

    <div class="checkout-address-heading">
      <div>
        <h3>Pilih alamat pengiriman</h3>
        <p class="helper">Alamat yang dipilih akan disimpan pada struk pesanan.</p>
      </div>
      <a
        class="button button-outline button-small"
        href="<?= url('alamat.php?return_to=checkout.php') ?>"
      >
        Kelola alamat
      </a>
    </div>

    <?php if ($addresses): ?>
      <div class="checkout-address-list">
        <?php foreach ($addresses as $address): ?>
          <label class="checkout-address-option">
            <input
              type="radio"
              name="id_alamat"
              value="<?= $address['id_alamat'] ?>"
              form="checkout-payment-form"
              <?= (int) $address['id_alamat'] === $selectedAddressId ? 'checked' : '' ?>
            >
            <span class="checkout-address-copy">
              <span class="checkout-address-title">
                <b><?= e($address['label']) ?></b>
                <?php if ($address['is_default']): ?>
                  <span class="badge badge-success">Utama</span>
                <?php endif; ?>
              </span>
              <strong><?= e($address['nama_penerima']) ?></strong>
              <span><?= e($address['nomor_telepon']) ?></span>
              <small><?= nl2br(e($address['alamat'])) ?></small>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-warning">Tambahkan alamat sebelum membuat pesanan.</div>
    <?php endif; ?>

    <div class="form-group checkout-email-field">
      <label for="checkout-email">Email akun</label>
      <input id="checkout-email" disabled value="<?= e($user['email']) ?>">
    </div>

    <hr class="receipt-rule">

    <!-- Form checkout memakai multipart agar bukti transfer ikut terkirim. -->
    <form
      method="post"
      id="checkout-payment-form"
      enctype="multipart/form-data"
      data-payment-form
    >
      <?= csrf_field() ?>

      <h2>Metode Pembayaran</h2>
      <div class="payment-options">
        <label class="payment-option">
          <input
            type="radio"
            name="metode_pembayaran"
            value="TRANSFER"
            checked
          >
          <b>Transfer bank</b><br>
          <span class="helper">Bayar terlebih dahulu lalu unggah bukti.</span>
        </label>
      </div>

      <div
        class="transfer-details"
        data-transfer-details
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
            required
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
