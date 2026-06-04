<?php
require_once 'auth.php';
require_once 'layout.php';

$success = '';
$error   = '';

// Yardimci fonksiyonlar
function fw_cmd(string $cmd): string {
    return trim(shell_exec("/usr/bin/sudo " . $cmd . " 2>&1") ?? '');
}

function get_open_services(): array {
    $out = fw_cmd("firewall-cmd --list-services");
    return $out ? explode(' ', $out) : [];
}

function get_open_ports(): array {
    $out = fw_cmd("firewall-cmd --list-ports");
    return $out ? explode(' ', $out) : [];
}

function get_blocked_ips(): array {
    $out = fw_cmd("firewall-cmd --list-rich-rules");
    $ips = [];
    if ($out) {
        preg_match_all('/source address="([^"]+)"/', $out, $m);
        $ips = $m[1] ?? [];
    }
    return $ips;
}

function get_fail2ban_status(): array {
    $active = trim(shell_exec("systemctl is-active fail2ban 2>/dev/null") ?? '') === 'active';
    $jails  = [];
    if ($active) {
        $jail_list = trim(shell_exec("/usr/bin/sudo fail2ban-client status 2>/dev/null | grep 'Jail list' | cut -d: -f2") ?? '');
        if ($jail_list) {
            foreach (array_map('trim', explode(',', $jail_list)) as $jail) {
                if (!$jail) continue;
                $banned_raw = shell_exec("/usr/bin/sudo fail2ban-client status {$jail} 2>/dev/null | grep 'Banned IP' | cut -d: -f2") ?? '';
                $banned = array_filter(array_map('trim', explode(' ', trim($banned_raw))));
                $jails[$jail] = array_values($banned);
            }
        }
    }
    return ['active' => $active, 'jails' => $jails];
}

// Servisler - toggle:true olanlar icin Ac/Kapat butonu gosterilir
$bilinen_servisler = [
    'http'   => ['label' => 'HTTP',   'port' => '80',   'icon' => 'ti-world',       'renk' => 'green',  'toggle' => false],
    'https'  => ['label' => 'HTTPS',  'port' => '443',  'icon' => 'ti-lock',        'renk' => 'green',  'toggle' => false],
    'smtp'   => ['label' => 'SMTP',   'port' => '25',   'icon' => 'ti-mail',        'renk' => 'amber',  'toggle' => false],
    'imaps'  => ['label' => 'IMAPS',  'port' => '993',  'icon' => 'ti-mail-opened', 'renk' => 'amber',  'toggle' => false],
    'mysql'  => ['label' => 'MySQL',  'port' => '3306', 'icon' => 'ti-database',    'renk' => 'purple', 'toggle' => true],
    'ssh'    => ['label' => 'SSH',    'port' => '22',   'icon' => 'ti-terminal',    'renk' => 'blue',   'toggle' => true],
    'ftp'    => ['label' => 'FTP',    'port' => '21',   'icon' => 'ti-file-upload', 'renk' => 'amber',  'toggle' => true],
];

// POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Servis ac/kapat
    if ($action === 'toggle_service') {
        $servis = $_POST['servis'] ?? '';
        $durum  = $_POST['durum'] ?? '';
        if (preg_match('/^[a-z0-9-]+$/', $servis) && in_array($durum, ['add', 'remove'])) {
            // SSH'i kapatmaya izin verme
            if ($servis === 'ssh' && $durum === 'remove') {
                $error = 'SSH servisi kapatilamaz! Sunucuya erisimi kaybedersiniz.';
            } else {
                fw_cmd("firewall-cmd --permanent --{$durum}-service=" . escapeshellarg($servis));
                fw_cmd("firewall-cmd --reload");
                $success = $durum === 'add'
                    ? "{$servis} servisi acildi."
                    : "{$servis} servisi kapatildi.";
            }
        }
    }

    // Port ac/kapat
    if ($action === 'toggle_port') {
        $port  = $_POST['port'] ?? '';
        $proto = $_POST['proto'] ?? 'tcp';
        $durum = $_POST['durum'] ?? '';
        if (preg_match('/^\d{1,5}$/', $port) && in_array($proto, ['tcp','udp']) && in_array($durum, ['add','remove'])) {
            if ($port === '22' && $durum === 'remove') {
                $error = 'SSH portu (22) kapatilamaz!';
            } else {
                fw_cmd("firewall-cmd --permanent --{$durum}-port={$port}/{$proto}");
                fw_cmd("firewall-cmd --reload");
                $success = $durum === 'add'
                    ? "{$port}/{$proto} portu acildi."
                    : "{$port}/{$proto} portu kapatildi.";
            }
        }
    }

    // IP engelle
    if ($action === 'block_ip') {
        $ip = trim($_POST['ip'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            fw_cmd("firewall-cmd --permanent --add-rich-rule='rule family=ipv4 source address={$ip} reject'");
            fw_cmd("firewall-cmd --reload");
            $success = "{$ip} adresi engellendi.";
        } else {
            $error = 'Gecersiz IP adresi.';
        }
    }

    // IP engel kaldir
    if ($action === 'unblock_ip') {
        $ip = trim($_POST['ip'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            fw_cmd("firewall-cmd --permanent --remove-rich-rule='rule family=ipv4 source address={$ip} reject'");
            fw_cmd("firewall-cmd --reload");
            $success = "{$ip} engeli kaldirildi.";
        }
    }

    // Fail2ban IP serbest birak
    if ($action === 'f2b_unban') {
        $ip   = trim($_POST['ip'] ?? '');
        $jail = trim($_POST['jail'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP) && preg_match('/^[a-z0-9-]+$/', $jail)) {
            shell_exec("/usr/bin/sudo fail2ban-client set " . escapeshellarg($jail) . " unbanip " . escapeshellarg($ip) . " 2>/dev/null");
            $success = "{$ip} adresi {$jail} jail'inden serbest birakildi.";
        }
    }

    // Fail2ban baslat/durdur
    if ($action === 'f2b_toggle') {
        $durum = $_POST['durum'] ?? '';
        if ($durum === 'start') {
            shell_exec("/usr/bin/sudo systemctl enable --now fail2ban 2>/dev/null");
            $success = 'Fail2ban baslatildi.';
        } elseif ($durum === 'stop') {
            shell_exec("/usr/bin/sudo systemctl stop fail2ban 2>/dev/null");
            $success = 'Fail2ban durduruldu.';
        }
    }
}

// Verileri topla
$open_services = get_open_services();
$open_ports    = get_open_ports();
$blocked_ips   = get_blocked_ips();
$f2b           = get_fail2ban_status();

layout_head('Firewall', 'firewall');
?>

<?php if ($success): ?>
<div style="background:var(--greenbg);border:1px solid rgba(34,197,94,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--green);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-circle-check" style="font-size:16px"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background:var(--redbg);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius);padding:12px 16px;font-size:13px;color:var(--red);margin-bottom:20px;display:flex;gap:10px;align-items:center">
  <i class="ti ti-alert-circle" style="font-size:16px"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- SERVISLER -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--bluebg)"><i class="ti ti-shield" style="color:var(--blue)"></i></div>
      <div>
        <div class="card-head-title">Servisler</div>
        <div class="card-head-sub">Firewalld servis kurallari</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
      <?php foreach ($bilinen_servisler as $key => $s):
        $aktif = in_array($key, $open_services);
        $renk  = $s['renk'];
      ?>
      <div style="background:var(--bg3);border:1px solid <?= $aktif ? 'var(--border)' : 'rgba(239,68,68,.25)' ?>;border-radius:var(--radius);padding:14px;display:flex;align-items:center;justify-content:space-between">
        <div style="display:flex;align-items:center;gap:10px">
          <i class="ti <?= $s['icon'] ?>" style="font-size:18px;color:var(--<?= $aktif ? $renk : 'text3' ?>)"></i>
          <div>
            <div style="font-size:13px;font-weight:600;color:<?= $aktif ? 'var(--text)' : 'var(--text3)' ?>"><?= $s['label'] ?></div>
            <div style="font-size:11px;color:var(--text3)">:<?= $s['port'] ?></div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <?php if ($aktif): ?>
            <span class="badge badge-green">Acik</span>
          <?php else: ?>
            <span class="badge badge-red">Kapali</span>
          <?php endif; ?>
          <?php if ($s['toggle']): ?>
          <form method="POST">
            <input type="hidden" name="action" value="toggle_service">
            <input type="hidden" name="servis" value="<?= $key ?>">
            <input type="hidden" name="durum" value="<?= $aktif ? 'remove' : 'add' ?>">
            <button type="submit" class="btn btn-sm"
              style="<?= $aktif ? 'background:var(--redbg);border-color:rgba(239,68,68,.4);color:var(--red)' : 'background:var(--greenbg);border-color:rgba(34,197,94,.4);color:var(--green)' ?>">
              <?= $aktif ? '<i class="ti ti-x"></i> Kapat' : '<i class="ti ti-check"></i> Ac' ?>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ACIK PORTLAR -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--amberbg)"><i class="ti ti-plug" style="color:var(--amber)"></i></div>
      <div>
        <div class="card-head-title">Acik Portlar</div>
        <div class="card-head-sub">Manuel port kurallari</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <!-- Mevcut portlar -->
    <?php if (!empty($open_ports)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
      <?php foreach ($open_ports as $port): ?>
      <div style="display:flex;align-items:center;gap:6px;background:var(--bg3);border:1px solid var(--border);border-radius:20px;padding:4px 10px 4px 12px;font-size:12px">
        <span style="color:var(--amber)"><?= htmlspecialchars($port) ?></span>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle_port">
          <?php
            [$p, $proto] = explode('/', $port) + ['', 'tcp'];
          ?>
          <input type="hidden" name="port" value="<?= htmlspecialchars($p) ?>">
          <input type="hidden" name="proto" value="<?= htmlspecialchars($proto) ?>">
          <input type="hidden" name="durum" value="remove">
          <button type="submit" style="background:none;border:none;color:var(--text3);cursor:pointer;padding:0;font-size:14px;line-height:1" title="Kapat">
            <i class="ti ti-x"></i>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="font-size:13px;color:var(--text3);margin-bottom:16px">Manuel acilmis port yok.</div>
    <?php endif; ?>

    <!-- Yeni port ekle -->
    <form method="POST" style="display:flex;gap:8px;align-items:center">
      <input type="hidden" name="action" value="toggle_port">
      <input type="hidden" name="durum" value="add">
      <input type="number" name="port" placeholder="Port (ornek: 8080)" min="1" max="65535" required
        style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;padding:8px 12px;outline:none;width:200px">
      <select name="proto" style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;padding:8px 12px;outline:none">
        <option value="tcp">TCP</option>
        <option value="udp">UDP</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-plus"></i> Port Ac</button>
    </form>
  </div>
</div>

<!-- IP ENGELLEME -->
<div class="card" style="margin-bottom:16px">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:var(--redbg)"><i class="ti ti-ban" style="color:var(--red)"></i></div>
      <div>
        <div class="card-head-title">IP Engelleme</div>
        <div class="card-head-sub">Kalici IP banlama (firewalld)</div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <!-- Engelli IPs -->
    <?php if (!empty($blocked_ips)): ?>
    <table class="gms-table" style="margin-bottom:16px">
      <thead><tr><th>IP Adresi</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($blocked_ips as $ip): ?>
        <tr>
          <td><code style="font-size:12px;color:var(--red)"><?= htmlspecialchars($ip) ?></code></td>
          <td style="text-align:right">
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="unblock_ip">
              <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
              <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-lock-open"></i> Engeli Kaldir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div style="font-size:13px;color:var(--text3);margin-bottom:16px">Engelli IP yok.</div>
    <?php endif; ?>

    <!-- Yeni IP engelle -->
    <form method="POST" style="display:flex;gap:8px;align-items:center">
      <input type="hidden" name="action" value="block_ip">
      <input type="text" name="ip" placeholder="IP adresi (ornek: 1.2.3.4)" required
        style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:13px;padding:8px 12px;outline:none;flex:1;max-width:300px">
      <button type="submit" class="btn btn-sm" style="background:var(--redbg);border-color:rgba(239,68,68,.4);color:var(--red)">
        <i class="ti ti-ban"></i> Engelle
      </button>
    </form>
  </div>
</div>

<!-- FAIL2BAN -->
<div class="card">
  <div class="card-head">
    <div class="card-head-left">
      <div class="card-head-icon" style="background:<?= $f2b['active'] ? 'var(--greenbg)' : 'var(--redbg)' ?>">
        <i class="ti ti-shield-lock" style="color:<?= $f2b['active'] ? 'var(--green)' : 'var(--red)' ?>"></i>
      </div>
      <div>
        <div class="card-head-title">Fail2ban</div>
        <div class="card-head-sub">Otomatik brute-force korumasi</div>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="f2b_toggle">
      <input type="hidden" name="durum" value="<?= $f2b['active'] ? 'stop' : 'start' ?>">
      <button type="submit" class="btn btn-sm <?= $f2b['active'] ? '' : 'btn-primary' ?>"
        style="<?= $f2b['active'] ? 'background:var(--redbg);border-color:rgba(239,68,68,.4);color:var(--red)' : '' ?>">
        <?= $f2b['active'] ? '<i class="ti ti-player-stop"></i> Durdur' : '<i class="ti ti-player-play"></i> Baslat' ?>
      </button>
    </form>
  </div>
  <div class="card-body">
    <?php if (!$f2b['active']): ?>
    <div style="font-size:13px;color:var(--red)">Fail2ban calismiyor! Brute-force korumaniz aktif degil.</div>
    <?php else: ?>
      <?php if (empty($f2b['jails'])): ?>
      <div style="font-size:13px;color:var(--text3)">Aktif jail yok.</div>
      <?php else: ?>
        <?php foreach ($f2b['jails'] as $jail => $banned_ips): ?>
        <div style="margin-bottom:16px">
          <div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:6px">
            <i class="ti ti-prison" style="font-size:14px"></i>
            <?= htmlspecialchars($jail) ?>
            <span class="badge <?= empty($banned_ips) ? 'badge-green' : 'badge-red' ?>">
              <?= count($banned_ips) ?> banli IP
            </span>
          </div>
          <?php if (!empty($banned_ips)): ?>
          <table class="gms-table">
            <thead><tr><th>Banli IP</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($banned_ips as $ip): ?>
              <tr>
                <td><code style="font-size:12px;color:var(--red)"><?= htmlspecialchars($ip) ?></code></td>
                <td style="text-align:right">
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="f2b_unban">
                    <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                    <input type="hidden" name="jail" value="<?= htmlspecialchars($jail) ?>">
                    <button type="submit" class="btn btn-sm"><i class="ti ti-lock-open"></i> Serbest Birak</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div style="font-size:12px;color:var(--text3)">Bu jail'de banli IP yok.</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php layout_foot(); ?>
