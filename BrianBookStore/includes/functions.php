<?php
require_once __DIR__ . '/../config/database.php';

function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . url($path)); exit; }
function rupiah($amount): string { return 'Rp ' . number_format((float) $amount, 0, ',', '.'); }
function set_flash(string $type, string $message): void { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }
function display_flash(): void {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash']; unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']))) {
        http_response_code(403); exit('Permintaan tidak valid. Silakan kembali dan coba lagi.');
    }
}
function is_user_logged_in(): bool { return isset($_SESSION['user_id']); }
function is_admin_logged_in(): bool { return isset($_SESSION['admin_id']); }
function require_user(): void { if (!is_user_logged_in()) { set_flash('warning', 'Silakan login terlebih dahulu.'); redirect('login.php'); } }
function require_admin(): void { if (!is_admin_logged_in()) { set_flash('warning', 'Silakan login sebagai admin.'); redirect('admin/login.php'); } }
function current_user(): ?array {
    global $pdo; if (!is_user_logged_in()) return null;
    $stmt = $pdo->prepare('SELECT id_user, nama, email, alamat, nomor_telepon FROM `user` WHERE id_user = ?');
    $stmt->execute([$_SESSION['user_id']]); return $stmt->fetch() ?: null;
}
function cart_count(): int {
    global $pdo; if (!is_user_logged_in()) return 0;
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(jumlah),0) FROM cart WHERE id_user = ?'); $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}
function upload_file(array $file, string $directory, array $allowed, int $maxBytes = 2097152): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [true, null];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || $file['size'] > $maxBytes) return [false, 'Ukuran file maksimal 2 MB.'];
    $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) return [false, 'Format file tidak diizinkan.'];
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $targetDir = __DIR__ . '/../' . trim($directory, '/') . '/';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) return [false, 'Folder unggahan tidak dapat dibuat.'];
    if (!move_uploaded_file($file['tmp_name'], $targetDir . $name)) return [false, 'File gagal diunggah.'];
    return [true, trim($directory, '/') . '/' . $name];
}
function delete_upload(?string $path): void {
    if ($path && str_starts_with($path, 'uploads/')) { $file = __DIR__ . '/../' . $path; if (is_file($file)) unlink($file); }
}
function order_status_class(string $status): string {
    return match ($status) { 'Selesai' => 'success', 'Dibatalkan' => 'danger', 'Diproses', 'Dikirim' => 'info', default => 'warning' };
}
