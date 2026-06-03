<?php
require_once 'auth.php';
require_once 'layout.php';

$user    = $_GET['user'] ?? '';
$error   = '';
$success = '';

// Kullanici dogrulama
if (empty($user) || !preg_match('/^[a-z0-9_]+$/', $user) || !is_dir('/home/' . $user)) {
    header('Location: accounts.php');
    exit;
}

// PHP versiyonunu tespit et
$mevcut_php = '83';
foreach (['84','83','82','81','80','74'] as $v) {
    if (file_exists("/etc/opt/remi/php{$v}/php-fpm.d/{$user}.conf")) {
        $mevcut_php = $v;
        break;
    }
}

// Domain tespit et
$mevcut_domain = '';
foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
    $content = file_get_contents($conf);
    if (strpos($content, "/home/{$user}/") !== false) {
        if (preg_match('/server_name\s+([^\s;]+)/', $content, $m)) {
            $mevcut_domain = $m[1];
        }
        break;
    }
}

// Disk kullanimi
$disk = trim(shell_exec("du -sh /home/{$user} 2>/dev/null | cut -f1"));

// POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // PHP versiyonu degistir
    if ($action === 'php') {
        $yeni_php = $_POST['php'] ?? '';
        if (!in_array($yeni_php, ['74','80','81','82','83','84'])) {
            $error = 'Gecersiz PHP versiyonu.';
        } elseif ($yeni_php === $mevcut_php) {
            $error = 'Secilen PHP versiyonu zaten aktif.';
        } else {
            // Eski pool sil
            $eski_conf = "/etc/opt/remi/php{$mevcut_php}/php-fpm.d/{$user}.conf";
            if (file_exists($eski_conf)) unlink($eski_conf);
            shell_exec("systemctl restart php{$mevcut_php}-php-fpm 2>/dev/null");

            // Yeni pool olustur
            $pool = "[{$user}]
user = {$user}
group = {$user}
listen = /var/opt/remi/php{$yeni_php}/run/php-fpm/{$user}.sock
listen.owner = nginx
listen.group = nginx
pm = dynamic
pm.max_children = 5
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 3
";
            file_put_contents("/etc/opt/remi/php{$yeni_php}/php-fpm.d/{$user}.conf", $pool);
            shell_exec("systemctl restart php{$yeni_php}-php-fpm 2>/dev/null");

            // Nginx config guncelle
            foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
                $content = file_get_contents($conf);
                if (strpos($content, "/home/{$user}/") !== false) {
                    $yeni = preg_replace(
                        '/php\d{2}\/run\/php-fpm\/' . preg_quote($user, '/') . '\.sock/',
                        "php{$yeni_php}/run/php-fpm/{$user}.sock",
                        $content
                    );
                    file_put_contents($conf, $yeni);
                    break;
                }
            }
            shell_exec("nginx -t && systemctl reload nginx 2>/dev/null");

            $mevcut_php = $yeni_php;
            $success = "PHP versiyonu PHP {$yeni_php} olarak guncellendi.";
        }
    }

    // Hesabi sil
    if ($action === 'delete' && ($_POST['confirm'] ?? '') === $user) {
        // PHP pool sil
        $pool_conf = "/etc/opt/remi/php{$mevcut_php}/php-fpm.d/{$user}.conf";
        if (file_exists($pool_conf)) unlink($pool_conf);
        shell_exec("systemctl restart php{$mevcut_php}-php-fpm 2>/dev/null");

        // Nginx config sil
        foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
            $content = file_get_contents($conf);
            if (strpos($content, "/home/{$user}/") !== false) {
                unlink($conf);
                break;
            }
        }
        shell_exec("nginx -t && systemctl reload nginx 2>/dev/null");

        // Linux kullanicisini sil
        shell_exec("userdel -r " . escapeshellarg($user) . " 2>/dev/null");

        header('Location: accounts.php?deleted=' . urlencode($user));
        exit;
    }
}

$php_versiyonlar = ['84'=>'PHP 8.4','83'=>'PHP 8.3','82'=>'PHP 8.2','81'=>'PHP 8.1','80'=>'PHP 8.0','74'=>'PHP 7.4'];

layout_head('Hesap Duzenleme: ' . $user, 'accounts');
?>

<div style="max-width:600px">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <a href="accounts.php" class="btn btn-sm"><i class="ti ti-arrow-left"></i> Geri</a>
  <div>
    <div style="font-size:16px;font-weight:700"><?= htmlspecialchars($user) ?></div>
    <div style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($mevcut_domain) ?> &nbsp;·&nbsp; Disk: <?= htmlspecialchars($disk) ?></div>
  </div>
</div>

<?php if ($error): ?>
<div style="background:var(--redbg);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-alert-circle" style="font-size:16px"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div style="background:var(--greenbg);border:1px solid rgba(34,197,94,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--green);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-circle-check" style="font-size:16px"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- PHP VERSIYON -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--purplebg)"><i class="ti ti-brand-php" style="color:var(--purple)"></i></div>
      <div>
        <div class="card-head-title">PHP Versiyonu</div>
        <div class="card-head-sub">Mevcut: PHP <?= $mevcut_php[0] . '.' . $mevcut_php[1] ?></div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="action" value="php">
      <div style="display:flex;gap:10px;align-items:center">
        <select name="php" style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:9px 12px;outline:none">
          <?php foreach ($php_versiyonlar as $val => $label): ?>
          <option value="<?= $val ?>" <?= $mevcut_php === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="ti ti-refresh"></i> Guncelle</button>
      </div>
    </form>
  </div>
</div>

<!-- DOSYA YOLU BILGISI -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bg3)"><i class="ti ti-folder" style="color:var(--text2)"></i></div>
      <div><div class="card-head-title">Dosya Yollari</div></div>
    </div>
  </div>
  <div class="card-body">
    <table class="sys-table">
      <tr><td>Web Klasoru</td><td><code style="font-size:12px;color:var(--blue)">/home/<?= htmlspecialchars($user) ?>/public_html/</code></td></tr>
      <tr><td>Log Klasoru</td><td><code style="font-size:12px;color:var(--blue)">/home/<?= htmlspecialchars($user) ?>/logs/</code></td></tr>
      <tr><td>PHP Pool</td><td><code style="font-size:12px;color:var(--purple)">/etc/opt/remi/php<?= $mevcut_php ?>/php-fpm.d/<?= htmlspecialchars($user) ?>.conf</code></td></tr>
      <tr><td>Nginx Config</td><td><code style="font-size:12px;color:var(--green)">/etc/nginx/conf.d/<?= htmlspecialchars($mevcut_domain) ?>.conf</code></td></tr>
      <tr><td>Disk Kullanimi</td><td><?= htmlspecialchars($disk) ?></td></tr>
    </table>
  </div>
</div>

<!-- HESAP SIL -->
<div class="card" style="border-color:rgba(239,68,68,.3)">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--redbg)"><i class="ti ti-trash" style="color:var(--red)"></i></div>
      <div>
        <div class="card-head-title" style="color:var(--red)">Hesabi Sil</div>
        <div class="card-head-sub">Bu islem geri alinamaz!</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div style="font-size:13px;color:var(--text2);margin-bottom:14px">
      Hesap silindiginde: Linux kullanicisi, tum dosyalar (/home/<?= htmlspecialchars($user) ?>), PHP-FPM pool ve Nginx config kalici olarak silinir.
    </div>
    <form method="POST" onsubmit="return confirmDelete()">
      <input type="hidden" name="action" value="delete">
      <div style="display:flex;gap:10px;align-items:center">
        <input type="text" name="confirm" id="confirm-input" placeholder="Onaylamak icin kullanici adini yaz: <?= htmlspecialchars($user) ?>"
          style="flex:1;background:var(--bg3);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);color:var(--text);font-size:13px;padding:9px 12px;outline:none">
        <button type="submit" class="btn btn-danger"><i class="ti ti-trash"></i> Sil</button>
      </div>
    </form>
  </div>
</div>

</div>

<script>
function confirmDelete() {
  const val = document.getElementById('confirm-input').value.trim();
  if (val !== '<?= addslashes($user) ?>') {
    alert('Kullanici adi eslesmiyor!');
    return false;
  }
  return confirm('<?= addslashes($user) ?> hesabi ve tum dosyalari kalici olarak silinecek. Emin misiniz?');
}
</script>

<?php layout_foot(); ?>
