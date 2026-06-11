<?php
require_once 'auth.php';
admin_only();
require_once 'layout.php';

// /home altındaki hesapları listele (panel kullanıcısı ve sistem kullanıcıları hariç)
$panel_user = $_SESSION['gms_user'];
$settings   = [];
$settings_file = "/home/{$panel_user}/config/settings.conf";
if (file_exists($settings_file)) {
    foreach (file($settings_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            [$k, $v] = explode('=', $line, 2);
            $settings[trim($k)] = trim($v);
        }
    }
}

// gmssys sistem kullanicisi ve diger sistem kullanicilari gizlenir
$sistem_kullanicilari = ['gmssys', 'nobody', 'root', 'nginx', 'apache', 'mysql', 'mariadb'];
$skip_users = array_merge([$panel_user, $settings['ANA_DOMAIN_KULLANICI'] ?? ''], $sistem_kullanicilari);

$hesaplar = [];
foreach (glob('/home/*', GLOB_ONLYDIR) as $dir) {
    $user = basename($dir);
    if (in_array($user, $skip_users)) continue;

    // PHP versiyonunu pool config'den tespit et
    $php_ver = '—';
    foreach (['84','83','82','81','80','74'] as $v) {
        if (file_exists("/etc/opt/remi/php{$v}/php-fpm.d/{$user}.conf")) {
            $php_ver = substr($v, 0, 1) . '.' . substr($v, 1);
            break;
        }
    }

    // Domain nginx config'den oku
    $domain = '—';
    foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
        $content = file_get_contents($conf);
        if (strpos($content, "/home/{$user}/") !== false) {
            if (preg_match('/server_name\s+([^\s;]+)/', $content, $m)) {
                $domain = $m[1];
            }
            break;
        }
    }

    // Disk kullanımı
    $disk = trim(shell_exec("du -sh /home/{$user} 2>/dev/null | cut -f1"));

    // SSL durumu
    $ssl = file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem");

    $hesaplar[] = compact('user', 'domain', 'php_ver', 'disk', 'ssl');
}

layout_head('Hesap Yönetimi', 'accounts');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div>
    <div style="font-size:13px;color:var(--text3)"><?= count($hesaplar) ?> hosting hesabı</div>
  </div>
  <a href="new_account.php" class="btn btn-primary">
    <i class="ti ti-plus"></i> Yeni Hesap
  </a>
</div>

<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-users" style="color:var(--blue)"></i></div>
      <div><div class="card-head-title">Hosting Hesapları</div></div>
    </div>
    <input type="text" id="search" placeholder="Hesap ara..." oninput="filterTable()"
      style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;padding:7px 12px;outline:none;width:200px">
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($hesaplar)): ?>
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <i class="ti ti-users" style="font-size:32px;display:block;margin-bottom:10px"></i>
      Henüz hosting hesabı yok.
      <br><br>
      <a href="new_account.php" class="btn btn-primary"><i class="ti ti-plus"></i> İlk Hesabı Oluştur</a>
    </div>
    <?php else: ?>
    <table class="gms-table" id="accounts-table">
      <thead>
        <tr>
          <th>Kullanıcı</th>
          <th>Domain</th>
          <th>PHP</th>
          <th>Disk</th>
          <th>SSL</th>
          <th>Durum</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($hesaplar as $h): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--bluebg);border:1px solid rgba(59,130,246,.3);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--blue)">
                <?= strtoupper(substr($h['user'], 0, 2)) ?>
              </div>
              <span style="font-weight:500"><?= htmlspecialchars($h['user']) ?></span>
            </div>
          </td>
          <td><a href="http://<?= htmlspecialchars($h['domain']) ?>" target="_blank" style="color:var(--blue);text-decoration:none"><?= htmlspecialchars($h['domain']) ?> ↗</a></td>
          <td><span class="badge badge-purple"><i class="ti ti-brand-php" style="font-size:11px"></i> <?= htmlspecialchars($h['php_ver']) ?></span></td>
          <td style="color:var(--text2)"><?= htmlspecialchars($h['disk']) ?></td>
          <td>
            <?php if ($h['ssl']): ?>
              <span class="badge badge-green"><i class="ti ti-lock" style="font-size:11px"></i> Aktif</span>
            <?php else: ?>
              <span class="badge badge-red"><i class="ti ti-lock-open" style="font-size:11px"></i> Yok</span>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-green">● Aktif</span></td>
          <td>
            <a href="edit_account.php?user=<?= urlencode($h['user']) ?>" class="btn btn-sm">
              <i class="ti ti-settings"></i> Düzenle
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<script>
function filterTable() {
  const q = document.getElementById('search').value.toLowerCase();
  document.querySelectorAll('#accounts-table tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>

<?php layout_foot(); ?>
