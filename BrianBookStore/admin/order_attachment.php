<?php
/** Endpoint admin untuk membuka bukti pembayaran pada pesanan mana pun. */
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$orderId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT bukti_pembayaran
     FROM orders
     WHERE id_order = ?'
);
$stmt->execute([$orderId]);
$path = $stmt->fetchColumn();

if (
    !is_string($path)
    || !preg_match('#^uploads/payment/[^/]+\.(jpg|png|pdf)$#i', $path, $matches)
) {
    http_response_code(404);
    exit('Lampiran tidak ditemukan.');
}

$paymentDirectory = realpath(__DIR__ . '/../uploads/payment');
$file = realpath(__DIR__ . '/../' . $path);

if (
    !$paymentDirectory
    || !$file
    || !str_starts_with($file, $paymentDirectory . DIRECTORY_SEPARATOR)
    || !is_file($file)
) {
    http_response_code(404);
    exit('Lampiran tidak ditemukan.');
}

$extension = strtolower($matches[1]);
$contentTypes = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
];

header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($file));
header(
    'Content-Disposition: inline; filename="bukti-pembayaran-'
    . $orderId
    . '.'
    . $extension
    . '"'
);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

readfile($file);
exit;
