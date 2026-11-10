<?php
/** Endpoint aman untuk membuka bukti foto pengajuan retur milik user atau admin. */
require_once __DIR__ . '/includes/functions.php';

// Admin dapat melihat semua pengajuan, sedangkan user hanya boleh melihat miliknya.
if (!is_admin_logged_in()) {
    require_user();
}

$requestId = (int) ($_GET['id'] ?? 0);

if (is_admin_logged_in()) {
    $stmt = $pdo->prepare(
        'SELECT bukti_foto
         FROM pengajuan_pengembalian
         WHERE id_pengajuan = ?'
    );
    $stmt->execute([$requestId]);
} else {
    $stmt = $pdo->prepare(
        'SELECT bukti_foto
         FROM pengajuan_pengembalian
         WHERE id_pengajuan = ? AND id_user = ?'
    );
    $stmt->execute([$requestId, $_SESSION['user_id']]);
}

$path = $stmt->fetchColumn();

if (
    !is_string($path)
    || !preg_match('#^uploads/returns/[^/]+\.(jpg|png|webp)$#i', $path, $matches)
) {
    http_response_code(404);
    exit('Bukti foto tidak ditemukan.');
}

$uploadDirectory = realpath(__DIR__ . '/uploads/returns');
$file = realpath(__DIR__ . '/' . $path);

if (
    !$uploadDirectory
    || !$file
    || !str_starts_with($file, $uploadDirectory . DIRECTORY_SEPARATOR)
    || !is_file($file)
) {
    http_response_code(404);
    exit('Bukti foto tidak ditemukan.');
}

$extension = strtolower($matches[1]);
$contentTypes = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="bukti-retur-' . $requestId . '.' . $extension . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

readfile($file);
exit;
