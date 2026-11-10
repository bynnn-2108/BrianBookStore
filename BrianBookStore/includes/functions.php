<?php

/** Helper terpusat untuk escaping, URL, autentikasi, CSRF, upload, dan status. */
require_once __DIR__ . '/../config/database.php';

// Mengamankan teks sebelum ditampilkan ke HTML untuk mencegah XSS.
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Menggabungkan BASE_URL dengan path relatif agar tautan konsisten.
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

// Mengembalikan path cover yang benar dan menyediakan fallback untuk database lama.
function book_cover_path(array $book): ?string
{
    $storedPath = trim((string) ($book['gambar'] ?? ''));
    $projectRoot = dirname(__DIR__);
    $bookDirectory = realpath($projectRoot . '/uploads/books');
    $storedFile = $storedPath !== ''
        ? realpath($projectRoot . '/' . $storedPath)
        : false;

    // Path tersimpan diprioritaskan selama file benar-benar tersedia di server.
    if (
        $storedPath !== ''
        && str_starts_with($storedPath, 'uploads/books/')
        && $bookDirectory
        && $storedFile
        && str_starts_with($storedFile, $bookDirectory . DIRECTORY_SEPARATOR)
        && is_file($storedFile)
    ) {
        return $storedPath;
    }

    // Judul lama dapat berbeda dari seed terbaru, sehingga pencocokan dibuat fleksibel.
    $title = mb_strtolower(trim((string) ($book['judul_buku'] ?? '')));
    $coverMap = [
        'atomic' => 'uploads/books/atomic-habits.jpg',
        'psychology' => 'uploads/books/psychology-of-money.jpg',
        'laut bercerita' => 'uploads/books/laut-bercerita.webp',
        'kancil' => 'uploads/books/petualangan-kancil-cerdas.jpg',
        'pemrograman web' => 'uploads/books/dasar-pemrograman-web.jpg',
    ];

    foreach ($coverMap as $keyword => $candidate) {
        if (str_contains($title, $keyword) && is_file($projectRoot . '/' . $candidate)) {
            return $candidate;
        }
    }

    return null;
}

// Mengirim redirect internal lalu menghentikan eksekusi request saat ini.
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// Memformat angka menjadi tampilan mata uang Rupiah tanpa desimal.
function rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Membuat nomor transaksi publik dengan format TBJ-ddmmyyNNN.
 *
 * ID auto-increment tetap digunakan untuk relasi database, sedangkan tiga digit
 * terakhir memakai urutan pesanan milik user yang bersangkutan.
 */
// Membuat ID transaksi publik TBJ dari tanggal order dan nomor urut per user.
function transaction_code(array $order): string
{
    // Mengambil tanggal pesanan untuk bagian tanggal pada kode transaksi.
    $timestamp = strtotime((string) ($order['tanggal_order'] ?? ''));
    // Mengubah tanggal menjadi format hari, bulan, dan tahun dua digit.
    $dateCode = $timestamp ? date('dmy', $timestamp) : '000000';
    // Mengutamakan urutan yang disimpan saat checkout agar tidak terpengaruh user lain.
    $userSequence = (int) ($order['urutan_user'] ?? 0);

    if (
        $userSequence < 1
        && !empty($order['id_user'])
        && !empty($order['id_order'])
    ) {
        // Fallback ini menjaga order lama tetap memakai urutan per user sebelum migrasi.
        global $pdo;

        try {
            $sequenceStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM orders
                 WHERE id_user = ? AND id_order <= ?'
            );
            $sequenceStmt->execute([
                (int) $order['id_user'],
                (int) $order['id_order'],
            ]);
            $userSequence = (int) $sequenceStmt->fetchColumn();
        } catch (Throwable $exception) {
            // Fallback terakhir dipakai hanya jika database lama belum dapat dihitung.
            $userSequence = 0;
        }
    }

    // Menjaga format tetap valid bila data lama benar-benar tidak memiliki urutan.
    if ($userSequence < 1) {
        $userSequence = (int) ($order['id_order'] ?? 0);
    }

    // Menambahkan nol di depan agar bagian urutan selalu memiliki minimal tiga digit.
    $sequenceCode = str_pad((string) $userSequence, 3, '0', STR_PAD_LEFT);

    // Menggabungkan awalan brand transaksi, tanggal, dan nomor urut.
    return 'TBJ-' . $dateCode . $sequenceCode;
}

// Menyimpan notifikasi satu kali yang akan dibaca setelah redirect.
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

// Merender lalu menghapus notifikasi flash dari session.
function display_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    echo '<div class="alert alert-' . e($flash['type']) . '">'
        . e($flash['message'])
        . '</div>';
}

// Membuat atau mengambil token CSRF yang tersimpan di session.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Menghasilkan hidden input CSRF untuk form POST.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token())
        . '">';
}

// Memvalidasi token CSRF agar form tidak dapat dipalsukan dari situs lain.
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($token) || !is_string($sessionToken)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan kembali dan coba lagi.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan kembali dan coba lagi.');
    }
}

// Mengecek apakah session customer sedang aktif.
function is_user_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

// Mengecek apakah session admin sedang aktif.
function is_admin_logged_in(): bool
{
    return isset($_SESSION['admin_id']);
}

// Memaksa halaman hanya dapat diakses customer yang sudah login.
function require_user(): void
{
    if (!is_user_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }
}

// Memaksa halaman hanya dapat diakses admin yang sudah login.
function require_admin(): void
{
    if (!is_admin_logged_in()) {
        set_flash('warning', 'Silakan login sebagai admin.');
        redirect('admin/login.php');
    }
}

// Mengambil profil customer aktif dari database.
function current_user(): ?array
{
    global $pdo;

    if (!is_user_logged_in()) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id_user, nama, email, alamat, nomor_telepon
         FROM `user`
         WHERE id_user = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);

    return $stmt->fetch() ?: null;
}

// Mengambil seluruh buku alamat customer dengan alamat utama di urutan pertama.
function user_addresses(int $userId): array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT id_alamat, id_user, label, nama_penerima, nomor_telepon, alamat,
                is_default, created_at, updated_at
         FROM alamat_user
         WHERE id_user = ?
         ORDER BY is_default DESC, updated_at DESC, id_alamat DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

// Membuat alamat utama dari field alamat lama agar user lama langsung memiliki pilihan alamat.
function seed_default_address(int $userId, array $user): void
{
    global $pdo;

    if (trim((string) ($user['alamat'] ?? '')) === '') {
        return;
    }

    $check = $pdo->prepare(
        'SELECT id_alamat
         FROM alamat_user
         WHERE id_user = ?
         LIMIT 1'
    );
    $check->execute([$userId]);

    if ($check->fetchColumn()) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO alamat_user (
            id_user,
            label,
            nama_penerima,
            nomor_telepon,
            alamat,
            is_default
        ) VALUES (?, ?, ?, ?, ?, 1)'
    );
    $insert->execute([
        $userId,
        'Alamat utama',
        $user['nama'],
        $user['nomor_telepon'],
        $user['alamat'],
    ]);
}

// Menghitung total unit buku pada keranjang customer aktif.
function cart_count(): int
{
    global $pdo;

    if (!is_user_logged_in()) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(jumlah), 0)
         FROM cart
         WHERE id_user = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);

    return (int) $stmt->fetchColumn();
}

// Mengubah kode error upload PHP menjadi pesan yang mudah dipahami.
function upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'Ukuran file maksimal 2 MB.',
        UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian. Silakan pilih ulang file.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak tersedia. Hubungi administrator.',
        UPLOAD_ERR_CANT_WRITE => 'Server tidak dapat menyimpan file. Coba lagi beberapa saat.',
        UPLOAD_ERR_EXTENSION => 'Unggahan file dihentikan oleh konfigurasi server.',
        default => 'File gagal diunggah. Silakan pilih file lain dan coba lagi.',
    };
}

// Memvalidasi MIME/ukuran lalu menyimpan file upload dengan nama acak.
function upload_file(
    array $file,
    string $directory,
    array $allowed,
    int $maxBytes = 2097152
): array {
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return [true, null];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return [false, upload_error_message($error)];
    }

    $temporaryFile = $file['tmp_name'] ?? '';
    $size = (int) ($file['size'] ?? 0);

    if (!is_string($temporaryFile) || $temporaryFile === '' || !is_uploaded_file($temporaryFile)) {
        return [false, 'File unggahan tidak valid. Silakan pilih ulang file.'];
    }

    if ($size < 1 || $size > $maxBytes) {
        return [false, 'Ukuran file maksimal 2 MB.'];
    }

    if (!class_exists('finfo')) {
        return [false, 'Ekstensi pemeriksa file (fileinfo) belum aktif pada server.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporaryFile);

    if (!is_string($mime) || !isset($allowed[$mime])) {
        return [false, 'Format file tidak diizinkan. Gunakan format yang tercantum.'];
    }

    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $relativeDirectory = trim($directory, '/');
    $targetDir = __DIR__ . '/../' . $relativeDirectory . '/';

    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        return [false, 'Folder unggahan tidak dapat dibuat.'];
    }

    if (!is_writable($targetDir)) {
        @chmod($targetDir, 0777);
    }

    if (!is_writable($targetDir)) {
        return [
            false,
            'Folder unggahan belum memiliki izin tulis. Jalankan '
                . '`chmod 777 ' . $relativeDirectory
                . '` dari folder BrianBookStore atau beri Apache hak tulis.',
        ];
    }

    if (!move_uploaded_file($temporaryFile, $targetDir . $name)) {
        return [false, 'File gagal disimpan. Silakan coba lagi.'];
    }

    return [true, $relativeDirectory . '/' . $name];
}

// Menghapus file upload milik aplikasi saat transaksi gagal dibatalkan.
function delete_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) {
        return;
    }

    $file = __DIR__ . '/../' . $path;

    if (is_file($file)) {
        unlink($file);
    }
}

// Memetakan status pesanan ke class badge warna pada UI.
function order_status_class(string $status): string
{
    return match ($status) {
        'Selesai', 'Diterima' => 'success',
        'Dibatalkan' => 'danger',
        'Diproses', 'Dikirim', 'Diproses Ulang' => 'info',
        'Pengajuan Pengembalian' => 'danger',
        default => 'warning',
    };
}

// Memetakan status pengajuan retur ke warna badge pada halaman user dan admin.
function return_status_class(string $status): string
{
    return match ($status) {
        'Disetujui', 'Selesai' => 'success',
        'Ditolak' => 'danger',
        default => 'warning',
    };
}
