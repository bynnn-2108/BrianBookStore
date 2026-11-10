<?php
/** Halaman informasi metode transfer untuk customer yang login. */
require_once __DIR__ . '/includes/functions.php';

require_user();

$page_title = 'Metode Pembayaran';

require __DIR__ . '/includes/header.php';
?>
<div class="section-heading">
  <div>
    <div class="eyebrow">Pengaturan akun</div>
    <h1>Metode Pembayaran</h1>
  </div>
  <a href="<?= url('pengaturan.php') ?>">← Kembali ke pengaturan</a>
</div>

<section class="payment-method-list">
  <article class="card payment-method-card">
    <div class="payment-method-icon" aria-hidden="true">⇄</div>
    <div>
      <div class="payment-method-title">
        <h2>Transfer manual</h2>
        <span class="badge badge-info">Perlu konfirmasi</span>
      </div>
      <p>
        Setelah transfer, unggah foto atau screenshot bukti pembayaran saat checkout.
        Admin akan meninjaunya sebelum pesanan diproses.
      </p>
      <div class="account-number-box">
        <span>Nomor rekening</span>
        <strong>082263872834</strong>
        <small>a.n. BrianBookStore</small>
        <button type="button" data-copy-value="082263872834">Salin nomor</button>
      </div>
    </div>
  </article>

</section>

<div class="alert alert-info" role="note">
  Demi keamanan, jangan mengirim bukti pembayaran melalui chat. Gunakan kolom unggah
  yang tersedia di checkout agar bukti langsung tercatat pada pesanan Anda.
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
