<?php
require_once 'auth.php';
admin_only();
require_once 'layout.php';

$success = '';
$error   = '';

// Paketleri listele
function get_paketler(): array {
    $paketler = [];
    foreach (glob('/etc/gms/paketler/*.conf') as $f) {
        $adi = basename($f, '.conf');
        $info = [];
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $info[trim($k)] = trim($v);
            }
        }
        $info['ADI'] = $adi;
        $paketler[] = $info;
    }
    return $paketler;
}

// POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Yeni kullanici olustur
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $hesap    = trim($_POST['hesap'] ?? '');
        $paket    = trim($_POST['paket'] ?? '');

        if (empty($username) || empty($password) || empty($hesap)) {
            $error = 'Kullanici adi, sifre ve hesap zorunludur.';
        } elseif (!preg_match('/^[a-z0-9_]{3,32}$/', $username)) {
            $error = 'Kullanici adi: sadece kucuk harf, rakam ve alt cizgi (3-32 karakter).';
        } elseif (strlen($password) < 8) {
            $error = 'Sifre en az 8 karakter olmalidir.';
        } elseif (!is_dir('/home/' . $hesap)) {
            $error = "'{$hesap}' hesabi bulunamadi.";
        } elseif (file_exists("/etc/gms/users/{$username}.conf")) {
            $error = "'{$username}' kullanicisi zaten mevcut.";
        } else {
            mkdir('/etc/gms/users', 0755, true);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $content = "# GMS Panel Alt Kullanici\n"
                     . "# Olusturma tarihi: " . date('d.m.Y H:i') . "\n"
                     . "USER={$username}\n"
                     . "HASH={$hash}\n"
                     . "ROLE=user\n"
                     . "HESAP={$hesap}\n"
                     . "PAKET={$paket}\n"
                     . "AKTIF=1\n"
                     . "OLUSTURMA=" . date('d.m.Y H:i') . "\n";
            file_put_contents("/etc/gms/users/{$username}.conf", $content);
            chmod("/etc/gms/users/{$username}.conf", 0640);
            $success = "{$username} kullanicisi olusturuldu.";
        }
    }

    // Kullanici aktif/pasif
    if ($action === 'toggle') {
        $username = trim($_POST['username'] ?? '');
        $file = "/etc/gms/users/{$username}.conf";
        if (file_exists($file) && preg_match('/^[a-z0-9_]+$/', $username)) {
            $content = file_get_contents($file);
            $aktif = strpos($content, 'AKTIF=1') !== false ? '1' : '0';
            $yeni  = $aktif === '1' ? '0' : '1';
            $content = preg_replace('/^AKTIF=.*/m', "AKTIF={$yeni}", $content);
            file_put_contents($file, $content);
            $success = "{$username} " . ($yeni === '1' ? 'aktif edildi.' : 'devre disi birakildi.');
        }
    }

    // Sifre degistir
    if ($action === 'password') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $file = "/etc/gms/users/{$username}.conf";
        if (file_exists($file) && preg_match('/^[a-z0-9_]+$/', $username) && strlen($password) >= 8) {
            $hash    = password_hash($password, PASSWORD_DEFAULT);
            $content = file_get_contents($file);
            $content = preg_replace('/^HASH=.*/m', "HASH={$hash}", $content);
            file_put_contents($file, $content);
            $success = "{$username} sifresi guncellendi.";
        } else {
            $error = 'Gecersiz kullanici veya sifre cok kisa.';
        }
    }

    // Kullanici sil
    if ($action === 'delete') {
        $username = trim($_POST['username'] ?? '');
        $confirm  = trim($_POST['confirm'] ?? '');
        if ($username === $confirm && preg_match('/^[a-z0-9_]+$/', $username)) {
            $file = "/etc/gms/users/{$username}.conf";
            if (file_exists($file)) {
                unlink($file);
                $success = "{$username} kullanicisi silindi.";
            }
        } else {
            $error = 'Kullanici adi eslesmiyor.';
        }
    }
}

$users   = get_all_users();
$paketler = get_paketler();

// Hesap listesi
$hesap_listesi = [];
$panel_user = $_SESSION['gms_user'];
foreach (glob('/home/*', GLOB_ONLYDIR) as $dir) {
    $h = basename($dir);
    if ($h === $panel_user) continue;
    if (is_dir($dir . '/public_html')) $hesap_listesi[] = $h;
}

layout_head('Kullanici Yonetimi', 'users');
?>

<?php if ($success): ?>
<div class="alert-box alert-success"><i class="ti ti-circle-check" style="font-size:16px"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-box alert-error"><i class="ti ti-alert-circle" style="font-size:16px"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- KULLANICI LISTESI -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-user-shield" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Alt Kullanicilar</div>
        <div class="card-head-sub"><?= count($users) ?> kullanici</div>
      </div>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($users)): ?>
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <i class="ti ti-users" style="font-size:32px;display:block;margin-bottom:10px"></i>
      Henuz alt kullanici yok.
    </div>
    <?php else: ?>
    <table class="gms-table">
      <thead>
        <tr>
          <th>Kullanici</th>
          <th>Hesap</th>
          <th>Paket</th>
          <th>Durum</th>
          <th>Olusturma</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--text2)">
                <?= strtoupper(substr($u['USER'] ?? 'U', 0, 2)) ?>
              </div>
              <span style="font-weight:500"><?= htmlspecialchars($u['USER'] ?? '') ?></span>
            </div>
          </td>
          <td style="color:var(--text2)"><?= htmlspecialchars($u['HESAP'] ?? '-') ?></td>
          <td>
            <?php if (!empty($u['PAKET'])): ?>
            <span class="badge badge-purple"><?= htmlspecialchars($u['PAKET']) ?></span>
            <?php else: ?>
            <span style="color:var(--text3)">-</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (($u['AKTIF'] ?? '1') === '1'): ?>
            <span class="badge badge-green">Aktif</span>
            <?php else: ?>
            <span class="badge badge-red">Pasif</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text3);font-size:12px"><?= htmlspecialchars($u['OLUSTURMA'] ?? '-') ?></td>
          <td>
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <!-- Aktif/Pasif -->
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="username" value="<?= htmlspecialchars($u['USER'] ?? '') ?>">
                <button type="submit" class="btn btn-sm" title="<?= ($u['AKTIF']??'1')==='1' ? 'Devre Disi' : 'Aktif Et' ?>">
                  <i class="ti <?= ($u['AKTIF']??'1')==='1' ? 'ti-player-pause' : 'ti-player-play' ?>"></i>
                </button>
              </form>
              <!-- Sifre degistir -->
              <button class="btn btn-sm" onclick="showPassword('<?= addslashes($u['USER']??'') ?>')">
                <i class="ti ti-key"></i>
              </button>
              <!-- Sil -->
              <button class="btn btn-sm btn-danger" onclick="showDelete('<?= addslashes($u['USER']??'') ?>')">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- YENI KULLANICI -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-user-plus" style="color:var(--blue)"></i></div>
      <div><div class="card-head-title">Yeni Alt Kullanici</div></div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" style="max-width:500px">
      <input type="hidden" name="action" value="create">
      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Kullanici Adi</label>
        <input type="text" name="username" placeholder="ornek: ahmet" required
          pattern="[a-z0-9_]+" minlength="3" maxlength="32"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
      </div>
      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Sifre</label>
        <input type="password" name="password" placeholder="En az 8 karakter" minlength="8" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
      </div>
      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Hosting Hesabi</label>
        <select name="hesap" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <option value="">-- Hesap secin --</option>
          <?php foreach ($hesap_listesi as $h): ?>
          <option value="<?= htmlspecialchars($h) ?>"><?= htmlspecialchars($h) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:20px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Paket (Opsiyonel)</label>
        <select name="paket"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <option value="">-- Paketsiz --</option>
          <?php foreach ($paketler as $p): ?>
          <option value="<?= htmlspecialchars($p['ADI']) ?>"><?= htmlspecialchars($p['ISIM'] ?? $p['ADI']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-user-plus"></i> Olustur</button>
    </form>
  </div>
</div>

<!-- Sifre Degistir Modal -->
<div id="password-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:100%;max-width:400px;margin:16px">
    <div style="font-size:15px;font-weight:700;margin-bottom:16px"><i class="ti ti-key"></i> Sifre Degistir: <span id="pw-username"></span></div>
    <form method="POST">
      <input type="hidden" name="action" value="password">
      <input type="hidden" name="username" id="pw-input">
      <input type="password" name="new_password" placeholder="Yeni sifre (en az 8 karakter)" minlength="8" required
        style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;padding:9px 12px;outline:none;margin-bottom:12px">
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">Kaydet</button>
        <button type="button" class="btn" onclick="closeModal('password-modal')" style="flex:1;justify-content:center">Iptal</button>
      </div>
    </form>
  </div>
</div>

<!-- Sil Modal -->
<div id="delete-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:100%;max-width:400px;margin:16px">
    <div style="font-size:15px;font-weight:700;margin-bottom:8px;color:var(--red)"><i class="ti ti-trash"></i> Kullanici Sil</div>
    <div style="font-size:13px;color:var(--text2);margin-bottom:16px">Silinecek: <strong id="del-username"></strong></div>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="username" id="del-input">
      <input type="text" name="confirm" placeholder="Kullanici adini yazin" required
        style="width:100%;background:var(--bg3);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);color:var(--text);font-size:13px;padding:9px 12px;outline:none;margin-bottom:12px">
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center">Sil</button>
        <button type="button" class="btn" onclick="closeModal('delete-modal')" style="flex:1;justify-content:center">Iptal</button>
      </div>
    </form>
  </div>
</div>

<script>
function showPassword(u){
  document.getElementById('pw-username').textContent=u;
  document.getElementById('pw-input').value=u;
  document.getElementById('password-modal').style.display='flex';
}
function showDelete(u){
  document.getElementById('del-username').textContent=u;
  document.getElementById('del-input').value=u;
  document.getElementById('delete-modal').style.display='flex';
}
function closeModal(id){document.getElementById(id).style.display='none';}
</script>

<?php layout_foot(); ?>
