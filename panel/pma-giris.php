<?php
require_once __DIR__ . '/auth.php';

$mysql_user = '';
$mysql_pass = '';

if (is_admin()) {
    $db_conf = parse_ini_file('/etc/gms/db.conf') ?: [];
    $mysql_user = 'root';
    $mysql_pass = $db_conf['DB_ROOT'] ?? '';
} elseif (is_user()) {
    $hesap    = $_SESSION['gms_hesap'] ?? '';
    $usr_conf = "/etc/gms/users/{$hesap}.conf";
    if ($hesap && file_exists($usr_conf)) {
        $conf        = parse_ini_file($usr_conf) ?: [];
        $mysql_user  = $conf['MYSQL_USER']  ?? '';
        $mysql_pass  = $conf['MYSQL_SIFRE'] ?? '';
    }
}

if (empty($mysql_user)) {
    header('Location: index.php');
    exit;
}

// Panel oturumunu kapat
session_write_close();

// PMA oturumu: cookie_path '/' olarak ayarla, site genelinde erisim
ini_set('session.cookie_path', '/');
session_name('GMS_PMA_Session');
session_start();
$_SESSION['PMA_single_signon_user']     = $mysql_user;
$_SESSION['PMA_single_signon_password'] = $mysql_pass;
$_SESSION['PMA_single_signon_host']     = 'localhost';
session_write_close();

header('Location: /phpmyadmin/');
exit;
