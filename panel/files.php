<?php
// files.php - Dosya yoneticisi (home dizini tabanli)
require_once 'auth.php';
require_once 'layout.php';

$is_admin = ($_SESSION['gms_role'] ?? '') === 'admin';

// --- Gecerli hosting hesaplarini listele ---
function hesap_listesi(): array {
    $liste = [];
    $skip  = ['gmssys','nobody','root','nginx','apache','mysql','mariadb'];
    foreach (glob('/home/*', GLOB_ONLYDIR) as $d) {
        $h = basename($d);
        if (!in_array($h, $skip)) $liste[] = $h;
    }
    return $liste;
}

// --- Hangi hesap? ---
if ($is_admin) {
    $hesap = trim($_GET['hesap'] ?? $_POST['hesap'] ?? '');
    if (!$hesap) {
        $liste = hesap_listesi();
        // Hesap secilmemisse secim ekrani goster
        layout_head('Dosya Yoneticisi', 'files');
        ?>
        <div class="card" style="max-width:480px">
          <div class="card-head">
            <div class="card-head-left">
              <div class="card-head-icon" style="background:var(--amberbg)"><i class="ti ti-folder" style="color:var(--amber)"></i></div>
              <div><div class="card-head-title">Dosya Yoneticisi</div><div class="card-head-sub">Yonetmek istediginiz hesabi secin</div></div>
            </div>
          </div>
          <div class="card-body">
            <form method="GET">
              <select name="hesap" onchange="this.form.submit()"
                style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
                <option value="">-- Hesap secin --</option>
                <?php foreach ($liste as $h): ?>
                <option value="<?= htmlspecialchars($h) ?>"><?= htmlspecialchars($h) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>
        <?php
        layout_foot();
        exit;
    }
} else {
    // Alt kullanici: sadece kendi hesabi
    $hesap = $_SESSION['gms_hesap'] ?? '';
}

// --- Hesap ve kok dizini dogrula ---
$kok = "/home/{$hesap}";
if (!$hesap || !is_dir($kok)) {
    layout_head('Dosya Yoneticisi', 'files');
    echo '<div class="alert-box alert-error"><i class="ti ti-alert-circle"></i> Gecersiz hesap.</div>';
    layout_foot();
    exit;
}

// --- Aktif dizin (path traversal koruması) ---
// URL'den gelen 'dir' parametresini kok altinda tut
$raw_dir = $_GET['dir'] ?? $kok;
$cur_dir  = realpath($raw_dir);
// realpath basarisizsa veya kok disindaysa kok'e don
if (!$cur_dir || strpos($cur_dir . '/', $kok . '/') !== 0) {
    $cur_dir = $kok;
}

// --- POST islemleri ---
$mesaj = '';
$hata  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eylem = $_POST['eylem'] ?? '';

    // Hedef yolu guvenlice coz
    $hedef_raw = $_POST['hedef'] ?? $cur_dir;
    $hedef     = realpath($hedef_raw);
    if (!$hedef || strpos($hedef . '/', $kok . '/') !== 0) {
        $hedef = $cur_dir;
    }

    // --- Yeni klasor olustur ---
    if ($eylem === 'mkdir') {
        $ad = basename(trim($_POST['ad'] ?? ''));
        if (!$ad || !preg_match('/^[a-zA-Z0-9_\-. ]+$/', $ad)) {
            $hata = 'Gecersiz klasor adi.';
        } else {
            $yol = $hedef . '/' . $ad;
            if (is_dir($yol)) {
                $hata = "'{$ad}' zaten mevcut.";
            } elseif (mkdir($yol, 0755)) {
                $mesaj = "'{$ad}' klasoru olusturuldu.";
            } else {
                $hata = 'Klasor olusturulamadi.';
            }
        }
    }

    // --- Dosya sil ---
    if ($eylem === 'sil') {
        $sil_yol = realpath($_POST['yol'] ?? '');
        if (!$sil_yol || strpos($sil_yol . '/', $kok . '/') !== 0) {
            $hata = 'Gecersiz yol.';
        } elseif (is_dir($sil_yol)) {
            // Klasoru recursive sil
            function rm_rf(string $p): bool {
                if (!is_dir($p)) return unlink($p);
                foreach (scandir($p) as $e) {
                    if ($e === '.' || $e === '..') continue;
                    rm_rf($p . '/' . $e);
                }
                return rmdir($p);
            }
            if (rm_rf($sil_yol)) $mesaj = 'Silindi.';
            else $hata = 'Silinemedi.';
        } else {
            if (unlink($sil_yol)) $mesaj = 'Dosya silindi.';
            else $hata = 'Dosya silinemedi.';
        }
    }

    // --- Dosya yukle ---
    if ($eylem === 'yukle') {
        if (!isset($_FILES['dosya']) || $_FILES['dosya']['error'] !== UPLOAD_ERR_OK) {
            $hata = 'Yuklenemedi. Dosya hatasi.';
        } else {
            $ad   = basename($_FILES['dosya']['name']);
            $yol  = $hedef . '/' . $ad;
            if (move_uploaded_file($_FILES['dosya']['tmp_name'], $yol)) {
                $mesaj = "'{$ad}' yuklendi.";
            } else {
                $hata = 'Dosya tasinamadi.';
            }
        }
    }

    // --- Dosya kaydet (editor) ---
    if ($eylem === 'kaydet') {
        $edit_yol = realpath($_POST['yol'] ?? '');
        if (!$edit_yol || strpos($edit_yol . '/', $kok . '/') !== 0 || !is_file($edit_yol)) {
            $hata = 'Gecersiz dosya.';
        } else {
            $icerik = $_POST['icerik'] ?? '';
            if (file_put_contents($edit_yol, $icerik) !== false) {
                $mesaj = 'Dosya kaydedildi.';
            } else {
                $hata = 'Kaydetme basarisiz.';
            }
        }
    }

    // POST sonrasi redirect (tekrar submit engellemek icin)
    if (!$hata && $mesaj) {
        // mesaji session'da tasiyalim
        $_SESSION['fm_mesaj'] = $mesaj;
        $redirect_dir = is_dir($cur_dir) ? $cur_dir : $kok;
        $q = http_build_query(['hesap' => $hesap, 'dir' => $redirect_dir]);
        header("Location: files.php?{$q}");
        exit;
    }
}

// Session mesajini al
if (!empty($_SESSION['fm_mesaj'])) {
    $mesaj = $_SESSION['fm_mesaj'];
    unset($_SESSION['fm_mesaj']);
}

// --- Dosya indir ---
if (isset($_GET['indir'])) {
    $indir_yol = realpath($_GET['indir']);
    if ($indir_yol && strpos($indir_yol . '/', $kok . '/') !== 0) $indir_yol = null;
    if ($indir_yol && is_file($indir_yol)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($indir_yol) . '"');
        header('Content-Length: ' . filesize($indir_yol));
        readfile($indir_yol);
        exit;
    }
}

// --- Metin editor ---
$editor_aktif = false;
$editor_icerik = '';
$editor_yol    = '';
$edit_uzantilar = ['php','html','htm','txt','conf','ini','js','css','json','xml','sh','md','log','htaccess','env'];

if (isset($_GET['edit'])) {
    $edit_yol_raw = realpath($_GET['edit']);
    if ($edit_yol_raw && strpos($edit_yol_raw . '/', $kok . '/') !== 0) $edit_yol_raw = null;
    if ($edit_yol_raw && is_file($edit_yol_raw)) {
        $uzanti = strtolower(pathinfo($edit_yol_raw, PATHINFO_EXTENSION));
        if (in_array($uzanti, $edit_uzantilar) && filesize($edit_yol_raw) < 512*1024) {
            $editor_aktif  = true;
            $editor_yol    = $edit_yol_raw;
            $editor_icerik = file_get_contents($edit_yol_raw);
        }
    }
}

// --- Dizin icerigi oku ---
$icerik_liste = [];
if (is_dir($cur_dir)) {
    $girdi = @scandir($cur_dir);
    if ($girdi) {
        foreach ($girdi as $e) {
            if ($e === '.' || $e === '..') continue;
            $tam = $cur_dir . '/' . $e;
            $icerik_liste[] = [
                'ad'      => $e,
                'tam'     => $tam,
                'dizin'   => is_dir($tam),
                'boyut'   => is_file($tam) ? filesize($tam) : 0,
                'tarih'   => filemtime($tam),
            ];
        }
        // Klasorler once, sonra dosyalar; alfabe sirasinda
        usort($icerik_liste, function($a, $b) {
            if ($a['dizin'] !== $b['dizin']) return $b['dizin'] - $a['dizin'];
            return strcmp($a['ad'], $b['ad']);
        });
    }
}

// --- Boyut formatlama ---
function fmt_boyut(int $b): string {
    if ($b >= 1048576) return round($b/1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b/1024, 1) . ' KB';
    return $b . ' B';
}

// --- Breadcrumb olustur ---
function breadcrumb(string $kok, string $cur): array {
    $bc = [];
    $p  = $cur;
    while (strlen($p) >= strlen($kok)) {
        $bc[] = ['yol' => $p, 'ad' => ($p === $kok ? '~' : basename($p))];
        if ($p === $kok) break;
        $p = dirname($p);
    }
    return array_reverse($bc);
}
$bc = breadcrumb($kok, $cur_dir);

// --- Parent dizin (yukari git) ---
$parent_dir = dirname($cur_dir);
if (strpos($parent_dir . '/', $kok . '/') !== 0 || $parent_dir === $cur_dir) {
    $parent_dir = null;
}

layout_head('Dosya Yoneticisi', 'files');
?>

<?php if ($mesaj): ?>
<div class="alert-box alert-success" style="margin-bottom:16px"><i class="ti ti-circle-check"></i> <?= htmlspecialchars($mesaj) ?></div>
<?php endif; ?>
<?php if ($hata): ?>
<div class="alert-box alert-error" style="margin-bottom:16px"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($hata) ?></div>
<?php endif; ?>

<?php if ($editor_aktif): ?>
<!-- METIN EDITORU -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
  <a href="files.php?<?= http_build_query(['hesap'=>$hesap,'dir'=>dirname($editor_yol)]) ?>" class="btn btn-sm"><i class="ti ti-arrow-left"></i> Geri</a>
  <div>
    <div style="font-size:15px;font-weight:700"><?= htmlspecialchars(basename($editor_yol)) ?></div>
    <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($editor_yol) ?></div>
  </div>
</div>
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--amberbg)"><i class="ti ti-file-code" style="color:var(--amber)"></i></div>
      <div><div class="card-head-title">Dosya Duzenle</div></div>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <form method="POST">
      <input type="hidden" name="eylem" value="kaydet">
      <input type="hidden" name="hesap" value="<?= htmlspecialchars($hesap) ?>">
      <input type="hidden" name="yol" value="<?= htmlspecialchars($editor_yol) ?>">
      <textarea name="icerik" spellcheck="false"
        style="width:100%;min-height:500px;background:var(--bg);border:none;border-bottom:1px solid var(--border);color:var(--text);font-family:'Cascadia Code','Fira Mono',monospace;font-size:13px;line-height:1.6;padding:16px;resize:vertical;outline:none;tab-size:4"><?= htmlspecialchars($editor_icerik) ?></textarea>
      <div style="padding:12px 16px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Kaydet</button>
        <a href="files.php?<?= http_build_query(['hesap'=>$hesap,'dir'=>dirname($editor_yol)]) ?>" class="btn">Iptal</a>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
<!-- DOSYA YONETICISI ANA GORUNUM -->

<!-- Ust bar: hesap badge + breadcrumb -->
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px">
  <?php if ($is_admin): ?>
  <a href="files.php" class="btn btn-sm"><i class="ti ti-users"></i> Hesap Sec</a>
  <span class="badge badge-blue"><i class="ti ti-user" style="font-size:11px"></i> <?= htmlspecialchars($hesap) ?></span>
  <?php endif; ?>
  <!-- Breadcrumb -->
  <div style="display:flex;align-items:center;gap:4px;font-size:13px;color:var(--text2);flex-wrap:wrap">
    <?php foreach ($bc as $i => $b): ?>
      <?php if ($i > 0): ?><span style="color:var(--text3)">/</span><?php endif; ?>
      <?php if ($i === count($bc)-1): ?>
        <span style="color:var(--text);font-weight:600"><?= htmlspecialchars($b['ad']) ?></span>
      <?php else: ?>
        <a href="files.php?<?= http_build_query(['hesap'=>$hesap,'dir'=>$b['yol']]) ?>" style="color:var(--blue);text-decoration:none"><?= htmlspecialchars($b['ad']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<!-- Arac cubugu: yukle + yeni klasor -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
  <!-- Dosya yukle -->
  <form method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
    <input type="hidden" name="eylem" value="yukle">
    <input type="hidden" name="hesap" value="<?= htmlspecialchars($hesap) ?>">
    <input type="hidden" name="hedef" value="<?= htmlspecialchars($cur_dir) ?>">
    <label style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--radius);font-size:13px;font-weight:600;cursor:pointer;color:var(--text)">
      <i class="ti ti-upload"></i>
      <span>Dosya Sec</span>
      <input type="file" name="dosya" style="display:none" onchange="this.form.submit()">
    </label>
  </form>
  <!-- Yeni klasor -->
  <form method="POST" style="display:flex;gap:8px;align-items:center">
    <input type="hidden" name="eylem" value="mkdir">
    <input type="hidden" name="hesap" value="<?= htmlspecialchars($hesap) ?>">
    <input type="hidden" name="hedef" value="<?= htmlspecialchars($cur_dir) ?>">
    <input type="text" name="ad" placeholder="Yeni klasor adi"
      style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;padding:7px 11px;outline:none;width:180px">
    <button type="submit" class="btn btn-sm"><i class="ti ti-folder-plus"></i> Olustur</button>
  </form>
</div>

<!-- Dosya listesi -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--amberbg)"><i class="ti ti-folder-open" style="color:var(--amber)"></i></div>
      <div>
        <div class="card-head-title">Dizin Icerigi</div>
        <div class="card-head-sub"><?= count($icerik_liste) ?> eleman</div>
      </div>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table class="gms-table">
      <thead>
        <tr>
          <th>Ad</th>
          <th>Boyut</th>
          <th>Tarih</th>
          <th style="text-align:right">Islemler</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($parent_dir): ?>
        <tr>
          <td colspan="4">
            <a href="files.php?<?= http_build_query(['hesap'=>$hesap,'dir'=>$parent_dir]) ?>"
               style="color:var(--text2);text-decoration:none;display:inline-flex;align-items:center;gap:8px">
              <i class="ti ti-corner-left-up" style="color:var(--amber)"></i>
              <span>.. (Yukari Git)</span>
            </a>
          </td>
        </tr>
        <?php endif; ?>

        <?php if (empty($icerik_liste)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:32px">Bu dizin bos.</td></tr>
        <?php endif; ?>

        <?php foreach ($icerik_liste as $e): ?>
        <tr>
          <td>
            <?php if ($e['dizin']): ?>
              <a href="files.php?<?= http_build_query(['hesap'=>$hesap,'dir'=>$e['tam']]) ?>"
                 style="color:var(--amber);text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:500">
                <i class="ti ti-folder-filled"></i> <?= htmlspecialchars($e['ad']) ?>
              </a>
            <?php else: ?>
              <?php
              $uzanti = strtolower(pathinfo($e['ad'], PATHINFO_EXTENSION));
              $ikon   = match($uzanti) {
                  'php'               => 'ti-brand-php',
                  'html','htm'        => 'ti-brand-html5',
                  'js'                => 'ti-brand-javascript',
                  'css'               => 'ti-brand-css3',
                  'json'              => 'ti-braces',
                  'xml'               => 'ti-code',
                  'sh','bash'         => 'ti-terminal',
                  'txt','md'          => 'ti-file-text',
                  'log'               => 'ti-file-analytics',
                  'jpg','jpeg','png','gif','webp','svg' => 'ti-photo',
                  'zip','tar','gz','bz2','rar'          => 'ti-file-zip',
                  'sql'               => 'ti-database',
                  default             => 'ti-file',
              };
              ?>
              <span style="display:inline-flex;align-items:center;gap:8px;color:var(--text2)">
                <i class="ti <?= $ikon ?>" style="color:var(--text3)"></i>
                <?= htmlspecialchars($e['ad']) ?>
              </span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text3);font-size:12px">
            <?= $e['dizin'] ? '—' : fmt_boyut($e['boyut']) ?>
          </td>
          <td style="color:var(--text3);font-size:12px">
            <?= date('d.m.Y H:i', $e['tarih']) ?>
          </td>
          <td style="text-align:right">
            <div style="display:inline-flex;gap:6px">
              <?php if (!$e['dizin']): ?>
                <!-- Indir -->
                <a href="files.php?<?= http_build_query(['hesap'=>$hesap,'dir'=>$cur_dir,'indir'=>$e['tam']]) ?>"
                   class="btn btn-sm" title="Indir"><i class="ti ti-download"></i></a>
                <?php if (in_array(strtolower(pathinfo($e['ad'],PATHINFO_EXTENSION)), $edit_uzantilar) && $e['boyut'] < 512*1024): ?>
                <!-- Duzenle -->
                <a href="files.php?<?= http_build_query(['hesap'=>$hesap,'dir'=>$cur_dir,'edit'=>$e['tam']]) ?>"
                   class="btn btn-sm" title="Duzenle"><i class="ti ti-pencil"></i></a>
                <?php endif; ?>
              <?php endif; ?>
              <!-- Sil -->
              <form method="POST" onsubmit="return confirm('<?= addslashes($e['ad']) ?> silinsin mi?')">
                <input type="hidden" name="eylem" value="sil">
                <input type="hidden" name="hesap" value="<?= htmlspecialchars($hesap) ?>">
                <input type="hidden" name="yol" value="<?= htmlspecialchars($e['tam']) ?>">
                <button type="submit" class="btn btn-sm btn-danger" title="Sil"><i class="ti ti-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // editor_aktif ?>

<?php layout_foot(); ?>
