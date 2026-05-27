<?php
// secure_admin_2026_logs.php
require_once '../config.php';

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: secure_admin_2026_login.php');
    exit;
}

require_once '../includes/Logger.php';
$logger = new Logger();
$stats  = $logger->getStats();

// Paginação
$currentPage = max(1, intval($_GET['page'] ?? 1));
$limit       = 50;
$offset      = ($currentPage - 1) * $limit;

// Filtros
$filtros = [
    'placa'       => trim($_GET['placa']       ?? ''),
    'ip'          => trim($_GET['ip']          ?? ''),
    'data_inicio' => trim($_GET['data_inicio'] ?? ''),
    'data_fim'    => trim($_GET['data_fim']    ?? ''),
];
$hasFilter = array_filter($filtros);

// Tab atual
$tab = $_GET['tab'] ?? 'plate_search';

// Buscar dados
$searchLogs = $logger->getPlateSearchLogs($limit, $offset, $filtros);
$pageLogs   = $logger->getPageAccessLogs($limit, $offset, $filtros);

// Paginação helpers
$totalSearch = $searchLogs['total'] ?? 0;
$totalPages  = max(1, ceil($totalSearch / $limit));

function buildUrl(array $override = []): string {
    global $filtros, $tab;
    $params = array_merge(['tab' => $tab], $filtros, $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logs do Sistema — Toll System Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset & Tokens ─────────────────────────────────────────────────────── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

:root{
  --bg:        #0a0c14;
  --surface:   #111422;
  --surface2:  #171b2e;
  --surface3:  #1c2035;
  --border:    rgba(255,255,255,.07);
  --border2:   rgba(255,255,255,.04);
  --text:      #e2e8f0;
  --muted:     #64748b;
  --muted2:    #94a3b8;
  --accent:    #e5ff51;
  --green:     #22c55e;
  --yellow:    #f59e0b;
  --red:       #ef4444;
  --cyan:      #06b6d4;
  --purple:    #a855f7;
  --sidebar-w: 260px;
  --radius:    14px;
  --shadow:    0 8px 32px rgba(0,0,0,.5);
  --font:      'Inter', system-ui, sans-serif;
  --mono:      'JetBrains Mono', monospace;
}

html{scroll-behavior:smooth}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;line-height:1.5}

/* ── Layout ──────────────────────────────────────────────────────────────── */
.layout{display:flex;min-height:100vh}

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
.sidebar{
  width:var(--sidebar-w);background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;
  z-index:100;transition:transform .3s ease;
}
.sidebar-logo{padding:24px 20px 16px;border-bottom:1px solid var(--border)}
.logo-badge{
  display:inline-flex;align-items:center;gap:10px;
  background:rgba(229,255,81,.08);border:1px solid rgba(229,255,81,.2);
  border-radius:10px;padding:10px 14px;width:100%;
}
.logo-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--accent),#a3e635);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.logo-text{display:flex;flex-direction:column;line-height:1.2}
.logo-title{font-size:13px;font-weight:700;color:var(--accent)}
.logo-sub{font-size:10px;color:var(--muted);font-weight:500;letter-spacing:.5px;text-transform:uppercase}

.sidebar-user{padding:16px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--border)}
.avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#a855f7);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;flex-shrink:0}
.user-info{flex:1;min-width:0}
.user-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-role{font-size:11px;color:var(--green);font-weight:500}
.online-dot{width:8px;height:8px;background:var(--green);border-radius:50%;flex-shrink:0;box-shadow:0 0 6px var(--green);animation:pdot 2s infinite}
@keyframes pdot{0%,100%{opacity:1}50%{opacity:.4}}

.sidebar-nav{flex:1;padding:12px;display:flex;flex-direction:column;gap:2px;overflow-y:auto}
.nav-section{font-size:10px;font-weight:600;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;padding:10px 8px 6px}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--muted);font-size:13.5px;font-weight:500;transition:all .18s ease;position:relative}
.nav-link:hover{background:rgba(255,255,255,.05);color:var(--text)}
.nav-link.active{background:rgba(229,255,81,.1);color:var(--accent);font-weight:600}
.nav-link.active::before{content:'';position:absolute;left:0;top:25%;bottom:25%;width:3px;background:var(--accent);border-radius:0 3px 3px 0}
.nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;min-width:20px;text-align:center}
.nav-badge.yellow{background:var(--yellow)}
.nav-badge.cyan{background:var(--cyan);color:#0a0c14}

.sidebar-footer{padding:16px 20px;border-top:1px solid var(--border)}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--muted);font-size:13px;font-weight:500;transition:all .18s ease;width:100%;background:none;border:none;cursor:pointer;font-family:var(--font)}
.logout-btn:hover{background:rgba(239,68,68,.1);color:var(--red)}

/* ── Main ────────────────────────────────────────────────────────────────── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* ── Topbar ──────────────────────────────────────────────────────────────── */
.topbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:0 28px;height:64px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:50;backdrop-filter:blur(12px);
}
.topbar-left{display:flex;align-items:center;gap:14px}
.menu-toggle{display:none;background:none;border:none;color:var(--text);font-size:20px;cursor:pointer;padding:4px}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted)}
.breadcrumb span:last-child{color:var(--text);font-weight:500}
.topbar-right{display:flex;align-items:center;gap:10px}
.time-display{font-family:var(--mono);font-size:12px;color:var(--muted);background:var(--surface2);border:1px solid var(--border);padding:6px 10px;border-radius:8px}
.icon-btn{width:36px;height:36px;border-radius:8px;background:none;border:1px solid var(--border);color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .18s;text-decoration:none}
.icon-btn:hover{background:rgba(255,255,255,.05);color:var(--text)}

/* ── Content ─────────────────────────────────────────────────────────────── */
.content{padding:28px;flex:1}

.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:28px;flex-wrap:wrap}
.page-title{font-size:22px;font-weight:800;color:var(--text);letter-spacing:-.4px}
.page-subtitle{font-size:13px;color:var(--muted);margin-top:2px}
.header-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:all .18s ease;text-decoration:none;font-family:var(--font);white-space:nowrap}
.btn-primary{background:var(--accent);color:#0a0c14;border-color:var(--accent)}
.btn-primary:hover{background:#d4ec3a;box-shadow:0 0 20px rgba(229,255,81,.3)}
.btn-ghost{background:rgba(255,255,255,.05);color:var(--text);border-color:var(--border)}
.btn-ghost:hover{background:rgba(255,255,255,.09)}
.btn-danger-sm{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.1);color:var(--red);transition:all .18s;font-family:var(--font);white-space:nowrap}
.btn-danger-sm:hover{background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.5)}
.btn-sm{padding:5px 10px;font-size:11.5px;border-radius:7px}
.btn-xs{padding:3px 8px;font-size:11px;border-radius:6px}

/* ── Stats Grid ──────────────────────────────────────────────────────────── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.stat-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.03) 0%,transparent 60%);pointer-events:none}
.stat-card.ac-cyan{border-color:rgba(6,182,212,.2)}
.stat-card.ac-green{border-color:rgba(34,197,94,.2)}
.stat-card.ac-yellow{border-color:rgba(245,158,11,.2)}
.stat-card.ac-purple{border-color:rgba(168,85,247,.2)}
.stat-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.stat-label{font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase}
.stat-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px}
.si-cyan{background:rgba(6,182,212,.1);color:var(--cyan)}
.si-green{background:rgba(34,197,94,.1);color:var(--green)}
.si-yellow{background:rgba(245,158,11,.1);color:var(--yellow)}
.si-purple{background:rgba(168,85,247,.1);color:var(--purple)}
.stat-value{font-size:34px;font-weight:800;color:var(--text);line-height:1;letter-spacing:-1px;font-variant-numeric:tabular-nums}
.stat-hint{font-size:12px;color:var(--muted);margin-top:6px}

/* ── Tabs ────────────────────────────────────────────────────────────────── */
.tab-bar{display:flex;gap:4px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:4px;margin-bottom:20px;flex-wrap:wrap}
.tab{padding:8px 16px;border-radius:7px;font-size:13px;font-weight:500;color:var(--muted);text-decoration:none;transition:all .18s ease;white-space:nowrap;display:flex;align-items:center;gap:6px}
.tab:hover{color:var(--text);background:rgba(255,255,255,.04)}
.tab.active{background:var(--accent);color:#0a0c14;font-weight:700}
.tab-count{font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;background:rgba(0,0,0,.15)}
.tab.active .tab-count{background:rgba(0,0,0,.2)}
.tab:not(.active) .tab-count{background:rgba(255,255,255,.08);color:var(--muted2)}

/* ── Filter panel ────────────────────────────────────────────────────────── */
.filter-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;overflow:hidden}
.filter-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;cursor:pointer;user-select:none}
.filter-title{font-size:13px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:8px}
.filter-toggle{font-size:12px;color:var(--muted);transition:transform .2s}
.filter-toggle.open{transform:rotate(180deg)}
.filter-body{padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:6px;min-width:0}
.filter-label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.filter-input{background:var(--surface2);border:1px solid var(--border);color:var(--text);font-family:var(--font);padding:8px 12px;border-radius:8px;font-size:13px;transition:border-color .18s;min-width:0}
.filter-input:focus{outline:none;border-color:rgba(229,255,81,.4)}
.filter-input::placeholder{color:var(--muted)}
input[type="date"].filter-input::-webkit-calendar-picker-indicator{filter:invert(1) opacity(.4)}

/* Active filter chips */
.filter-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:rgba(229,255,81,.08);border:1px solid rgba(229,255,81,.2);border-radius:20px;font-size:12px;color:var(--accent);font-weight:500}
.chip-remove{cursor:pointer;opacity:.7;transition:opacity .15s;text-decoration:none;color:var(--accent);font-size:14px;line-height:1}
.chip-remove:hover{opacity:1}

/* ── Panel ───────────────────────────────────────────────────────────────── */
.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.panel-header{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap}
.panel-title{font-size:14px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.panel-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px}
.panel-body{padding:0}

/* ── Table ───────────────────────────────────────────────────────────────── */
.table-wrap{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse}
.data-table th{background:var(--surface2);padding:11px 16px;font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;position:sticky;top:0}
.data-table td{padding:11px 16px;font-size:13px;color:var(--text);border-bottom:1px solid var(--border2);vertical-align:middle}
.data-table tbody tr{transition:background .12s}
.data-table tbody tr:hover td{background:rgba(255,255,255,.025)}
.data-table tbody tr:last-child td{border-bottom:none}

.mono{font-family:var(--mono);font-size:11.5px}
.truncate{max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}

/* ── Badges ──────────────────────────────────────────────────────────────── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-found{background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.25)}
.badge-not-found{background:rgba(245,158,11,.12);color:var(--yellow);border:1px solid rgba(245,158,11,.25)}
.badge-error{background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.25)}
.badge-get{background:rgba(6,182,212,.1);color:var(--cyan);border:1px solid rgba(6,182,212,.2)}
.badge-post{background:rgba(245,158,11,.1);color:var(--yellow);border:1px solid rgba(245,158,11,.2)}
.badge-method{background:rgba(168,85,247,.1);color:var(--purple);border:1px solid rgba(168,85,247,.2)}
.badge-neutral{background:rgba(255,255,255,.06);color:var(--muted2);border:1px solid var(--border)}

/* Plate chip */
.plate-chip{display:inline-flex;align-items:center;gap:5px;background:var(--surface3);border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-family:var(--mono);font-size:12px;font-weight:600;color:var(--text);letter-spacing:.5px}

/* IP chip */
.ip-chip{display:inline-flex;align-items:center;gap:5px;font-family:var(--mono);font-size:11.5px;color:var(--cyan);background:rgba(6,182,212,.07);border:1px solid rgba(6,182,212,.15);border-radius:5px;padding:3px 8px}

/* Response time coloring */
.time-fast{color:var(--green);font-weight:600}
.time-mid{color:var(--yellow);font-weight:600}
.time-slow{color:var(--red);font-weight:600}

/* ── Empty state ─────────────────────────────────────────────────────────── */
.empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;color:var(--muted);gap:10px}
.empty-icon{font-size:40px;opacity:.35}
.empty-text{font-size:14px;font-weight:500}
.empty-hint{font-size:12px;color:var(--muted);opacity:.7}

/* ── Pagination ──────────────────────────────────────────────────────────── */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;padding:18px 20px;border-top:1px solid var(--border);flex-wrap:wrap}
.page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:8px;border:1px solid var(--border);background:none;color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;font-family:var(--font);cursor:pointer}
.page-btn:hover{background:rgba(255,255,255,.05);color:var(--text)}
.page-btn.active{background:var(--accent);color:#0a0c14;border-color:var(--accent);font-weight:700}
.page-btn.disabled{opacity:.3;pointer-events:none}
.page-info{font-size:12px;color:var(--muted);padding:0 8px}

/* ── Stats tab ───────────────────────────────────────────────────────────── */
.stats-panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}
.rank-list{list-style:none;display:flex;flex-direction:column;gap:8px;padding:16px}
.rank-item{display:flex;align-items:center;gap:10px}
.rank-num{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0}
.rn-1{background:rgba(229,255,81,.2);color:var(--accent)}
.rn-2{background:rgba(255,255,255,.08);color:var(--muted2)}
.rn-3{background:rgba(245,158,11,.15);color:var(--yellow)}
.rn-other{background:rgba(255,255,255,.05);color:var(--muted)}
.rank-label{flex:1;min-width:0;font-size:12.5px;color:var(--text);font-family:var(--mono);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rank-bar-wrap{width:80px;flex-shrink:0}
.rank-bar-bg{height:5px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden}
.rank-bar-fill{height:100%;background:linear-gradient(90deg,var(--accent),#a3e635);border-radius:3px;transition:width .5s ease}
.rank-count{font-size:11px;font-weight:700;color:var(--muted2);min-width:30px;text-align:right}

/* Hour chart */
.hour-chart{padding:16px;display:flex;flex-direction:column;gap:8px}
.hour-row{display:flex;align-items:center;gap:10px}
.hour-label{font-family:var(--mono);font-size:11px;color:var(--muted);width:40px;flex-shrink:0;text-align:right}
.hour-bar-wrap{flex:1;height:20px;background:rgba(255,255,255,.04);border-radius:5px;overflow:hidden;position:relative}
.hour-bar{height:100%;border-radius:5px;background:linear-gradient(90deg,rgba(6,182,212,.6),rgba(124,58,237,.6));transition:width .6s ease}
.hour-count{font-size:11px;font-weight:600;color:var(--muted2);min-width:30px}

/* ── Live badge ──────────────────────────────────────────────────────────── */
@keyframes ring{0%{transform:scale(1);opacity:.6}100%{transform:scale(2.4);opacity:0}}
.live-badge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:var(--green);position:relative;padding-left:14px}
.live-badge::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:8px;height:8px;border-radius:50%;background:var(--green)}
.live-badge::after{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:8px;height:8px;border-radius:50%;background:var(--green);animation:ring 1.4s ease-out infinite}

/* ── Toast ───────────────────────────────────────────────────────────────── */
.toast-wrap{position:fixed;bottom:24px;right:24px;z-index:999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast{pointer-events:auto;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 16px;font-size:13px;font-weight:500;box-shadow:var(--shadow);display:flex;align-items:center;gap:10px;animation:slide-in .3s ease;max-width:320px}
@keyframes slide-in{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.toast.success{border-color:rgba(34,197,94,.3);color:var(--green)}
.toast.error{border-color:rgba(239,68,68,.3);color:var(--red)}
.toast.fade-out{animation:fade-out .3s ease forwards}
@keyframes fade-out{to{opacity:0;transform:translateX(20px)}}

/* ── Scrollbar ───────────────────────────────────────────────────────────── */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media(max-width:900px){
  .sidebar{transform:translateX(-100%);box-shadow:var(--shadow)}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .menu-toggle{display:flex}
  .stats-panels{grid-template-columns:1fr}
}
@media(max-width:600px){
  .stats-grid{grid-template-columns:1fr 1fr}
  .content{padding:16px}
  .topbar{padding:0 16px}
}
</style>
</head>
<body>

<?php
// Calcular total de registros e paginação do tab atual
$totalRecords = ($tab === 'plate_search') ? ($searchLogs['total'] ?? 0) : count($pageLogs);
$totalPages   = max(1, ceil($totalRecords / $limit));
?>

<div class="layout">

  <!-- ══ SIDEBAR ══════════════════════════════════════════════════════════ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">
        <div class="logo-icon">🛡</div>
        <div class="logo-text">
          <span class="logo-title">Security Admin</span>
          <span class="logo-sub">2026 · Toll System</span>
        </div>
      </div>
    </div>

    <div class="sidebar-user">
      <div class="avatar"><?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_username']) ?></div>
        <div class="user-role">● Administrador</div>
      </div>
      <div class="online-dot"></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">Segurança</div>
      <a href="security_admin_2026.php"           class="nav-link"><span class="nav-icon">🛡</span> Segurança</a>

      <div class="nav-section">Painel</div>
      <a href="secure_admin_2026.php"             class="nav-link"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="secure_admin_2026_logs.php?tab=plate_search" class="nav-link <?php echo $tab === 'plate_search' ? 'active' : '' ?>">
        <span class="nav-icon">🔍</span> Consultas de Placas
        <span class="nav-badge cyan"><?php echo number_format($stats['consultas_hoje'] ?? 0) ?></span>
      </a>
      <a href="secure_admin_2026_logs.php?tab=pages" class="nav-link <?php echo $tab === 'pages' ? 'active' : '' ?>">
        <span class="nav-icon">🌐</span> Acessos às Páginas
      </a>
      <a href="secure_admin_2026_logs.php?tab=stats" class="nav-link <?php echo $tab === 'stats' ? 'active' : '' ?>">
        <span class="nav-icon">📈</span> Estatísticas
      </a>
      <a href="admin_pix_stats.php"               class="nav-link"><span class="nav-icon">💰</span> PIX Gerados</a>
    </nav>

    <div class="sidebar-footer">
      <a href="secure_admin_2026.php?action=logout" class="logout-btn">
        <span style="font-size:16px">🚪</span> Sair do sistema
      </a>
    </div>
  </aside>

  <!-- ══ MAIN ═════════════════════════════════════════════════════════════ -->
  <div class="main">

    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Menu">☰</button>
        <div class="breadcrumb">
          <span>Admin</span>
          <span style="color:var(--border)">›</span>
          <span>Logs</span>
          <span style="color:var(--border)">›</span>
          <span><?php echo match($tab) {
            'plate_search' => 'Consultas de Placas',
            'pages'        => 'Acessos às Páginas',
            'stats'        => 'Estatísticas',
            default        => 'Logs'
          } ?></span>
        </div>
      </div>
      <div class="topbar-right">
        <span class="live-badge">AO VIVO</span>
        <div class="time-display" id="clock">--:--:--</div>
        <a href="<?php echo buildUrl(['page' => $currentPage]) ?>" class="icon-btn" title="Atualizar página">↻</a>
      </div>
    </header>

    <!-- Content -->
    <div class="content">

      <!-- Page header -->
      <div class="page-header">
        <div>
          <div class="page-title">📋 Logs do Sistema</div>
          <div class="page-subtitle">
            <?php echo number_format($totalRecords) ?> registros encontrados
            <?php if ($hasFilter): ?> · <span style="color:var(--accent)">Filtros ativos</span><?php endif ?>
          </div>
        </div>
        <div class="header-actions">
          <?php if ($hasFilter): ?>
          <a href="?tab=<?php echo $tab ?>" class="btn btn-ghost">✕ Limpar filtros</a>
          <?php endif ?>
          <a href="?tab=<?php echo $tab ?>" class="btn btn-primary">↻ Atualizar</a>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="stats-grid">
        <div class="stat-card ac-cyan">
          <div class="stat-header">
            <div class="stat-label">Consultas Hoje</div>
            <div class="stat-icon si-cyan">🔍</div>
          </div>
          <div class="stat-value"><?php echo number_format($stats['consultas_hoje'] ?? 0) ?></div>
          <div class="stat-hint">Buscas de placa no dia</div>
        </div>
        <div class="stat-card ac-purple">
          <div class="stat-header">
            <div class="stat-label">Consultas no Mês</div>
            <div class="stat-icon si-purple">📅</div>
          </div>
          <div class="stat-value"><?php echo number_format($stats['consultas_mes'] ?? 0) ?></div>
          <div class="stat-hint">Total acumulado mensal</div>
        </div>
        <div class="stat-card ac-green">
          <div class="stat-header">
            <div class="stat-label">IPs Únicos</div>
            <div class="stat-icon si-green">🌐</div>
          </div>
          <div class="stat-value"><?php echo number_format($stats['ips_unicos'] ?? 0) ?></div>
          <div class="stat-hint">Visitantes distintos</div>
        </div>
        <div class="stat-card ac-yellow">
          <div class="stat-header">
            <div class="stat-label">Total de Registros</div>
            <div class="stat-icon si-yellow">📦</div>
          </div>
          <div class="stat-value"><?php echo number_format($totalRecords) ?></div>
          <div class="stat-hint">Nesta visualização</div>
        </div>
      </div>

      <!-- Active filter chips -->
      <?php if ($hasFilter): ?>
      <div class="filter-chips">
        <?php if ($filtros['placa']): ?>
        <span class="chip">🚘 Placa: <strong><?php echo htmlspecialchars($filtros['placa']) ?></strong>
          <a class="chip-remove" href="<?php echo buildUrl(['placa'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
        <?php if ($filtros['ip']): ?>
        <span class="chip">🌐 IP: <strong><?php echo htmlspecialchars($filtros['ip']) ?></strong>
          <a class="chip-remove" href="<?php echo buildUrl(['ip'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
        <?php if ($filtros['data_inicio']): ?>
        <span class="chip">📅 De: <strong><?php echo date('d/m/Y', strtotime($filtros['data_inicio'])) ?></strong>
          <a class="chip-remove" href="<?php echo buildUrl(['data_inicio'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
        <?php if ($filtros['data_fim']): ?>
        <span class="chip">📅 Até: <strong><?php echo date('d/m/Y', strtotime($filtros['data_fim'])) ?></strong>
          <a class="chip-remove" href="<?php echo buildUrl(['data_fim'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
      </div>
      <?php endif ?>

      <!-- Filter panel -->
      <div class="filter-panel">
        <div class="filter-header" onclick="toggleFilter()">
          <div class="filter-title">
            <span>⚙</span> Filtros de busca
            <?php if ($hasFilter): ?><span class="badge badge-found" style="font-size:10px">Ativo</span><?php endif ?>
          </div>
          <span class="filter-toggle <?php echo $hasFilter ? 'open' : '' ?>" id="filterToggleIcon">▼</span>
        </div>
        <div class="filter-body" id="filterBody" style="<?php echo !$hasFilter ? 'display:none' : '' ?>">
          <div class="filter-group">
            <label class="filter-label">Placa</label>
            <input class="filter-input" type="text" id="fPlaca" placeholder="ex: ABC-1234" value="<?php echo htmlspecialchars($filtros['placa']) ?>" style="width:140px">
          </div>
          <div class="filter-group">
            <label class="filter-label">IP</label>
            <input class="filter-input" type="text" id="fIP" placeholder="ex: 192.168.1.1" value="<?php echo htmlspecialchars($filtros['ip']) ?>" style="width:160px">
          </div>
          <div class="filter-group">
            <label class="filter-label">Data início</label>
            <input class="filter-input" type="date" id="fDe" value="<?php echo $filtros['data_inicio'] ?>">
          </div>
          <div class="filter-group">
            <label class="filter-label">Data fim</label>
            <input class="filter-input" type="date" id="fAte" value="<?php echo $filtros['data_fim'] ?>">
          </div>
          <div class="filter-group" style="flex-direction:row;gap:8px;margin-top:18px">
            <button class="btn btn-primary btn-sm" onclick="applyFilters()">Filtrar</button>
            <a href="?tab=<?php echo $tab ?>" class="btn btn-ghost btn-sm">Limpar</a>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tab-bar">
        <a href="?tab=plate_search" class="tab <?php echo $tab === 'plate_search' ? 'active' : '' ?>">
          🔍 Consultas de Placas
          <span class="tab-count"><?php echo number_format($searchLogs['total'] ?? 0) ?></span>
        </a>
        <a href="?tab=pages" class="tab <?php echo $tab === 'pages' ? 'active' : '' ?>">
          🌐 Acessos às Páginas
          <span class="tab-count"><?php echo count($pageLogs) ?></span>
        </a>
        <a href="?tab=stats" class="tab <?php echo $tab === 'stats' ? 'active' : '' ?>">
          📈 Estatísticas Avançadas
        </a>
      </div>

      <?php /* ══════════════ TAB: PLATE SEARCH ══════════════ */ ?>
      <?php if ($tab === 'plate_search'): ?>

      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-icon si-cyan" style="background:rgba(6,182,212,.1)">🔍</div>
            Consultas de Placas
          </div>
          <div style="font-size:12px;color:var(--muted)">
            Pág. <?php echo $currentPage ?> de <?php echo $totalPages ?> · <?php echo number_format($searchLogs['total'] ?? 0) ?> total
          </div>
        </div>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Data / Hora</th>
                <th>Placa</th>
                <th>IP</th>
                <th>Resultado</th>
                <th>Veículo</th>
                <th>Tempo</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($searchLogs['data'])): ?>
              <tr>
                <td colspan="7">
                  <div class="empty">
                    <div class="empty-icon">🔍</div>
                    <div class="empty-text">Nenhuma consulta encontrada</div>
                    <?php if ($hasFilter): ?><div class="empty-hint">Tente remover os filtros ativos</div><?php endif ?>
                  </div>
                </td>
              </tr>
              <?php else: foreach ($searchLogs['data'] as $log):
                $resultado = $log['resultado'] ?? '';
                $badge_class = match($resultado) { 'found' => 'badge-found', 'error' => 'badge-error', default => 'badge-not-found' };
                $badge_label = match($resultado) { 'found' => '✓ Encontrado', 'error' => '✗ Erro', default => '⚠ Não encontrado' };
                $ms = intval($log['tempo_resposta'] ?? 0);
                $time_class = $ms > 0 ? ($ms < 500 ? 'time-fast' : ($ms < 1500 ? 'time-mid' : 'time-slow')) : '';
                $dados_str = '';
                if (!empty($log['dados_veiculo'])) {
                    $d = json_decode($log['dados_veiculo'], true);
                    $dados_str = trim(($d['marca'] ?? '') . ' ' . ($d['modelo'] ?? ''));
                }
              ?>
              <tr>
                <td class="mono" style="white-space:nowrap;color:var(--muted2)"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td>
                  <span class="plate-chip">🚘 <?php echo htmlspecialchars($log['placa']) ?></span>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span class="ip-chip"><?php echo htmlspecialchars($log['ip']) ?></span>
                    <button class="btn-danger-sm" onclick="blockIP('<?php echo htmlspecialchars($log['ip'], ENT_QUOTES) ?>')">🚫</button>
                  </div>
                </td>
                <td><span class="badge <?php echo $badge_class ?>"><?php echo $badge_label ?></span></td>
                <td style="color:var(--muted2);font-size:12.5px"><?php echo $dados_str ?: '—' ?></td>
                <td>
                  <?php if ($ms > 0): ?>
                  <span class="mono <?php echo $time_class ?>"><?php echo $ms ?>ms</span>
                  <?php else: ?><span style="color:var(--muted)">—</span><?php endif ?>
                </td>
                <td>
                  <a href="<?php echo buildUrl(['placa' => $log['placa'], 'tab' => 'plate_search', 'page' => 1]) ?>"
                     class="btn btn-ghost btn-xs">Ver</a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($currentPage > 1): ?>
            <a href="<?php echo buildUrl(['page' => 1]) ?>" class="page-btn" title="Primeira">«</a>
            <a href="<?php echo buildUrl(['page' => $currentPage - 1]) ?>" class="page-btn">‹</a>
          <?php else: ?>
            <span class="page-btn disabled">«</span>
            <span class="page-btn disabled">‹</span>
          <?php endif ?>

          <?php
          $window = 2;
          $start  = max(1, $currentPage - $window);
          $end    = min($totalPages, $currentPage + $window);
          if ($start > 1) echo '<span class="page-info">…</span>';
          for ($p = $start; $p <= $end; $p++):
          ?>
            <a href="<?php echo buildUrl(['page' => $p]) ?>"
               class="page-btn <?php echo $p === $currentPage ? 'active' : '' ?>"><?php echo $p ?></a>
          <?php endfor;
          if ($end < $totalPages) echo '<span class="page-info">…</span>';
          ?>

          <?php if ($currentPage < $totalPages): ?>
            <a href="<?php echo buildUrl(['page' => $currentPage + 1]) ?>" class="page-btn">›</a>
            <a href="<?php echo buildUrl(['page' => $totalPages]) ?>" class="page-btn" title="Última">»</a>
          <?php else: ?>
            <span class="page-btn disabled">›</span>
            <span class="page-btn disabled">»</span>
          <?php endif ?>

          <span class="page-info">Pág. <?php echo $currentPage ?> / <?php echo $totalPages ?></span>
        </div>
        <?php endif ?>
      </div>

      <?php /* ══════════════ TAB: PAGE ACCESS ══════════════ */ ?>
      <?php elseif ($tab === 'pages'): ?>

      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-icon" style="background:rgba(168,85,247,.1);color:var(--purple)">🌐</div>
            Acessos às Páginas
          </div>
          <div style="font-size:12px;color:var(--muted)"><?php echo count($pageLogs) ?> registros</div>
        </div>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Data / Hora</th>
                <th>IP</th>
                <th>Página</th>
                <th>Método</th>
                <th>Referer</th>
                <th>User Agent</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pageLogs)): ?>
              <tr>
                <td colspan="6">
                  <div class="empty">
                    <div class="empty-icon">🌐</div>
                    <div class="empty-text">Nenhum acesso registrado</div>
                  </div>
                </td>
              </tr>
              <?php else: foreach ($pageLogs as $log):
                $method = strtoupper($log['metodo'] ?? 'GET');
                $method_class = match($method) { 'GET' => 'badge-get', 'POST' => 'badge-post', default => 'badge-method' };
              ?>
              <tr>
                <td class="mono" style="white-space:nowrap;color:var(--muted2)"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span class="ip-chip"><?php echo htmlspecialchars($log['ip']) ?></span>
                    <button class="btn-danger-sm" onclick="blockIP('<?php echo htmlspecialchars($log['ip'], ENT_QUOTES) ?>')">🚫</button>
                  </div>
                </td>
                <td>
                  <span class="truncate mono" style="font-size:11.5px;max-width:180px" title="<?php echo htmlspecialchars($log['pagina']) ?>">
                    <?php echo htmlspecialchars($log['pagina']) ?>
                  </span>
                </td>
                <td><span class="badge <?php echo $method_class ?>"><?php echo $method ?></span></td>
                <td>
                  <span class="truncate" style="font-size:12px;color:var(--muted2);max-width:160px" title="<?php echo htmlspecialchars($log['referer'] ?? '') ?>">
                    <?php echo $log['referer'] ? htmlspecialchars($log['referer']) : '<span style="color:var(--muted)">—</span>' ?>
                  </span>
                </td>
                <td>
                  <span class="truncate mono" style="font-size:11px;color:var(--muted);max-width:200px" title="<?php echo htmlspecialchars($log['user_agent'] ?? '') ?>">
                    <?php echo htmlspecialchars(substr($log['user_agent'] ?? '—', 0, 55)) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php /* ══════════════ TAB: STATS ══════════════ */ ?>
      <?php elseif ($tab === 'stats'): ?>

      <div class="stats-panels">

        <!-- Top Placas -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <div class="panel-icon" style="background:rgba(229,255,81,.08);color:var(--accent)">🏆</div>
              Top 10 Placas Consultadas
            </div>
          </div>
          <div class="panel-body">
            <?php if (empty($stats['placas_top'])): ?>
            <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Sem dados</div></div>
            <?php else:
              $maxP = max(array_column($stats['placas_top'], 'total') ?: [1]);
            ?>
            <ul class="rank-list">
              <?php foreach ($stats['placas_top'] as $i => $row):
                $pct = round($row['total'] / $maxP * 100);
                $rnClass = match($i) { 0 => 'rn-1', 1 => 'rn-2', 2 => 'rn-3', default => 'rn-other' };
              ?>
              <li class="rank-item">
                <span class="rank-num <?php echo $rnClass ?>"><?php echo $i + 1 ?></span>
                <a href="<?php echo buildUrl(['tab'=>'plate_search','placa'=>$row['placa'],'page'=>1]) ?>"
                   class="rank-label" style="text-decoration:none;color:var(--text);transition:color .15s"
                   onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text)'">
                  <?php echo htmlspecialchars($row['placa']) ?>
                </a>
                <div class="rank-bar-wrap">
                  <div class="rank-bar-bg">
                    <div class="rank-bar-fill" style="width:<?php echo $pct ?>%"></div>
                  </div>
                </div>
                <span class="rank-count"><?php echo number_format($row['total']) ?></span>
              </li>
              <?php endforeach ?>
            </ul>
            <?php endif ?>
          </div>
        </div>

        <!-- Top IPs -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <div class="panel-icon si-cyan" style="background:rgba(6,182,212,.1)">🌐</div>
              Top 10 IPs Mais Ativos
            </div>
          </div>
          <div class="panel-body">
            <?php if (empty($stats['ips_top'])): ?>
            <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Sem dados</div></div>
            <?php else:
              $maxI = max(array_column($stats['ips_top'], 'total') ?: [1]);
            ?>
            <ul class="rank-list">
              <?php foreach ($stats['ips_top'] as $i => $row):
                $pct = round($row['total'] / $maxI * 100);
                $rnClass = match($i) { 0 => 'rn-1', 1 => 'rn-2', 2 => 'rn-3', default => 'rn-other' };
              ?>
              <li class="rank-item">
                <span class="rank-num <?php echo $rnClass ?>"><?php echo $i + 1 ?></span>
                <a href="<?php echo buildUrl(['tab'=>'plate_search','ip'=>$row['ip'],'page'=>1]) ?>"
                   class="rank-label" style="text-decoration:none;color:var(--cyan)"
                   title="Filtrar por este IP">
                  <?php echo htmlspecialchars($row['ip']) ?>
                </a>
                <div style="display:flex;align-items:center;gap:6px">
                  <button class="btn-danger-sm" onclick="blockIP('<?php echo htmlspecialchars($row['ip'], ENT_QUOTES) ?>')" title="Bloquear IP">🚫</button>
                </div>
                <div class="rank-bar-wrap">
                  <div class="rank-bar-bg">
                    <div class="rank-bar-fill" style="width:<?php echo $pct ?>%;background:linear-gradient(90deg,var(--cyan),#0ea5e9)"></div>
                  </div>
                </div>
                <span class="rank-count"><?php echo number_format($row['total']) ?></span>
              </li>
              <?php endforeach ?>
            </ul>
            <?php endif ?>
          </div>
        </div>

        <!-- Horários de Pico -->
        <div class="panel" style="grid-column:1/-1">
          <div class="panel-header">
            <div class="panel-title">
              <div class="panel-icon si-yellow" style="background:rgba(245,158,11,.1)">⏰</div>
              Horários de Pico
            </div>
            <span style="font-size:12px;color:var(--muted)">Top 5 horas mais movimentadas</span>
          </div>
          <div class="panel-body">
            <?php if (empty($stats['horarios_pico'])): ?>
            <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Sem dados</div></div>
            <?php else:
              $maxH = max(array_column($stats['horarios_pico'], 'total') ?: [1]);
            ?>
            <div class="hour-chart">
              <?php foreach ($stats['horarios_pico'] as $hora):
                $h   = str_pad($hora['hora'], 2, '0', STR_PAD_LEFT);
                $pct = round($hora['total'] / $maxH * 100);
              ?>
              <div class="hour-row">
                <span class="hour-label"><?php echo $h ?>h</span>
                <div class="hour-bar-wrap">
                  <div class="hour-bar" style="width:<?php echo $pct ?>%"></div>
                  <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;color:var(--muted2);font-family:var(--mono)">
                    <?php echo number_format($hora['total']) ?>
                  </span>
                </div>
                <span class="hour-count"><?php echo number_format($hora['total']) ?></span>
              </div>
              <?php endforeach ?>
            </div>
            <?php endif ?>
          </div>
        </div>

      </div><!-- /stats-panels -->
      <?php endif ?>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<!-- Sidebar overlay (mobile) -->
<div id="overlay" onclick="toggleSidebar()"
  style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:90;backdrop-filter:blur(2px)"></div>

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<script>
// ── Clock ──────────────────────────────────────────────────────────────────
(function tick(){
  document.getElementById('clock').textContent =
    new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  setTimeout(tick, 1000);
})();

// ── Sidebar ────────────────────────────────────────────────────────────────
function toggleSidebar(){
  const s=document.getElementById('sidebar');
  const o=document.getElementById('overlay');
  s.classList.toggle('open');
  o.style.display=s.classList.contains('open')?'block':'none';
}

// ── Filter panel toggle ────────────────────────────────────────────────────
function toggleFilter(){
  const body = document.getElementById('filterBody');
  const icon = document.getElementById('filterToggleIcon');
  const open = body.style.display !== 'none';
  body.style.display = open ? 'none' : 'flex';
  icon.classList.toggle('open', !open);
}

// ── Apply filters ──────────────────────────────────────────────────────────
function applyFilters(){
  const placa  = document.getElementById('fPlaca').value.trim();
  const ip     = document.getElementById('fIP').value.trim();
  const de     = document.getElementById('fDe').value;
  const ate    = document.getElementById('fAte').value;
  const tab    = '<?php echo $tab ?>';

  let url = '?tab=' + tab + '&page=1';
  if (placa) url += '&placa='       + encodeURIComponent(placa);
  if (ip)    url += '&ip='          + encodeURIComponent(ip);
  if (de)    url += '&data_inicio=' + de;
  if (ate)   url += '&data_fim='    + ate;
  window.location.href = url;
}

// ── Toast helper ───────────────────────────────────────────────────────────
function showToast(msg, type='success'){
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = (type==='success'?'✅':'❌') + ' ' + msg;
  wrap.appendChild(t);
  setTimeout(()=>{ t.classList.add('fade-out'); setTimeout(()=>t.remove(), 350); }, 3500);
}

// ── Block IP ───────────────────────────────────────────────────────────────
function blockIP(ip){
  if (!confirm('Bloquear o IP ' + ip + ' por 60 minutos?')) return;
  fetch('../ajax_block_ip.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ip})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showToast('IP ' + ip + ' bloqueado com sucesso!', 'success');
    } else {
      showToast('Erro: ' + (d.error || 'Falha ao bloquear'), 'error');
    }
  })
  .catch(() => showToast('Erro de conexão ao bloquear IP', 'error'));
}

// ── Animate stat counters ──────────────────────────────────────────────────
document.querySelectorAll('.stat-value').forEach(el => {
  const raw = el.textContent.replace(/\D/g, '');
  if (!raw || raw.length > 7) return;
  const target = parseInt(raw, 10);
  let cur = 0;
  const step = Math.max(1, Math.ceil(target / 40));
  const id = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = cur.toLocaleString('pt-BR');
    if (cur >= target) clearInterval(id);
  }, 16);
});

// ── Enter to filter ────────────────────────────────────────────────────────
['fPlaca','fIP'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
});
</script>
</body>
</html>
