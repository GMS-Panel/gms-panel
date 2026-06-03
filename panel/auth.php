<?php
// GMS Panel - Oturum Kontrol Dosyası
// Her sayfanın en başında require_once 'auth.php'; ile dahil edilir

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['gms_user'])) {
    header('Location: login.php');
    exit;
}

// Oturum süresi: 8 saat
if (isset($_SESSION['gms_login_at']) && (time() - $_SESSION['gms_login_at']) > 28800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Aktif kullanıcı adı
$gms_current_user = $_SESSION['gms_user'];
