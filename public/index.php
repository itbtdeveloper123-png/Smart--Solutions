<?php
/**
 * index.php — Form Solver · Mobile-First AI Google Form Auto-Answer
 * Protected — requires login
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/CreditManager.php';

$auth = new Auth();
$user = $auth->requireAuth();
$creditMgr = new CreditManager($user['user_id']);
$balance = $creditMgr->getBalance();
?>
?><!DOCTYPE html>
<html lang="km">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Form Solver · AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f172a;--surface:#1e293b;--surface2:#334155;
  --border:rgba(148,163,184,0.1);--accent:#818cf8;--accent2:#c084fc;
  --accent-glow:rgba(129,140,248,0.2);--text:#f1f5f9;--muted:#94a3b8;
  --success:#34d399;--success-bg:rgba(52,211,153,0.08);
  --error:#f87171;--error-bg:rgba(248,113,113,0.08);
  --warning:#fbbf24;--radius:20px;--radius-sm:14px;
  --font:'Inter','Noto Sans Khmer',system-ui,sans-serif;
  --safe-bottom:env(safe-area-inset-bottom,16px);
  --safe-top:env(safe-area-inset-top,0px);
}
html{height:100%;-webkit-tap-highlight-color:transparent}
body{
  font-family:var(--font);background:var(--bg);color:var(--text);
  min-height:100dvh;display:flex;flex-direction:column;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;padding-top:var(--safe-top);
}
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background:radial-gradient(ellipse 80% 30% at 10% 0%,rgba(129,140,248,0.1) 0%,transparent 50%),
             radial-gradient(ellipse 60% 30% at 90% 100%,rgba(192,132,252,0.08) 0%,transparent 50%);
}

/* ─── HEADER ─── */
.app-header{
  position:sticky;top:0;z-index:50;background:rgba(15,23,42,0.85);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);padding:12px 16px;
  display:flex;align-items:center;gap:12px;
}
.app-header .logo{
  width:40px;height:40px;border-radius:12px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;box-shadow:0 0 20px var(--accent-glow);flex-shrink:0;
}
.app-header .title{font-weight:800;font-size:1.1rem;letter-spacing:-.02em}
.app-header .badge{
  margin-left:auto;font-size:.7rem;font-weight:600;padding:4px 12px;
  border-radius:100px;white-space:nowrap;transition:all .3s;
}
.badge-idle{background:var(--surface2);color:var(--muted);border:1px solid var(--border)}
.badge-busy{background:rgba(129,140,248,0.12);color:var(--accent);border:1px solid rgba(129,140,248,0.3);animation:pulse 1.5s infinite}
.badge-done{background:var(--success-bg);color:var(--success);border:1px solid rgba(52,211,153,0.3)}
.badge-error{background:var(--error-bg);color:var(--error);border:1px solid rgba(248,113,113,0.3)}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}

/* ─── MAIN CONTENT ─── */
.app-content{
  flex:1;overflow-y:auto;padding:16px;position:relative;z-index:1;
  -webkit-overflow-scrolling:touch;
}

/* ─── INPUT CARD ─── */
.input-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:16px;margin-bottom:16px;
}
.input-card label{
  font-size:.75rem;font-weight:600;color:var(--muted);
  text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;display:block;
}
.input-card .url-input{
  width:100%;padding:14px 16px;background:var(--bg);
  border:2px solid var(--border);border-radius:var(--radius-sm);
  color:var(--text);font-family:var(--font);font-size:.9rem;outline:none;
  transition:border-color .25s,box-shadow .25s;
}
.input-card .url-input:focus{border-color:var(--accent);box-shadow:0 0 0 4px var(--accent-glow)}
.input-card .url-input::placeholder{color:var(--muted)}
.input-card .row{display:flex;gap:8px;margin-top:10px}
.input-card select{
  flex:1;padding:12px;background:var(--bg);border:2px solid var(--border);
  border-radius:var(--radius-sm);color:var(--text);font-family:var(--font);
  font-size:.85rem;outline:none;
}
.input-card .btn-solve{
  flex:1;padding:14px;border:none;border-radius:var(--radius-sm);
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  color:#fff;font-family:var(--font);font-size:.9rem;font-weight:700;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 4px 20px rgba(129,140,248,0.35);transition:transform .2s,box-shadow .2s;
}
.btn-solve:active{transform:scale(.97)}
.btn-solve:disabled{opacity:.5;transform:none}

/* ─── CONTEXT ROW ─── */
.context-input{
  width:100%;padding:12px 16px;background:var(--surface);
  border:2px solid var(--border);border-radius:var(--radius-sm);
  color:var(--text);font-family:var(--font);font-size:.85rem;outline:none;
  margin-bottom:12px;transition:border-color .25s;
}
.context-input:focus{border-color:var(--accent)}

/* ─── FORM IFRAME ─── */
.form-preview{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);overflow:hidden;margin-bottom:16px;
}
.form-preview .form-header-bar{
  padding:10px 16px;background:var(--surface2);display:flex;
  align-items:center;justify-content:space-between;font-size:.8rem;
}
.form-preview iframe{width:100%;height:400px;border:none;display:block;background:#fff}
.form-preview .placeholder{
  padding:40px 20px;text-align:center;color:var(--muted);
  display:flex;flex-direction:column;align-items:center;gap:12px;
}
.form-preview .placeholder i{font-size:3rem;opacity:.3}

/* ─── SECTION HEADER ─── */
.section-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:12px;
}
.section-header h2{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:8px}
.section-header .stats{display:flex;gap:8px;font-size:.72rem;color:var(--muted)}

/* ─── ANSWER CARD ─── */
.answer-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius-sm);padding:14px;margin-bottom:10px;
  animation:cardIn .4s ease both;transition:border-color .2s;
}
.answer-card:active{border-color:rgba(129,140,248,0.3)}
@keyframes cardIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.answer-card .card-top{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px}
.answer-card .q-num{
  min-width:26px;height:26px;border-radius:8px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  color:#fff;font-size:.7rem;font-weight:700;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;
}
.answer-card .q-text{font-size:.85rem;font-weight:500;line-height:1.45;flex:1}
.answer-card .q-type{
  font-size:.6rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  color:var(--muted);background:var(--surface2);padding:3px 8px;border-radius:6px;flex-shrink:0;
}
.answer-card .answer-row{
  background:var(--success-bg);border:1px solid rgba(52,211,153,0.18);
  border-radius:10px;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;gap:8px;
}
.answer-row.error-row{background:var(--error-bg);border-color:rgba(248,113,113,0.18)}
.answer-row .answer-val{font-size:.85rem;font-weight:600;color:var(--success);word-break:break-word;flex:1}
.answer-row.error-row .answer-val{color:var(--error)}
.answer-row .copy-icon{
  background:none;border:1px solid rgba(52,211,153,0.25);color:var(--success);
  width:32px;height:32px;border-radius:8px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;
  transition:all .2s;
}
.copy-icon:active{background:var(--success);color:#000;border-color:var(--success)}

/* ─── EMPTY / LOADING STATES ─── */
.empty-state,.loading-state{
  text-align:center;padding:40px 20px;color:var(--muted);
  display:flex;flex-direction:column;align-items:center;gap:12px;
}
.empty-state i{font-size:3.5rem;opacity:.3}
.spinner{
  width:40px;height:40px;border-radius:50%;border:3px solid var(--border);
  border-top-color:var(--accent);animation:spin .8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ─── ACTION BAR ─── */
.action-bar{
  display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;
}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:12px 18px;border:none;border-radius:var(--radius-sm);
  font-family:var(--font);font-size:.82rem;font-weight:600;
  cursor:pointer;transition:transform .15s,box-shadow .15s,opacity .15s;
  white-space:nowrap;-webkit-tap-highlight-color:transparent;
}
.btn:active{transform:scale(.96)}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;box-shadow:0 4px 16px rgba(129,140,248,0.3)}
.btn-outline{background:var(--surface2);border:1px solid var(--border);color:var(--text)}
.btn-success{background:linear-gradient(135deg,#34d399,#10b981);color:#000;box-shadow:0 4px 16px rgba(52,211,153,0.3)}
.btn-accent{background:linear-gradient(135deg,#c084fc,#e879f9);color:#fff;box-shadow:0 4px 16px rgba(192,132,252,0.3)}
.btn-sm{padding:8px 14px;font-size:.75rem;border-radius:10px}
.btn-block{width:100%}

/* ─── CONSOLE HELPER ─── */
.console-helper{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:16px;margin-top:12px;display:none;
}
.console-helper.show{display:block}
.console-helper .ch-title{
  font-size:.8rem;font-weight:600;margin-bottom:10px;
  display:flex;align-items:center;gap:6px;
}
.console-helper textarea{
  width:100%;height:70px;background:var(--bg);color:var(--success);
  border:1px solid rgba(52,211,153,0.2);border-radius:10px;
  padding:10px;font-size:.65rem;font-family:'SF Mono','Consolas',monospace;
  resize:none;outline:none;margin-bottom:10px;
}
.console-helper .ch-actions{display:flex;gap:8px;flex-wrap:wrap}
.console-helper .ch-note{
  font-size:.7rem;color:var(--muted);margin-top:8px;text-align:center;
}
.console-helper .ch-note i{color:var(--warning);margin:0 2px}

/* ─── BOTTOM NAV ─── */
.bottom-nav{
  position:sticky;bottom:0;z-index:50;background:rgba(15,23,42,0.9);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-top:1px solid var(--border);padding:8px 12px calc(8px + var(--safe-bottom));
  display:flex;gap:6px;
}
.bottom-nav .nav-btn{
  flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:2px;padding:8px 4px;border:none;border-radius:12px;
  background:transparent;color:var(--muted);font-family:var(--font);
  font-size:.65rem;font-weight:500;cursor:pointer;transition:all .2s;
  -webkit-tap-highlight-color:transparent;
}
.bottom-nav .nav-btn i{font-size:1.1rem}
.bottom-nav .nav-btn.active{color:var(--accent);background:rgba(129,140,248,0.1)}
.bottom-nav .nav-btn:active{background:var(--surface2)}
.bottom-nav .nav-btn.primary{
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  color:#fff;box-shadow:0 4px 16px rgba(129,140,248,0.35);font-weight:700;
}

/* ─── TOAST ─── */
.toast{
  position:fixed;bottom:100px;left:16px;right:16px;z-index:200;
  background:var(--surface);border:1px solid var(--border);
  color:var(--text);padding:12px 18px;border-radius:var(--radius-sm);
  font-size:.82rem;font-weight:500;text-align:center;
  box-shadow:0 12px 40px rgba(0,0,0,.5);
  animation:toastIn .3s ease,toastOut .3s ease 2.8s forwards;
  pointer-events:none;display:none;
}
@keyframes toastIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes toastOut{from{opacity:1}to{opacity:0}}

/* ─── OVERLAY ─── */
.overlay{
  position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:150;
  display:flex;align-items:center;justify-content:center;padding:20px;
}
.overlay-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:24px;max-width:400px;width:100%;text-align:center;
}
.overlay-card h3{font-size:1.1rem;margin-bottom:8px}
.overlay-card p{font-size:.82rem;color:var(--muted);margin-bottom:16px;line-height:1.5}
.overlay-card .steps{text-align:left;background:var(--surface2);border-radius:var(--radius-sm);padding:14px;margin-bottom:14px}
.overlay-card .steps .step{font-size:.78rem;padding:4px 0;display:flex;align-items:center;gap:8px}
.overlay-card .steps .step i{color:var(--accent);width:20px;text-align:center}

/* ─── ERROR DISPLAY ─── */
.error-msg{
  background:var(--error-bg);border:1px solid rgba(248,113,113,0.2);
  border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:12px;
  font-size:.82rem;color:var(--error);display:flex;align-items:flex-start;gap:8px;
}

/* ─── DESKTOP CLEAN SPLIT-PANEL (≥640px) ─── */
@media(min-width:640px){
  body{padding-top:0;overflow:hidden;height:100dvh}
  
  /* Header */
  .app-header{flex-shrink:0;height:52px;padding:0 20px;background:var(--surface);border-bottom:1px solid var(--border)}
  
  /* Content: fills remaining height */
  .app-content{flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden}
  
  /* Input row */
  .input-card{flex-shrink:0;margin:0;border-radius:0;padding:10px 20px;display:flex;align-items:center;gap:10px;background:var(--surface);border-bottom:1px solid var(--border)}
  .input-card label{display:none}
  .input-card .url-input{flex:1;font-size:.84rem;padding:10px 14px}
  .input-card .row{flex-shrink:0;display:flex;gap:8px}
  .input-card select{font-size:.78rem;padding:10px 12px}
  .input-card .btn-solve{padding:10px 20px;font-size:.84rem}
  
  /* Context */
  .context-input{flex-shrink:0;margin:0;border-radius:0;padding:7px 20px;font-size:.73rem;background:var(--bg);border-bottom:1px solid var(--border);color:var(--muted)}
  
  /* Split panels */
  .desktop-split{flex:1;min-height:0;display:flex!important;flex-direction:row;overflow:hidden}
  
  /* Left: iframe */
  .split-left{flex:1;min-width:0;display:flex!important;flex-direction:column;background:#fff;overflow:hidden}
  .split-left .form-preview{flex:1;min-height:0;display:flex;flex-direction:column;margin:0;border:none;border-radius:0}
  .split-left .form-preview .form-header-bar{
    display:flex;flex-shrink:0;padding:6px 16px;background:var(--surface2);
    font-size:.72rem;border-bottom:1px solid rgba(0,0,0,.06);
  }
  .split-left .form-preview iframe{flex:1;min-height:0;border:none}
  .split-left .form-preview .placeholder{flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;background:var(--bg);color:var(--muted)}
  .split-left .form-preview .placeholder i{font-size:3rem;opacity:.3}
  
  /* Right: answers */
  .split-right{
    width:400px;min-width:320px;display:flex!important;flex-direction:column;
    background:var(--surface);overflow:hidden;border-left:1px solid var(--border);
  }
  .split-right .section-header{flex-shrink:0;padding:14px 16px 10px;border-bottom:1px solid var(--border)}
  .split-right .empty-state,.split-right .loading-state{flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px}
  .split-right #answersContainer{flex:1;min-height:0;overflow-y:auto;padding:6px 12px}
  .split-right #answersContainer::-webkit-scrollbar{width:4px}
  .split-right #answersContainer::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:10px}
  .split-right .action-bar{flex-shrink:0;padding:8px 12px;border-top:1px solid var(--border);margin:0;background:var(--surface)}
  .split-right .console-helper{flex-shrink:0;margin:0;border-radius:0;border-top:1px solid var(--border);max-height:180px;overflow-y:auto}
  
  /* Answer cards */
  .split-right .answer-card{padding:10px 12px;margin-bottom:5px;border-radius:10px}
  .split-right .answer-card .q-text{font-size:.78rem}
  .split-right .answer-card .q-num{min-width:22px;height:22px;font-size:.65rem}
  .split-right .answer-card .answer-row{padding:7px 10px;border-radius:8px}
  
  .console-helper .btn-block{font-size:.73rem}
}

/* ─── MOBILE SPLIT-PANEL (after solving) ─── */
@media(max-width:639px){
  /* When answers are ready, switch to sticky-form layout */
  body.has-answers{overflow:hidden;height:100dvh}
  body.has-answers .app-content{
    display:flex;flex-direction:column;height:100dvh;overflow:hidden;
  }
  body.has-answers .input-card{
    flex-shrink:0;margin:0;border-radius:0;padding:8px 12px;
    background:var(--surface);border-bottom:1px solid var(--border);
    display:flex;align-items:center;gap:8px;
  }
  body.has-answers .input-card label{display:none}
  body.has-answers .input-card .url-input{
    flex:1;font-size:.75rem;padding:8px 10px;margin:0;
    background:var(--bg);border:1px solid var(--border);
  }
  body.has-answers .input-card .row{margin:0;flex-shrink:0}
  body.has-answers .input-card select{font-size:.7rem;padding:8px 6px}
  body.has-answers .input-card .btn-solve{padding:8px 14px;font-size:.75rem}
  body.has-answers .context-input{display:none}
  
  /* Form panel: sticky at top, fixed height */
  body.has-answers .desktop-split{
    flex:1;min-height:0;display:flex!important;flex-direction:column;overflow:hidden;
  }
  body.has-answers .split-left{
    flex-shrink:0;display:flex!important;flex-direction:column;
    height:45vh;max-height:45vh;background:#fff;overflow:hidden;position:relative;
  }
  body.has-answers .split-left .form-preview{
    flex:1;min-height:0;display:flex;flex-direction:column;margin:0;border:none;border-radius:0;
  }
  body.has-answers .split-left .form-preview .form-header-bar{
    flex-shrink:0;padding:5px 10px;font-size:.65rem;background:var(--surface2);
    display:flex;align-items:center;justify-content:space-between;
  }
  body.has-answers .split-left .form-preview .form-header-bar .toggle-form-btn{display:block!important}
  body.has-answers .split-left .form-preview iframe{flex:1;min-height:0;border:none}
  body.has-answers .split-left .form-preview .placeholder{display:none!important}
  
  /* Collapsed form state */
  body.has-answers .split-left.collapsed{height:auto!important;max-height:none!important}
  body.has-answers .split-left.collapsed .form-preview iframe{display:none!important}
  
  /* Resize handle */
  body.has-answers .split-handle{
    flex-shrink:0;height:6px;background:var(--surface2);cursor:row-resize;
    border-top:1px solid var(--border);border-bottom:1px solid var(--border);
    display:flex!important;align-items:center;justify-content:center;
  }
  body.has-answers .split-handle::after{
    content:'';width:32px;height:3px;border-radius:2px;background:var(--muted);opacity:.4;
  }
  
  /* Answers panel: scrollable, fills remaining space */
  body.has-answers .split-right{
    flex:1;min-height:0;display:flex!important;flex-direction:column;
    background:var(--surface);overflow:hidden;
  }
  body.has-answers .split-right .section-header{
    flex-shrink:0;padding:6px 12px;display:flex;align-items:center;
    justify-content:space-between;border-bottom:1px solid var(--border);
  }
  body.has-answers .split-right .section-header h2{font-size:.78rem}
  body.has-answers .split-right .empty-state,.split-right .loading-state{display:none!important}
  body.has-answers .split-right #answersContainer{
    flex:1;min-height:0;overflow-y:auto;padding:4px 8px;
    -webkit-overflow-scrolling:touch;
  }
  body.has-answers .split-right .action-bar{
    flex-shrink:0;padding:6px 8px;border-top:1px solid var(--border);
    display:flex;gap:4px;flex-wrap:wrap;
  }
  body.has-answers .split-right .action-bar .btn{padding:6px 10px;font-size:.7rem}
  body.has-answers .split-right .console-helper{display:none}
  
  /* Compact cards */
  body.has-answers .answer-card{padding:8px 10px;margin-bottom:4px;border-radius:8px}
  body.has-answers .answer-card .q-text{font-size:.73rem}
  body.has-answers .answer-card .q-num{min-width:20px;height:20px;font-size:.6rem;border-radius:5px}
  body.has-answers .answer-card .q-type{font-size:.55rem;padding:2px 6px}
  body.has-answers .answer-card .answer-row{padding:6px 8px;border-radius:6px}
  body.has-answers .answer-card .answer-val{font-size:.75rem}
  body.has-answers .answer-card .copy-icon{width:28px;height:28px;font-size:.7rem}
  
  /* Bottom nav */
  body.has-answers .bottom-nav{
    flex-shrink:0;padding:4px 8px;gap:2px;
    position:static;border-top:1px solid var(--border);
  }
  body.has-answers .bottom-nav .nav-btn{padding:4px 2px;font-size:.6rem}
  body.has-answers .bottom-nav .nav-btn i{font-size:.9rem}
}

/* Mobile default */
.desktop-split{display:block}
.split-left,.split-right{display:block}
.split-handle{display:none}
body.has-answers .split-handle{display:flex}
@media(min-width:640px){
  .split-left{display:flex!important}
  .split-right{display:flex!important}
  .split-handle{display:none!important}
}
</style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header class="app-header">
  <div class="logo">🤖</div>
  <span class="title">Form Solver</span>
  <span class="badge badge-idle" id="statusBadge" style="margin-left:auto;margin-right:8px;">
    <i class="fa-solid fa-circle" style="font-size:.4rem;margin-right:4px;"></i>រង់ចាំ
  </span>
  <span style="font-size:.7rem;color:var(--success);font-weight:600;white-space:nowrap;" id="creditBadge" title="Credits របស់អ្នក">
    <i class="fa-solid fa-coins"></i> <strong id="creditCount"><?= $balance ?></strong>
  </span>
</header>

<!-- ═══ MAIN CONTENT ═══ -->
<main class="app-content" id="appContent">

  <!-- URL Input Card -->
  <div class="input-card">
    <label><i class="fa-solid fa-link"></i> Google Form URL</label>
    <input type="url" class="url-input" id="formUrlInput"
      placeholder="https://docs.google.com/forms/d/..."
      autocomplete="off" autofocus />
    <div class="row">
      <select id="langSelect">
        <option value="">🌐 Auto-detect</option>
        <option value="km">🇰🇭 ភាសាខ្មែរ</option>
        <option value="en">🇺🇸 English</option>
      </select>
      <button class="btn-solve" id="solveBtn" onclick="solveForm()">
        <i class="fa-solid fa-rocket"></i> ដំណើរការ
      </button>
    </div>
  </div>

  <!-- Context -->
  <input type="text" class="context-input" id="contextInput"
    placeholder="📝 បរិបទបន្ថែម (ឧ. ប្រធានបទ, ភាសា...) — ជម្រើស" />

  <!-- Error Display -->
  <div id="errorContainer" style="display:none;"></div>

  <!-- ═══ DESKTOP SPLIT WRAPPER ═══ -->
  <div class="desktop-split">

    <!-- LEFT: Form Preview (iframe) -->
    <div class="split-left">
      <div class="form-preview" id="formPreview">
        <div class="form-header-bar">
          <span><i class="fa-solid fa-file-lines"></i> <strong id="formTitleLabel">Form Preview</strong></span>
          <span style="font-size:.7rem;color:var(--muted);flex:1;text-align:center;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;margin:0 8px;" id="formUrlLabel"></span>
          <button onclick="toggleFormPanel()" class="toggle-form-btn" title="បង្រួម/ពង្រីក Form" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:.8rem;padding:2px 6px;display:none;">
            <i class="fa-solid fa-chevron-up"></i>
          </button>
        </div>
        <div class="placeholder" id="iframePlaceholder">
          <i class="fa-solid fa-arrow-up"></i>
          <span>បញ្ចូល URL ខាងលើដើម្បីមើល Form</span>
        </div>
        <iframe id="formIframe" style="display:none;" allow="camera;microphone;autoplay;clipboard-write"></iframe>
      </div>
    </div>

    <!-- Split resize handle (mobile only) -->
    <div class="split-handle" id="splitHandle"></div>

    <!-- RIGHT: Answers + Actions -->
    <div class="split-right">
      <div class="section-header">
        <h2><i class="fa-solid fa-brain" style="color:var(--accent2);"></i> AI ចម្លើយ</h2>
        <div class="stats" id="summaryStats" style="display:none;">
          <span><i class="fa-solid fa-list-check"></i> <strong id="qCount">0</strong></span>
          <span><i class="fa-solid fa-clock"></i> <strong id="timeStat">0s</strong></span>
        </div>
      </div>
      <div class="empty-state" id="emptyState">
        <i class="fa-solid fa-sparkles"></i>
        <span>ចម្លើយ AI នឹងបង្ហាញនៅទីនេះ</span>
        <span style="font-size:.75rem;">បញ្ចូល URL ហើយចុច "ដំណើរការ"</span>
      </div>
      <div class="loading-state" id="loadingState" style="display:none;">
        <div class="spinner"></div>
        <span>កំពុងវិភាគ Form និងស្វែងរកចម្លើយ...</span>
      </div>
      <div id="answersContainer" style="display:none;"></div>
      <div class="action-bar" id="actionBar" style="display:none;">
        <button class="btn btn-primary btn-sm" onclick="generateFillScript()">
          <i class="fa-solid fa-copy"></i> Copy All
        </button>
        <button class="btn btn-outline btn-sm" onclick="openFormNewTab()">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Form
        </button>
        <button class="btn btn-accent btn-sm" onclick="openMobileFill()">
          <i class="fa-solid fa-mobile-screen"></i> Mobile Fill
        </button>
      </div>
      <div class="console-helper" id="consoleHelper">
        <div class="ch-title">
          <i class="fa-solid fa-terminal"></i> Auto-Fill Script
          <button class="btn btn-sm btn-outline" onclick="document.getElementById('consoleHelper').classList.remove('show')" style="margin-left:auto;padding:4px 10px;font-size:.7rem;">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <textarea id="consoleScript" readonly onclick="this.select()"></textarea>
        <div class="ch-actions">
          <button class="btn btn-accent btn-sm btn-block" onclick="openMobileFill()">
            <i class="fa-solid fa-mobile-screen"></i> 📱 Mobile Fill — ចុចប៊ូតុង Fill លើទម្រង់ផ្ទាល់
          </button>
          <button class="btn btn-primary btn-sm" onclick="copyConsoleScript()">
            <i class="fa-solid fa-copy"></i> Copy Script
          </button>
          <button class="btn btn-outline btn-sm" onclick="openFormNewTab()">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Form
          </button>
        </div>
        <div class="ch-note">
          <i class="fa-solid fa-lightbulb"></i> <strong>Desktop:</strong> F12 → Console → វាយ <code style="color:var(--warning);">allow pasting</code> → Enter → Paste → Enter
        </div>
      </div>
    </div>

  </div><!-- end desktop-split -->

</main>

<!-- ═══ BOTTOM NAV ═══ -->
<nav class="bottom-nav" id="bottomNav">
  <button class="nav-btn active" id="navHome" onclick="scrollToTop()">
    <i class="fa-solid fa-house"></i> ដើម
  </button>
  <button class="nav-btn" id="navCopyAll" onclick="copyAllAnswers()" style="display:none;">
    <i class="fa-solid fa-copy"></i> ចម្លង
  </button>
  <button class="nav-btn primary" id="navFill" onclick="openMobileFill()" style="display:none;">
    <i class="fa-solid fa-wand-magic-sparkles"></i> Fill
  </button>
  <button class="nav-btn" id="navOpenForm" onclick="openFormNewTab()" style="display:none;">
    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open
  </button>
</nav>

<!-- ═══ TOAST ═══ -->
<div class="toast" id="toast"></div>

<!-- ═══ SCRIPTS ═══ -->
<script>
// ═══════════════════════════════════════
// STATE
// ═══════════════════════════════════════
let formData = null;
let isProcessing = false;

// ═══════════════════════════════════════
// SOLVE FORM
// ═══════════════════════════════════════
async function solveForm() {
  const urlInput = document.getElementById('formUrlInput');
  const formUrl = urlInput.value.trim();
  if (!formUrl) { showToast('⚠️ សូមបញ្ចូល Google Form URL!'); urlInput.focus(); return; }
  if (!/docs\.google\.com\/forms/i.test(formUrl)) { showToast('⚠️ សូមបញ្ចូល URL ត្រឹមត្រូវ!'); return; }
  if (isProcessing) return;
  
  // Check credits first
  try {
    const cr = await fetch('api/credits.php', { headers: { 'Authorization': 'Bearer ' + getCookie('auth_token') } });
    const crData = await cr.json();
    if (!crData.can_solve) {
      showTopupModal(crData.credits || 0);
      return;
    }
  } catch(e) { /* proceed anyway, server will validate */ }
  
  isProcessing = true;

  setStatus('busy','<i class="fa-solid fa-spinner fa-spin"></i> កំពុងដំណើរការ...');
  document.getElementById('solveBtn').disabled = true;
  document.getElementById('solveBtn').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> កំពុងដំណើរការ...';
  showLoading();
  hideResults();
  document.body.classList.remove('has-answers');
  document.getElementById('actionBar').style.display = 'none';
  document.getElementById('consoleHelper').classList.remove('show');
  document.getElementById('navCopyAll').style.display = 'none';
  document.getElementById('navFill').style.display = 'none';
  document.getElementById('navOpenForm').style.display = 'none';

  const iframe = document.getElementById('formIframe');
  const placeholder = document.getElementById('iframePlaceholder');
  iframe.style.display = 'none';
  placeholder.style.display = 'flex';

  const context = document.getElementById('contextInput').value.trim();
  const lang = document.getElementById('langSelect').value;
  const fullContext = [context, lang ? 'Answer in ' + (lang === 'km' ? 'Khmer' : 'English') : ''].filter(Boolean).join('. ');

  try {
    const resp = await fetch('api/solve.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ form_url: formUrl, context: fullContext })
    });
    const data = await resp.json();
    if (!resp.ok || data.error) throw new Error(data.error || 'Unknown error');

    formData = data;

    // Load iframe
    if (data.embedUrl) {
      iframe.src = data.embedUrl;
      iframe.style.display = 'block';
      placeholder.style.display = 'none';
      document.getElementById('formTitleLabel').textContent = data.formTitle || 'Google Form';
      document.getElementById('formUrlLabel').textContent = formUrl.substring(0, 60) + '...';
    }

    // Render
    renderAnswers(data);
    updateConsoleHelper();

    // Update credits from solve response
    if (data.credits !== undefined) {
      updateCreditDisplay(data.credits);
    }

    setStatus('done','<i class="fa-solid fa-circle-check"></i> រួចរាល់');

    document.getElementById('actionBar').style.display = 'flex';
    document.getElementById('navCopyAll').style.display = 'flex';
    document.getElementById('navFill').style.display = 'flex';
    document.getElementById('navOpenForm').style.display = 'flex';
    document.getElementById('summaryStats').style.display = 'flex';
    document.getElementById('qCount').textContent = data.questions.length;
    document.getElementById('timeStat').textContent = data.timeTaken + 's';

    // Enable mobile split-panel view
    document.body.classList.add('has-answers');

    // Auto-prompt for mobile
    const isMobile = /Android|iPhone|iPad|iPod|webOS/i.test(navigator.userAgent);
    const hasValid = data.questions.some(q => q.answer && q.answer !== 'N/A' && !q.answer.startsWith('⚠️') && !q.answer.startsWith('❌'));
    if (hasValid) {
      if (isMobile) {
        setStatus('done','<i class="fa-solid fa-mobile-screen"></i> ចុច Fill ខាងក្រោម!');
        setTimeout(() => showToast('📱 ចុច <strong>Fill</strong> នៅរបារខាងក្រោម ដើម្បីបំពេញចម្លើយដោយស្វ័យប្រវត្តិ!'), 1500);
      } else {
        setTimeout(() => autoSubmit(true), 2000);
      }
    }

  } catch (err) {
    console.error('Solve error:', err);
    showError(err.message);
    setStatus('error','<i class="fa-solid fa-circle-exclamation"></i> បរាជ័យ');
  } finally {
    isProcessing = false;
    document.getElementById('solveBtn').disabled = false;
    document.getElementById('solveBtn').innerHTML = '<i class="fa-solid fa-rocket"></i> ដំណើរការ';
  }
}

// ═══════════════════════════════════════
// RENDER ANSWERS
// ═══════════════════════════════════════
function renderAnswers(data) {
  hideLoading();
  hideError();
  const container = document.getElementById('answersContainer');
  container.style.display = 'block';
  document.getElementById('emptyState').style.display = 'none';

  let html = '';
  data.questions.forEach((q, i) => {
    const typeLabel = getTypeLabel(q.type);
    const answerText = q.answer || 'N/A';
    const isError = answerText.startsWith('⚠️') || answerText.startsWith('❌') || answerText === 'N/A';
    html += `<div class="answer-card" style="animation-delay:${i*0.04}s">
      <div class="card-top">
        <div class="q-num">${i+1}</div>
        <div class="q-text">${escHtml(q.question)}</div>
        <div class="q-type">${typeLabel}</div>
      </div>
      <div class="answer-row${isError?' error-row':''}">
        <span class="answer-val">${escHtml(answerText)}</span>
        <button class="copy-icon" onclick="copyAnswer(this,'${escAttr(answerText)}')" title="ចម្លង">
          <i class="fa-solid fa-copy"></i>
        </button>
      </div>
    </div>`;
  });
  container.innerHTML = html;
}

// ═══════════════════════════════════════
// AUTO SUBMIT
// ═══════════════════════════════════════
async function autoSubmit(silent) {
  silent = silent || false;
  if (!formData || !formData.submitUrl) return;
  const params = new URLSearchParams();
  let hasAnswers = false;
  formData.questions.forEach(q => {
    if (q.entryId && q.answer && q.answer !== 'N/A' && !q.answer.startsWith('⚠️') && !q.answer.startsWith('❌')) {
      params.append('entry.' + q.entryId, q.answer); hasAnswers = true;
    }
  });
  if (!hasAnswers) return;
  if (formData.fbzx) { params.append('fbzx', formData.fbzx); params.append('draftResponse', '[null,null,"' + formData.fbzx + '"]'); }
  params.append('fvv', '1'); params.append('pageHistory', '0');

  setStatus('busy','<i class="fa-solid fa-spinner fa-spin"></i> កំពុងបញ្ជូន...');
  try {
    await fetch(formData.submitUrl, { method: 'POST', mode: 'no-cors', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() });
    setStatus('done','<i class="fa-solid fa-circle-check"></i> បានបញ្ជូន!');
    if (!silent) showToast('✅ ចម្លើយបានបញ្ជូនដោយស្វ័យប្រវត្តិ!');
  } catch (err) {
    console.log('Fetch failed, using fallback:', err.message);
    fallbackToManual(generateFillScriptInternal(), silent);
  }
}

function fallbackToManual(script, silent) {
  const textarea = document.getElementById('consoleScript');
  if (textarea) textarea.value = script;
  document.getElementById('consoleHelper').classList.add('show');
  navigator.clipboard.writeText(script).then(() => {
    if (formData.embedUrl) window.open(formData.embedUrl.replace('embedded=true', ''), '_blank');
    setStatus('done','📋 ស្គ្រីបរួចរាល់ — F12 → Console → Paste');
  }).catch(() => { setStatus('done','📋 ប្រើ Console Helper ខាងក្រោម'); });
}

// ═══════════════════════════════════════
// GENERATE FILL SCRIPT
// ═══════════════════════════════════════
function generateFillScriptInternal() {
  const qData = formData.questions.map(q => ({ q: q.question, a: q.answer || '', t: q.type, choices: q.choices || [] }));
  return '(function(){var a=' + JSON.stringify(qData) +
    ';console.clear();console.log("📋 Form Solver — Filling "+a.length+" answers...");' +
    'var cs=document.querySelectorAll("[data-params],.freebirdFormviewerViewItemsItemItem,[role=\\"listitem\\"]:not([data-automation-id]),.freebirdFormviewerViewNumberedItemContainer");' +
    'if(cs.length===0)cs=document.querySelectorAll(".freebirdFormviewerViewItemsItem,[data-item-id]");' +
    'console.log("Found "+cs.length+" containers");var filled=0;' +
    'cs.forEach(function(c,i){if(i>=a.length)return;var ans=a[i],t=ans.t,answer=ans.a;' +
    'if(!answer||answer==="N/A"||answer.startsWith("⚠")||answer.startsWith("❌"))return;' +
    'if(t==="multiple_choice"||t==="dropdown"||t==="checkbox"){var ac=answer.replace(/^[A-Z]\\.\\s*/,"").trim();var ls=c.querySelectorAll("label");var clicked=false;' +
    'ls.forEach(function(l){if((t!=="checkbox"&&clicked))return;var txt=(l.textContent||"").trim();if(txt===ac||txt===answer||txt.includes(ac)||ac.includes(txt)){l.click();clicked=true;filled++;console.log("✅ Q"+(i+1)+": "+txt.substring(0,40));}});' +
    'if(!clicked){var rs=c.querySelectorAll("[role=\\"radio\\"],[role=\\"checkbox\\"]");rs.forEach(function(r){if((t!=="checkbox"&&clicked))return;var lbl=r.getAttribute("aria-label")||r.textContent||"";if(lbl.includes(ac)||ac.includes(lbl)){r.click();clicked=true;filled++;}});}' +
    'if(!clicked)console.warn("❌ Q"+(i+1)+": not found → "+answer.substring(0,30));}' +
    'else if(t==="short_text"||t==="paragraph"||t==="text"){var inp=c.querySelector("input[type=\\"text\\"],input:not([type]),textarea");' +
    'if(!inp)inp=c.querySelector("input,textarea");if(inp){inp.focus();try{var ns=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,"value");if(ns&&ns.set)ns.set.call(inp,answer);else inp.value=answer;}catch(e){inp.value=answer;}' +
    'inp.dispatchEvent(new Event("input",{bubbles:true}));inp.dispatchEvent(new Event("change",{bubbles:true}));filled++;console.log("✅ Q"+(i+1)+": "+answer.substring(0,40));}else{console.warn("❌ Q"+(i+1)+": no input found");}}' +
    'else{var ls2=c.querySelectorAll("label");var done=false;ls2.forEach(function(l){if((l.textContent||"").trim()===answer){l.click();done=true;filled++;}});' +
    'if(!done){var inp2=c.querySelector("input");if(inp2){inp2.value=answer;inp2.dispatchEvent(new Event("change",{bubbles:true}));filled++;done=true;}}' +
    'if(!done)console.warn("❌ Q"+(i+1)+": type="+t+" not handled");}});' +
    'console.log("✅ Filled: "+filled+"/"+a.length);' +
    'alert("✅ បានបំពេញ "+filled+"/"+a.length+" ចម្លើយរួចរាល់!\\n\\nសូមពិនិត្យមើល រួចចុច Submit។");})();';
}

function generateFillScript() {
  if (!formData || !formData.questions) { showToast('⚠️ មិនមានទិន្នន័យ'); return; }
  const script = generateFillScriptInternal();
  navigator.clipboard.writeText(script).then(() => {
    if (formData.embedUrl) window.open(formData.embedUrl.replace('embedded=true', ''), '_blank');
    updateConsoleHelper();
    showToast('✅ ស្គ្រីបបានចម្លង! F12 → Console → "allow pasting" → Paste → Enter');
  }).catch(() => {
    updateConsoleHelper();
    showToast('📋 សូមចម្លងស្គ្រីបពី Console Helper ខាងក្រោម');
  });
}

// ═══════════════════════════════════════
// CONSOLE HELPER
// ═══════════════════════════════════════
function updateConsoleHelper() {
  if (!formData) return;
  const textarea = document.getElementById('consoleScript');
  if (textarea) textarea.value = generateFillScriptInternal();
  document.getElementById('consoleHelper').classList.add('show');
  document.getElementById('consoleHelper').scrollIntoView({ behavior: 'smooth' });
}

function copyConsoleScript() {
  const textarea = document.getElementById('consoleScript');
  if (!textarea || !textarea.value) return;
  textarea.select();
  navigator.clipboard.writeText(textarea.value).then(() => {
    showToast('✅ បានចម្លង! → F12 → Console → "allow pasting" → Enter → Paste → Enter');
  }).catch(() => { textarea.select(); showToast('📋 ចុច Ctrl+C ដើម្បីចម្លង'); });
}

function openFormNewTab() {
  if (!formData) return;
  let formUrl = formData.embedUrl ? formData.embedUrl.replace('embedded=true', '') : '';
  if (!formUrl && formData.submitUrl) formUrl = formData.submitUrl.replace('formResponse', 'viewform');
  if (formUrl) { window.open(formUrl, '_blank'); showToast('🌐 Google Form បានបើកក្នុង Tab ថ្មី!'); }
}

function openMobileFill() {
  if (!formData || !formData.questions) { showToast('⚠️ សូមដំណើរការ Form ជាមុន'); return; }
  let formUrl = formData.embedUrl ? formData.embedUrl.replace('embedded=true', '') : '';
  if (!formUrl && formData.submitUrl) formUrl = formData.submitUrl.replace('formResponse', 'viewform');
  if (!formUrl) { showToast('⚠️ រកមិនឃើញ Form URL'); return; }

  const questions = formData.questions.map(q => ({ question: q.question, answer: q.answer, type: q.type, choices: q.choices || [], entryId: q.entryId }));
  const form = document.createElement('form');
  form.method = 'POST'; form.action = 'mobile-fill.php'; form.target = '_blank'; form.style.display = 'none';
  const input = document.createElement('input');
  input.type = 'hidden'; input.name = 'payload';
  input.value = JSON.stringify({ url: formUrl, questions: questions });
  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();
  setTimeout(() => form.remove(), 1000);
  showToast('📱 កំពុងបើក Mobile Fill — ចុច "🤖 Fill Answers" នៅខាងក្រោមទំព័រ!');
}

// ═══════════════════════════════════════
// COPY HELPERS
// ═══════════════════════════════════════
function copyAnswer(btn, text) {
  navigator.clipboard.writeText(text).then(() => {
    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
    btn.style.background = 'var(--success)'; btn.style.color = '#000';
    setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-copy"></i>'; btn.style.background = ''; btn.style.color = ''; }, 1500);
    showToast('✅ បានចម្លង!');
  }).catch(() => showToast('⚠️ មិនអាចចម្លងបាន'));
}

function copyAllAnswers() {
  if (!formData) return;
  const lines = formData.questions.map((q, i) => (i + 1) + '. ' + q.question + '\n   → ' + (q.answer || 'N/A'));
  navigator.clipboard.writeText(lines.join('\n\n')).then(() => showToast('✅ បានចម្លងចម្លើយទាំងអស់!')).catch(() => showToast('⚠️ មិនអាចចម្លងបាន'));
}

// ═══════════════════════════════════════
// UI HELPERS
// ═══════════════════════════════════════
function setStatus(state, text) {
  const badge = document.getElementById('statusBadge');
  badge.className = 'badge badge-' + state;
  badge.innerHTML = text;
}

function showLoading() {
  document.getElementById('loadingState').style.display = 'flex';
  document.getElementById('emptyState').style.display = 'none';
  document.getElementById('answersContainer').style.display = 'none';
  hideError();
}

function hideLoading() { document.getElementById('loadingState').style.display = 'none'; }

function hideResults() {
  document.getElementById('answersContainer').style.display = 'none';
  document.getElementById('answersContainer').innerHTML = '';
}

function showError(msg) {
  hideLoading();
  const el = document.getElementById('errorContainer');
  el.style.display = 'block';
  el.innerHTML = '<div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> ' + escHtml(msg) + '</div>';
}

function hideError() { document.getElementById('errorContainer').style.display = 'none'; document.getElementById('errorContainer').innerHTML = ''; }

function showToast(msg) {
  const toast = document.getElementById('toast');
  toast.innerHTML = msg;
  toast.style.display = 'block';
  toast.style.animation = 'none'; toast.offsetHeight;
  toast.style.animation = 'toastIn .3s ease, toastOut .3s ease 2.8s forwards';
  clearTimeout(toast._timeout);
  toast._timeout = setTimeout(() => { toast.style.display = 'none'; }, 3200);
}

function scrollToTop() {
  document.getElementById('appContent').scrollTo({ top: 0, behavior: 'smooth' });
}

function toggleFormPanel() {
  const left = document.querySelector('.split-left');
  if (!left) return;
  const btn = document.querySelector('.toggle-form-btn i');
  left.classList.toggle('collapsed');
  if (left.classList.contains('collapsed')) {
    if (btn) { btn.className = 'fa-solid fa-chevron-down'; }
  } else {
    if (btn) { btn.className = 'fa-solid fa-chevron-up'; }
  }
}

function getTypeLabel(type) {
  const map = { 'multiple_choice': 'MCQ', 'checkbox': 'Checkbox', 'dropdown': 'Dropdown', 'short_text': 'Short', 'paragraph': 'Para', 'linear_scale': 'Scale', 'date': 'Date', 'time': 'Time', 'grid': 'Grid' };
  return map[type] || 'Text';
}

function escHtml(str) { const div = document.createElement('div'); div.textContent = str; return div.innerHTML; }
function escAttr(str) { return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;'); }
function getCookie(name) { const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)'); return v ? v[2] : ''; }

// ═══════════════════════════════════════
// CREDITS & TOPUP
// ═══════════════════════════════════════
function updateCreditDisplay(credits) {
  const el = document.getElementById('creditCount');
  if (el) el.textContent = credits;
}

function showTopupModal(currentCredits) {
  const overlay = document.createElement('div');
  overlay.id = '_topupOverlay';
  overlay.className = 'overlay';
  overlay.innerHTML = 
    '<div class="overlay-card">' +
      '<div style="font-size:3rem;margin-bottom:8px;">🪙</div>' +
      '<h3>អស់ Credit ហើយ!</h3>' +
      '<p>អ្នកមាន <strong>' + currentCredits + '</strong> Credits ដែលមិនគ្រប់គ្រាន់ (ត្រូវការ 5)</p>' +
      '<div class="steps">' +
        '<div class="step"><i class="fa-solid fa-qrcode"></i> <strong>Topup $2.12 = 1000 Credits</strong></div>' +
        '<div class="step"><i class="fa-solid fa-1"></i> ចុចប៊ូតុងខាងក្រោមដើម្បីទទួល QR Code</div>' +
        '<div class="step"><i class="fa-solid fa-2"></i> ស្កេន QR ដើម្បីបង់ប្រាក់</div>' +
        '<div class="step"><i class="fa-solid fa-3"></i> Telegram Bot នឹងផ្ញើ Link ផ្ទៀងផ្ទាត់</div>' +
        '<div class="step"><i class="fa-solid fa-4"></i> ចុច Link → ទទួលបាន Credits ភ្លាមៗ!</div>' +
      '</div>' +
      '<button class="btn btn-accent btn-block" onclick="createTopup()" style="margin-bottom:8px;">' +
        '<i class="fa-solid fa-qrcode"></i> ទទួល QR Code សម្រាប់ Topup</button>' +
      '<div id="_qrArea" style="margin-top:8px;"></div>' +
      '<button class="btn btn-outline btn-sm btn-block" onclick="this.parentElement.parentElement.remove()" style="margin-top:8px;">បិទ</button>' +
    '</div>';
  document.body.appendChild(overlay);
  overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.remove(); });
}

async function createTopup() {
  try {
    const resp = await fetch('api/credits.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + getCookie('auth_token') },
      body: JSON.stringify({ action: 'topup' })
    });
    const data = await resp.json();
    if (data.qr_url) {
      const qrArea = document.getElementById('_qrArea');
      qrArea.innerHTML = 
        '<div style="text-align:center;margin-top:8px;">' +
          '<img src="' + data.qr_url + '" alt="QR Code" style="width:200px;border-radius:12px;" onerror="this.src=\'https://via.placeholder.com/200x200/1e293b/818cf8?text=QR+Code\'">' +
          '<p style="font-size:.7rem;color:var(--muted);margin-top:6px;">Ref: ' + data.qr_reference + '</p>' +
          '<p style="font-size:.7rem;color:var(--muted);">ស្កេន QR ដើម្បីបង់ប្រាក់ $2.12 តាម Telegram Bot</p>' +
          '<button class="btn btn-success btn-sm" onclick="verifyTopup(' + data.order_id + ')" style="margin-top:6px;"><i class="fa-solid fa-check"></i> ផ្ទៀងផ្ទាត់ការទូទាត់</button>' +
        '</div>';
    }
  } catch(e) {
    showToast('⚠️ មិនអាចបង្កើត QR Code បានទេ');
  }
}

async function verifyTopup(orderId) {
  try {
    const resp = await fetch('api/credits.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + getCookie('auth_token') },
      body: JSON.stringify({ action: 'verify', order_id: orderId })
    });
    const data = await resp.json();
    if (data.success) {
      updateCreditDisplay(data.credits);
      const overlay = document.getElementById('_topupOverlay');
      if (overlay) overlay.remove();
      showToast('✅ ទទួលបាន 1000 Credits! អាចប្រើប្រាស់បានឥឡូវនេះ!');
    } else {
      showToast('⚠️ ការទូទាត់មិនទាន់បានផ្ទៀងផ្ទាត់នៅឡើយទេ។ សូមពិនិត្យ Telegram Bot។');
    }
  } catch(e) {
    showToast('⚠️ មិនអាចផ្ទៀងផ្ទាត់បានទេ');
  }
}

// ═══════════════════════════════════════
// ENTER KEY
// ═══════════════════════════════════════
document.getElementById('formUrlInput').addEventListener('keydown', function(e) { if (e.key === 'Enter') solveForm(); });
</script>
</body>
</html>