<?php
require_once 'auth.php';
require_once 'layout.php';

$success = '';
$error   = '';

// MariaDB baglantisi kur (root ile)
function db_baglan(): ?mysqli {
    $db_conf = parse_ini_file('/etc/gms/db.conf') ?: [];
    $root_pw = $db_conf['DB_ROOT'] ?? '';
    $mysqli = @new mysqli('localhost', 'root', $root_pw);
    if ($mysqli->connect_error) return null;
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

// Sistem DB'lerini filtrele
$sistem_db = ['information_schema', 'mysql', 'performance_schema', 'sys'];

// Mevcut kullanicinin DB prefixini belirle
// Admin: tum DB'ler, alt kullanici: sadece kendi prefixiyle baslayanlar
function kullanici_db_prefix(): string {
    if (is_admin()) return '';
    return ($_SESSION['gms_hesap'] ?? '') . '_';
}

// POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mysqli = db_baglan();

    if (!$mysqli) {
        $error = 'Veritabani baglantisi kurulamadi.';
    } else {

        // Yeni veritabani olustur
        if ($action === 'create') {
            $db_adi_ham = trim($_POST['db_adi'] ?? '');
            $prefix     = kullanici_db_prefix();
            $db_adi     = $prefix . $db_adi_ham;

            if (empty($db_adi_ham)) {
                $error = 'Veritabani adi bos olamaz.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{1,50}$/', $db_adi_ham)) {
                $error = 'Gecersiz veritabani adi. Sadece harf, rakam ve alt cizgi kullanin.';
            } else {
                $sql = "CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($db_adi) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
                if ($mysqli->query($sql)) {
                    // Alt kullanici icin ayri MySQL kullanicisi olustur
                    if (is_user()) {
                        $hesap    = $_SESSION['gms_hesap'] ?? '';
                        $usr_conf = "/etc/gms/users/{$hesap}.conf";
                        $conf     = parse_ini_file($usr_conf) ?: [];
                        $mu       = $conf['MYSQL_USER']  ?? $hesap;
                        $mp       = $conf['MYSQL_SIFRE'] ?? '';
                        if ($mu) {
                            $mysqli->query("GRANT ALL PRIVILEGES ON `" . $mysqli->real_escape_string($db_adi) . "`.* TO '" . $mysqli->real_escape_string($mu) . "'@'localhost'");
                            $mysqli->query("FLUSH PRIVILEGES");
                        }
                    }
                    $success = "'{$db_adi}' veritabani olusturuldu.";
                } else {
                    $error = 'Veritabani olusturulamadi: ' . htmlspecialchars($mysqli->error);
                }
            }
        }

        // Veritabani sil
        if ($action === 'delete') {
            $db_adi  = trim($_POST['db_adi'] ?? '');
            $confirm = trim($_POST['confirm'] ?? '');

            if ($db_adi !== $confirm) {
                $error = 'Veritabani adi eslesmiyor.';
            } elseif (in_array($db_adi, $sistem_db)) {
                $error = 'Sistem veritabanlari silinemez.';
            } elseif (is_user() && strpos($db_adi, kullanici_db_prefix()) !== 0) {
                $error = 'Bu veritabanina erisim yetkiniz yok.';
            } else {
                $sql = "DROP DATABASE IF EXISTS `" . $mysqli->real_escape_string($db_adi) . "`";
                if ($mysqli->query($sql)) {
                    $success = "'{$db_adi}' veritabani silindi.";
                } else {
                    $error = 'Silme islemi basarisiz: ' . htmlspecialchars($mysqli->error);
                }
            }
        }

        $mysqli->close();
    }
}

// Veritabanlarini listele
$veritabanlari = [];
$mysqli = db_baglan();
if ($mysqli) {
    $prefix = kullanici_db_prefix();
    $result = $mysqli->query("SHOW DATABASES");
    if ($result) {
        while ($row = $result->fetch_row()) {
            $db = $row[0];
            if (in_array($db, $sistem_db)) continue;
            if ($prefix && strpos($db, $prefix) !== 0) continue;
            // Tablo sayisi ve boyut
            $tablo = $mysqli->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='" . $mysqli->real_escape_string($db) . "'");
            $tablo_sayisi = $tablo ? $tablo->fetch_row()[0] : 0;
            $boyut_q = $mysqli->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.TABLES WHERE TABLE_SCHEMA='" . $mysqli->real_escape_string($db) . "'");
            $boyut = $boyut_q ? ($boyut_q->fetch_row()[0] ?? 0) : 0;
            $veritabanlari[] = ['ad' => $db, 'tablolar' => $tablo_sayisi, 'boyut' => $boyut . ' MB'];
        }
    }
    $mysqli->close();
}

layout_head('Veritabani Yonetimi', 'databases');
?>

<?php if ($success): ?>
<div style="background:var(--greenbg);border:1px solid rgba(34,197,94,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--green);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-circle-check" style="font-size:16px;flex-shrink:0"></i><span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:var(--redbg);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
  <i class="ti ti-alert-circle" style="font-size:16px;flex-shrink:0"></i><span><?= $error ?></span>
</div>
<?php endif; ?>

<!-- VERITABANI LISTESI -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-database" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Veritabanlari</div>
        <div class="card-head-sub"><?= count($veritabanlari) ?> veritabani</div>
      </div>
    </div>
    <a href="pma-giris.php" class="btn btn-sm" target="_blank"><i class="ti ti-external-link"></i> phpMyAdmin</a>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($veritabanlari)): ?>
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <i class="ti ti-database" style="font-size:32px;display:block;margin-bottom:10px"></i>
      Henuz veritabani yok.
    </div>
    <?php else: ?>
    <table class="gms-table">
      <thead>
        <tr>
          <th>Veritabani Adi</th>
          <th>Tablo Sayisi</th>
          <th>Boyut</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($veritabanlari as $db): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <i class="ti ti-database" style="color:var(--blue);font-size:15px"></i>
              <strong><?= htmlspecialchars($db['ad']) ?></strong>
            </div>
          </td>
          <td style="color:var(--text2)"><?= $db['tablolar'] ?> tablo</td>
          <td style="color:var(--text2)"><?= $db['boyut'] ?></td>
          <td>
            <button class="btn btn-sm btn-danger" onclick="showDelete('<?= addslashes($db['ad']) ?>')">
              <i class="ti ti-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- YENI VERITABANI -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-plus" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Yeni Veritabani</div>
        <?php if (is_user()): ?>
        <div class="card-head-sub">Prefix: <strong><?= htmlspecialchars(kullanici_db_prefix()) ?></strong></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" style="max-width:500px">
      <input type="hidden" name="action" value="create">
      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Veritabani Adi</label>
        <div style="display:flex;align-items:center;gap:0">
          <?php if (is_user()): ?>
          <span style="background:var(--bg3);border:1px solid var(--border);border-right:none;border-radius:var(--radius) 0 0 var(--radius);padding:10px 12px;font-size:13px;color:var(--text3)"><?= htmlspecialchars(kullanici_db_prefix()) ?></span>
          <?php endif; ?>
          <input type="text" name="db_adi" placeholder="veritabani_adi" required
            pattern="[a-zA-Z0-9_]+" maxlength="50"
            style="flex:1;background:var(--bg3);border:1px solid var(--border);<?= is_user() ? 'border-radius:0 var(--radius) var(--radius) 0' : 'border-radius:var(--radius)' ?>;color:var(--text);font-size:14px;padding:10px 12px;outline:none">
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Sadece harf, rakam ve alt cizgi. UTF8MB4 karakter seti ile olusturulur.</div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-plus"></i> Olustur</button>
    </form>
  </div>
</div>

<!-- Sil Modal -->
<div id="delete-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:100%;max-width:420px;margin:16px">
    <div style="font-size:15px;font-weight:700;margin-bottom:8px;color:var(--red)"><i class="ti ti-trash"></i> Veritabani Sil</div>
    <div style="font-size:13px;color:var(--text2);margin-bottom:16px">
      <strong id="del-db-name"></strong> veritabanini ve icindeki TUM VERILERI kalici olarak silmek istediginizi onaylayin.
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="db_adi" id="del-db-input">
      <input type="text" name="confirm" placeholder="Veritabani adini yazin" required
        style="width:100%;background:var(--bg3);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);color:var(--text);font-size:13px;padding:9px 12px;outline:none;margin-bottom:12px">
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center"><i class="ti ti-trash"></i> Sil</button>
        <button type="button" class="btn" onclick="document.getElementById('delete-modal').style.display='none'" style="flex:1;justify-content:center">Iptal</button>
      </div>
    </form>
  </div>
</div>

<script>
function showDelete(db) {
  document.getElementById('del-db-name').textContent = db;
  document.getElementById('del-db-input').value = db;
  document.getElementById('delete-modal').style.display = 'flex';
}
</script>

<?php layout_foot(); ?>
