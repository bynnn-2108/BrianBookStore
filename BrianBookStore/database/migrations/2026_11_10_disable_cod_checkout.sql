-- Menonaktifkan COD untuk checkout baru tanpa menghapus histori pesanan lama.
-- Nilai COD tetap dipertahankan di ENUM agar struk dan laporan lama tidak rusak.
USE brianbookstore;

ALTER TABLE orders
  MODIFY COLUMN metode_pembayaran ENUM('COD', 'TRANSFER')
  NOT NULL DEFAULT 'TRANSFER';

