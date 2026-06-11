<?php
require_once 'auth.php';
admin_only();
require_once 'layout.php';

$error   = '';
$success = '';
$ssl_log = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullanici = trim($_POST['kullanici'] ?? '');
    $domain    = trim($_POST['domain'] ?? '');
    $php       = trim($_POST['php'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $ssl_kur   = isset($_POST['ssl_kur']) ? '1' : '0';

    // Validasyon
    if (empty($kullanici) || empty($domain) || empty($php)) {
        $error = 'Kullanici adi, domain ve PHP versiyonu zorunludur.';
    } elseif (!preg_match('/^[a-z0-9_]{3,32}$/', $kullanici)) {
        $error = 'Kullanici adi: sadece kucuk harf, rakam ve alt cizgi (3-32 karakter).';
    } elseif (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
        $error = 'Gecersiz domain formati.';
    } elseif (!in_array($php, ['74','80','81','82','83','84'])) {
        $error = 'Gecersiz PHP versiyonu.';
    } elseif (is_dir('/home/' . $kullanici)) {
        $error = "'{$kullanici}' kullanicisi zaten mevcut.";
    } else {
        // Hesap olustur: kullanici domain php email ssl
        $cmd    = "/usr/bin/sudo /usr/local/bin/yeni-hesap.sh "
                . escapeshellarg($kullanici) . " "
                . escapeshellarg($domain) . " "
                . escapeshellarg($php) . " "
                . escapeshellarg($email) . " "
                . escapeshellarg($ssl_kur)
                . " 2>&1";
        $output = shell_exec($cmd);

        if (is_dir('/home/' . $kullanici)) {
            $php_fmt = substr($php, 0, 1) . '.' . substr($php, 1);
            $success = "Hesap basariyla olusturuldu: <strong>{$kullanici}</strong> / {$domain} / PHP {$php_fmt}";
            // SSL kurulduysa certbot ciktisini goster
            if ($ssl_kur === '1') {
                $ssl_log = $output;
            }
        } else {
            $error = "Hesap olusturulamadi. Cikti:<br><pre style='margin-top:8px;font-size:11px'>"
                   . htmlspecialchars($output) . "</pre>";
        }
    }
}

$php_versiyonlar = [
    '84' => 'PHP 8.4',
    '83' => 'PHP 8.3 (Onerilen)',
    '82' => 'PHP 8.2',
    '81' => 'PHP 8.1',
    '80' => 'PHP 8.0',
    '74' => 'PHP 7.4',
];

layout_head('Yeni Hesap', 'accounts');
?>

<div style="max-width:600px">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <a href="accounts.php" class="btn btn-sm"><i class="ti ti-arrow-left"></i> Geri</a>
  <div>
    <div style="font-size:16px;font-weight:700">Yeni Hosting Hesabi</div>
    <div style="font-size:12px;color:var(--text3)">Linux kullanicisi, PHP-FPM pool ve Nginx vhost otomatik olusturulur</div>
  </div>
</div>

<?php if ($error): ?>
<div class="alert-box alert-error" style="margin-bottom:20px">
  <i class="ti ti-alert-circle" style="font-size:16px;flex-shrink:0"></i>
  <span><?= $error ?></span>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-box alert-success" style="margin-bottom:16px">
  <i class="ti ti-circle-check" style="font-size:16px;flex-shrink:0"></i>
  <span><?= $success ?></span>
</div>
<?php if ($ssl_log): ?>
<div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;margin-bottom:16px">
  <div style="font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">SSL Kurulum Ciktisi</div>
  <pre style="font-size:11px;color:var(--text2);white-space:pre-wrap;overflow-x:auto"><?= htmlspecialchars($ssl_log) ?></pre>
</div>
<?php endif; ?>
<div style="margin-bottom:20px">
  <a href="accounts.php" class="btn btn-primary"><i class="ti ti-users"></i> Hesaplara Don</a>
  <a href="new_account.php" class="btn" style="margin-left:8px"><i class="ti ti-plus"></i> Yeni Hesap Daha</a>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-user-plus" style="color:var(--blue)"></i></div>
      <div><div class="card-head-title">Hesap Bilgileri</div></div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST">

      <!-- Kullanici Adi -->
      <div style="margin-bottom:18px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-user" style="font-size:13px"></i> Kullanici Adi <span style="color:var(--red)">*</span>
        </label>
        <input type="text" name="kullanici" placeholder="ornek: ahmet"
          value="<?= htmlspecialchars($_POST['kullanici'] ?? '') ?>"
          pattern="[a-z0-9_]+" minlength="3" maxlength="32" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none"
          oninput="autoFill()">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Sadece kucuk harf, rakam ve alt cizgi. Linux kullanici adi olarak kullanilir.</div>
      </div>

      <!-- E-posta -->
      <div style="margin-bottom:18px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-mail" style="font-size:13px"></i> E-posta
        </label>
        <input type="text" name="email" placeholder="ornek: ahmet@musteri.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Iletisim ve SSL sertifikasi icin kullanilir.</div>
      </div>

      <!-- Domain -->
      <div style="margin-bottom:18px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-world" style="font-size:13px"></i> Domain <span style="color:var(--red)">*</span>
        </label>
        <input type="text" name="domain" id="domain" placeholder="ornek: musteri.com"
          value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>"
          required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">www olmadan girin. Nginx her iki sekilde de yanitlar.</div>
      </div>

      <!-- PHP Versiyonu -->
      <div style="margin-bottom:18px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-brand-php" style="font-size:13px"></i> PHP Versiyonu <span style="color:var(--red)">*</span>
        </label>
        <select name="php" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <?php foreach ($php_versiyonlar as $val => $label): ?>
          <option value="<?= $val ?>" <?= (($_POST['php'] ?? '83') === $val) ? 'selected' : '' ?>>
            <?= $label ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- SSL Sec -->
      <div style="margin-bottom:24px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer">
          <input type="checkbox" name="ssl_kur" value="1"
            <?= (($_POST['ssl_kur'] ?? '') ? 'checked' : '') ?>
            style="margin-top:2px;width:16px;height:16px;accent-color:var(--blue);flex-shrink:0">
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--text)">
              <i class="ti ti-lock" style="color:var(--green)"></i> SSL Sertifikasi Olustur (Let's Encrypt)
            </div>
            <div style="font-size:11px;color:var(--text3);margin-top:3px">
              Domain DNS'i bu sunucuya yonlendirilmis olmalidir. E-posta alanindaki adres kullanilir.
            </div>
          </div>
        </label>
      </div>

      <!-- Onizleme -->
      <div id="preview" style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:20px;display:none">
        <div style="font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Olusturulacak Yapi</div>
        <div style="font-size:12px;color:var(--text2);line-height:2">
          <div><i class="ti ti-folder" style="color:var(--blue)"></i> /home/<span id="prev-user" style="color:var(--text)"></span>/public_html/</div>
          <div><i class="ti ti-folder" style="color:var(--blue)"></i> /home/<span id="prev-user2" style="color:var(--text)"></span>/logs/</div>
          <div><i class="ti ti-settings" style="color:var(--amber)"></i> PHP-FPM pool: <span id="prev-pool" style="color:var(--text)"></span></div>
          <div><i class="ti ti-server" style="color:var(--green)"></i> Nginx vhost: <span id="prev-domain" style="color:var(--text)"></span></div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">
        <i class="ti ti-plus"></i> Hesabi Olustur
      </button>

    </form>
  </div>
</div>

</div>

<script>
function autoFill() {
  const u = document.querySelector('[name=kullanici]').value.trim();
  if (u.length >= 2) {
    document.getElementById('preview').style.display = 'block';
    document.getElementById('prev-user').textContent  = u;
    document.getElementById('prev-user2').textContent = u;
    document.getElementById('prev-pool').textContent  = u + '.conf';
    const d = document.getElementById('domain').value || 'domain.com';
    document.getElementById('prev-domain').textContent = d;
  } else {
    document.getElementById('preview').style.display = 'none';
  }
}
document.getElementById('domain').addEventListener('input', autoFill);
</script>

<?php layout_foot(); ?>
