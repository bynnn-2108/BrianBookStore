<?php require_once __DIR__ . '/includes/functions.php'; unset($_SESSION['user_id'], $_SESSION['user_nama']); set_flash('success', 'Anda sudah keluar dari akun.'); redirect('index.php');
