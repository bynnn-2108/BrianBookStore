-- Migrasi status diterima dan pengajuan pengembalian customer.
-- Jalankan satu kali pada database BrianBookStore yang sudah terlanjur dibuat.

USE brianbookstore;

-- Menambahkan status baru untuk alur konfirmasi dan retur barang.
ALTER TABLE orders
  MODIFY COLUMN status ENUM(
    'Menunggu Konfirmasi',
    'Diproses',
    'Dikirim',
    'Diterima',
    'Pengajuan Pengembalian',
    'Diproses Ulang',
    'Selesai',
    'Dibatalkan'
  ) NOT NULL DEFAULT 'Menunggu Konfirmasi';

-- Menyimpan survey retur, bukti foto, keputusan admin, dan detail refund.
CREATE TABLE pengajuan_pengembalian (
  id_pengajuan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_order INT UNSIGNED NOT NULL,
  id_user INT UNSIGNED NOT NULL,
  nomor_telepon VARCHAR(25) NOT NULL,
  alasan VARCHAR(120) NOT NULL,
  detail_masalah TEXT NOT NULL,
  bukti_foto VARCHAR(255) NOT NULL,
  metode_pengembalian VARCHAR(40) NOT NULL DEFAULT 'Transfer bank',
  akun_pengembalian VARCHAR(120) DEFAULT NULL,
  status ENUM('Diajukan', 'Disetujui', 'Ditolak', 'Selesai') NOT NULL DEFAULT 'Diajukan',
  jumlah_pengembalian DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 0,
  catatan_admin TEXT DEFAULT NULL,
  diajukan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diverifikasi_pada DATETIME DEFAULT NULL,
  diproses_pada DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pengembalian_order (id_order),
  KEY idx_pengembalian_status (status),
  CONSTRAINT fk_pengembalian_order
    FOREIGN KEY (id_order)
    REFERENCES orders (id_order)
    ON DELETE CASCADE,
  CONSTRAINT fk_pengembalian_user
    FOREIGN KEY (id_user)
    REFERENCES `user` (id_user)
    ON DELETE CASCADE
) ENGINE = InnoDB;
