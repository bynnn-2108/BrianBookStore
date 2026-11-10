<?php
/** Komponen struk reusable; pemanggil wajib menyediakan $order, $details, dan $buyer. */
?>
<div class="receipt">
  <div class="receipt-header">
    <div class="receipt-logo">B</div>
    <h2>BrianBookStore</h2>
    <p>Jl. Buku Impian No. 10, Indonesia</p>
    <p>Telp. 0812-3456-7890</p>
    <!-- Menampilkan kode transaksi publik yang menggabungkan tanggal dan urutan pesanan. -->
    <p>ID Transaksi <?= e(transaction_code($order)) ?></p>
  </div>

  <hr class="receipt-rule">

  <div class="receipt-row">
    <span>
      <?= date('d-m-Y', strtotime($order['tanggal_order'])) ?><br>
      <?= date('H:i', strtotime($order['tanggal_order'])) ?>
    </span>
    <span style="text-align:right">
      <?= e($buyer['nama']) ?><br>
      <?= e($buyer['nomor_telepon']) ?>
    </span>
  </div>

  <p><?= nl2br(e($buyer['alamat'])) ?></p>
  <hr class="receipt-rule">

  <?php foreach ($details as $key => $detail): ?>
    <div class="receipt-product">
      <b><?= $key + 1 ?>. <?= e($detail['judul_buku']) ?></b>
      <div class="receipt-row">
        <span><?= $detail['jumlah'] ?> × <?= rupiah($detail['subtotal'] / $detail['jumlah']) ?></span>
        <span><?= rupiah($detail['subtotal']) ?></span>
      </div>
    </div>
  <?php endforeach; ?>

  <hr class="receipt-rule">

  <div class="receipt-row">
    <span>Total QTY</span>
    <span><?= array_sum(array_column($details, 'jumlah')) ?></span>
  </div>
  <div class="receipt-row receipt-total">
    <span>Total</span>
    <span><?= rupiah($order['total_harga']) ?></span>
  </div>
  <div class="receipt-row">
    <span>Metode</span>
    <span><?= e($order['metode_pembayaran']) ?></span>
  </div>
  <?php if (($order['metode_pembayaran'] ?? '') === 'COD'): ?>
    <!-- Menampilkan nominal tunai yang dicatat saat customer memilih COD. -->
    <div class="receipt-row">
      <span>Uang dibayar</span>
      <span><?= rupiah($order['uang_dibayar'] ?? 0) ?></span>
    </div>
    <!-- Menampilkan hasil perhitungan uang tunai dikurangi total pesanan. -->
    <div class="receipt-row">
      <span>Uang kembalian</span>
      <span><?= rupiah($order['uang_kembalian'] ?? 0) ?></span>
    </div>
  <?php else: ?>
    <!-- Transfer tidak memiliki kembalian; status bukti ditampilkan sebagai gantinya. -->
    <div class="receipt-row">
      <span>Bukti transfer</span>
      <span><?= !empty($order['bukti_pembayaran']) ? 'Terlampir' : 'Belum ada' ?></span>
    </div>
  <?php endif; ?>
  <div class="receipt-row">
    <span>Status</span>
    <span><?= e($order['status']) ?></span>
  </div>

  <p class="receipt-note">
    Terima kasih telah berbelanja<br>
    di BrianBookStore
  </p>
</div>
