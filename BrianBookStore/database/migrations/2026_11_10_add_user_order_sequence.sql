-- Migrasi nomor urut transaksi agar kode TBJ terpisah untuk setiap customer.
-- Jalankan satu kali pada database BrianBookStore yang sudah terlanjur dibuat.

USE brianbookstore;

-- Kolom sementara diisi dahulu agar order lama dapat diberi nomor berdasarkan user.
ALTER TABLE orders
  ADD COLUMN urutan_user INT UNSIGNED NULL AFTER id_user;

-- Menyusun nomor order lama pada tabel sementara agar kompatibel dengan MySQL/MariaDB.
DROP TEMPORARY TABLE IF EXISTS tmp_order_user_sequence;

CREATE TEMPORARY TABLE tmp_order_user_sequence (
  id_order INT UNSIGNED PRIMARY KEY,
  sequence_number INT UNSIGNED NOT NULL
) ENGINE = InnoDB;

INSERT INTO tmp_order_user_sequence (id_order, sequence_number)
SELECT newer.id_order, COUNT(older.id_order) AS sequence_number
FROM orders AS newer
JOIN orders AS older
  ON older.id_user = newer.id_user
 AND older.id_order <= newer.id_order
GROUP BY newer.id_order;

UPDATE orders AS target_order
JOIN tmp_order_user_sequence AS numbered_orders
  ON numbered_orders.id_order = target_order.id_order
SET target_order.urutan_user = numbered_orders.sequence_number;

DROP TEMPORARY TABLE tmp_order_user_sequence;

-- Setelah data lama terisi, kolom dibuat wajib dan unik untuk setiap user.
ALTER TABLE orders
  MODIFY COLUMN urutan_user INT UNSIGNED NOT NULL,
  ADD UNIQUE KEY uq_orders_user_sequence (id_user, urutan_user);
