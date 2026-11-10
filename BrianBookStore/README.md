# BrianBookStore

Aplikasi toko buku online berbasis PHP Native dan MySQL/MariaDB, dapat dijalankan
dengan XAMPP di Windows. Membutuhkan PHP 8.0+ (XAMPP versi terbaru sudah
mendukungnya).

## Akun awal

- Admin: `admin@brianbookstore.test` / `admin123` 
— buka: `http://localhost/BrianBookStore/admin/login.php`

- User: buat akun melalui halaman **Daftar**.
- buka: `http://localhost/BrianBookStore/register.php`
- buka: `http://localhost/BrianBookStore/login.php` (jika sudah memiliki akun)

## Testing singkat

1. Daftarkan user, login, cari buku dan masukkan ke keranjang.
2. Ubah jumlah buku, lalu checkout dengan COD atau TRANSFER. Untuk COD, isi
   nominal uang yang diberikan pembeli; aplikasi akan menghitung uang kembalian
   dan menampilkannya pada struk.
3. Untuk transfer, gunakan nomor rekening `082263872834`, lalu unggah foto atau
   screenshot JPG/PNG (PDF juga diterima) maksimal 2 MB. COD tidak meminta
   unggahan bukti.
4. Gunakan tombol **+ Keranjang** pada kartu buku untuk memilih jumlah pembelian
   sesuai stok. Setelah login, buka menu titik tiga **Profil & Pengaturan** untuk
   mencoba mode gelap, log transaksi, metode pembayaran, dan pengelolaan akun.
   Pengunjung yang belum login akan melihat tombol **Daftar**.
5. Buka Pesanan untuk melihat atau mencetak struk mini dan bukti pembayaran yang pernah diunggah.
   Struk dan daftar pesanan menggunakan ID transaksi publik berformat
   `TBJ-ddmmyyNNN`, misalnya `TBJ-101126001`; ID database internal tetap tidak berubah.
6. Login admin, kelola kategori dan buku, lalu lihat lampiran bukti transfer serta ubah status pesanan.

## Struktur proyek

```text
BrianBookStore/
├── admin/                 # Login dan panel admin
├── assets/css, assets/js  # Tampilan ungu dan interaksi ringan
├── config/database.php    # Koneksi PDO dan BASE_URL
├── database/              # SQL yang diimpor lewat phpMyAdmin
├── includes/              # Header, footer, fungsi keamanan, struk
├── uploads/books/         # Gambar buku dari admin
├── uploads/payment/       # Bukti transfer dari user
├── index.php              # Beranda pelanggan
├── buku.php, detail_buku.php, includes/book_card.php
├── cart.php, cart_add.php, checkout.php, pesanan.php, order_success.php
├── pengaturan.php, metode_pembayaran.php, kelola_akun.php
└── about.php, contact.php, login.php, register.php
```
