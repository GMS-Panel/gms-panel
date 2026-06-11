<?php
require_once 'auth.php';
require_once 'layout.php';

$success = '';
$error   = '';

// Sertifikalari listele
function get_certificates(): array {
    $certs = [];
    $dirs  = glob('/etc/letsencrypt/live/*', GLOB_ONLYDIR);
    if (!$dirs) return $certs;

    foreach ($dirs as $dir) {
        $domain   = basename($dir);
        if ($domain === 'README') continue;
        $cert_file = $dir . '/fullchain.pem';
        if (!file_exists($cert_file)) continue;

        // Son kullanma tarihi
        $exp_raw  = trim(shell_exec("openssl x509 -enddate -noout -in " . escapeshellarg($cert_file) . " 2>/dev/null") ?? '');
        $exp_date = '';
        $kalan    = 0;
        if (preg_match('/notAfter=(.+)/', $exp_raw, $m)) {
            $exp_date = date('d.m.Y', strtotime($m[1]));
            $kalan    = (int) ceil((strtotime($m[1]) - time()) / 86400);
        }

        // Nginx'te aktif mi?
        $nginx_aktif = false;
        foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
            if (strpos(file_get_contents($conf), $domain) !== false) {
                $nginx_aktif = true;
                break;
            }
        }

        $certs[] = [
            'domain'      => $domain,
            'exp_date'    => $exp_date,
            'kalan'       => $kalan,
            'nginx_aktif' => $nginx_aktif,
            'cert_path'   => $dir . '/fullchain.pem',
            'key_path'    => $dir . '/privkey.pem',
        ];
    }
    // Alt kullanici sadece kendi hesabinin sertifikalarini gorebilir
    if (is_user()) {
        $kendi_hesap = $_SESSION['gms_hesap'] ?? '';
        $filtered = [];
        foreach ($certs as $c) {
            // Nginx config'den hangi hesaba ait oldugunu bul
            foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
                $conf_content = file_get_contents($conf);
                if (strpos($conf_content, $c['domain']) !== false &&
                    preg_match('|/home/([^/]+)/|', $conf_content, $hm) &&
                    $hm[1] === $kendi_hesap) {
                    $filtered[] = $c;
                    break;
                }
            }
        }
        return $filtered;
    }
    return $certs;
}

// POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Yeni SSL al
    if ($action === 'new_ssl') {
        $domain = trim($_POST['domain'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $www    = isset($_POST['www']) ? true : false;

        // Alt kullanici erisim kontrolu
        if (is_user()) {
            $conf_file = "/etc/nginx/conf.d/{$domain}.conf";
            if (!file_exists($conf_file)) {
                $error = 'Bu domain icin yetkiniz yok.';
            } else {
                preg_match('|/home/([^/]+)/|', file_get_contents($conf_file), $hm);
                if (!hesap_erisim($hm[1] ?? '')) {
                    $error = 'Bu domain icin yetkiniz yok.';
                }
            }
            if (!empty($error)) goto new_ssl_end;
        }

        if (false) { new_ssl_end: }
        if (empty($domain) || empty($email)) {
            $error = 'Domain ve eposta adresi zorunludur.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Gecersiz eposta adresi.';
        } else {
            $cmd = "/usr/bin/sudo /usr/bin/certbot --nginx -d " . escapeshellarg($domain);
            if ($www) $cmd .= " -d " . escapeshellarg('www.' . $domain);
            $cmd .= " --non-interactive --agree-tos -m " . escapeshellarg($email) . " 2>&1";
            $out = shell_exec($cmd);

            if (strpos($out, 'Congratulations') !== false || strpos($out, 'Successfully') !== false) {
                $success = "{$domain} icin SSL sertifikasi basariyla alindi.";
            } else {
                $error = "SSL alinamadi. Cikti:<br><pre style='margin-top:8px;font-size:11px'>" . htmlspecialchars($out) . "</pre>";
            }
        }
    }

    // Sertifika yenile
    if ($action === 'renew') {
        $domain = trim($_POST['domain'] ?? '');
        if (preg_match('/^[a-z0-9.-]+$/', $domain)) {
            // Alt kullanici erisim kontrolu
            $yetki_ok = true;
            if (is_user()) {
                $conf_file = "/etc/nginx/conf.d/{$domain}.conf";
                preg_match('|/home/([^/]+)/|', file_exists($conf_file) ? file_get_contents($conf_file) : '', $hm);
                if (!hesap_erisim($hm[1] ?? '')) { $error = 'Yetki yok.'; $yetki_ok = false; }
            }
            if ($yetki_ok) {
                $out = shell_exec("/usr/bin/sudo /usr/bin/certbot renew --cert-name " . escapeshellarg($domain) . " --force-renewal 2>&1");
                if (strpos($out, 'Congratulations') !== false || strpos($out, 'Successfully') !== false) {
                    $success = "{$domain} sertifikasi yenilendi.";
                } else {
                    $error = "Yenileme basarisiz:<br><pre style='margin-top:8px;font-size:11px'>" . htmlspecialchars($out) . "</pre>";
                }
            }
        }
    }

    // Sertifika sil
    if ($action === 'delete') {
        $domain  = trim($_POST['domain'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');
        if ($domain === $confirm && preg_match('/^[a-z0-9.-]+$/', $domain)) {
            // Alt kullanici erisim kontrolu
            if (is_user()) {
                $conf_file = "/etc/nginx/conf.d/{$domain}.conf";
                preg_match('|/home/([^/]+)/|', file_exists($conf_file) ? file_get_contents($conf_file) : '', $hm);
                if (!hesap_erisim($hm[1] ?? '')) { $error = 'Yetki yok.'; }
            }
            if (empty($error)) {
                $out = shell_exec("/usr/bin/sudo /usr/bin/certbot delete --cert-name " . escapeshellarg($domain) . " --non-interactive 2>&1");
                $success = "{$domain} sertifikasi silindi.";
            }
        } else {
            $error = 'Domain adi eslesmiyor.';
        }
    }

    // Tumunu yenile (sadece admin)
    if ($action === 'renew_all' && is_admin()) {
        $out = shell_exec("/usr/bin/sudo /usr/bin/certbot renew 2>&1");
        $success = "Tum sertifikalar yenileme islemi tamamlandi.";
    }

    // Ozel SSL sertifikasi yukle
    if ($action === 'custom_ssl') {
        $domain   = trim($_POST['domain'] ?? '');
        $cert_pem = trim($_POST['cert_pem'] ?? '');
        $key_pem  = trim($_POST['key_pem'] ?? '');

        // Alt kullanici erisim kontrolu
        if (is_user()) {
            $conf_file = "/etc/nginx/conf.d/{$domain}.conf";
            preg_match('|/home/([^/]+)/|', file_exists($conf_file) ? file_get_contents($conf_file) : '', $hm);
            if (!hesap_erisim($hm[1] ?? '')) { $error = 'Bu domain icin yetkiniz yok.'; }
        }

        if (empty($error)) {
            if (empty($domain) || empty($cert_pem) || empty($key_pem)) {
                $error = 'Domain, sertifika ve anahtar alanlari zorunludur.';
            } else {
                // Sertifika klasorunu olustur
                $ssl_dir  = "/etc/nginx/ssl/{$domain}";
                $cert_yol = "{$ssl_dir}/fullchain.pem";
                $key_yol  = "{$ssl_dir}/privkey.pem";
                @mkdir($ssl_dir, 0700, true);

                // PEM dosyalarini kaydet
                file_put_contents($cert_yol, $cert_pem);
                file_put_contents($key_yol, $key_pem);
                chmod($cert_yol, 0644);
                chmod($key_yol, 0600);

                // Nginx config'e HTTPS blogu ekle
                $conf_file = "/etc/nginx/conf.d/{$domain}.conf";
                if (file_exists($conf_file)) {
                    $conf_icerik = file_get_contents($conf_file);
                    // Zaten HTTPS blogu var mi?
                    if (strpos($conf_icerik, 'listen 443') === false) {
                        $https_blok = "\nserver {\n"
                            . "    listen 443 ssl;\n"
                            . "    server_name {$domain} www.{$domain};\n"
                            . "    ssl_certificate     {$cert_yol};\n"
                            . "    ssl_certificate_key {$key_yol};\n"
                            . "    ssl_protocols TLSv1.2 TLSv1.3;\n"
                            . "    ssl_ciphers HIGH:!aNULL:!MD5;\n";
                        // HTTP blokundaki location bloklarini kopyala
                        if (preg_match('/root\s+([^;]+);/', $conf_icerik, $rm)) {
                            $https_blok .= "    root {$rm[1]};\n"
                                . "    index index.php index.html;\n";
                        }
                        if (preg_match('|/home/([^/]+)/|', $conf_icerik, $hm2)) {
                            $https_blok .= "    access_log /home/{$hm2[1]}/logs/access.log;\n"
                                . "    error_log  /home/{$hm2[1]}/logs/error.log;\n";
                        }
                        // PHP FPM socket
                        if (preg_match('/fastcgi_pass\s+([^;]+);/', $conf_icerik, $fm)) {
                            $https_blok .= "    location / { try_files \$uri \$uri/ /index.php?\$query_string; }\n"
                                . "    location ~ \\.php\$ {\n"
                                . "        fastcgi_pass {$fm[1]};\n"
                                . "        fastcgi_index index.php;\n"
                                . "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n"
                                . "        include fastcgi_params;\n"
                                . "    }\n"
                                . "    location ~ /\\.ht { deny all; }\n";
                        }
                        $https_blok .= "}\n";
                        file_put_contents($conf_file, $conf_icerik . $https_blok);
                    }
                    // Nginx test + reload
                    $nginx_test = shell_exec("/usr/bin/sudo /usr/sbin/nginx -t 2>&1");
                    if (strpos($nginx_test, 'successful') !== false) {
                        shell_exec("/usr/bin/sudo /usr/bin/systemctl reload nginx 2>/dev/null");
                        $success = "{$domain} icin ozel SSL sertifikasi yuklendi ve aktif edildi.";
                    } else {
                        // Hata varsa eklenen blogu geri al
                        file_put_contents($conf_file, $conf_icerik);
                        $error = "Nginx config hatasi:<br><pre style='font-size:11px;margin-top:8px'>" . htmlspecialchars($nginx_test) . "</pre>";
                    }
                } else {
                    $error = "Nginx config dosyasi bulunamadi: {$domain}";
                }
            }
        }
    }
}

$sertifikalar = get_certificates();

// SSL'si olmayan domainleri bul
$ssl_domain_listesi = array_column($sertifikalar, 'domain');
$sertifikalar_haric = [];
foreach (glob('/etc/nginx/conf.d/*.conf') as $conf) {
    $basename = basename($conf, '.conf');
    if (in_array($basename, ['00-default', 'php-fpm'])) continue;
    $conf_content = file_get_contents($conf);
    // Alt kullanici sadece kendi hesabinin domainlerini gorebilir
    if (is_user()) {
        preg_match('|/home/([^/]+)/|', $conf_content, $hm_check);
        if (!hesap_erisim($hm_check[1] ?? '')) continue;
    }
    if (preg_match('/server_name\s+([^;]+);/', $conf_content, $m)) {
        foreach (array_map('trim', explode(' ', trim($m[1]))) as $sn) {
            if ($sn === '_' || strpos($sn, 'www.') === 0) continue;
            if (!in_array($sn, $ssl_domain_listesi)) {
                $sertifikalar_haric[] = $sn;
            }
        }
    }
}

// Otomatik yenileme durumu
$auto_renew = trim(shell_exec("systemctl is-active certbot-renew.timer 2>/dev/null") ?? '') === 'active';

layout_head('SSL Yonetimi', 'ssl');
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

<!-- OZET STAT -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:20px">
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-certificate" style="font-size:14px"></i> Toplam Sertifika</div>
    <div class="stat-val"><?= count($sertifikalar) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-circle-check" style="font-size:14px"></i> Gecerli</div>
    <div class="stat-val" style="color:var(--green)"><?= count(array_filter($sertifikalar, fn($c) => $c['kalan'] > 30)) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-alert-triangle" style="font-size:14px"></i> Yaklasan Süre</div>
    <div class="stat-val" style="color:var(--amber)"><?= count(array_filter($sertifikalar, fn($c) => $c['kalan'] > 0 && $c['kalan'] <= 30)) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-label"><i class="ti ti-refresh" style="font-size:14px"></i> Otomatik Yenileme</div>
    <div class="stat-val" style="font-size:14px;margin-top:4px">
      <?php if ($auto_renew): ?>
        <span class="badge badge-green"><i class="ti ti-check" style="font-size:11px"></i> Aktif</span>
      <?php else: ?>
        <span class="badge badge-red"><i class="ti ti-x" style="font-size:11px"></i> Pasif</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- SERTIFIKA LISTESI -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--greenbg)"><i class="ti ti-certificate" style="color:var(--green)"></i></div>
      <div>
        <div class="card-head-title">Sertifikalar</div>
        <div class="card-head-sub">Let's Encrypt sertifikalari</div>
      </div>
    </div>
    <?php if (is_admin()): ?>
    <form method="POST">
      <input type="hidden" name="action" value="renew_all">
      <button type="submit" class="btn btn-sm"><i class="ti ti-refresh"></i> Tumunu Yenile</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($sertifikalar)): ?>
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <i class="ti ti-certificate" style="font-size:32px;display:block;margin-bottom:10px"></i>
      Henuz SSL sertifikasi yok.
    </div>
    <?php else: ?>
    <table class="gms-table">
      <thead>
        <tr>
          <th>Domain</th>
          <th>Bitis Tarihi</th>
          <th>Kalan Gun</th>
          <th>Nginx</th>
          <th>Durum</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sertifikalar as $c): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <i class="ti ti-lock" style="color:var(--green);font-size:15px"></i>
              <strong><?= htmlspecialchars($c['domain']) ?></strong>
            </div>
          </td>
          <td style="color:var(--text2)"><?= $c['exp_date'] ?></td>
          <td>
            <?php if ($c['kalan'] > 30): ?>
              <span style="color:var(--green);font-weight:600"><?= $c['kalan'] ?> gun</span>
            <?php elseif ($c['kalan'] > 0): ?>
              <span style="color:var(--amber);font-weight:600"><?= $c['kalan'] ?> gun</span>
            <?php else: ?>
              <span style="color:var(--red);font-weight:600">Suresi dolmus!</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['nginx_aktif']): ?>
              <span class="badge badge-green">Aktif</span>
            <?php else: ?>
              <span class="badge badge-amber">Tanimi yok</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['kalan'] > 30): ?>
              <span class="badge badge-green">Gecerli</span>
            <?php elseif ($c['kalan'] > 0): ?>
              <span class="badge badge-amber">Yenile</span>
            <?php else: ?>
              <span class="badge badge-red">Suresi doldu</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <!-- Yenile -->
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="renew">
                <input type="hidden" name="domain" value="<?= htmlspecialchars($c['domain']) ?>">
                <button type="submit" class="btn btn-sm"><i class="ti ti-refresh"></i> Yenile</button>
              </form>
              <!-- Sil -->
              <button class="btn btn-sm btn-danger" onclick="showDelete('<?= addslashes($c['domain']) ?>')">
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

<!-- YENI SSL AL -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-plus" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Yeni SSL Sertifikasi Al</div>
        <div class="card-head-sub">Let's Encrypt ucretsiz SSL - Domain DNS'i bu sunucuya isaret etmeli</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" style="max-width:500px">
      <input type="hidden" name="action" value="new_ssl">

      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-world" style="font-size:13px"></i> Domain
        </label>
        <select name="domain" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <option value="">-- Domain secin --</option>
          <?php foreach ($sertifikalar_haric as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= (($_POST['domain'] ?? '') === $d) ? 'selected' : '' ?>>
            <?= htmlspecialchars($d) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div style="margin-top:8px">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text2);cursor:pointer">
            <input type="checkbox" name="www" value="1" checked style="accent-color:var(--blue)">
            www.domain icin de al
          </label>
        </div>
      </div>

      <div style="margin-bottom:20px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-mail" style="font-size:13px"></i> Eposta Adresi
        </label>
        <input type="email" name="email" placeholder="ornek: admin@domain.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Sertifika suresi dolmadan uyari emaili gonderilir.</div>
      </div>

      <div style="background:var(--amberbg);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius);padding:10px 14px;font-size:12px;color:var(--amber);margin-bottom:16px;display:flex;gap:8px">
        <i class="ti ti-alert-triangle" style="font-size:14px;flex-shrink:0"></i>
        Domain A kaydi bu sunucunun IP adresine (<?= trim(shell_exec('hostname -I | cut -d" " -f1')) ?>) isaret etmiyorsa SSL alinamaz.
      </div>

      <button type="submit" class="btn btn-primary"><i class="ti ti-certificate"></i> SSL Al</button>
    </form>
  </div>
</div>

<!-- OZEL SSL SERTIFIKASI -->
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--amberbg)"><i class="ti ti-file-certificate" style="color:var(--amber)"></i></div>
      <div>
        <div class="card-head-title">Ozel SSL Sertifikasi Yukle</div>
        <div class="card-head-sub">Kendi sertifikanizi (PEM formatinda) ekleyin</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" style="max-width:600px">
      <input type="hidden" name="action" value="custom_ssl">

      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-world" style="font-size:13px"></i> Domain
        </label>
        <select name="domain" required
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px;outline:none">
          <option value="">-- Domain secin --</option>
          <?php foreach (glob('/etc/nginx/conf.d/*.conf') as $c):
              $bn = basename($c, '.conf');
              if (in_array($bn, ['00-default','php-fpm'])) continue;
              $cc = file_get_contents($c);
              if (is_user()) {
                  preg_match('|/home/([^/]+)/|', $cc, $hmc);
                  if (!hesap_erisim($hmc[1] ?? '')) continue;
              }
              if (!preg_match('/server_name\s+([^;\s]+)/', $cc, $snm)) continue;
          ?>
          <option value="<?= htmlspecialchars($snm[1]) ?>"><?= htmlspecialchars($snm[1]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-bottom:16px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-certificate" style="font-size:13px"></i> Sertifika (Certificate + CA Chain) — PEM
        </label>
        <textarea name="cert_pem" rows="6" required
          placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:12px;font-family:monospace;padding:10px 12px;outline:none;resize:vertical"></textarea>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">fullchain.pem — sertifikanizi ve varsa ara sertifikalari (CA chain) birlikte yapistirin.</div>
      </div>

      <div style="margin-bottom:20px">
        <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px">
          <i class="ti ti-key" style="font-size:13px"></i> Ozel Anahtar (Private Key) — PEM
        </label>
        <textarea name="key_pem" rows="6" required
          placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
          style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:12px;font-family:monospace;padding:10px 12px;outline:none;resize:vertical"></textarea>
        <div style="font-size:11px;color:var(--text3);margin-top:4px">privkey.pem — sertifika ile eslesen ozel anahtarinizi yapistirin.</div>
      </div>

      <div style="background:var(--amberbg);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius);padding:10px 14px;font-size:12px;color:var(--amber);margin-bottom:16px;display:flex;gap:8px">
        <i class="ti ti-shield-lock" style="font-size:14px;flex-shrink:0"></i>
        Ozel anahtar sunucuda guvenli sekilde saklanir. Sertifika suresi dolunca bu formu tekrar kullanabilirsiniz.
      </div>

      <button type="submit" class="btn btn-primary"><i class="ti ti-upload"></i> Sertifikayi Yukle ve Aktif Et</button>
    </form>
  </div>
</div>

<!-- Sil Modal -->
<div id="delete-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:24px;width:100%;max-width:420px;margin:16px">
    <div style="font-size:15px;font-weight:700;margin-bottom:8px;color:var(--red)"><i class="ti ti-trash"></i> Sertifika Sil</div>
    <div style="font-size:13px;color:var(--text2);margin-bottom:16px">
      <strong id="delete-domain-name"></strong> sertifikasini silmek istediginizi onaylayin.
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
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
function showDelete(domain) {
  document.getElementById('delete-domain-name').textContent = domain;
  document.getElementById('delete-domain-input').value = domain;
  document.getElementById('delete-modal').style.display = 'flex';
}
function closeDelete() {
  document.getElementById('delete-modal').style.display = 'none';
}
</script>

<?php layout_foot(); ?>
