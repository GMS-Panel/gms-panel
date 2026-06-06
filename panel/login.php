<?php
session_start();

if (isset($_SESSION['gms_user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$attempts_key  = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$lockout_key   = 'login_lockout_'  . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time()) {
    $remaining = ceil(($_SESSION[$lockout_key] - time()) / 60);
    $error = "Cok fazla hatali deneme. {$remaining} dakika bekleyin.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $authenticated = false;
    $role          = '';
    $hesap         = '';

    // Once admin kontrolu
    if (file_exists('/etc/gms/auth.conf')) {
        $panel_user = '';
        $panel_hash = '';
        foreach (file('/etc/gms/auth.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos($line, 'USER=') === 0) $panel_user = trim(substr($line, 5));
            if (strpos($line, 'HASH=') === 0) $panel_hash = trim(substr($line, 5));
        }
        if ($username === $panel_user && password_verify($password, $panel_hash)) {
            $authenticated = true;
            $role  = 'admin';
            $hesap = '';
        }
    }

    // Alt kullanici kontrolu
    if (!$authenticated) {
        $user_file = "/etc/gms/users/{$username}.conf";
        if (file_exists($user_file)) {
            $user_hash  = '';
            $user_hesap = '';
            $user_aktif = '1';
            foreach (file($user_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if ($line[0] === '#') continue;
                if (strpos($line, 'HASH=')  === 0) $user_hash  = trim(substr($line, 5));
                if (strpos($line, 'HESAP=') === 0) $user_hesap = trim(substr($line, 6));
                if (strpos($line, 'AKTIF=') === 0) $user_aktif = trim(substr($line, 6));
            }
            if ($user_aktif === '1' && password_verify($password, $user_hash)) {
                $authenticated = true;
                $role  = 'user';
                $hesap = $user_hesap;
            }
        }
    }

    if ($authenticated) {
        $_SESSION['gms_user']     = $username;
        $_SESSION['gms_role']     = $role;
        $_SESSION['gms_hesap']    = $hesap;
        $_SESSION['gms_login_at'] = time();
        unset($_SESSION[$attempts_key], $_SESSION[$lockout_key]);
        header('Location: index.php');
        exit;
    } else {
        $_SESSION[$attempts_key] = ($_SESSION[$attempts_key] ?? 0) + 1;
        $left = 5 - $_SESSION[$attempts_key];
        if ($_SESSION[$attempts_key] >= 5) {
            $_SESSION[$lockout_key] = time() + 900;
            $error = 'Cok fazla hatali deneme. 15 dakika bekleyin.';
        } else {
            $error = "Kullanici adi veya sifre hatali. ({$left} deneme hakki kaldi)";
        }
    }
}

$attempts     = $_SESSION[$attempts_key] ?? 0;
$attempts_pct = min(($attempts / 5) * 100, 100);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GMS Panel - Giris</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f1117;--bg2:#161b27;--bg3:#1e2535;--bg4:#252d3d;
  --border:#2a3348;--text:#e2e8f0;--text2:#94a3b8;--text3:#64748b;
  --blue:#3b82f6;--blue2:#1d4ed8;--bluebg:#1e3a5f;
  --green:#22c55e;--greenbg:rgba(34,197,94,.12);
  --red:#ef4444;--redbg:rgba(239,68,68,.12);
  --radius:8px;--radius2:14px;
}
body{background:var(--bg);color:var(--text);font:14px/1.5 system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px}
.page-wrap{width:100%;max-width:400px}
.brand{text-align:center;margin-bottom:32px}
.brand-icon{width:52px;height:52px;background:#1d4ed8;border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:22px;color:#fff;margin:0 auto 14px}
.brand-title{font-size:22px;font-weight:700;letter-spacing:-.3px}
.brand-sub{font-size:13px;color:var(--text3);margin-top:4px}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius2);padding:28px}
.field{margin-bottom:18px}
.field-label{font-size:12px;font-weight:600;color:var(--text2);margin-bottom:6px;display:flex;align-items:center;gap:6px}
.field-input{position:relative}
.field-input i.ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:16px;color:var(--text3);pointer-events:none}
.field-input input{width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 12px 10px 38px;outline:none;transition:border-color .15s}
.field-input input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.field-input input::placeholder{color:var(--text3)}
.field-input.has-toggle input{padding-right:40px}
.eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:0;font-size:16px}
.field-error{font-size:11px;color:var(--red);margin-top:5px;display:none}
.field-error.show{display:block}
.btn-submit{width:100%;padding:11px;background:var(--blue);border:none;border-radius:var(--radius);color:#fff;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s;margin-top:6px}
.btn-submit:hover{background:var(--blue2)}
.divider{border:none;border-top:1px solid var(--border);margin:20px 0}
.footer-links{display:flex;justify-content:space-between;font-size:12px;color:var(--text3)}
.footer-links a{color:var(--text3);text-decoration:none}
.alert-box{border-radius:var(--radius);padding:11px 14px;font-size:13px;display:flex;align-items:center;gap:10px;margin-bottom:18px}
.alert-error{background:var(--redbg);border:1px solid rgba(239,68,68,.3);color:var(--red)}
.spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:none}
@keyframes spin{to{transform:rotate(360deg)}}
.capslock-warn{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius);padding:8px 12px;font-size:12px;color:#f59e0b;margin-top:8px;display:none;align-items:center;gap:8px}
.capslock-warn.show{display:flex}
.sys-info{text-align:center;margin-top:20px;font-size:11px;color:var(--text3)}
.attempts-bar{height:3px;background:var(--border);border-radius:2px;margin-bottom:20px;overflow:hidden}
.attempts-fill{height:100%;background:var(--red);border-radius:2px;transition:width .3s}
</style>
</head>
<body>
<div class="page-wrap">
  <div class="brand">
    <div class="brand-icon">G</div>
    <div class="brand-title">GMS Panel</div>
    <div class="brand-sub">Hosting Yonetim Paneli - v2.5</div>
  </div>
  <div class="card">
    <div class="attempts-bar"><div class="attempts-fill" style="width:<?= $attempts_pct ?>%"></div></div>
    <?php if ($error): ?>
    <div class="alert-box alert-error">
      <i class="ti ti-alert-circle" style="font-size:16px;flex-shrink:0"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
      <div class="field">
        <div class="field-label"><i class="ti ti-user" style="font-size:14px"></i> Kullanici Adi</div>
        <div class="field-input">
          <i class="ti ti-user ico"></i>
          <input type="text" name="username" placeholder="admin" autocomplete="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="field-error" id="err-username">Kullanici adi bos olamaz.</div>
      </div>
      <div class="field">
        <div class="field-label"><i class="ti ti-lock" style="font-size:14px"></i> Sifre</div>
        <div class="field-input has-toggle">
          <i class="ti ti-lock ico"></i>
          <input type="password" name="password" id="password" placeholder="••••••••"
                 autocomplete="current-password"
                 onkeydown="checkCaps(event)" onkeyup="checkCaps(event)">
          <button type="button" class="eye-btn" onclick="togglePass()">
            <i class="ti ti-eye" id="eye-icon"></i>
          </button>
        </div>
        <div class="field-error" id="err-password">Sifre bos olamaz.</div>
        <div class="capslock-warn" id="capslock-warn">
          <i class="ti ti-alert-triangle" style="font-size:14px"></i> Caps Lock acik
        </div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text2)">
          <input type="checkbox" name="remember" style="width:14px;height:14px;accent-color:var(--blue)"> Oturumu acik tut
        </label>
      </div>
      <button type="submit" class="btn-submit" onclick="return validate()">
        <div class="spinner" id="spinner"></div>
        <span id="btn-text">Giris Yap</span>
        <i class="ti ti-arrow-right" id="btn-icon"></i>
      </button>
    </form>
    <hr class="divider">
    <div class="footer-links">
      <span>Alma Linux 9 - Nginx - PHP-FPM</span>
      <a href="https://github.com/GMS-Panel/gms-panel" target="_blank">GitHub</a>
    </div>
  </div>
  <div class="sys-info"><?= gethostname() ?> &nbsp;·&nbsp; <span style="color:var(--green)">● Online</span></div>
</div>
<script>
let passVisible=false;
function togglePass(){
  const inp=document.getElementById('password');
  passVisible=!passVisible;
  inp.type=passVisible?'text':'password';
  document.getElementById('eye-icon').className=passVisible?'ti ti-eye-off':'ti ti-eye';
}
function checkCaps(e){
  const w=document.getElementById('capslock-warn');
  if(e.getModifierState&&e.getModifierState('CapsLock'))w.classList.add('show');
  else w.classList.remove('show');
}
function validate(){
  const u=document.querySelector('[name=username]').value.trim();
  const p=document.getElementById('password').value;
  let ok=true;
  if(!u){document.getElementById('err-username').classList.add('show');ok=false;}
  if(!p){document.getElementById('err-password').classList.add('show');ok=false;}
  if(ok){
    document.getElementById('spinner').style.display='block';
    document.getElementById('btn-icon').style.display='none';
    document.getElementById('btn-text').textContent='Giris yapiliyor...';
  }
  return ok;
}
</script>
</body>
</html>
