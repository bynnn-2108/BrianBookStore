-- Skema awal database: akun, katalog, keranjang, pesanan, detail pesanan, dan pesan.
CREATE DATABASE IF NOT EXISTS brianbookstore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE brianbookstore;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_detail;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS pesan;
DROP TABLE IF EXISTS buku;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS `user`;
DROP TABLE IF EXISTS admin;

SET FOREIGN_KEY_CHECKS = 1;

-- Tabel admin menyimpan akun pengelola panel backend.
CREATE TABLE admin (
  id_admin INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- Tabel user menyimpan profil, alamat, dan kredensial customer.
CREATE TABLE `user` (
  id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  alamat TEXT NOT NULL,
  nomor_telepon VARCHAR(25) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- Tabel kategori menjadi referensi klasifikasi buku.
CREATE TABLE kategori (
  id_kategori INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(100) NOT NULL UNIQUE
) ENGINE = InnoDB;

-- Tabel buku menyimpan katalog, harga, gambar, deskripsi, dan stok.
CREATE TABLE buku (
  id_buku INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_kategori INT UNSIGNED NOT NULL,
  judul_buku VARCHAR(200) NOT NULL,
  penulis VARCHAR(150) NOT NULL,
  penerbit VARCHAR(150) NOT NULL,
  tahun_terbit YEAR NOT NULL,
  harga DECIMAL(12, 2) UNSIGNED NOT NULL,
  stok INT UNSIGNED NOT NULL DEFAULT 0,
  deskripsi TEXT NOT NULL,
  gambar VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_buku_kategori
    FOREIGN KEY (id_kategori)
    REFERENCES kategori (id_kategori)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Tabel cart menyimpan item sementara milik tiap customer.
CREATE TABLE cart (
  id_cart INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user INT UNSIGNED NOT NULL,
  id_buku INT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_user_buku (id_user, id_buku),
  CONSTRAINT fk_cart_user
    FOREIGN KEY (id_user)
    REFERENCES `user` (id_user)
    ON DELETE CASCADE,
  CONSTRAINT fk_cart_buku
    FOREIGN KEY (id_buku)
    REFERENCES buku (id_buku)
    ON DELETE CASCADE
) ENGINE = InnoDB;

-- Tabel pesan menampung pesan customer untuk admin.
CREATE TABLE pesan (
  id_pesan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user INT UNSIGNED NOT NULL,
  pesan TEXT NOT NULL,
  tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pesan_user
    FOREIGN KEY (id_user)
    REFERENCES `user` (id_user)
    ON DELETE CASCADE
) ENGINE = InnoDB;

-- Tabel orders menyimpan header transaksi dan metode pembayarannya.
CREATE TABLE orders (
  id_order INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user INT UNSIGNED NOT NULL,
  tanggal_order DATETIME DEFAULT CURRENT_TIMESTAMP,
  total_harga DECIMAL(12, 2) UNSIGNED NOT NULL,
  metode_pembayaran ENUM('COD', 'TRANSFER') NOT NULL DEFAULT 'COD',
  -- Menyimpan nominal uang tunai yang diserahkan pembeli untuk pesanan COD.
  uang_dibayar DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 0,
  -- Menyimpan hasil perhitungan uang kembalian untuk pesanan COD.
  uang_kembalian DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 0,
  bukti_pembayaran VARCHAR(255) DEFAULT NULL,
  status ENUM(
    'Menunggu Konfirmasi',
    'Diproses',
    'Dikirim',
    'Selesai',
    'Dibatalkan'
  ) NOT NULL DEFAULT 'Menunggu Konfirmasi',
  CONSTRAINT fk_orders_user
    FOREIGN KEY (id_user)
    REFERENCES `user` (id_user)
    ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Tabel order_detail menyimpan buku dan jumlah pada setiap transaksi.
CREATE TABLE order_detail (
  id_detail INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_order INT UNSIGNED NOT NULL,
  id_buku INT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  subtotal DECIMAL(12, 2) UNSIGNED NOT NULL,
  CONSTRAINT fk_detail_order
    FOREIGN KEY (id_order)
    REFERENCES orders (id_order)
    ON DELETE CASCADE,
  CONSTRAINT fk_detail_buku
    FOREIGN KEY (id_buku)
    REFERENCES buku (id_buku)
    ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Seed akun admin untuk login awal setelah import database.
INSERT INTO admin (nama, email, password)
VALUES (
  'Administrator',
  'admin@brianbookstore.test',
  '$2y$10$7/Ppvg8IRXT9Yhk5OlH7cOm6lpny3AGpELVjkA1b6MOoELxZhFIFO'
);

-- Seed kategori awal agar katalog langsung dapat digunakan.
INSERT INTO kategori (nama_kategori)
VALUES
  ('Novel'),
  ('Pengembangan Diri'),
  ('Teknologi'),
  ('Anak & Remaja'),
  ('Bisnis');

INSERT INTO buku (
  id_kategori,
  judul_buku,
  penulis,
  penerbit,
  tahun_terbit,
  harga,
  stok,
  deskripsi,
  gambar
)
VALUES
  (
    1,
    'Laut Bercerita',
    'Leila S. Chudori',
    'Kepustakaan Populer Gramedia',
    2017,
    115000,
    18,
    'Novel yang mengajak pembaca menelusuri persahabatan, keluarga, dan perjuangan kemanusiaan.',
    NULL
  ),
  (
    2,
    'Atomic Habits',
    'James Clear',
    'Gramedia Pustaka Utama',
    2019,
    108000,
    25,
    'Panduan praktis membangun kebiasaan kecil untuk menghasilkan perubahan besar.',
    NULL
  ),
  (
    3,
    'Dasar-Dasar Pemrograman Web',
    'Budi Raharjo',
    'Informatika',
    2023,
    95000,
    14,
    'Buku pengantar HTML, CSS, JavaScript, PHP, dan basis data untuk pemula.',
    NULL
  ),
  (
    5,
    'The Psychology of Money',
    'Morgan Housel',
    'Baca',
    2021,
    99000,
    20,
    'Cerita singkat tentang cara manusia berpikir, bertindak, dan mengambil keputusan mengenai uang.',
    NULL
  ),
  (
    4,
    'Petualangan Kancil Cerdas',
    'Nadia Pratiwi',
    'Pelangi Anak',
    2022,
    58000,
    30,
    'Kumpulan cerita anak penuh warna dengan pesan baik untuk keluarga.',
    NULL
  );
