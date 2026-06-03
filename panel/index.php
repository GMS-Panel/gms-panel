<?php
require_once 'auth.php';
require_once 'layout.php';

function servis_kontrol(string $servis): bool {
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

// Sistem bilgileri
$hostname   = gethostname();
$ip         = trim(shell_exec('hostname -I | cut -d" " -f1'));
$uptime     = trim(shell_exec('uptime -p'));
$php_ver    = PHP_VERSION;
$nginx_ver  = trim(shell_exec('nginx -v 2>&1 | sed "s/.*\///"'));
$disk_usage = trim(shell_exec("df -h / | awk 'NR==2{print \$3\"/\"\$2\" (\"\$5\" dolu\")}'"));
$ram_usage  = trim(shell_exec("free -h | awk 'NR==2{print \$3\"/\"\$2}'"));
$ram_pct    = (int) trim(shell_exec("free | awk 'NR==2{printf \"%.0f\", \$3/\$2*100}'"));
$disk_pct   = (int) trim(shell_exec("df / | awk 'NR==2{print \$5}' | tr -d '%'"));
$load       = trim(shell_exec('cat /proc/loadavg | cut -d" " -f1-3'));

// Hesap sayısı (/home altındaki kullanıcı dizinleri)
$home_dirs  = glob('/home/*', GLOB_ONLYDIR);
$hesap_sayisi = max(0, count($home_dirs) - 1); // panel kullanıcısı hariç

layout_head('Dashboard', 'dashboard');
?>

<!-- STAT CARDS -->
<div class="stat-grid">
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-users" style="font-size:14px"></i> Hosting Hesapları</div>
    <div class="stat-val"><?= $hesap_sayisi ?></div>
    <div class="stat-sub">Aktif hesap</div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-cpu" style="font-size:14px"></i> Sistem Yükü</div>
    <div class="stat-val" style="font-size:18px"><?= $load ?></div>
    <div class="stat-sub">1m / 5m / 15m ortalama</div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-device-desktop" style="font-size:14px"></i> RAM Kullanımı</div>
    <div class="stat-val"><?= $ram_pct ?>%</div>
    <div class="stat-sub"><?= $ram_usage ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-database" style="font-size:14px"></i> Disk Kullanımı</div>
    <div class="stat-val"><?= $disk_pct ?>%</div>
    <div class="stat-sub"><?= $disk_usage ?></div>
  </div>
</div>

<!-- SERVİSLER -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-server" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Servis Durumları</div>
        <div class="card-head-sub">Otomatik yenileme her 30 saniyede</div>
      </div>
    </div>
    <button class="btn btn-sm" onclick="location.reload()"><i class="ti ti-refresh"></i> Yenile</button>
  </div>
  <div class="card-body">
    <div class="servis-grid">
      <?php foreach ($servisler as $isim => $servis): ?>
      <div class="servis-kart">
        <div class="servis-name"><?= $isim ?></div>
        <?php if (servis_kontrol($servis)): ?>
          <div class="servis-aktif"><i class="ti ti-circle-check" style="font-size:15px"></i> Çalışıyor</div>
        <?php else: ?>
          <div class="servis-pasif"><i class="ti ti-circle-x" style="font-size:15px"></i> Durdu</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- SİSTEM BİLGİLERİ -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bg3)"><i class="ti ti-info-circle" style="color:var(--text2)"></i></div>
      <div><div class="card-head-title">Sistem Bilgileri</div></div>
    </div>
  </div>
  <div class="card-body">
    <table class="sys-table">
      <tr><td>Hostname</td><td><?= htmlspecialchars($hostname) ?></td></tr>
      <tr><td>IP Adresi</td><td><?= htmlspecialchars($ip) ?></td></tr>
      <tr><td>Uptime</td><td><?= htmlspecialchars($uptime) ?></td></tr>
      <tr><td>PHP (Panel)</td><td><?= htmlspecialchars($php_ver) ?></td></tr>
      <tr><td>MariaDB</td><td>11.4</td></tr>
      <tr><td>Nginx</td><td><?= htmlspecialchars($nginx_ver) ?></td></tr>
      <tr><td>Disk Kullanımı</td><td><?= htmlspecialchars($disk_usage) ?></td></tr>
      <tr><td>RAM Kullanımı</td><td><?= htmlspecialchars($ram_usage) ?></td></tr>
    </table>
  </div>
</div>

<meta http-equiv="refresh" content="30">

<?php layout_foot(); ?>
