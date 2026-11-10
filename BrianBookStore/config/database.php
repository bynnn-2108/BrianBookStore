<?php

/**
 * Konfigurasi koneksi database, session, dan BASE_URL aplikasi XAMPP.
 * Ubah nilai berikut bila username/password MySQL atau nama folder berbeda.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'brianbookstore');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/BrianBookStore');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    exit(
        'Koneksi database gagal. Pastikan Apache/MySQL aktif, database sudah diimpor, '
        . 'lalu cek config/database.php.'
    );
}
