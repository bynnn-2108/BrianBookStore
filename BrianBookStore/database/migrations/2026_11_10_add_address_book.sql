-- Migrasi buku alamat customer dan snapshot alamat pada transaksi.
-- Jalankan satu kali pada database BrianBookStore yang sudah terlanjur dibuat.

USE brianbookstore;

-- Tabel ini menyimpan lebih dari satu alamat untuk setiap customer.
CREATE TABLE IF NOT EXISTS alamat_user (
  id_alamat INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user INT UNSIGNED NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT 'Rumah',
  nama_penerima VARCHAR(100) NOT NULL,
  nomor_telepon VARCHAR(25) NOT NULL,
  alamat TEXT NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_alamat_user (id_user),
  CONSTRAINT fk_alamat_user
    FOREIGN KEY (id_user)
    REFERENCES `user` (id_user)
    ON DELETE CASCADE
) ENGINE = InnoDB;

-- Kolom snapshot menjaga alamat pada struk tetap sama walau alamat akun berubah.
ALTER TABLE orders
  ADD COLUMN nama_penerima VARCHAR(100) DEFAULT NULL AFTER id_user,
  ADD COLUMN telepon_pengiriman VARCHAR(25) DEFAULT NULL AFTER nama_penerima,
  ADD COLUMN alamat_pengiriman TEXT DEFAULT NULL AFTER telepon_pengiriman;
