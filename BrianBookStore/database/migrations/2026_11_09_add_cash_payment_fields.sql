-- Migrasi ini menambahkan data uang tunai untuk pesanan COD pada database lama.
-- Jalankan satu kali pada database BrianBookStore yang sudah terlanjur dibuat.

USE brianbookstore;

-- Kolom ini menyimpan uang yang diterima dari pembeli saat checkout COD.
ALTER TABLE orders
  ADD COLUMN uang_dibayar DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 0
  AFTER metode_pembayaran;

-- Kolom ini menyimpan hasil uang_dibayar dikurangi total_harga.
ALTER TABLE orders
  ADD COLUMN uang_kembalian DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 0
  AFTER uang_dibayar;
