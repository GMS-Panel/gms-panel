<?php
require_once 'auth.php';
admin_only();
require_once 'layout.php';

$success = '';
$error   = '';

// URL'den veya POST'tan hesap al
$user = trim($_GET['user'] ?? $_POST['user'] ?? '');

// Gecerli hosting hesaplarini listele
$hesap_listesi = [];
foreach (glob('/home/*', GLOB_ONLYDIR) as $dir) {
    $h = basename($dir);
    $skip = ['gmssys', 'nobody', 'root', 'nginx', 'apache', 'mysql'];
    if (in_array($h, $skip)) continue;
    if (is_dir($dir . '/public_html')) $hesap_listesi[] = $h;
}

// Kullanicinin PHP versiyonu ve pool dosyasini bul
$mevcut_php = '';
$pool_conf  = '';
if ($user) {
    foreach (['84','83','82','81','80','74'] as $v) {
        $conf = "/etc/opt/remi/php{$v}/php-fpm.d/{$user}.conf";
        if (file_exists($conf)) {
            $mevcut_php = $v;
            $pool_conf  = $conf;
            break;
        }
    }
    if (!$mevcut_php) {
        $error = "'{$user}' icin PHP pool bulunamadi.";
        $user  = '';
    }
}

// Duzenlenebilir PHP ayarlari ve varsayilanlari
$ayar_tanim = [
    'memory_limit'        => ['label'=>'Bellek Limiti',          'type'=>'select', 'opts'=>['64M','128M','256M','512M','1G'],  'default'=>'128M'],
    'upload_max_filesize' => ['label'=>'Maksimum Yuklenecek Dosya','type'=>'select','opts'=>['16M','32M','64M','128M','256M'], 'default'=>'32M'],
    'post_max_size'       => ['label'=>'Maksimum POST Boyutu',    'type'=>'select', 'opts'=>['16M','32M','64M','128M','256M'], 'default'=>'32M'],
    'max_execution_time'  => ['label'=>'Maksimum Calisma Suresi', 'type'=>'select', 'opts'=>['30','60','120','300','600'],      'default'=>'60'],
    'max_input_time'      => ['label'=>'Maksimum Girdi Suresi',   'type'=>'select', 'opts'=>['60','120','300','600'],           'default'=>'120'],
    'max_input_vars'      => ['label'=>'Maksimum Girdi Degiskeni','type'=>'select', 'opts'=>['1000','3000','5000','10000'],     'default'=>'3000'],
    'display_errors'      => ['label'=>'Hata Goster',             'type'=>'select', 'opts'=>['Off','On'],                      'default'=>'Off'],
    'error_reporting'     => ['label'=>'Hata Seviyesi',           'type'=>'select', 'opts'=>['E_ALL','E_ERROR','0'],           'default'=>'E_ALL'],
    'session.gc_maxlifetime'=>['label'=>'Oturum Suresi (sn)',     'type'=>'select', 'opts'=>['1440','3600','7200','86400'],     'default'=>'1440'],
];

// Pool dosyasindaki mevcut php_value ayarlarini oku
function pool_ayarlari_oku(string $conf_dosya): array {
    $ayarlar = [];
    if (!file_exists($conf_dosya)) return $ayarlar;
    foreach (file($conf_dosya, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $satir) {
        // "php_value[memory_limit] = 256M" veya "php_admin_value[display_errors] = Off"
        if (preg_match('/^php(?:_admin)?_value\[(.+?)\]\s*=\s*(.+)$/', trim($satir), $m)) {
            $ayarlar[trim($m[1])] = trim($m[2]);
        }
    }
    return $ayarlar;
}

// Pool dosyasina php_value satirlarini yaz (temp dosya + sudo kopya)
function pool_ayarlari_yaz(string $conf_dosya, array $yeni_ayarlar): bool {
    if (!file_exists($conf_dosya)) return false;
    $satirlar = file($conf_dosya, FILE_IGNORE_NEW_LINES);

    // Mevcut php_value satirlarini kaldir
    $satirlar = array_filter($satirlar, function($s) {
        return !preg_match('/^php(?:_admin)?_value\[/', trim($s));
    });

    // Yeni ayarlari sona ekle
    foreach ($yeni_ayarlar as $key => $val) {
        // display_errors ve error_reporting icin php_admin_value kullan
        $tip = in_array($key, ['display_errors', 'error_reporting']) ? 'php_admin_value' : 'php_value';
        $satirlar[] = "{$tip}[{$key}] = {$val}";
    }

    // Gecici dosyaya yaz, sonra sudo ile hedef konuma kopyala
    $tmp = tempnam(sys_get_temp_dir(), 'gms_pool_');
    if (!$tmp) return false;
    $icerik = implode("\n", $satirlar) . "\n";
    if (file_put_contents($tmp, $icerik) === false) {
        unlink($tmp);
        return false;
    }
    $out = shell_exec("/usr/bin/sudo /usr/local/bin/php-pool-yaz.sh " . escapeshellarg($conf_dosya) . " " . escapeshellarg($tmp) . " 2>&1");
    unlink($tmp);
    return trim($out ?? '') === 'OK';
}

// POST: ayarlari kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && $pool_conf) {
    $kaydedilecek = [];
    foreach ($ayar_tanim as $key => $tanim) {
        $val = trim($_POST[$key] ?? $tanim['default']);
        // Degeri dogrula (sadece tanimlanan seceneklerden biri olmali)
        if (in_array($val, $tanim['opts'])) {
            $kaydedilecek[$key] = $val;
        }
    }

    if (pool_ayarlari_yaz($pool_conf, $kaydedilecek)) {
        // PHP-FPM servisini yeniden yukle
        $reload = shell_exec("/usr/bin/sudo /usr/bin/systemctl reload php{$mevcut_php}-php-fpm 2>&1");
        $success = "{$user} icin PHP ayarlari kaydedildi. PHP-FPM yeniden yuklendi.";
    } else {
        $error = "Ayarlar kaydedilemedi. Pool dosyasi yazma hatasi.";
    }
}

// Mevcut ayarlari oku (POST sonrasi guncellenmis halini goster)
$mevcut_ayarlar = $pool_conf ? pool_ayarlari_oku($pool_conf) : [];

// PHP versiyon formatlama: "83" -> "8.3"
$php_fmt = $mevcut_php ? ($mevcut_php[0] . '.' . $mevcut_php[1]) : '';

layout_head('PHP Ayarlari', 'php_settings');
?>

<?php if ($success): ?>
<div style="background:var(--greenbg);border:1px solid rgba(34,197,94,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--green);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-circle-check" style="font-size:16px"></i><span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:var(--redbg);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-alert-circle" style="font-size:16px"></i><span><?= $error ?></span>
</div>
<?php endif; ?>

<!-- HESAP SEC -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--purplebg)"><i class="ti ti-brand-php" style="color:var(--purple)"></i></div>
      <div>
        <div class="card-head-title">PHP Ayarlari</div>
        <div class="card-head-sub">Hesap bazinda php.ini degerlerini duzenle</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Hosting Hesabi</label>
        <select name="user" onchange="this.form.submit()"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <option value="">-- Hesap secin --</option>
          <?php foreach ($hesap_listesi as $h): ?>
          <option value="<?= htmlspecialchars($h) ?>" <?= $h === $user ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($user): ?>
      <div style="display:flex;gap:8px;align-items:center;padding-bottom:2px">
        <span class="badge badge-purple"><i class="ti ti-brand-php" style="font-size:11px"></i> PHP <?= $php_fmt ?></span>
        <a href="edit_account.php?user=<?= urlencode($user) ?>" class="btn btn-sm">
          <i class="ti ti-settings"></i> Hesap Duzenle
        </a>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($user && $pool_conf): ?>
<!-- PHP AYARLARI FORMU -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--purplebg)"><i class="ti ti-adjustments" style="color:var(--purple)"></i></div>
      <div>
        <div class="card-head-title"><?= htmlspecialchars($user) ?> — PHP <?= $php_fmt ?> Ayarlari</div>
        <div class="card-head-sub">
          <code style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($pool_conf) ?></code>
        </div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="user" value="<?= htmlspecialchars($user) ?>">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px">
        <?php foreach ($ayar_tanim as $key => $tanim):
            $deger = $mevcut_ayarlar[$key] ?? $tanim['default'];
        ?>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
            <?= $tanim['label'] ?>
            <span style="font-weight:400;color:var(--text3);font-family:monospace;font-size:11px"> <?= $key ?></span>
          </label>
          <select name="<?= $key ?>"
            style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:9px 12px;outline:none">
            <?php foreach ($tanim['opts'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $deger === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:10px;align-items:center">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Kaydet ve Uygula</button>
        <span style="font-size:12px;color:var(--text3)">
          <i class="ti ti-info-circle" style="font-size:13px"></i>
          Kaydetme sonrasi PHP-FPM otomatik yeniden yuklenir.
        </span>
      </div>
    </form>
  </div>
</div>

<?php elseif (!$user): ?>
<div style="padding:40px;text-align:center;color:var(--text3)">
  <i class="ti ti-brand-php" style="font-size:48px;display:block;margin-bottom:12px;color:var(--purple);opacity:.4"></i>
  Yukardaki listeden bir hosting hesabi secin.
</div>
<?php endif; ?>

<?php layout_foot(); ?>
