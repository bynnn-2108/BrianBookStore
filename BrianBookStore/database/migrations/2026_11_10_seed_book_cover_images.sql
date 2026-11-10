-- Menetapkan cover buku awal ke file permanen di uploads/books.
-- Jalankan sekali setelah file gambar ikut disalin ke folder aplikasi.
USE brianbookstore;

UPDATE buku
SET gambar = 'uploads/books/laut-bercerita.webp'
WHERE judul_buku LIKE '%Laut Bercerita%';

UPDATE buku
SET gambar = 'uploads/books/atomic-habits.jpg'
WHERE judul_buku LIKE '%Atomic Habits%';

UPDATE buku
SET gambar = 'uploads/books/dasar-pemrograman-web.jpg'
WHERE judul_buku LIKE '%Pemrograman Web%';

UPDATE buku
SET gambar = 'uploads/books/psychology-of-money.jpg'
WHERE judul_buku LIKE '%Psychology%Money%';

UPDATE buku
SET gambar = 'uploads/books/petualangan-kancil-cerdas.jpg'
WHERE judul_buku LIKE '%Kancil%';
