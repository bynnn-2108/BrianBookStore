# BrianBookStore

Aplikasi toko buku online berbasis PHP Native dan MySQL/MariaDB, siap dijalankan
dengan XAMPP di Windows. Membutuhkan PHP 8.0+ (XAMPP versi terkini sudah
mendukungnya).

## Menjalankan di XAMPP

1. Salin folder `BrianBookStore` ini ke `C:\xampp\htdocs\BrianBookStore`.
2. Buka **XAMPP Control Panel**, lalu klik **Start** pada Apache dan MySQL.
3. Kunjungi `http://localhost/phpmyadmin`, klik **Import**, pilih file
   `database/brianbookstore.sql`, lalu klik **Import/Go**. File SQL akan membuat
   database dan data contoh otomatis.
   Jika database lama sudah pernah dibuat, jalankan juga migrasi
   `database/migrations/2026_11_09_add_cash_payment_fields.sql` (hanya untuk kompatibilitas histori),
   `database/migrations/2026_11_10_add_address_book.sql`,
   `database/migrations/2026_11_10_add_user_order_sequence.sql`, dan
   `database/migrations/2026_11_10_add_return_requests.sql`, serta
   `database/migrations/2026_11_10_disable_cod_checkout.sql` dan
   `database/migrations/2026_11_10_seed_book_cover_images.sql` satu kali agar
   buku alamat, snapshot alamat, urutan transaksi per user, alur retur, default
   transfer, dan cover buku tersedia.
4. Buka `http://localhost/BrianBookStore/` di browser.
5. Pastikan folder `uploads/books`, `uploads/payment`, dan `uploads/returns` dapat
   ditulis oleh Apache (umumnya sudah bisa pada XAMPP Windows). Jika macOS menolak
   unggahan, jalankan perintah berikut dari folder proyek:

   ```sh
   chmod 777 uploads/books uploads/payment uploads/returns
   ```

## Akun awal

- Admin: `admin@brianbookstore.test` / `admin123` — buka
  `http://localhost/BrianBookStore/admin/login.php`
- User: buat akun melalui halaman **Daftar**.

## Testing singkat

1. Daftarkan user, login, cari buku dan masukkan ke keranjang.
2. Ubah jumlah buku, lalu checkout menggunakan transfer. Gunakan nomor rekening
   `082263872834`, kemudian unggah foto atau screenshot JPG/PNG (PDF juga diterima)
   maksimal 2 MB. COD tidak tersedia pada checkout baru.
4. Gunakan tombol **+ Keranjang** pada kartu buku untuk memilih jumlah pembelian
   sesuai stok. Setelah login, buka menu titik tiga **Profil & Pengaturan** untuk
   mencoba mode gelap, log transaksi, metode pembayaran, dan pengelolaan akun.
   Pengunjung yang belum login akan melihat tombol **Daftar**.
5. Pada beranda, lihat bagian **Best seller** dan **Rekomendasi**. Arahkan pointer
   ke cover buku untuk melihat sinopsis singkat tanpa membuka halaman detail.
6. Buka **Alamat Pengiriman** dari Pengaturan untuk menambah beberapa alamat,
   memilih alamat utama, atau mengubah alamat. Alamat yang dipilih otomatis muncul
   sebagai pilihan pada checkout dan disimpan sebagai snapshot di struk.
7. Buka Pesanan untuk melihat atau mencetak struk mini dan bukti pembayaran yang pernah diunggah.
   Struk dan daftar pesanan menggunakan ID transaksi publik berformat
   `TBJ-ddmmyyNNN`, misalnya `TBJ-101126001`. Tiga digit terakhir dimulai dari
   `001` untuk setiap user, sehingga pesanan user lain juga dapat memiliki urutan
   `001`; ID database internal tetap tidak berubah.
8. Setelah pesanan berstatus Diterima, user dapat memilih Selesaikan atau Ajukan Pengembalian.
   Isi survey dengan alasan, nomor telepon, akun refund, dan bukti foto. Admin memverifikasi
   survey dari panel Pengajuan Pengembalian; persetujuan mencatat refund dan mengubah order
   menjadi Diproses Ulang. User menekan Proses Ulang Selesai setelah dana/proses diterima.
9. Login admin, kelola kategori dan buku, lalu lihat lampiran bukti transfer serta ubah status pesanan.

Jika folder proyek diganti namanya, ubah `BASE_URL` dalam `config/database.php`
agar sesuai, misalnya `'/nama-folder-baru'`.

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
├── uploads/returns/       # Bukti foto pengajuan pengembalian
├── index.php              # Beranda pelanggan
├── buku.php, detail_buku.php, includes/book_card.php
├── cart.php, cart_add.php, checkout.php, pesanan.php, order_success.php
├── pengaturan.php, alamat.php, metode_pembayaran.php, kelola_akun.php
└── about.php, contact.php, login.php, register.php
```

Cover bawaan sudah berada di `uploads/books`. Kolom `buku.gambar` menyimpan path
relatif file tersebut sehingga gambar tetap terhubung setelah database diimpor;
admin juga dapat mengganti cover dari menu **Manajemen Buku**.
