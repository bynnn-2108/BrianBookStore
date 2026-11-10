<?php
/** Endpoint POST untuk menambahkan buku ke keranjang dengan validasi stok dan CSRF. */
require_once __DIR__ . '/includes/functions.php';

require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('buku.php');
}

verify_csrf();

$returnTarget = $_POST['return_to'] ?? '';
$returnTo = is_string($returnTarget) && $returnTarget === 'index.php'
    ? 'index.php'
    : 'buku.php';
$bookIdValue = $_POST['id_buku'] ?? 0;
$quantityValue = $_POST['jumlah'] ?? 1;
$bookId = is_numeric($bookIdValue) ? (int) $bookIdValue : 0;
$requestedQuantity = is_numeric($quantityValue)
    ? max(1, (int) $quantityValue)
    : 1;

try {
    $pdo->beginTransaction();

    $bookStmt = $pdo->prepare(
        'SELECT id_buku, judul_buku, stok
         FROM buku
         WHERE id_buku = ?
         FOR UPDATE'
    );
    $bookStmt->execute([$bookId]);
    $book = $bookStmt->fetch();

    if (!$book) {
        $pdo->rollBack();
        set_flash('danger', 'Buku tidak ditemukan.');
        redirect($returnTo);
    }

    $stock = (int) $book['stok'];

    if ($stock < 1) {
        $pdo->rollBack();
        set_flash('warning', 'Maaf, stok buku ini sudah habis.');
        redirect($returnTo);
    }

    $cartStmt = $pdo->prepare(
        'SELECT id_cart, jumlah
         FROM cart
         WHERE id_user = ? AND id_buku = ?
         FOR UPDATE'
    );
    $cartStmt->execute([$_SESSION['user_id'], $bookId]);
    $cartItem = $cartStmt->fetch();

    $currentQuantity = $cartItem ? (int) $cartItem['jumlah'] : 0;
    $newQuantity = min($currentQuantity + $requestedQuantity, $stock);

    if ($cartItem) {
        $update = $pdo->prepare(
            'UPDATE cart
             SET jumlah = ?
             WHERE id_cart = ? AND id_user = ?'
        );
        $update->execute([$newQuantity, $cartItem['id_cart'], $_SESSION['user_id']]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO cart (id_user, id_buku, jumlah)
             VALUES (?, ?, ?)'
        );
        $insert->execute([$_SESSION['user_id'], $bookId, $newQuantity]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash('danger', 'Buku belum dapat dimasukkan ke keranjang. Silakan coba lagi.');
    redirect($returnTo);
}

if ($newQuantity < $currentQuantity + $requestedQuantity) {
    set_flash(
        'warning',
        'Jumlah ' . $book['judul_buku'] . ' disesuaikan dengan stok yang tersedia.'
    );
} else {
    set_flash('success', $book['judul_buku'] . ' ditambahkan ke keranjang.');
}

redirect($returnTo);
