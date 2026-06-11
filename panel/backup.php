<?php
require_once 'auth.php';
admin_only();
require_once 'layout.php';

$success = '';
$error   = '';

// Yedek dizini
$backup_dir = '/var/gms/backups';

// Yedek al
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Hesap yedeği al
    if ($action === 'backup') {
        $hesap = trim($_POST['hesap'] ?? '');
        if (!preg_match('/^[a-z0-9_]+$/', $hesap) || !is_dir("/home/{$hesap}")) {
            $error = 'Gecersiz hesap.';
        } else {
            // Yedek dizinini olustur
            if (!is_dir($backup_dir)) mkdir($backup_dir, 0750, true);

            $tarih    = date('Ymd_His');
            $dosya    = "{$backup_dir}/{$hesap}_{$tarih}.tar.gz";
            $cmd      = "tar -czf " . escapeshellarg($dosya) . " -C /home " . escapeshellarg($hesap) . " 2>&1";
            $out      = shell_exec($cmd);

            if (file_exists($dosya)) {
                $success = "{$hesap} hesabi yedeklendi: " . basename($dosya) . " (" . round(filesize($dosya)/1024/1024, 2) . " MB)";
            } else {
                $error = "Yedekleme basarisiz: " . htmlspecialchars($out ?? '');
            }
        }
    }

    // Yedek sil
    if ($action === 'delete') {
        $dosya = basename(trim($_POST['dosya'] ?? ''));
        $tam_yol = $backup_dir . '/' . $dosya;
        if (preg_match('/^[a-z0-9_\-\.]+\.tar\.gz$/', $dosya) && file_exists($tam_yol)) {
            unlink($tam_yol);
            $success = "{$dosya} silindi.";
        } else {
            $error = 'Dosya bulunamadi veya gecersiz.';
        }
    }
}

// Mevcut yedekleri listele
$yedekler = [];
if (is_dir($backup_dir)) {
    foreach (glob($backup_dir . '/*.tar.gz') as $dosya) {
        $yedekler[] = [
            'ad'     => basename($dosya),
            'boyut'  => round(filesize($dosya)/1024/1024, 2) . ' MB',
            'tarih'  => date('d.m.Y H:i', filemtime($dosya)),
        ];
    }
    // En yeni once
    usort($yedekler, fn($a, $b) => strcmp($b['tarih'], $a['tarih']));
}

// Hesap listesi
$hesap_listesi = [];
foreach (glob('/home/*', GLOB_ONLYDIR) as $dir) {
    $h = basename($dir);
    $skip = ['gmssys', 'nobody', 'root'];
    if (in_array($h, $skip)) continue;
    if (is_dir($dir . '/public_html')) $hesap_listesi[] = $h;
}

layout_head('Yedekleme', 'backup');
?>

<?php if ($success): ?>
<div style="background:var(--greenbg);border:1px solid rgba(34,197,94,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--green);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-circle-check" style="font-size:16px"></i><span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:var(--redbg);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:20px;display:flex;gap:10px">
  <i class="ti ti-alert-circle" style="font-size:16px"></i><span><?= $error ?></span>
</div>
<?php endif; ?>

<!-- STAT -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-bottom:20px">
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-archive" style="font-size:14px"></i> Toplam Yedek</div>
    <div class="stat-val"><?= count($yedekler) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-folder" style="font-size:14px"></i> Yedek Dizini</div>
    <div class="stat-val" style="font-size:13px;margin-top:6px"><code style="color:var(--blue)"><?= $backup_dir ?></code></div>
  </div>
</div>

<!-- YEDEK AL -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-archive" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Yedek Al</div>
        <div class="card-head-sub">Hosting hesabini tar.gz olarak yedekler</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="action" value="backup">
      <div style="flex:1;min-width:200px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Hesap</label>
        <select name="hesap" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <option value="">-- Hesap secin --</option>
          <?php foreach ($hesap_listesi as $h): ?>
          <option value="<?= htmlspecialchars($h) ?>"><?= htmlspecialchars($h) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-archive"></i> Yedek Al</button>
    </form>
    <div style="font-size:11px;color:var(--text3);margin-top:10px">
      <i class="ti ti-info-circle" style="font-size:12px"></i>
      Buyuk hesaplar icin yedekleme birkaç dakika surebilir. Sayfa zaman asimina ugramasın diye sabırla bekleyin.
    </div>
  </div>
</div>

<!-- YEDEK LISTESI -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bg3)"><i class="ti ti-files" style="color:var(--text2)"></i></div>
      <div><div class="card-head-title">Mevcut Yedekler</div></div>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($yedekler)): ?>
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <i class="ti ti-archive" style="font-size:32px;display:block;margin-bottom:10px"></i>
      Henuz yedek yok.
    </div>
    <?php else: ?>
    <table class="gms-table">
      <thead>
        <tr><th>Dosya</th><th>Boyut</th><th>Tarih</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($yedekler as $y): ?>
        <tr>
          <td><i class="ti ti-file-zip" style="color:var(--amber);margin-right:6px"></i><?= htmlspecialchars($y['ad']) ?></td>
          <td style="color:var(--text2)"><?= $y['boyut'] ?></td>
          <td style="color:var(--text3);font-size:12px"><?= $y['tarih'] ?></td>
          <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Bu yedegi silmek istiyor musunuz?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="dosya" value="<?= htmlspecialchars($y['ad']) ?>">
              <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php layout_foot(); ?>
