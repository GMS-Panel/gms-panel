<?php
// GMS Panel - Ortak Layout
// Kullanım: layout_head('Sayfa Başlığı', 'aktif-menu');
//           layout_foot();

function layout_head(string $title = 'GMS Panel', string $active = 'dashboard') {
    $menu = [
        'dashboard' => ['icon' => 'ti-layout-dashboard', 'label' => 'Dashboard',  'href' => 'index.php'],
        'accounts'  => ['icon' => 'ti-users',            'label' => 'Hesaplar',   'href' => 'accounts.php'],
        'domains'   => ['icon' => 'ti-world',            'label' => 'Domainler',  'href' => 'domains.php'],
        'databases' => ['icon' => 'ti-database',         'label' => 'Veritabanı', 'href' => 'databases.php'],
        'ssl'       => ['icon' => 'ti-lock',             'label' => 'SSL',        'href' => 'ssl.php'],
        'backup'    => ['icon' => 'ti-device-floppy',    'label' => 'Yedekleme',  'href' => 'backup.php'],
    ];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> · GMS Panel</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f1117;--bg2:#161b27;--bg3:#1e2535;--bg4:#252d3d;
  --border:#2a3348;--border2:#3a4560;
  --text:#e2e8f0;--text2:#94a3b8;--text3:#64748b;
  --blue:#3b82f6;--blue2:#1d4ed8;--bluebg:#1e3a5f;
  --green:#22c55e;--greenbg:rgba(34,197,94,.1);
  --red:#ef4444;--redbg:rgba(239,68,68,.1);
  --amber:#f59e0b;--amberbg:rgba(245,158,11,.1);
  --purple:#a855f7;--purplebg:rgba(168,85,247,.12);
  --sidebar:220px;--radius:8px;--radius2:12px;
}
body{background:var(--bg);color:var(--text);font:14px/1.5 system-ui,sans-serif;display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{width:var(--sidebar);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh;z-index:100}
.sidebar-brand{padding:18px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.brand-icon{width:34px;height:34px;background:#1d4ed8;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;flex-shrink:0}
.brand-name{font-size:15px;font-weight:700;letter-spacing:-.2px}
.brand-ver{font-size:10px;color:var(--text3);margin-top:1px}
.sidebar-nav{flex:1;padding:12px 8px;overflow-y:auto}
.nav-item{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:var(--radius);font-size:13px;font-weight:500;color:var(--text2);text-decoration:none;transition:all .15s;margin-bottom:2px}
.nav-item:hover{background:var(--bg3);color:var(--text)}
.nav-item.active{background:var(--bluebg);color:var(--blue)}
.nav-item i{font-size:17px;flex-shrink:0}
.sidebar-bottom{padding:12px 8px;border-top:1px solid var(--border)}
.nav-user{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:var(--radius)}
.user-avatar{width:30px;height:30px;border-radius:50%;background:var(--bluebg);border:1px solid rgba(59,130,246,.3);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--blue);flex-shrink:0}
.user-name{font-size:13px;font-weight:600;color:var(--text)}
.user-role{font-size:10px;color:var(--text3)}
.logout-btn{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:var(--radius);font-size:13px;font-weight:500;color:var(--text3);text-decoration:none;transition:all .15s;width:100%;background:none;border:none;cursor:pointer;margin-top:4px}
.logout-btn:hover{background:var(--redbg);color:var(--red)}

/* MAIN */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
.topbar-title{font-size:16px;font-weight:700}
.topbar-right{display:flex;align-items:center;gap:12px;font-size:12px;color:var(--text3)}
.status-dot{width:7px;height:7px;border-radius:50%;background:var(--green);display:inline-block;margin-right:4px}
.content{padding:24px;flex:1}

/* CARDS */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);overflow:hidden;margin-bottom:20px}
.card-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-head-left{display:flex;align-items:center;gap:10px}
.card-head-icon{width:30px;height:30px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:15px}
.card-head-title{font-size:13px;font-weight:600}
.card-head-sub{font-size:11px;color:var(--text3);margin-top:1px}
.card-body{padding:18px}

/* STAT GRID */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:20px}
.stat-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:16px 18px}
.stat-label{font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.stat-val{font-size:26px;font-weight:700;color:var(--text);line-height:1}
.stat-sub{font-size:11px;color:var(--text3);margin-top:4px}

/* SERVIS GRID */
.servis-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}
.servis-kart{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px}
.servis-name{font-size:12px;color:var(--text2);margin-bottom:5px;font-weight:600}
.servis-aktif{color:var(--green);font-size:13px;font-weight:600;display:flex;align-items:center;gap:5px}
.servis-pasif{color:var(--red);font-size:13px;font-weight:600;display:flex;align-items:center;gap:5px}

/* TABLE */
table.gms-table{width:100%;border-collapse:collapse}
table.gms-table th{font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;padding:10px 14px;text-align:left;border-bottom:1px solid var(--border)}
table.gms-table td{padding:12px 14px;font-size:13px;border-bottom:1px solid var(--border)}
table.gms-table tr:last-child td{border-bottom:none}
table.gms-table tr:hover td{background:rgba(255,255,255,.02)}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;padding:2px 8px;border-radius:20px}
.badge-green{background:var(--greenbg);color:var(--green);border:1px solid rgba(34,197,94,.25)}
.badge-red{background:var(--redbg);color:var(--red);border:1px solid rgba(239,68,68,.25)}
.badge-amber{background:var(--amberbg);color:var(--amber);border:1px solid rgba(245,158,11,.25)}
.badge-blue{background:var(--bluebg);color:var(--blue);border:1px solid rgba(59,130,246,.25)}
.badge-purple{background:var(--purplebg);color:var(--purple);border:1px solid rgba(168,85,247,.25)}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--radius);font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border2);background:var(--bg3);color:var(--text);transition:all .15s;text-decoration:none}
.btn:hover{background:var(--bg4)}
.btn-primary{background:var(--blue);border-color:var(--blue);color:#fff}
.btn-primary:hover{background:var(--blue2)}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-danger{background:var(--redbg);border-color:rgba(239,68,68,.4);color:var(--red)}
.btn-danger:hover{border-color:var(--red)}

/* SYS TABLE */
.sys-table{width:100%;border-collapse:collapse}
.sys-table td{padding:8px 0;font-size:13px;border-bottom:1px solid var(--border)}
.sys-table tr:last-child td{border-bottom:none}
.sys-table td:first-child{color:var(--text3);width:40%}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">G</div>
    <div>
      <div class="brand-name">GMS Panel</div>
      <div class="brand-ver">v2.1</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($menu as $key => $item): ?>
    <a href="<?= $item['href'] ?>" class="nav-item <?= $active === $key ? 'active' : '' ?>">
      <i class="ti <?= $item['icon'] ?>"></i>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
    <a href="/phpmyadmin" target="_blank" class="nav-item" style="margin-top:8px;border-top:1px solid var(--border);padding-top:12px">
      <i class="ti ti-database-import"></i> phpMyAdmin ↗
    </a>
  </nav>
  <div class="sidebar-bottom">
    <div class="nav-user">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['gms_user'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['gms_user'] ?? '') ?></div>
        <div class="user-role">Yönetici</div>
      </div>
    </div>
    <a href="logout.php" class="logout-btn"><i class="ti ti-logout"></i> Çıkış Yap</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
    <div class="topbar-right">
      <span><span class="status-dot"></span>Sistem Normal</span>
      <span><?= date('d.m.Y H:i') ?></span>
    </div>
  </div>
  <div class="content">
<?php
}

function layout_foot() {
?>
  </div><!-- /content -->
</div><!-- /main -->
</body>
</html>
<?php
}
