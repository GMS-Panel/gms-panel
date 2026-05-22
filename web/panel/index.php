<?php
function servis_kontrol($servis) {
    $sonuc = shell_exec("systemctl is-active " . escapeshellarg($servis) . " 2>/dev/null");
    return trim($sonuc) === 'active';
}

$servisler = [
    'Nginx'    => 'nginx',
    'MariaDB'  => 'mariadb',
    'PHP 7.4'  => 'php74-php-fpm',
    'PHP 8.0'  => 'php80-php-fpm',
    'PHP 8.1'  => 'php81-php-fpm',
    'PHP 8.2'  => 'php82-php-fpm',
    'PHP 8.3'  => 'php83-php-fpm',
    'PHP 8.4'  => 'php84-php-fpm',
    'Firewall' => 'firewalld',
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="30">
    <title>GMS Panel - Sistem Durumu</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #1a1a2e; color: #eee; padding: 30px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h1 { color: #e94560; }
        .altyazi { color: #aaa; font-size: 12px; margin-top: 5px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .kart { background: #16213e; border-radius: 8px; padding: 20px; border: 1px solid #0f3460; }
        .kart h3 { font-size: 13px; color: #aaa; margin-bottom: 8px; }
        .aktif { color: #2ecc71; font-weight: bold; }
        .pasif { color: #e74c3c; font-weight: bold; }
        .bilgi { background: #16213e; border-radius: 8px; padding: 20px; border: 1px solid #0f3460; margin-bottom: 20px; }
        .bilgi h2 { color: #e94560; margin-bottom: 15px; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #0f3460; }
        td:first-child { color: #aaa; width: 40%; }
        a { color: #e94560; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .menu { display: flex; gap: 15px; margin-bottom: 25px; }
        .menu a { background: #16213e; padding: 10px 20px; border-radius: 5px; border: 1px solid #0f3460; color: #eee; font-size: 13px; }
        .menu a:hover { border-color: #e94560; color: #e94560; text-decoration: none; }
    </style>
</head>
<body>

<header>
    <div>
        <h1>GMS Panel</h1>
        <p class="altyazi">Sistem Durumu &bull; <?php echo date('d.m.Y H:i:s'); ?> &bull; 30 saniyede bir yenilenir</p>
    </div>
</header>

<div class="menu">
    <a href="/phpmyadmin" target="_blank">phpMyAdmin</a>
    <a href="#">Hesaplar</a>
    <a href="#">Domainler</a>
    <a href="#">Yedekleme</a>
</div>

<div class="bilgi">
    <h2>Sistem Bilgileri</h2>
    <table>
        <tr><td>Hostname</td><td><?php echo gethostname(); ?></td></tr>
        <tr><td>IP Adresi</td><td><?php echo trim(shell_exec('hostname -I | cut -d" " -f1')); ?></td></tr>
        <tr><td>Uptime</td><td><?php echo trim(shell_exec('uptime -p')); ?></td></tr>
        <tr><td>PHP</td><td><?php echo PHP_VERSION; ?></td></tr>
        <tr><td>MariaDB</td><td>11.4</td></tr>
        <tr><td>Nginx</td><td><?php echo trim(shell_exec('nginx -v 2>&1 | cut -d"/" -f2')); ?></td></tr>
        <tr><td>Disk Kullanimi</td><td><?php echo trim(shell_exec('df -h / | awk \'NR==2{print $3"/"$2" ("$5" dolu")"}\'')); ?></td></tr>
        <tr><td>RAM Kullanimi</td><td><?php echo trim(shell_exec('free -h | awk \'NR==2{print $3"/"$2}\'')); ?></td></tr>
    </table>
</div>

<div class="grid">
<?php foreach ($servisler as $isim => $servis): ?>
    <div class="kart">
        <h3><?php echo $isim; ?></h3>
        <?php if (servis_kontrol($servis)): ?>
            <span class="aktif">&#10004; Calisiyor</span>
        <?php else: ?>
            <span class="pasif">&#10008; Durdu</span>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>