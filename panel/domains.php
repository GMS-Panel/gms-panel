<?php
require_once 'auth.php';
require_once 'layout.php';

$success = '';
$error   = '';

// Mevcut hesaplari al
function get_hesaplar(): array {
    $panel_user = $_SESSION['gms_user'];
    $hesaplar   = [];
    foreach (glob('/home/*', GLOB_ONLYDIR) as $dir) {
        $user = basename($dir);
        if ($user === $panel_user) continue;
        if (!is_dir($dir . '/public_html')) continue;
        $hesaplar[] = $user;
    }
    return $hesaplar;
}

// Mevcut domainleri al
function get_domains(): array {
    $domains = [];
    foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
        $basename = basename($conf, '.conf');
        if (in_array($basename, ['00-default', 'php-fpm'])) continue;

        $content = file_get_contents($conf);

        // server_name'leri al
        if (!preg_match('/server_name\s+([^;]+);/', $content, $m)) continue;
        $server_names = array_filter(array_map('trim', explode(' ', trim($m[1]))));

        // Ana domain (www olmayan)
        $ana_domain = '';
        foreach ($server_names as $sn) {
            if (strpos($sn, 'www.') !== 0 && $sn !== '_') {
                $ana_domain = $sn;
                break;
            }
        }
        if (!$ana_domain) continue;

        // Hangi kullaniciya ait
        $kullanici = '';
        if (preg_match('|/home/([^/]+)/|', $content, $hm)) {
            $kullanici = $hm[1];
        }

        // SSL var mi
        $ssl = strpos($content, 'ssl_certificate') !== false
            || file_exists('/etc/letsencrypt/live/' . $ana_domain . '/fullchain.pem');

        // SSL bitis tarihi
        $ssl_exp  = '';
        $ssl_kalan = 0;
        $cert_file = '/etc/letsencrypt/live/' . $ana_domain . '/fullchain.pem';
        if (file_exists($cert_file)) {
            $exp_raw = trim(shell_exec("openssl x509 -enddate -noout -in " . escapeshellarg($cert_file) . " 2>/dev/null") ?? '');
            if (preg_match('/notAfter=(.+)/', $exp_raw, $em)) {
                $ssl_exp   = date('d.m.Y', strtotime($em[1]));
                $ssl_kalan = (int) ceil((strtotime($em[1]) - time()) / 86400);
            }
        }

        $domains[] = [
            'domain'    => $ana_domain,
            'www'       => in_array('www.' . $ana_domain, $server_names),
            'kullanici' => $kullanici,
            'ssl'       => $ssl,
            'ssl_exp'   => $ssl_exp,
            'ssl_kalan' => $ssl_kalan,
            'conf_file' => $conf,
        ];
    }
    return $domains;
}

// POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Yeni domain ekle
    if ($action === 'add_domain') {
        $domain    = strtolower(trim($_POST['domain'] ?? ''));
        // Alt kullanici sadece kendi hesabina domain ekleyebilir
        $kullanici = is_user() ? ($_SESSION['gms_hesap'] ?? '') : trim($_POST['kullanici'] ?? '');
        $www       = isset($_POST['www']);
        $ssl       = isset($_POST['ssl']);
        $email     = trim($_POST['email'] ?? '');

        if (empty($domain) || empty($kullanici)) {
            $error = 'Domain ve kullanici adi zorunludur.';
        } elseif (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            $error = 'Gecersiz domain formati.';
        } elseif (!is_dir('/home/' . $kullanici . '/public_html')) {
            $error = 'Secilen kullanici bulunamadi.';
        } elseif (file_exists('/etc/nginx/conf.d/' . $domain . '.conf')) {
            $error = "'{$domain}' icin nginx config zaten mevcut.";
        } else {
            // PHP versiyonunu bul
            $php_ver = '83';
            foreach (['84','83','82','81','80','74'] as $v) {
                if (file_exists("/etc/opt/remi/php{$v}/php-fpm.d/{$kullanici}.conf")) {
                    $php_ver = $v;
                    break;
                }
            }

            // domain-ekle.sh scriptini calistir
            $www_flag = $www ? '1' : '0';
            $cmd = "/usr/bin/sudo /usr/local/bin/domain-ekle.sh "
                 . escapeshellarg($domain) . " "
                 . escapeshellarg($kullanici) . " "
                 . escapeshellarg($php_ver) . " "
                 . escapeshellarg($www_flag)
                 . " 2>&1";
            $out = shell_exec($cmd);

            if (file_exists("/etc/nginx/conf.d/{$domain}.conf")) {
                $success = "{$domain} basariyla eklendi.";

                // SSL al
                if ($ssl && !empty($email)) {
                    $ssl_cmd = "/usr/bin/sudo /usr/bin/certbot --nginx -d " . escapeshellarg($domain);
                    if ($www) $ssl_cmd .= " -d " . escapeshellarg('www.' . $domain);
                    $ssl_cmd .= " --non-interactive --agree-tos -m " . escapeshellarg($email) . " 2>&1";
                    $ssl_out = shell_exec($ssl_cmd);

                    if (strpos($ssl_out, 'Congratulations') !== false || strpos($ssl_out, 'Successfully') !== false) {
                        $success .= " SSL sertifikasi da alindi.";
                    } else {
                        $success .= " <span style='color:var(--amber)'>Uyari: SSL alinamadi, DNS henuz yayilmamis olabilir.</span>";
                    }
                }
            } else {
                $error = "Domain eklenemedi. Cikti:<br><pre style='font-size:11px'>" . htmlspecialchars($out) . "</pre>";
            }
        }
    }

    // Domain sil
    if ($action === 'delete_domain') {
        $domain  = trim($_POST['domain'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');

        if ($domain !== $confirm) {
            $error = 'Domain adi eslesmiyor.';
        } elseif (preg_match('/^[a-z0-9.-]+$/', $domain)) {
            $conf_file = "/etc/nginx/conf.d/{$domain}.conf";
            if (file_exists($conf_file)) unlink($conf_file);
            shell_exec("/usr/bin/sudo /usr/bin/systemctl reload nginx 2>/dev/null");
            $success = "{$domain} silindi.";
        }
    }
}

$domains  = get_domains();
$hesaplar = get_hesaplar();
$server_ip = trim(shell_exec('hostname -I | cut -d" " -f1'));

layout_head('Domain Yonetimi', 'domains');
?>

<?php if ($success): ?>
<div style="background:var(--greenbg);border:1px solid rgba(34,197,94,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--green);margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
  <i class="ti ti-circle-check" style="font-size:16px;flex-shrink:0"></i><span><?= $success ?></span>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background:var(--redbg);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
  <i class="ti ti-alert-circle" style="font-size:16px;flex-shrink:0"></i><span><?= $error ?></span>
</div>
<?php endif; ?>

<!-- DOMAIN LISTESI -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-world" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Domainler</div>
        <div class="card-head-sub"><?= count($domains) ?> domain tanimli</div>
      </div>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($domains)): ?>
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <i class="ti ti-world" style="font-size:32px;display:block;margin-bottom:10px"></i>
      Henuz domain yok.
    </div>
    <?php else: ?>
    <table class="gms-table">
      <thead>
        <tr>
          <th>Domain</th>
          <th>Hesap</th>
          <th>SSL</th>
          <th>SSL Bitis</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($domains as $d): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <i class="ti <?= $d['ssl'] ? 'ti-lock' : 'ti-lock-open' ?>"
                 style="color:<?= $d['ssl'] ? 'var(--green)' : 'var(--text3)' ?>;font-size:15px"></i>
              <div>
                <div style="font-weight:500">
                  <a href="http<?= $d['ssl'] ? 's' : '' ?>://<?= htmlspecialchars($d['domain']) ?>"
                     target="_blank" style="color:var(--blue);text-decoration:none">
                    <?= htmlspecialchars($d['domain']) ?> ↗
                  </a>
                </div>
                <?php if ($d['www']): ?>
                <div style="font-size:11px;color:var(--text3)">www.<?= htmlspecialchars($d['domain']) ?> de aktif</div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <a href="edit_account.php?user=<?= urlencode($d['kullanici']) ?>"
               style="color:var(--text2);text-decoration:none;font-size:13px">
              <i class="ti ti-user" style="font-size:12px"></i> <?= htmlspecialchars($d['kullanici']) ?>
            </a>
          </td>
          <td>
            <?php if ($d['ssl']): ?>
              <span class="badge badge-green"><i class="ti ti-lock" style="font-size:11px"></i> Aktif</span>
            <?php else: ?>
              <span class="badge badge-red"><i class="ti ti-lock-open" style="font-size:11px"></i> Yok</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text2)">
            <?php if ($d['ssl_exp']): ?>
              <?= $d['ssl_exp'] ?>
              <?php if ($d['ssl_kalan'] <= 30): ?>
                <span style="color:var(--amber);font-size:11px">(<?= $d['ssl_kalan'] ?> gun)</span>
              <?php endif; ?>
            <?php else: ?>
              <span style="color:var(--text3)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-danger" onclick="showDeleteDomain('<?= addslashes($d['domain']) ?>')">
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

<!-- YENI DOMAIN EKLE -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-plus" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Yeni Domain Ekle</div>
        <div class="card-head-sub">Mevcut bir hesaba yeni domain bagla</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" style="max-width:500px" id="domain-form">
      <input type="hidden" name="action" value="add_domain">

      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-world" style="font-size:13px"></i> Domain
        </label>
        <input type="text" name="domain" id="domain-input" placeholder="ornek: musteri.com"
          value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text2);cursor:pointer;margin-top:8px">
          <input type="checkbox" name="www" value="1" checked style="accent-color:var(--blue)">
          www.domain icin de ekle
        </label>
      </div>

      <?php if (is_admin()): ?>
      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-user" style="font-size:13px"></i> Hesap
        </label>
        <select name="kullanici" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <option value="">-- Hesap secin --</option>
          <?php foreach ($hesaplar as $h): ?>
          <option value="<?= htmlspecialchars($h) ?>" <?= (($_POST['kullanici'] ?? '') === $h) ? 'selected' : '' ?>>
            <?= htmlspecialchars($h) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <div style="margin-bottom:16px;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);font-size:13px;color:var(--text2)">
        <i class="ti ti-user" style="font-size:13px"></i> Hesap: <strong style="color:var(--text)"><?= htmlspecialchars($_SESSION['gms_hesap'] ?? '') ?></strong>
      </div>
      <?php endif; ?>

      <!-- SSL Secenegi -->
      <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text);cursor:pointer">
          <input type="checkbox" name="ssl" id="ssl-check" value="1" style="accent-color:var(--blue)" onchange="toggleSSL()">
          <i class="ti ti-lock" style="color:var(--green)"></i> SSL sertifikasi da al (Let's Encrypt)
        </label>
        <div id="ssl-fields" style="display:none;margin-top:12px">
          <div style="background:var(--amberbg);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius);padding:8px 12px;font-size:12px;color:var(--amber);margin-bottom:12px;display:flex;gap:8px">
            <i class="ti ti-alert-triangle" style="font-size:13px;flex-shrink:0"></i>
            Domain A kaydi <?= $server_ip ?> adresine isaret etmiyorsa SSL alinamaz.
          </div>
          <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">Eposta Adresi</label>
          <input type="email" name="email" placeholder="admin@domain.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            style="width:100%;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;padding:9px 12px;outline:none">
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="ti ti-plus"></i> Domain Ekle</button>
    </form>
  </div>
</div>

<!-- Sil Modal -->
<div id="delete-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:100%;max-width:420px;margin:16px">
    <div style="font-size:15px;font-weight:700;margin-bottom:8px;color:var(--red)"><i class="ti ti-trash"></i> Domain Sil</div>
    <div style="font-size:13px;color:var(--text2);margin-bottom:4px">Silinecek domain: <strong id="delete-domain-name"></strong></div>
    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">Sadece nginx config silinir. Dosyalar ve SSL etkilenmez.</div>
    <form method="POST">
      <input type="hidden" name="action" value="delete_domain">
      <input type="hidden" name="domain" id="delete-domain-input">
      <input type="text" name="confirm" placeholder="Domain adini yazin" required
        style="width:100%;background:var(--bg3);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);color:var(--text);font-size:13px;padding:9px 12px;outline:none;margin-bottom:12px">
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center"><i class="ti ti-trash"></i> Sil</button>
        <button type="button" class="btn" onclick="closeDelete()" style="flex:1;justify-content:center">Iptal</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSSL() {
  document.getElementById('ssl-fields').style.display =
    document.getElementById('ssl-check').checked ? 'block' : 'none';
}
function showDeleteDomain(domain) {
  document.getElementById('delete-domain-name').textContent = domain;
  document.getElementById('delete-domain-input').value = domain;
  document.getElementById('delete-modal').style.display = 'flex';
}
function closeDelete() {
  document.getElementById('delete-modal').style.display = 'none';
}
</script>

<?php layout_foot(); ?>
