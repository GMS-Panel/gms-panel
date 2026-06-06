<?php
// GMS Panel - Oturum ve Yetki Kontrol Dosyasi
// Her sayfanin en basinda require_once 'auth.php'; ile dahil edilir

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Oturum yoksa login'e yonlendir
if (!isset($_SESSION['gms_user'])) {
    header('Location: login.php');
    exit;
}

// Oturum suresi: 8 saat
if (isset($_SESSION['gms_login_at']) && (time() - $_SESSION['gms_login_at']) > 28800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

$gms_current_user = $_SESSION['gms_user'];
$gms_current_role = $_SESSION['gms_role'] ?? 'user';
$gms_current_hesap = $_SESSION['gms_hesap'] ?? '';

// Rol kontrol fonksiyonlari
function is_admin(): bool {
    return ($_SESSION['gms_role'] ?? '') === 'admin';
}

function is_user(): bool {
    return ($_SESSION['gms_role'] ?? '') === 'user';
}

// Sadece admin erisebilir, degilse dashboard'a yonlendir
function admin_only(): void {
    if (!is_admin()) {
        header('Location: index.php?error=yetki');
        exit;
    }
}

// Kullanicinin belirli bir hesaba erisimi var mi
function hesap_erisim(string $hesap): bool {
    if (is_admin()) return true;
    return ($_SESSION['gms_hesap'] ?? '') === $hesap;
}

// Kullanici bilgilerini oku
function get_user_info(string $username): array {
    $file = "/etc/gms/users/{$username}.conf";
    if (!file_exists($file)) return [];
    $info = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $info[trim($k)] = trim($v);
        }
    }
    return $info;
}

// Tum alt kullanicilari listele
function get_all_users(): array {
    $users = [];
    foreach (glob('/etc/gms/users/*.conf') as $file) {
        $username = basename($file, '.conf');
        $info = get_user_info($username);
        if (!empty($info)) {
            $info['username'] = $username;
            $users[] = $info;
        }
    }
    return $users;
}

// Paket bilgilerini oku
function get_paket(string $paket_adi): array {
    $file = "/etc/gms/paketler/{$paket_adi}.conf";
    if (!file_exists($file)) return [];
    $info = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $info[trim($k)] = trim($v);
        }
    }
    return $info;
}

// Mevcut kullanicinin paketini getir
function get_current_paket(): array {
    if (is_admin()) return [];
    $info = get_user_info($_SESSION['gms_user']);
    $paket_adi = $info['PAKET'] ?? '';
    return $paket_adi ? get_paket($paket_adi) : [];
}
