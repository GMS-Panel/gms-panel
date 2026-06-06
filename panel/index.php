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

// Alt kullanici: kendi hesap verileri
if (is_user()) {
    $kendi_hesap = $_SESSION['gms_hesap'] ?? '';
    $hesap_disk  = trim(shell_exec("du -sh /home/" . escapeshellarg($kendi_hesap) . " 2>/dev/null | cut -f1") ?? '-');
    $hesap_php   = '';
    foreach (['84','83','82','81','80','74'] as $v) {
        if (file_exists("/etc/opt/remi/php{$v}/php-fpm.d/{$kendi_hesap}.conf")) {
            $hesap_php = "PHP " . $v[0] . "." . $v[1];
            break;
        }
    }
    $hesap_domain_sayisi = 0;
    foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
        if (in_array(basename($conf, '.conf'), ['00-default', 'php-fpm'])) continue;
        if (strpos(file_get_contents($conf), '/home/' . $kendi_hesap . '/') !== false) {
            $hesap_domain_sayisi++;
        }
    }
}

layout_head('Dashboard', 'dashboard');
?>

<?php if (is_admin()): ?>
<!-- ADMIN DASHBOARD -->

<!-- STAT CARDS -->
<div class="stat-grid">
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-users" style="font-size:14px"></i> Hosting Hesaplari</div>
    <div class="stat-val"><?= $hesap_sayisi ?></div>
    <div class="stat-sub">Aktif hesap</div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-cpu" style="font-size:14px"></i> Sistem Yuku</div>
    <div class="stat-val" style="font-size:18px"><?= $load ?></div>
    <div class="stat-sub">1m / 5m / 15m ortalama</div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-device-desktop" style="font-size:14px"></i> RAM Kullanimi</div>
    <div class="stat-val"><?= $ram_pct ?>%</div>
    <div class="stat-sub"><?= $ram_usage ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-database" style="font-size:14px"></i> Disk Kullanimi</div>
    <div class="stat-val"><?= $disk_pct ?>%</div>
    <div class="stat-sub"><?= $disk_usage ?></div>
  </div>
</div>

<!-- SERVISLER -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-server" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Servis Durumlari</div>
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
          <div class="servis-aktif"><i class="ti ti-circle-check" style="font-size:15px"></i> Calisiyor</div>
        <?php else: ?>
          <div class="servis-pasif"><i class="ti ti-circle-x" style="font-size:15px"></i> Durdu</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- SISTEM BILGILERI -->
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
      <tr><td>Disk Kullanimi</td><td><?= htmlspecialchars($disk_usage) ?></td></tr>
      <tr><td>RAM Kullanimi</td><td><?= htmlspecialchars($ram_usage) ?></td></tr>
    </table>
  </div>
</div>

<meta http-equiv="refresh" content="30">

<?php else: ?>
<!-- ALT KULLANICI DASHBOARD -->

<div class="stat-grid">
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-world" style="font-size:14px"></i> Domainlerim</div>
    <div class="stat-val"><?= $hesap_domain_sayisi ?></div>
    <div class="stat-sub">Aktif domain</div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-folder" style="font-size:14px"></i> Disk Kullanimi</div>
    <div class="stat-val" style="font-size:20px"><?= $hesap_disk ?></div>
    <div class="stat-sub">/home/<?= htmlspecialchars($kendi_hesap) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-brand-php" style="font-size:14px"></i> PHP Versiyonu</div>
    <div class="stat-val" style="font-size:18px"><?= $hesap_php ?: '-' ?></div>
    <div class="stat-sub">Aktif versiyon</div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-user" style="color:var(--blue)"></i></div>
      <div><div class="card-head-title">Hesap Bilgilerim</div></div>
    </div>
  </div>
  <div class="card-body">
    <table class="sys-table">
      <tr><td>Kullanici Adi</td><td><?= htmlspecialchars($_SESSION['gms_user']) ?></td></tr>
      <tr><td>Hesap</td><td><?= htmlspecialchars($kendi_hesap) ?></td></tr>
      <?php
        $paket_info = get_current_paket();
        if (!empty($paket_info)):
      ?>
      <tr><td>Paket</td><td><?= htmlspecialchars($paket_info['ISIM'] ?? '-') ?></td></tr>
      <tr><td>Max Domain</td><td><?= htmlspecialchars($paket_info['MAX_DOMAIN'] ?? '-') ?></td></tr>
      <tr><td>Max Veritabani</td><td><?= htmlspecialchars($paket_info['MAX_DB'] ?? '-') ?></td></tr>
      <tr><td>Disk Kotasi</td><td><?= isset($paket_info['DISK_MB']) ? round($paket_info['DISK_MB']/1024, 1) . ' GB' : '-' ?></td></tr>
      <?php endif; ?>
      <tr><td>PHP Versiyonu</td><td><?= $hesap_php ?: '-' ?></td></tr>
      <tr><td>Disk Kullanimi</td><td><?= htmlspecialchars($hesap_disk) ?></td></tr>
    </table>
  </div>
</div>

<?php endif; ?>

<?php layout_foot(); ?>
