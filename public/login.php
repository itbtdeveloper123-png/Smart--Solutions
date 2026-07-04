<?php
/**
 * login.php — Login / Register Page
 * Users enter username + key from Telegram Bot to access the app
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

$error = '';
$auth = new Auth();

// If already logged in, redirect to index
if ($auth->getCurrentUser()) {
    header('Location: index.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $key = trim($_POST['key'] ?? '');
    $mode = $_POST['mode'] ?? 'new'; // 'new' = first time with key, 'returning' = username only
    
    if ($mode === 'returning') {
        $key = ''; // Username-only login
    }
    
    try {
        $result = $auth->login($username, $key);
        setcookie('auth_token', $result['token'], [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get mode from URL or default to 'new'
$mode = $_GET['mode'] ?? 'new';
?><!DOCTYPE html>
<html lang="km">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title>Form Solver · Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f172a;--surface:#1e293b;--surface2:#334155;
  --border:rgba(148,163,184,0.12);--accent:#818cf8;--accent2:#c084fc;
  --text:#f1f5f9;--muted:#94a3b8;--success:#34d399;--error:#f87171;
  --font:'Inter','Noto Sans Khmer',system-ui,sans-serif;
}
body{
  font-family:var(--font);background:var(--bg);color:var(--text);
  min-height:100dvh;display:flex;align-items:center;justify-content:center;
  padding:20px;-webkit-font-smoothing:antialiased;
}
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 80% 30% at 50% 0%,rgba(129,140,248,0.1) 0%,transparent 50%),
             radial-gradient(ellipse 60% 30% at 50% 100%,rgba(192,132,252,0.08) 0%,transparent 50%);
}
.login-card{
  background:var(--surface);border:1px solid var(--border);border-radius:24px;
  padding:40px 32px;width:100%;max-width:420px;
  box-shadow:0 20px 60px rgba(0,0,0,.4);position:relative;z-index:1;
}
.logo{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;font-size:1.6rem;
  margin:0 auto 16px;box-shadow:0 0 24px rgba(129,140,248,.3);}
h1{font-size:1.4rem;font-weight:800;text-align:center;margin-bottom:4px}
.subtitle{font-size:.8rem;color:var(--muted);text-align:center;margin-bottom:28px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.75rem;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
.form-group input{width:100%;padding:14px 16px;background:var(--bg);border:2px solid var(--border);
  border-radius:14px;color:var(--text);font-family:var(--font);font-size:.9rem;outline:none;transition:border-color .25s}
.form-group input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(129,140,248,.15)}
.btn{width:100%;padding:14px;border:none;border-radius:14px;font-family:var(--font);font-size:.9rem;font-weight:700;
  cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;box-shadow:0 4px 20px rgba(129,140,248,.35)}
.btn-primary:active{transform:scale(.97)}
.btn-telegram{width:100%;padding:12px;margin-top:12px;border:1px solid rgba(0,136,204,.3);border-radius:14px;
  background:rgba(0,136,204,.1);color:#08c;font-family:var(--font);font-size:.82rem;font-weight:600;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .2s}
.btn-telegram:hover{background:rgba(0,136,204,.2)}
.error-msg{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);border-radius:12px;
  padding:10px 14px;color:var(--error);font-size:.8rem;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.credit-info{background:rgba(52,211,153,.06);border:1px solid rgba(52,211,153,.15);border-radius:14px;
  padding:14px;margin-top:20px;text-align:center;font-size:.78rem;color:var(--muted);line-height:1.6}
.credit-info strong{color:var(--success)}
</style>
</head>
<body>

<div class="login-card">
  <div class="logo">🤖</div>
  <h1>Form Solver</h1>
  <p class="subtitle">AI បំពេញចម្លើយ Google Form ដោយស្វ័យប្រវត្តិ</p>

  <?php if ($error): ?>
    <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Mode Toggle -->
  <div style="display:flex;gap:0;margin-bottom:20px;background:var(--bg);border-radius:14px;padding:4px;">
    <button type="button" id="tabNew" onclick="switchMode('new')" 
      style="flex:1;padding:10px;border:none;border-radius:11px;font-family:var(--font);font-size:.8rem;font-weight:600;cursor:pointer;
      background:<?= $mode==='new'?'var(--accent)':'transparent' ?>;color:<?= $mode==='new'?'#fff':'var(--muted)' ?>;">
      <i class="fa-solid fa-user-plus"></i> អ្នកប្រើថ្មី
    </button>
    <button type="button" id="tabReturning" onclick="switchMode('returning')"
      style="flex:1;padding:10px;border:none;border-radius:11px;font-family:var(--font);font-size:.8rem;font-weight:600;cursor:pointer;
      background:<?= $mode==='returning'?'var(--accent)':'transparent' ?>;color:<?= $mode==='returning'?'#fff':'var(--muted)' ?>;">
      <i class="fa-solid fa-arrow-right-to-bracket"></i> មានគណនីហើយ
    </button>
  </div>

  <form method="POST" id="loginForm">
    <input type="hidden" name="mode" id="modeInput" value="<?= $mode ?>">
    
    <div class="form-group">
      <label><i class="fa-solid fa-user"></i> Username</label>
      <input type="text" name="username" placeholder="បញ្ចូលឈ្មោះអ្នកប្រើប្រាស់" required autocomplete="off" autofocus>
    </div>
    
    <div class="form-group" id="keyGroup" style="<?= $mode==='returning'?'display:none':'' ?>">
      <label><i class="fa-solid fa-key"></i> Access Key <span style="color:var(--muted);font-weight:400;">(ពី Telegram Bot)</span></label>
      <input type="text" name="key" id="keyInput" placeholder="FS-XXXXXXXX" <?= $mode==='returning'?'':'required' ?>>
    </div>
    
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid fa-right-to-bracket"></i> ចូលប្រើប្រាស់
    </button>
  </form>

  <a href="https://t.me/SmartSolutionsSupport_bot" target="_blank" class="btn-telegram" id="telegramBtn" style="<?= $mode==='returning'?'display:none':'' ?>">
    <i class="fa-brands fa-telegram"></i> ទទួល Key ពី Telegram Bot
  </a>

  <div class="credit-info" id="creditInfoNew" style="<?= $mode==='returning'?'display:none':'' ?>">
    <i class="fa-solid fa-gift"></i> <strong>25 Credits ឥតគិតថ្លៃ</strong><br>
    ១ដងប្រើ = 5 Credits • Topup <strong>$2.12 = 1000 Credits</strong>
  </div>
  <div class="credit-info" id="creditInfoReturning" style="<?= $mode==='new'?'display:none':'' ?>">
    <i class="fa-solid fa-rotate"></i> <strong>សូមស្វាគមន៍មកកាន់ Form Solver!</strong><br>
    បញ្ចូលតែ Username របស់អ្នកដើម្បីចូលប្រើប្រាស់
  </div>
</div>

<script>
function switchMode(mode) {
  document.getElementById('modeInput').value = mode;
  const isNew = mode === 'new';
  document.getElementById('keyGroup').style.display = isNew ? '' : 'none';
  document.getElementById('keyInput').required = isNew;
  document.getElementById('telegramBtn').style.display = isNew ? '' : 'none';
  document.getElementById('creditInfoNew').style.display = isNew ? '' : 'none';
  document.getElementById('creditInfoReturning').style.display = isNew ? 'none' : '';
  
  // Update tab styles
  document.getElementById('tabNew').style.background = isNew ? 'var(--accent)' : 'transparent';
  document.getElementById('tabNew').style.color = isNew ? '#fff' : 'var(--muted)';
  document.getElementById('tabReturning').style.background = isNew ? 'transparent' : 'var(--accent)';
  document.getElementById('tabReturning').style.color = isNew ? 'var(--muted)' : '#fff';
}
</script>

</body>
</html>
