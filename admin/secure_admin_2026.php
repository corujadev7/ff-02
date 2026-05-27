<?php
// security_admin_2026.php — Security Dashboard
require_once '../config.php';

session_start();

// ── Auth guard ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: secure_admin_2026_login.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: secure_admin_2026_login.php');
    exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────
$logsDir  = dirname(__DIR__) . '/logs/';
$secLog   = $logsDir . 'security.log';
$statsFile = $logsDir . 'pix_stats.json';

// Parse security.log (newest first, up to $limit)
function readSecurityLog(string $file, int $limit = 200): array {
    if (!file_exists($file)) return [];
    $lines = array_filter(array_map('trim', file($file)));
    $lines = array_reverse(array_values($lines));
    $out   = [];
    foreach (array_slice($lines, 0, $limit) as $line) {
        // Format: 2026-05-26 14:33:55 | 127.0.0.1 | event | details | uri
        $parts = array_map('trim', explode(' | ', $line));
        if (count($parts) >= 4) {
            $event   = $parts[2] ?? '';
            $threat  = 'low';
            if (preg_match('/block|banned|sql|inject|attack|brute|xss|rce|lfi|rfi|exploit/i', $event . ($parts[3] ?? ''))) {
                $threat = 'critical';
            } elseif (preg_match('/fail|denied|rate|limit|attempt|error|warn/i', $event . ($parts[3] ?? ''))) {
                $threat = 'medium';
            } elseif (preg_match('/access|login|success/i', $event)) {
                $threat = 'low';
            }
            $out[] = [
                'ts'      => $parts[0] ?? '',
                'ip'      => $parts[1] ?? '',
                'event'   => $event,
                'details' => $parts[3] ?? '',
                'uri'     => $parts[4] ?? '',
                'threat'  => $threat,
            ];
        }
    }
    return $out;
}

// Parse rate-limit files
function readRateLimits(string $dir): array {
    $files = glob($dir . 'rate_limit_*') ?: [];
    $out   = [];
    foreach ($files as $f) {
        $data = @json_decode(@file_get_contents($f), true);
        if ($data) {
            $data['file']    = basename($f);
            $data['age_min'] = round((time() - ($data['first_attempt'] ?? time())) / 60, 1);
            $out[] = $data;
        }
    }
    usort($out, fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
    return $out;
}

// PIX stats
$pixStats   = [];
if (file_exists($statsFile)) {
    $pixStats = json_decode(file_get_contents($statsFile), true) ?: [];
}

$secEvents  = readSecurityLog($secLog, 200);
$rateLimits = readRateLimits($logsDir);

// PIX request log (pix_requests.log — format TSV: date\tip\tplate\tamount\tstatus\ttxid\terror)
$pixLogFile = $logsDir . 'pix_requests.log';
$pixLogs    = [];
if (file_exists($pixLogFile)) {
    $rawLines = array_reverse(array_values(array_filter(array_map('trim', file($pixLogFile)))));
    foreach (array_slice($rawLines, 0, 100) as $line) {
        $parts = explode("\t", $line);
        if (count($parts) >= 5) {
            $pixLogs[] = [
                'date'   => $parts[0],
                'ip'     => $parts[1],
                'plate'  => $parts[2],
                'amount' => floatval($parts[3]),
                'status' => $parts[4],
                'txid'   => isset($parts[5]) ? substr($parts[5], 0, 20) : '-',
                'error'  => $parts[6] ?? '-',
            ];
        }
    }
}

// Compute aggregate counters
$totalEvents  = count($secEvents);
$criticalCnt  = count(array_filter($secEvents, fn($e) => $e['threat'] === 'critical'));
$mediumCnt    = count(array_filter($secEvents, fn($e) => $e['threat'] === 'medium'));
$todayStr     = date('Y-m-d');
$todayEvents  = count(array_filter($secEvents, fn($e) => str_starts_with($e['ts'], $todayStr)));
$uniqueIPs    = count(array_unique(array_column($secEvents, 'ip')));
$activeRLs    = count(array_filter($rateLimits, fn($r) => ($r['age_min'] ?? 999) < 60));

$currentTab = $_GET['tab'] ?? 'overview';

// ── Logs sub-tab — defaults (populated only when $currentTab === 'logs') ───
$logTab          = $_GET['logtab'] ?? 'plate_search';
$logStats        = [];
$logFiltros      = ['placa'=>'','ip'=>'','data_inicio'=>'','data_fim'=>''];
$logHasFilter    = false;
$searchLogs      = ['total'=>0,'data'=>[]];
$pageLogs        = [];
$logTotalRecords = 0;
$logLimit        = 50;
$logOffset       = 0;
$currentLogPage  = 1;
$logTotalPages   = 1;

function buildLogUrl(array $override = []): string {
    global $logFiltros, $logTab;
    $params = array_merge(['tab' => 'logs', 'logtab' => $logTab], $logFiltros, $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}

if ($currentTab === 'logs') {
    require_once '../includes/Logger.php';
    $logger          = new Logger();
    $logStats        = $logger->getStats();
    $currentLogPage  = max(1, intval($_GET['page'] ?? 1));
    $logOffset       = ($currentLogPage - 1) * $logLimit;
    $logFiltros      = [
        'placa'       => trim($_GET['placa']       ?? ''),
        'ip'          => trim($_GET['ip']          ?? ''),
        'data_inicio' => trim($_GET['data_inicio'] ?? ''),
        'data_fim'    => trim($_GET['data_fim']    ?? ''),
    ];
    $logHasFilter    = (bool) array_filter($logFiltros);
    $searchLogs      = $logger->getPlateSearchLogs($logLimit, $logOffset, $logFiltros);
    $pageLogs        = $logger->getPageAccessLogs($logLimit, $logOffset, $logFiltros);
    $logTotalRecords = ($logTab === 'plate_search') ? ($searchLogs['total'] ?? 0) : count($pageLogs);
    $logTotalPages   = max(1, ceil($logTotalRecords / $logLimit));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security Admin 2026 — Painel de Segurança</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset & Tokens ──────────────────────────────────────────────────────── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

:root{
  --bg:         #0a0c14;
  --surface:    #111422;
  --surface2:   #171b2e;
  --surface3:   #1c2035;
  --border:     rgba(255,255,255,.07);
  --border2:    rgba(255,255,255,.04);
  --text:       #e2e8f0;
  --muted:      #64748b;
  --muted2:     #94a3b8;
  --accent:     #e5ff51;
  --accent2:    #7c3aed;
  --green:      #22c55e;
  --yellow:     #f59e0b;
  --red:        #ef4444;
  --cyan:       #06b6d4;
  --purple:     #a855f7;
  --sidebar-w:  260px;
  --radius:     14px;
  --shadow:     0 4px 32px rgba(0,0,0,.5);
  --font:       'Inter', system-ui, sans-serif;
  --mono:       'JetBrains Mono', monospace;
}

html{scroll-behavior:smooth}

body{
  font-family:var(--font);
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  overflow-x:hidden;
  line-height:1.5;
}

/* ── Layout ──────────────────────────────────────────────────────────────── */
.layout{display:flex;min-height:100vh}

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
.sidebar{
  width:var(--sidebar-w);
  background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;
  flex-direction:column;
  position:fixed;
  top:0;left:0;bottom:0;
  z-index:100;
  transition:transform .3s ease;
}

.sidebar-logo{
  padding:24px 20px 16px;
  border-bottom:1px solid var(--border);
}
.logo-badge{
  display:inline-flex;
  align-items:center;
  gap:10px;
  background:rgba(229,255,81,.08);
  border:1px solid rgba(229,255,81,.2);
  border-radius:10px;
  padding:10px 14px;
  width:100%;
}
.logo-icon{
  width:32px;height:32px;
  background:linear-gradient(135deg,var(--accent),#a3e635);
  border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:16px;
  flex-shrink:0;
}
.logo-text{
  display:flex;flex-direction:column;
  line-height:1.2;
}
.logo-title{font-size:13px;font-weight:700;color:var(--accent)}
.logo-sub{font-size:10px;color:var(--muted);font-weight:500;letter-spacing:.5px;text-transform:uppercase}

.sidebar-user{
  padding:16px 20px;
  display:flex;align-items:center;gap:12px;
  border-bottom:1px solid var(--border);
}
.avatar{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,var(--accent2),#a855f7);
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:700;color:#fff;
  flex-shrink:0;
}
.user-info{flex:1;min-width:0}
.user-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-role{font-size:11px;color:var(--green);font-weight:500}
.online-dot{width:8px;height:8px;background:var(--green);border-radius:50%;flex-shrink:0;
  box-shadow:0 0 6px var(--green);animation:pulse-dot 2s infinite}

@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}

.sidebar-nav{
  flex:1;
  padding:12px 12px;
  display:flex;flex-direction:column;
  gap:2px;
  overflow-y:auto;
}

.nav-section{
  font-size:10px;font-weight:600;
  color:var(--muted);letter-spacing:.8px;text-transform:uppercase;
  padding:10px 8px 6px;
}

.nav-link{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;
  border-radius:10px;
  text-decoration:none;
  color:var(--muted);
  font-size:13.5px;font-weight:500;
  transition:all .18s ease;
  position:relative;
}
.nav-link:hover{background:rgba(255,255,255,.05);color:var(--text)}
.nav-link.active{
  background:rgba(229,255,81,.1);
  color:var(--accent);
  font-weight:600;
}
.nav-link.active::before{
  content:'';position:absolute;left:0;top:25%;bottom:25%;
  width:3px;background:var(--accent);border-radius:0 3px 3px 0;
}
.nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0}

.nav-badge{
  margin-left:auto;
  background:var(--red);
  color:#fff;
  font-size:10px;font-weight:700;
  padding:2px 6px;border-radius:20px;
  min-width:20px;text-align:center;
}
.nav-badge.yellow{background:var(--yellow)}
.nav-badge.green{background:var(--green)}

.sidebar-footer{
  padding:16px 20px;
  border-top:1px solid var(--border);
}
.logout-btn{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;border-radius:10px;
  text-decoration:none;color:var(--muted);
  font-size:13px;font-weight:500;
  transition:all .18s ease;width:100%;
  background:none;border:none;cursor:pointer;
  font-family:var(--font);
}
.logout-btn:hover{background:rgba(239,68,68,.1);color:var(--red)}

/* ── Main ────────────────────────────────────────────────────────────────── */
.main{
  margin-left:var(--sidebar-w);
  flex:1;
  display:flex;flex-direction:column;
  min-height:100vh;
}

/* ── Topbar ──────────────────────────────────────────────────────────────── */
.topbar{
  background:var(--surface);
  border-bottom:1px solid var(--border);
  padding:0 28px;
  height:64px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:50;
  backdrop-filter:blur(12px);
}
.topbar-left{display:flex;align-items:center;gap:14px}
.menu-toggle{
  display:none;
  background:none;border:none;color:var(--text);
  font-size:20px;cursor:pointer;padding:4px;
}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted)}
.breadcrumb span:last-child{color:var(--text);font-weight:500}
.breadcrumb-sep{color:var(--border)}

.topbar-right{display:flex;align-items:center;gap:12px}

.threat-indicator{
  display:flex;align-items:center;gap:8px;
  background:rgba(239,68,68,.1);
  border:1px solid rgba(239,68,68,.25);
  border-radius:8px;padding:6px 12px;
  font-size:12px;font-weight:600;color:var(--red);
}
.threat-indicator.safe{
  background:rgba(34,197,94,.1);
  border-color:rgba(34,197,94,.25);
  color:var(--green);
}
.threat-dot{width:7px;height:7px;border-radius:50%;background:currentColor;
  animation:pulse-dot 1.5s infinite}

.icon-btn{
  width:36px;height:36px;border-radius:8px;
  background:none;border:1px solid var(--border);
  color:var(--muted);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:15px;transition:all .18s;
  text-decoration:none;
}
.icon-btn:hover{background:rgba(255,255,255,.05);color:var(--text)}

.time-display{
  font-family:var(--mono);
  font-size:12px;color:var(--muted);
  background:var(--surface2);
  border:1px solid var(--border);
  padding:6px 10px;border-radius:8px;
}

/* ── Page content ────────────────────────────────────────────────────────── */
.content{padding:28px;flex:1}

.page-header{
  display:flex;align-items:flex-start;justify-content:space-between;
  gap:16px;margin-bottom:28px;flex-wrap:wrap;
}
.page-title{
  font-size:22px;font-weight:800;color:var(--text);letter-spacing:-.4px;
}
.page-subtitle{font-size:13px;color:var(--muted);margin-top:2px}
.header-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 16px;border-radius:9px;
  font-size:13px;font-weight:600;cursor:pointer;
  border:1px solid transparent;
  transition:all .18s ease;text-decoration:none;
  font-family:var(--font);white-space:nowrap;
}
.btn-primary{background:var(--accent);color:#0a0c14;border-color:var(--accent)}
.btn-primary:hover{background:#d4ec3a;box-shadow:0 0 20px rgba(229,255,81,.3)}
.btn-ghost{background:rgba(255,255,255,.05);color:var(--text);border-color:var(--border)}
.btn-ghost:hover{background:rgba(255,255,255,.09)}
.btn-danger{background:rgba(239,68,68,.15);color:var(--red);border-color:rgba(239,68,68,.3)}
.btn-danger:hover{background:rgba(239,68,68,.25)}
.btn-sm{padding:5px 10px;font-size:11.5px;border-radius:7px}

/* ── Stats Grid ──────────────────────────────────────────────────────────── */
.stats-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:16px;
  margin-bottom:24px;
}

.stat-card{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:20px;
  position:relative;
  overflow:hidden;
  transition:transform .2s,box-shadow .2s;
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow)}

.stat-card::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.03) 0%,transparent 60%);
  pointer-events:none;
}

.stat-card.accent-green{border-color:rgba(34,197,94,.25)}
.stat-card.accent-red{border-color:rgba(239,68,68,.25)}
.stat-card.accent-yellow{border-color:rgba(245,158,11,.25)}
.stat-card.accent-cyan{border-color:rgba(6,182,212,.25)}
.stat-card.accent-purple{border-color:rgba(124,58,237,.25)}

.stat-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:14px;
}
.stat-label{font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.3px;text-transform:uppercase}
.stat-icon{
  width:36px;height:36px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  font-size:17px;
}
.si-green{background:rgba(34,197,94,.12);color:var(--green)}
.si-red{background:rgba(239,68,68,.12);color:var(--red)}
.si-yellow{background:rgba(245,158,11,.12);color:var(--yellow)}
.si-cyan{background:rgba(6,182,212,.12);color:var(--cyan)}
.si-purple{background:rgba(124,58,237,.12);color:#a855f7}
.si-accent{background:rgba(229,255,81,.12);color:var(--accent)}

.stat-value{
  font-size:34px;font-weight:800;color:var(--text);
  line-height:1;letter-spacing:-1px;
  font-variant-numeric:tabular-nums;
}
.stat-value.green{color:var(--green)}
.stat-value.red{color:var(--red)}
.stat-value.yellow{color:var(--yellow)}
.stat-value.cyan{color:var(--cyan)}

.stat-change{
  display:flex;align-items:center;gap:5px;
  margin-top:6px;font-size:12px;font-weight:500;
}
.stat-change.up{color:var(--red)}
.stat-change.down{color:var(--green)}
.stat-change.neutral{color:var(--muted)}

/* ── Cards / Panels ──────────────────────────────────────────────────────── */
.panel{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
  margin-bottom:20px;
}
.panel-header{
  padding:16px 20px;
  display:flex;align-items:center;justify-content:space-between;
  border-bottom:1px solid var(--border);
  gap:12px;flex-wrap:wrap;
}
.panel-title{
  font-size:14px;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:8px;
}
.panel-title-icon{
  width:28px;height:28px;border-radius:7px;
  display:flex;align-items:center;justify-content:center;
  font-size:14px;
}
.panel-body{padding:0}
.panel-body.padded{padding:20px}

/* ── Tabs ────────────────────────────────────────────────────────────────── */
.tab-bar{
  display:flex;gap:4px;
  background:var(--surface2);
  border:1px solid var(--border);
  border-radius:10px;padding:4px;
  margin-bottom:20px;
  flex-wrap:wrap;
}
.tab{
  padding:8px 16px;border-radius:7px;
  font-size:13px;font-weight:500;
  color:var(--muted);text-decoration:none;
  transition:all .18s ease;white-space:nowrap;
}
.tab:hover{color:var(--text);background:rgba(255,255,255,.04)}
.tab.active{background:var(--accent);color:#0a0c14;font-weight:700}

/* ── Filter bar ──────────────────────────────────────────────────────────── */
.filter-bar{
  display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:16px 20px;
  border-bottom:1px solid var(--border);
  background:var(--surface2);
}
.filter-input{
  background:var(--bg);border:1px solid var(--border);
  color:var(--text);font-family:var(--font);
  padding:8px 12px;border-radius:8px;font-size:13px;
  transition:border-color .18s;
  min-width:0;
}
.filter-input:focus{outline:none;border-color:rgba(229,255,81,.4)}
.filter-input::placeholder{color:var(--muted)}

/* ── Table ───────────────────────────────────────────────────────────────── */
.data-table{width:100%;border-collapse:collapse}
.data-table th{
  background:var(--surface2);
  padding:11px 16px;
  font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;
  color:var(--muted);text-align:left;
  border-bottom:1px solid var(--border);
  white-space:nowrap;
}
.data-table td{
  padding:11px 16px;
  font-size:13px;color:var(--text);
  border-bottom:1px solid rgba(255,255,255,.03);
  vertical-align:middle;
}
.data-table tr:hover td{background:rgba(255,255,255,.02)}
.data-table tr:last-child td{border-bottom:none}

.mono{font-family:var(--mono);font-size:12px}

/* ── Badges ──────────────────────────────────────────────────────────────── */
.badge{
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 9px;border-radius:20px;
  font-size:11px;font-weight:700;letter-spacing:.2px;
  white-space:nowrap;
}
.badge-critical{background:rgba(239,68,68,.15);color:var(--red);border:1px solid rgba(239,68,68,.3)}
.badge-medium{background:rgba(245,158,11,.15);color:var(--yellow);border:1px solid rgba(245,158,11,.3)}
.badge-low{background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.25)}
.badge-info{background:rgba(6,182,212,.12);color:var(--cyan);border:1px solid rgba(6,182,212,.25)}
.badge-purple{background:rgba(124,58,237,.12);color:#a855f7;border:1px solid rgba(124,58,237,.25)}
.badge-neutral{background:rgba(255,255,255,.07);color:var(--muted);border:1px solid var(--border)}

/* ── Threat dot ──────────────────────────────────────────────────────────── */
.tdot{
  display:inline-block;width:8px;height:8px;border-radius:50%;
  flex-shrink:0;
}
.tdot-critical{background:var(--red);box-shadow:0 0 5px var(--red)}
.tdot-medium{background:var(--yellow);box-shadow:0 0 5px var(--yellow)}
.tdot-low{background:var(--green)}

/* ── Timeline entries ────────────────────────────────────────────────────── */
.event-log{max-height:480px;overflow-y:auto;scrollbar-width:thin;
  scrollbar-color:var(--border) transparent}
.event-row{
  display:flex;align-items:flex-start;gap:12px;
  padding:12px 20px;
  border-bottom:1px solid rgba(255,255,255,.03);
  transition:background .15s;
  position:relative;
}
.event-row:hover{background:rgba(255,255,255,.025)}
.event-row:last-child{border-bottom:none}
.event-ts{
  font-family:var(--mono);font-size:11px;color:var(--muted);
  white-space:nowrap;flex-shrink:0;margin-top:2px;min-width:130px;
}
.event-body{flex:1;min-width:0}
.event-event{font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:2px}
.event-meta{font-size:11.5px;color:var(--muted)}
.event-ip{
  font-family:var(--mono);font-size:11px;
  color:var(--cyan);background:rgba(6,182,212,.08);
  padding:2px 6px;border-radius:4px;
  flex-shrink:0;align-self:center;white-space:nowrap;
}

/* ── Rate limit cards ────────────────────────────────────────────────────── */
.rl-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
  gap:12px;padding:16px;
}
.rl-card{
  background:var(--surface2);border:1px solid var(--border);
  border-radius:10px;padding:14px;transition:transform .18s;
}
.rl-card:hover{transform:translateY(-2px)}
.rl-card.danger{border-color:rgba(239,68,68,.3)}
.rl-ip{font-family:var(--mono);font-size:13px;font-weight:600;color:var(--text);margin-bottom:8px}
.rl-bar-wrap{
  background:rgba(255,255,255,.05);border-radius:4px;height:6px;overflow:hidden;margin-bottom:8px;
}
.rl-bar{height:100%;border-radius:4px;transition:width .5s ease}
.rl-bar.low{background:var(--green)}
.rl-bar.mid{background:var(--yellow)}
.rl-bar.high{background:var(--red)}
.rl-meta{display:flex;justify-content:space-between;font-size:11px;color:var(--muted)}

/* ── Sparkline ───────────────────────────────────────────────────────────── */
.sparkline{margin-top:10px}

/* ── Pix summary cards ───────────────────────────────────────────────────── */
.pix-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
  gap:14px;padding:16px;
}
.pix-card{
  background:var(--surface2);border:1px solid var(--border);
  border-radius:10px;padding:16px;text-align:center;
}
.pix-icon{font-size:26px;margin-bottom:8px}
.pix-label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.pix-val{font-size:24px;font-weight:800;color:var(--text);margin-top:4px}

/* ── Empty state ─────────────────────────────────────────────────────────── */
.empty{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:60px 20px;
  color:var(--muted);gap:10px;
}
.empty-icon{font-size:40px;opacity:.4}
.empty-text{font-size:14px;font-weight:500}

/* ── Two-column layout ───────────────────────────────────────────────────── */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}

/* ── Pulse ring ──────────────────────────────────────────────────────────── */
@keyframes ring{0%{transform:scale(1);opacity:.6}100%{transform:scale(2.4);opacity:0}}
.live-badge{
  display:inline-flex;align-items:center;gap:6px;
  font-size:11px;font-weight:700;color:var(--red);
  position:relative;padding-left:14px;
}
.live-badge::before{
  content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);
  width:8px;height:8px;border-radius:50%;background:var(--red);
}
.live-badge::after{
  content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);
  width:8px;height:8px;border-radius:50%;background:var(--red);
  animation:ring 1.4s ease-out infinite;
}

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
  .two-col{grid-template-columns:1fr}
}
@media(max-width:600px){
  .stats-grid{grid-template-columns:1fr 1fr}
  .content{padding:16px}
  .topbar{padding:0 16px}
  .stat-value{font-size:26px}
}

/* ── Logs: stat card accent aliases ──────────────────────────────────────── */
.stat-card.ac-cyan{border-color:rgba(6,182,212,.2)}
.stat-card.ac-green{border-color:rgba(34,197,94,.2)}
.stat-card.ac-yellow{border-color:rgba(245,158,11,.2)}
.stat-card.ac-purple{border-color:rgba(168,85,247,.2)}
.stat-hint{font-size:12px;color:var(--muted);margin-top:6px}

/* ── Logs: tab count chip ────────────────────────────────────────────────── */
.tab-count{font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;background:rgba(0,0,0,.15)}
.tab.active .tab-count{background:rgba(0,0,0,.2)}
.tab:not(.active) .tab-count{background:rgba(255,255,255,.08);color:var(--muted2)}

/* ── Logs: buttons ───────────────────────────────────────────────────────── */
.btn-danger-sm{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.1);color:var(--red);transition:all .18s;font-family:var(--font);white-space:nowrap}
.btn-danger-sm:hover{background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.5)}
.btn-xs{padding:3px 8px;font-size:11px;border-radius:6px}

/* ── Logs: filter panel ──────────────────────────────────────────────────── */
.filter-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;overflow:hidden}
.filter-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;cursor:pointer;user-select:none}
.filter-title{font-size:13px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:8px}
.filter-toggle{font-size:12px;color:var(--muted);transition:transform .2s}
.filter-toggle.open{transform:rotate(180deg)}
.filter-body{padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:6px;min-width:0}
.filter-label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
input[type="date"].filter-input::-webkit-calendar-picker-indicator{filter:invert(1) opacity(.4)}

/* ── Logs: active filter chips ───────────────────────────────────────────── */
.filter-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:rgba(229,255,81,.08);border:1px solid rgba(229,255,81,.2);border-radius:20px;font-size:12px;color:var(--accent);font-weight:500}
.chip-remove{cursor:pointer;opacity:.7;transition:opacity .15s;text-decoration:none;color:var(--accent);font-size:14px;line-height:1}
.chip-remove:hover{opacity:1}

/* ── Logs: table ─────────────────────────────────────────────────────────── */
.table-wrap{overflow-x:auto}
.truncate{max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.time-fast{color:var(--green);font-weight:600}
.time-mid{color:var(--yellow);font-weight:600}
.time-slow{color:var(--red);font-weight:600}

/* ── Logs: badges ────────────────────────────────────────────────────────── */
.badge-found{background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.25)}
.badge-not-found{background:rgba(245,158,11,.12);color:var(--yellow);border:1px solid rgba(245,158,11,.25)}
.badge-error{background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.25)}
.badge-get{background:rgba(6,182,212,.1);color:var(--cyan);border:1px solid rgba(6,182,212,.2)}
.badge-post{background:rgba(245,158,11,.1);color:var(--yellow);border:1px solid rgba(245,158,11,.2)}
.badge-method{background:rgba(168,85,247,.1);color:var(--purple);border:1px solid rgba(168,85,247,.2)}

/* ── Logs: chips ─────────────────────────────────────────────────────────── */
.plate-chip{display:inline-flex;align-items:center;gap:5px;background:var(--surface3);border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-family:var(--mono);font-size:12px;font-weight:600;color:var(--text);letter-spacing:.5px}
.ip-chip{display:inline-flex;align-items:center;gap:5px;font-family:var(--mono);font-size:11.5px;color:var(--cyan);background:rgba(6,182,212,.07);border:1px solid rgba(6,182,212,.15);border-radius:5px;padding:3px 8px}

/* ── Logs: pagination ────────────────────────────────────────────────────── */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;padding:18px 20px;border-top:1px solid var(--border);flex-wrap:wrap}
.page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:8px;border:1px solid var(--border);background:none;color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;font-family:var(--font);cursor:pointer}
.page-btn:hover{background:rgba(255,255,255,.05);color:var(--text)}
.page-btn.active{background:var(--accent);color:#0a0c14;border-color:var(--accent);font-weight:700}
.page-btn.disabled{opacity:.3;pointer-events:none}
.page-info{font-size:12px;color:var(--muted);padding:0 8px}

/* ── Logs: stats panels ──────────────────────────────────────────────────── */
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

/* ── Logs: hour chart ────────────────────────────────────────────────────── */
.hour-chart{padding:16px;display:flex;flex-direction:column;gap:8px}
.hour-row{display:flex;align-items:center;gap:10px}
.hour-label{font-family:var(--mono);font-size:11px;color:var(--muted);width:40px;flex-shrink:0;text-align:right}
.hour-bar-wrap{flex:1;height:20px;background:rgba(255,255,255,.04);border-radius:5px;overflow:hidden;position:relative}
.hour-bar{height:100%;border-radius:5px;background:linear-gradient(90deg,rgba(6,182,212,.6),rgba(124,58,237,.6));transition:width .6s ease}
.hour-count{font-size:11px;font-weight:600;color:var(--muted2);min-width:30px}

/* ── Logs: empty-hint ────────────────────────────────────────────────────── */
.empty-hint{font-size:12px;color:var(--muted);opacity:.7}

/* ── Toast ───────────────────────────────────────────────────────────────── */
.toast-wrap{position:fixed;bottom:24px;right:24px;z-index:999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast{pointer-events:auto;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 16px;font-size:13px;font-weight:500;box-shadow:var(--shadow);display:flex;align-items:center;gap:10px;animation:slide-in .3s ease;max-width:320px}
@keyframes slide-in{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.toast.success{border-color:rgba(34,197,94,.3);color:var(--green)}
.toast.error{border-color:rgba(239,68,68,.3);color:var(--red)}
.toast.fade-out{animation:fade-out .3s ease forwards}
@keyframes fade-out{to{opacity:0;transform:translateX(20px)}}

@media(max-width:900px){.stats-panels{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php
// Security helpers
$threat_level = $criticalCnt > 0 ? 'high' : ($mediumCnt > 5 ? 'medium' : 'safe');
$threat_label = ['high'=>'⚠ Ameaças Críticas', 'medium'=>'⚡ Atividade Suspeita', 'safe'=>'✓ Sistema Seguro'];
$threat_class = ['high'=>'', 'medium'=>'', 'safe'=>'safe'];

// Today PIX stats
$todayPix = $pixStats['daily'][$todayStr] ?? ['total_attempts'=>0,'successful'=>0,'failed'=>0,'total_amount'=>0];
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
      <div class="avatar"><?php echo strtoupper(substr($_SESSION['admin_username'],0,1)); ?></div>
      <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
        <div class="user-role">● Administrador</div>
      </div>
      <div class="online-dot"></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">Segurança</div>
      <a href="?tab=overview"   class="nav-link <?php echo $currentTab==='overview'  ?'active':'' ?>">
        <span class="nav-icon">🏠</span> Overview
        <?php if($criticalCnt>0): ?><span class="nav-badge"><?php echo $criticalCnt ?></span><?php endif ?>
      </a>
      <a href="?tab=events"     class="nav-link <?php echo $currentTab==='events'    ?'active':'' ?>">
        <span class="nav-icon">📡</span> Eventos de Segurança
        <span class="nav-badge yellow"><?php echo $todayEvents ?></span>
      </a>
      <a href="?tab=ratelimits" class="nav-link <?php echo $currentTab==='ratelimits'?'active':'' ?>">
        <span class="nav-icon">⏱</span> Rate Limits
        <span class="nav-badge <?php echo $activeRLs>0?'':'green' ?>"><?php echo $activeRLs ?></span>
      </a>

      <div class="nav-section">Monitoramento</div>
      <a href="?tab=pix"        class="nav-link <?php echo $currentTab==='pix'       ?'active':'' ?>">
        <span class="nav-icon">💰</span> Pagamentos PIX
      </a>

      <div class="nav-section">Painel</div>
      <a href="secure_admin_2026.php"      class="nav-link"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="?tab=logs" class="nav-link <?php echo $currentTab==='logs'?'active':'' ?>">
        <span class="nav-icon">📋</span> Logs de Consultas
        <?php if(!empty($logStats['consultas_hoje'])): ?>
          <span class="nav-badge" style="background:var(--cyan);color:#0a0c14"><?php echo number_format($logStats['consultas_hoje']) ?></span>
        <?php endif ?>
      </a>
      <a href="?tab=pix"                    class="nav-link <?php echo $currentTab==='pix'?'active':'' ?>"><span class="nav-icon">📈</span> Estatísticas PIX</a>
    </nav>

    <div class="sidebar-footer">
      <a href="?action=logout" class="logout-btn">
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
          <span class="breadcrumb-sep">›</span>
          <span><?php echo match($currentTab){
            'overview'=>'Overview','events'=>'Eventos','ratelimits'=>'Rate Limits',
            'pix'=>'PIX','logs'=>'Logs',default=>'Overview'
          } ?></span>
        </div>
      </div>
      <div class="topbar-right">
        <div class="threat-indicator <?php echo $threat_level==='safe'?'safe':'' ?>">
          <span class="threat-dot"></span>
          <?php echo $threat_label[$threat_level] ?>
        </div>
        <div class="time-display" id="clock"></div>
        <a href="?tab=<?php echo $currentTab ?>" class="icon-btn" title="Atualizar">↻</a>
      </div>
    </header>

    <!-- Page Content -->
    <div class="content">

      <!-- ── PAGE HEADER ──────────────────────────────────────────────── -->
      <div class="page-header">
        <div>
          <div class="page-title">🛡 Painel de Segurança</div>
          <div class="page-subtitle">Monitoramento em tempo real · Última leitura: <?php echo date('d/m/Y H:i:s') ?></div>
        </div>
        <div class="header-actions">
          <span class="live-badge">AO VIVO</span>
          <a href="?tab=<?php echo $currentTab ?>" class="btn btn-ghost">↻ Atualizar</a>
          <a href="?tab=events" class="btn btn-primary">📡 Ver Eventos</a>
        </div>
      </div>

      <!-- ── TABS ─────────────────────────────────────────────────────── -->
      <div class="tab-bar">
        <a href="?tab=overview"   class="tab <?php echo $currentTab==='overview'  ?'active':'' ?>">🏠 Overview</a>
        <a href="?tab=events"     class="tab <?php echo $currentTab==='events'    ?'active':'' ?>">📡 Eventos</a>
        <a href="?tab=ratelimits" class="tab <?php echo $currentTab==='ratelimits'?'active':'' ?>">⏱ Rate Limits</a>
        <a href="?tab=pix"        class="tab <?php echo $currentTab==='pix'       ?'active':'' ?>">💰 PIX</a>
        <a href="?tab=logs"       class="tab <?php echo $currentTab==='logs'      ?'active':'' ?>">📋 Logs</a>
      </div>

      <?php if ($currentTab === 'overview'): ?>
      <!-- ══════════════════════ OVERVIEW ═══════════════════════════════ -->

      <!-- Stats Cards -->
      <div class="stats-grid">

        <div class="stat-card accent-red">
          <div class="stat-header">
            <div class="stat-label">Ameaças Críticas</div>
            <div class="stat-icon si-red">🚨</div>
          </div>
          <div class="stat-value red"><?php echo $criticalCnt ?></div>
          <div class="stat-change <?php echo $criticalCnt>0?'up':'neutral' ?>">
            <?php echo $criticalCnt>0 ? '↑ Requer atenção' : '✓ Nenhuma ameaça crítica' ?>
          </div>
        </div>

        <div class="stat-card accent-yellow">
          <div class="stat-header">
            <div class="stat-label">Alertas Médios</div>
            <div class="stat-icon si-yellow">⚡</div>
          </div>
          <div class="stat-value yellow"><?php echo $mediumCnt ?></div>
          <div class="stat-change neutral">Eventos suspeitos detectados</div>
        </div>

        <div class="stat-card accent-cyan">
          <div class="stat-header">
            <div class="stat-label">Eventos Hoje</div>
            <div class="stat-icon si-cyan">📅</div>
          </div>
          <div class="stat-value cyan"><?php echo $todayEvents ?></div>
          <div class="stat-change neutral">Total geral: <?php echo $totalEvents ?></div>
        </div>

        <div class="stat-card accent-green">
          <div class="stat-header">
            <div class="stat-label">IPs Únicos</div>
            <div class="stat-icon si-green">🌐</div>
          </div>
          <div class="stat-value green"><?php echo $uniqueIPs ?></div>
          <div class="stat-change neutral">No histórico de eventos</div>
        </div>

        <div class="stat-card accent-yellow">
          <div class="stat-header">
            <div class="stat-label">Rate Limits Ativos</div>
            <div class="stat-icon si-yellow">⏱</div>
          </div>
          <div class="stat-value <?php echo $activeRLs>0?'yellow':'' ?>"><?php echo $activeRLs ?></div>
          <div class="stat-change neutral">Última hora</div>
        </div>

        <div class="stat-card accent-purple">
          <div class="stat-header">
            <div class="stat-label">PIX Hoje</div>
            <div class="stat-icon si-purple">💸</div>
          </div>
          <div class="stat-value"><?php echo $todayPix['total_attempts'] ?></div>
          <div class="stat-change <?php echo ($todayPix['successful']??0)>0?'down':'neutral' ?>">
            ✓ <?php echo $todayPix['successful']??0 ?> gerados com sucesso
          </div>
        </div>

      </div>

      <div class="two-col">

        <!-- Recent Events preview -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <div class="panel-title-icon si-red" style="background:rgba(239,68,68,.1)">🚨</div>
              Ameaças Recentes
            </div>
            <a href="?tab=events" class="btn btn-ghost btn-sm">Ver tudo</a>
          </div>
          <div class="panel-body">
            <?php
            $critical_events = array_filter($secEvents, fn($e)=>$e['threat']==='critical');
            if(empty($critical_events)):
            ?>
            <div class="empty">
              <div class="empty-icon">✅</div>
              <div class="empty-text">Nenhuma ameaça crítica detectada</div>
            </div>
            <?php else: foreach(array_slice($critical_events,0,8) as $ev): ?>
            <div class="event-row">
              <div class="event-ts"><?php echo htmlspecialchars($ev['ts']) ?></div>
              <div class="event-body">
                <div class="event-event"><?php echo htmlspecialchars($ev['event']) ?></div>
                <div class="event-meta"><?php echo htmlspecialchars(substr($ev['details'],0,60)) ?></div>
              </div>
              <div class="event-ip"><?php echo htmlspecialchars($ev['ip']) ?></div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- Rate limit summary -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <div class="panel-title-icon" style="background:rgba(245,158,11,.1);color:var(--yellow)">⏱</div>
              Rate Limits Ativos
            </div>
            <a href="?tab=ratelimits" class="btn btn-ghost btn-sm">Gerenciar</a>
          </div>
          <div class="panel-body">
            <?php if(empty($rateLimits)): ?>
            <div class="empty">
              <div class="empty-icon">🟢</div>
              <div class="empty-text">Nenhum rate limit ativo</div>
            </div>
            <?php else: foreach(array_slice($rateLimits,0,6) as $rl):
              $pct = min(100,intval(($rl['count']??0)/5*100));
              $bar_class = $pct>=80?'high':($pct>=50?'mid':'low');
            ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span class="mono" style="font-size:12px;color:var(--text)"><?php echo htmlspecialchars($rl['ip']??'N/A') ?></span>
                <span class="badge badge-<?php echo $pct>=80?'critical':($pct>=50?'medium':'low') ?>"><?php echo $rl['count']??0 ?> req</span>
              </div>
              <div class="rl-bar-wrap"><div class="rl-bar <?php echo $bar_class ?>" style="width:<?php echo $pct ?>%"></div></div>
              <div style="font-size:11px;color:var(--muted)"><?php echo $rl['age_min'] ?> min atrás</div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

      </div>

      <!-- Threat Distribution -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-title-icon si-accent" style="background:rgba(229,255,81,.1)">📊</div>
            Distribuição de Ameaças
          </div>
        </div>
        <div class="panel-body padded">
          <?php
          $lowCnt = count(array_filter($secEvents, fn($e)=>$e['threat']==='low'));
          $total_nz = max(1,$totalEvents);
          $bars = [
            ['label'=>'Críticas','cnt'=>$criticalCnt,'color'=>'var(--red)'],
            ['label'=>'Médias',  'cnt'=>$mediumCnt,  'color'=>'var(--yellow)'],
            ['label'=>'Baixas',  'cnt'=>$lowCnt,      'color'=>'var(--green)'],
          ];
          foreach($bars as $bar):
            $pct = round($bar['cnt']/$total_nz*100,1);
          ?>
          <div style="margin-bottom:18px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <span style="font-size:13px;font-weight:600;color:var(--text)"><?php echo $bar['label'] ?></span>
              <span style="font-size:13px;font-weight:700;color:var(--text)"><?php echo $bar['cnt'] ?> <span style="color:var(--muted);font-weight:400">(<?php echo $pct ?>%)</span></span>
            </div>
            <div class="rl-bar-wrap" style="height:10px;border-radius:6px">
              <div style="height:100%;width:<?php echo $pct ?>%;background:<?php echo $bar['color'] ?>;border-radius:6px;transition:width .6s ease"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php elseif ($currentTab === 'events'): ?>
      <!-- ══════════════════════ SECURITY EVENTS ════════════════════════ -->

      <?php
      $filterThreat = $_GET['threat'] ?? '';
      $filterIP     = $_GET['ip']     ?? '';
      $filterEv     = $_GET['event']  ?? '';
      $filtered = $secEvents;
      if($filterThreat) $filtered = array_filter($filtered,fn($e)=>$e['threat']===$filterThreat);
      if($filterIP)     $filtered = array_filter($filtered,fn($e)=>str_contains($e['ip'],$filterIP));
      if($filterEv)     $filtered = array_filter($filtered,fn($e)=>str_contains(strtolower($e['event']),strtolower($filterEv)));
      $filtered = array_values($filtered);
      ?>

      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-title-icon si-cyan" style="background:rgba(6,182,212,.1)">📡</div>
            Log de Eventos de Segurança
            <span class="badge badge-info" style="font-size:11px"><?php echo count($filtered) ?> registros</span>
          </div>
          <span class="live-badge">AO VIVO</span>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
          <input class="filter-input" type="text" placeholder="🔍 Filtrar por IP…" id="fi_ip"
            value="<?php echo htmlspecialchars($filterIP) ?>" style="width:160px">
          <input class="filter-input" type="text" placeholder="🔍 Tipo de evento…" id="fi_ev"
            value="<?php echo htmlspecialchars($filterEv) ?>" style="width:200px">
          <select class="filter-input" id="fi_threat">
            <option value="">Todos os níveis</option>
            <option value="critical" <?php echo $filterThreat==='critical'?'selected':'' ?>>🔴 Crítico</option>
            <option value="medium"   <?php echo $filterThreat==='medium'  ?'selected':'' ?>>🟡 Médio</option>
            <option value="low"      <?php echo $filterThreat==='low'     ?'selected':'' ?>>🟢 Baixo</option>
          </select>
          <button class="btn btn-primary btn-sm" onclick="applyEventFilters()">Filtrar</button>
          <button class="btn btn-ghost btn-sm" onclick="location.href='?tab=events'">Limpar</button>
        </div>

        <!-- Event table -->
        <div class="panel-body">
          <div class="event-log" style="max-height:600px">
            <?php if(empty($filtered)): ?>
            <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Nenhum evento encontrado</div></div>
            <?php else: foreach($filtered as $ev):
              $threat_map = ['critical'=>'badge-critical','medium'=>'badge-medium','low'=>'badge-low'];
              $threat_lbl = ['critical'=>'🔴 Crítico','medium'=>'🟡 Médio','low'=>'🟢 Baixo'];
              $dot_class  = ['critical'=>'tdot-critical','medium'=>'tdot-medium','low'=>'tdot-low'];
            ?>
            <div class="event-row">
              <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;margin-top:3px">
                <span class="tdot <?php echo $dot_class[$ev['threat']] ?>"></span>
              </div>
              <div class="event-ts"><?php echo htmlspecialchars($ev['ts']) ?></div>
              <div class="event-body">
                <div class="event-event"><?php echo htmlspecialchars($ev['event']) ?></div>
                <div class="event-meta"><?php echo htmlspecialchars($ev['details']) ?></div>
                <?php if($ev['uri']): ?>
                <div class="event-meta mono" style="margin-top:2px;opacity:.7"><?php echo htmlspecialchars(substr($ev['uri'],0,80)) ?></div>
                <?php endif ?>
              </div>
              <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0">
                <div class="event-ip"><?php echo htmlspecialchars($ev['ip']) ?></div>
                <span class="badge <?php echo $threat_map[$ev['threat']] ?>" style="font-size:10px">
                  <?php echo $threat_lbl[$ev['threat']] ?>
                </span>
              </div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <?php elseif ($currentTab === 'ratelimits'): ?>
      <!-- ══════════════════════ RATE LIMITS ════════════════════════════ -->

      <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
        <div class="stat-card">
          <div class="stat-header"><div class="stat-label">Total de arquivos</div><div class="stat-icon si-yellow">📁</div></div>
          <div class="stat-value"><?php echo count($rateLimits) ?></div>
        </div>
        <div class="stat-card accent-red">
          <div class="stat-header"><div class="stat-label">Ativos (última hora)</div><div class="stat-icon si-red">⚡</div></div>
          <div class="stat-value red"><?php echo $activeRLs ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header"><div class="stat-label">IPs afetados</div><div class="stat-icon si-cyan">🌐</div></div>
          <div class="stat-value"><?php echo count(array_unique(array_column($rateLimits,'ip'))) ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-header"><div class="stat-label">Máx. requisições</div><div class="stat-icon si-accent">📈</div></div>
          <div class="stat-value"><?php echo $rateLimits ? max(array_column($rateLimits,'count')) : 0 ?></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-title-icon" style="background:rgba(245,158,11,.1);color:var(--yellow)">⏱</div>
            IPs com Rate Limit
          </div>
          <span style="font-size:12px;color:var(--muted)">Ordenado por nº de requisições</span>
        </div>
        <div class="panel-body">
          <?php if(empty($rateLimits)): ?>
          <div class="empty"><div class="empty-icon">🟢</div><div class="empty-text">Nenhum rate limit ativo no momento</div></div>
          <?php else: ?>
          <div class="rl-grid">
            <?php foreach($rateLimits as $rl):
              $cnt     = $rl['count'] ?? 0;
              $pct     = min(100,intval($cnt/5*100));
              $bar_cls = $pct>=80?'high':($pct>=50?'mid':'low');
              $is_active = ($rl['age_min']??999) < 60;
            ?>
            <div class="rl-card <?php echo $pct>=80?'danger':'' ?>">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
                <div class="rl-ip"><?php echo htmlspecialchars($rl['ip']??'—') ?></div>
                <?php if($is_active): ?>
                  <span class="badge badge-medium" style="font-size:10px">Ativo</span>
                <?php else: ?>
                  <span class="badge badge-neutral" style="font-size:10px">Expirado</span>
                <?php endif ?>
              </div>
              <div class="rl-bar-wrap"><div class="rl-bar <?php echo $bar_cls ?>" style="width:<?php echo $pct ?>%"></div></div>
              <div class="rl-meta">
                <span><strong style="color:var(--text)"><?php echo $cnt ?></strong> requisições</span>
                <span><?php echo $rl['age_min'] ?> min atrás</span>
              </div>
              <div style="margin-top:8px;font-size:11px;color:var(--muted);font-family:var(--mono)">
                <?php echo date('d/m H:i', $rl['first_attempt']??0) ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php elseif ($currentTab === 'pix'): ?>
      <!-- ══════════════════════ PIX ════════════════════════════════════ -->

      <!-- Overall Stats -->
      <div class="stats-grid">
        <div class="stat-card accent-cyan">
          <div class="stat-header"><div class="stat-label">Total Tentativas</div><div class="stat-icon si-cyan">📊</div></div>
          <div class="stat-value"><?php echo number_format($pixStats['total_attempts']??0) ?></div>
          <div class="stat-change neutral">Hoje: <?php echo $todayPix['total_attempts'] ?></div>
        </div>
        <div class="stat-card accent-green">
          <div class="stat-header"><div class="stat-label">PIX Gerados</div><div class="stat-icon si-green">✅</div></div>
          <div class="stat-value green"><?php echo number_format($pixStats['total_successful']??0) ?></div>
          <div class="stat-change down">Hoje: <?php echo $todayPix['successful']??0 ?></div>
        </div>
        <div class="stat-card accent-red">
          <div class="stat-header"><div class="stat-label">Falhas</div><div class="stat-icon si-red">❌</div></div>
          <div class="stat-value red"><?php echo number_format($pixStats['total_failed']??0) ?></div>
          <div class="stat-change neutral">
            Taxa: <?php
              $tot = max(1,$pixStats['total_attempts']??1);
              echo number_format(($pixStats['total_failed']??0)/$tot*100,1) ?>%
          </div>
        </div>
        <div class="stat-card accent-purple">
          <div class="stat-header"><div class="stat-label">Valor Total</div><div class="stat-icon si-purple">💰</div></div>
          <div class="stat-value" style="font-size:24px">R$ <?php echo number_format($pixStats['total_amount']??0,2,',','.') ?></div>
          <div class="stat-change down">Hoje: R$ <?php echo number_format($todayPix['total_amount']??0,2,',','.') ?></div>
        </div>
      </div>

      <!-- Daily table -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-title-icon si-purple" style="background:rgba(124,58,237,.1)">📅</div>
            Histórico Diário de PIX
          </div>
        </div>
        <div class="panel-body" style="overflow-x:auto">
          <?php $daily = array_reverse(array_slice(($pixStats['daily'] ?? []),0,30,true),true); ?>
          <?php if(empty($daily)): ?>
          <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Nenhum dado disponível</div></div>
          <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Tentativas</th>
                <th>Sucesso</th>
                <th>Falhas</th>
                <th>Taxa Sucesso</th>
                <th>Valor Gerado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($daily as $date=>$d):
                $succ = $d['successful']??0;
                $att  = max(1,$d['total_attempts']??1);
                $rate = round($succ/$att*100,1);
              ?>
              <tr>
                <td class="mono"><?php echo date('d/m/Y',strtotime($date)) ?></td>
                <td><?php echo number_format($d['total_attempts']??0) ?></td>
                <td><span class="badge badge-low"><?php echo $succ ?></span></td>
                <td><span class="badge <?php echo ($d['failed']??0)>0?'badge-critical':'badge-neutral' ?>"><?php echo $d['failed']??0 ?></span></td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div class="rl-bar-wrap" style="width:80px;margin:0"><div class="rl-bar low" style="width:<?php echo $rate ?>%"></div></div>
                    <span style="font-size:12px;font-weight:600;color:var(--green)"><?php echo $rate ?>%</span>
                  </div>
                </td>
                <td style="font-weight:700;color:var(--accent)">R$ <?php echo number_format($d['total_amount']??0,2,',','.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <?php endif; ?>

      <?php if ($currentTab === 'logs'): ?>
      <!-- ══════════════════════ LOGS ══════════════════════════════════════ -->

      <!-- Stats cards -->
      <div class="stats-grid">
        <div class="stat-card ac-cyan">
          <div class="stat-header"><div class="stat-label">Consultas Hoje</div><div class="stat-icon si-cyan">🔍</div></div>
          <div class="stat-value"><?php echo number_format($logStats['consultas_hoje'] ?? 0) ?></div>
          <div class="stat-hint">Buscas de placa no dia</div>
        </div>
        <div class="stat-card ac-purple">
          <div class="stat-header"><div class="stat-label">Consultas no Mês</div><div class="stat-icon si-purple">📅</div></div>
          <div class="stat-value"><?php echo number_format($logStats['consultas_mes'] ?? 0) ?></div>
          <div class="stat-hint">Total acumulado mensal</div>
        </div>
        <div class="stat-card ac-green">
          <div class="stat-header"><div class="stat-label">IPs Únicos</div><div class="stat-icon si-green">🌐</div></div>
          <div class="stat-value"><?php echo number_format($logStats['ips_unicos'] ?? 0) ?></div>
          <div class="stat-hint">Visitantes distintos</div>
        </div>
        <div class="stat-card ac-yellow">
          <div class="stat-header"><div class="stat-label">Total de Registros</div><div class="stat-icon si-yellow">📦</div></div>
          <div class="stat-value"><?php echo number_format($logTotalRecords) ?></div>
          <div class="stat-hint">Nesta visualização</div>
        </div>
      </div>

      <!-- Active filter chips -->
      <?php if ($logHasFilter): ?>
      <div class="filter-chips">
        <?php if ($logFiltros['placa']): ?>
        <span class="chip">🚘 Placa: <strong><?php echo htmlspecialchars($logFiltros['placa']) ?></strong>
          <a class="chip-remove" href="<?php echo buildLogUrl(['placa'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
        <?php if ($logFiltros['ip']): ?>
        <span class="chip">🌐 IP: <strong><?php echo htmlspecialchars($logFiltros['ip']) ?></strong>
          <a class="chip-remove" href="<?php echo buildLogUrl(['ip'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
        <?php if ($logFiltros['data_inicio']): ?>
        <span class="chip">📅 De: <strong><?php echo date('d/m/Y', strtotime($logFiltros['data_inicio'])) ?></strong>
          <a class="chip-remove" href="<?php echo buildLogUrl(['data_inicio'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
        <?php if ($logFiltros['data_fim']): ?>
        <span class="chip">📅 Até: <strong><?php echo date('d/m/Y', strtotime($logFiltros['data_fim'])) ?></strong>
          <a class="chip-remove" href="<?php echo buildLogUrl(['data_fim'=>'','page'=>1]) ?>">×</a>
        </span>
        <?php endif ?>
      </div>
      <?php endif ?>

      <!-- Filter panel -->
      <div class="filter-panel">
        <div class="filter-header" onclick="toggleLogFilter()">
          <div class="filter-title">
            <span>⚙</span> Filtros de busca
            <?php if ($logHasFilter): ?><span class="badge badge-low" style="font-size:10px">Ativo</span><?php endif ?>
          </div>
          <span class="filter-toggle <?php echo $logHasFilter ? 'open' : '' ?>" id="logFilterIcon">▼</span>
        </div>
        <div class="filter-body" id="logFilterBody" style="<?php echo !$logHasFilter ? 'display:none' : '' ?>">
          <div class="filter-group">
            <label class="filter-label">Placa</label>
            <input class="filter-input" type="text" id="fPlaca" placeholder="ex: ABC-1234"
              value="<?php echo htmlspecialchars($logFiltros['placa']) ?>" style="width:140px">
          </div>
          <div class="filter-group">
            <label class="filter-label">IP</label>
            <input class="filter-input" type="text" id="fIP" placeholder="ex: 192.168.1.1"
              value="<?php echo htmlspecialchars($logFiltros['ip']) ?>" style="width:160px">
          </div>
          <div class="filter-group">
            <label class="filter-label">Data início</label>
            <input class="filter-input" type="date" id="fDe" value="<?php echo $logFiltros['data_inicio'] ?>">
          </div>
          <div class="filter-group">
            <label class="filter-label">Data fim</label>
            <input class="filter-input" type="date" id="fAte" value="<?php echo $logFiltros['data_fim'] ?>">
          </div>
          <div class="filter-group" style="flex-direction:row;gap:8px;margin-top:18px">
            <button class="btn btn-primary btn-sm" onclick="applyLogFilters()">Filtrar</button>
            <a href="<?php echo buildLogUrl(['placa'=>'','ip'=>'','data_inicio'=>'','data_fim'=>'','page'=>1]) ?>"
               class="btn btn-ghost btn-sm">Limpar</a>
          </div>
        </div>
      </div>

      <!-- Sub-tabs -->
      <div class="tab-bar">
        <a href="<?php echo buildLogUrl(['logtab'=>'plate_search','page'=>1]) ?>"
           class="tab <?php echo $logTab==='plate_search'?'active':'' ?>">
          🔍 Consultas de Placas
          <span class="tab-count"><?php echo number_format($searchLogs['total'] ?? 0) ?></span>
        </a>
        <a href="<?php echo buildLogUrl(['logtab'=>'pages','page'=>1]) ?>"
           class="tab <?php echo $logTab==='pages'?'active':'' ?>">
          🌐 Acessos às Páginas
          <span class="tab-count"><?php echo count($pageLogs) ?></span>
        </a>
        <a href="<?php echo buildLogUrl(['logtab'=>'stats','page'=>1]) ?>"
           class="tab <?php echo $logTab==='stats'?'active':'' ?>">
          📈 Estatísticas Avançadas
        </a>
      </div>

      <?php if ($logTab === 'plate_search'): ?>
      <!-- ── CONSULTAS DE PLACAS ──────────────────────────────────────────── -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-title-icon si-cyan" style="background:rgba(6,182,212,.1)">🔍</div>
            Consultas de Placas
          </div>
          <div style="font-size:12px;color:var(--muted)">
            Pág. <?php echo $currentLogPage ?> de <?php echo $logTotalPages ?> · <?php echo number_format($searchLogs['total'] ?? 0) ?> total
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
              <tr><td colspan="7">
                <div class="empty">
                  <div class="empty-icon">🔍</div>
                  <div class="empty-text">Nenhuma consulta encontrada</div>
                  <?php if ($logHasFilter): ?><div class="empty-hint">Tente remover os filtros ativos</div><?php endif ?>
                </div>
              </td></tr>
              <?php else: foreach ($searchLogs['data'] as $log):
                $resultado   = $log['resultado'] ?? '';
                $bdg_cls     = match($resultado) { 'found'=>'badge-found','error'=>'badge-error',default=>'badge-not-found' };
                $bdg_lbl     = match($resultado) { 'found'=>'✓ Encontrado','error'=>'✗ Erro',default=>'⚠ Não encontrado' };
                $ms          = intval($log['tempo_resposta'] ?? 0);
                $time_cls    = $ms > 0 ? ($ms < 500 ? 'time-fast' : ($ms < 1500 ? 'time-mid' : 'time-slow')) : '';
                $dados_str   = '';
                if (!empty($log['dados_veiculo'])) {
                    $dv = json_decode($log['dados_veiculo'], true);
                    $dados_str = trim(($dv['marca']??'').' '.($dv['modelo']??''));
                }
              ?>
              <tr>
                <td class="mono" style="white-space:nowrap;color:var(--muted2)"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td><span class="plate-chip">🚘 <?php echo htmlspecialchars($log['placa']) ?></span></td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span class="ip-chip"><?php echo htmlspecialchars($log['ip']) ?></span>
                    <button class="btn-danger-sm" onclick="blockIP('<?php echo htmlspecialchars($log['ip'],ENT_QUOTES) ?>')">🚫</button>
                  </div>
                </td>
                <td><span class="badge <?php echo $bdg_cls ?>"><?php echo $bdg_lbl ?></span></td>
                <td style="color:var(--muted2);font-size:12.5px"><?php echo $dados_str ?: '—' ?></td>
                <td>
                  <?php if ($ms > 0): ?>
                    <span class="mono <?php echo $time_cls ?>"><?php echo $ms ?>ms</span>
                  <?php else: ?><span style="color:var(--muted)">—</span><?php endif ?>
                </td>
                <td>
                  <a href="<?php echo buildLogUrl(['logtab'=>'plate_search','placa'=>$log['placa'],'page'=>1]) ?>"
                     class="btn btn-ghost btn-xs">Ver</a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Paginação -->
        <?php if ($logTotalPages > 1): ?>
        <div class="pagination">
          <?php if ($currentLogPage > 1): ?>
            <a href="<?php echo buildLogUrl(['page'=>1]) ?>" class="page-btn" title="Primeira">«</a>
            <a href="<?php echo buildLogUrl(['page'=>$currentLogPage-1]) ?>" class="page-btn">‹</a>
          <?php else: ?>
            <span class="page-btn disabled">«</span><span class="page-btn disabled">‹</span>
          <?php endif ?>
          <?php
          $win = 2;
          $ps  = max(1, $currentLogPage - $win);
          $pe  = min($logTotalPages, $currentLogPage + $win);
          if ($ps > 1) echo '<span class="page-info">…</span>';
          for ($p = $ps; $p <= $pe; $p++):
          ?>
            <a href="<?php echo buildLogUrl(['page'=>$p]) ?>"
               class="page-btn <?php echo $p===$currentLogPage?'active':'' ?>"><?php echo $p ?></a>
          <?php endfor;
          if ($pe < $logTotalPages) echo '<span class="page-info">…</span>';
          ?>
          <?php if ($currentLogPage < $logTotalPages): ?>
            <a href="<?php echo buildLogUrl(['page'=>$currentLogPage+1]) ?>" class="page-btn">›</a>
            <a href="<?php echo buildLogUrl(['page'=>$logTotalPages]) ?>" class="page-btn" title="Última">»</a>
          <?php else: ?>
            <span class="page-btn disabled">›</span><span class="page-btn disabled">»</span>
          <?php endif ?>
          <span class="page-info">Pág. <?php echo $currentLogPage ?> / <?php echo $logTotalPages ?></span>
        </div>
        <?php endif ?>
      </div>

      <?php elseif ($logTab === 'pages'): ?>
      <!-- ── ACESSOS ÀS PÁGINAS ──────────────────────────────────────────── -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-title-icon" style="background:rgba(168,85,247,.1);color:var(--purple)">🌐</div>
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
              <tr><td colspan="6">
                <div class="empty">
                  <div class="empty-icon">🌐</div>
                  <div class="empty-text">Nenhum acesso registrado</div>
                </div>
              </td></tr>
              <?php else: foreach ($pageLogs as $log):
                $method     = strtoupper($log['metodo'] ?? 'GET');
                $meth_cls   = match($method) { 'GET'=>'badge-get','POST'=>'badge-post',default=>'badge-method' };
              ?>
              <tr>
                <td class="mono" style="white-space:nowrap;color:var(--muted2)"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span class="ip-chip"><?php echo htmlspecialchars($log['ip']) ?></span>
                    <button class="btn-danger-sm" onclick="blockIP('<?php echo htmlspecialchars($log['ip'],ENT_QUOTES) ?>')">🚫</button>
                  </div>
                </td>
                <td>
                  <span class="truncate mono" style="font-size:11.5px;max-width:180px"
                        title="<?php echo htmlspecialchars($log['pagina']) ?>">
                    <?php echo htmlspecialchars($log['pagina']) ?>
                  </span>
                </td>
                <td><span class="badge <?php echo $meth_cls ?>"><?php echo $method ?></span></td>
                <td>
                  <span class="truncate" style="font-size:12px;color:var(--muted2);max-width:160px"
                        title="<?php echo htmlspecialchars($log['referer'] ?? '') ?>">
                    <?php echo $log['referer'] ? htmlspecialchars($log['referer']) : '<span style="color:var(--muted)">—</span>' ?>
                  </span>
                </td>
                <td>
                  <span class="truncate mono" style="font-size:11px;color:var(--muted);max-width:200px"
                        title="<?php echo htmlspecialchars($log['user_agent'] ?? '') ?>">
                    <?php echo htmlspecialchars(substr($log['user_agent'] ?? '—', 0, 55)) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($logTab === 'stats'): ?>
      <!-- ── ESTATÍSTICAS AVANÇADAS ──────────────────────────────────────── -->
      <div class="stats-panels">

        <!-- Top Placas -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <div class="panel-title-icon" style="background:rgba(229,255,81,.08);color:var(--accent)">🏆</div>
              Top 10 Placas Consultadas
            </div>
          </div>
          <div class="panel-body">
            <?php if (empty($logStats['placas_top'])): ?>
            <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Sem dados</div></div>
            <?php else:
              $maxP = max(array_column($logStats['placas_top'], 'total') ?: [1]);
            ?>
            <ul class="rank-list">
              <?php foreach ($logStats['placas_top'] as $i => $row):
                $pct = round($row['total'] / $maxP * 100);
                $rnC = match($i) { 0=>'rn-1',1=>'rn-2',2=>'rn-3',default=>'rn-other' };
              ?>
              <li class="rank-item">
                <span class="rank-num <?php echo $rnC ?>"><?php echo $i+1 ?></span>
                <a href="<?php echo buildLogUrl(['logtab'=>'plate_search','placa'=>$row['placa'],'page'=>1]) ?>"
                   class="rank-label" style="text-decoration:none;color:var(--text)"
                   onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text)'">
                  <?php echo htmlspecialchars($row['placa']) ?>
                </a>
                <div class="rank-bar-wrap"><div class="rank-bar-bg">
                  <div class="rank-bar-fill" style="width:<?php echo $pct ?>%"></div>
                </div></div>
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
              <div class="panel-title-icon si-cyan" style="background:rgba(6,182,212,.1)">🌐</div>
              Top 10 IPs Mais Ativos
            </div>
          </div>
          <div class="panel-body">
            <?php if (empty($logStats['ips_top'])): ?>
            <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Sem dados</div></div>
            <?php else:
              $maxI = max(array_column($logStats['ips_top'], 'total') ?: [1]);
            ?>
            <ul class="rank-list">
              <?php foreach ($logStats['ips_top'] as $i => $row):
                $pct = round($row['total'] / $maxI * 100);
                $rnC = match($i) { 0=>'rn-1',1=>'rn-2',2=>'rn-3',default=>'rn-other' };
              ?>
              <li class="rank-item">
                <span class="rank-num <?php echo $rnC ?>"><?php echo $i+1 ?></span>
                <a href="<?php echo buildLogUrl(['logtab'=>'plate_search','ip'=>$row['ip'],'page'=>1]) ?>"
                   class="rank-label" style="text-decoration:none;color:var(--cyan)"
                   title="Filtrar por este IP">
                  <?php echo htmlspecialchars($row['ip']) ?>
                </a>
                <div style="display:flex;align-items:center;gap:6px">
                  <button class="btn-danger-sm" onclick="blockIP('<?php echo htmlspecialchars($row['ip'],ENT_QUOTES) ?>')" title="Bloquear IP">🚫</button>
                </div>
                <div class="rank-bar-wrap"><div class="rank-bar-bg">
                  <div class="rank-bar-fill" style="width:<?php echo $pct ?>%;background:linear-gradient(90deg,var(--cyan),#0ea5e9)"></div>
                </div></div>
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
              <div class="panel-title-icon si-yellow" style="background:rgba(245,158,11,.1)">⏰</div>
              Horários de Pico
            </div>
            <span style="font-size:12px;color:var(--muted)">Top 5 horas mais movimentadas</span>
          </div>
          <div class="panel-body">
            <?php if (empty($logStats['horarios_pico'])): ?>
            <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Sem dados</div></div>
            <?php else:
              $maxH = max(array_column($logStats['horarios_pico'], 'total') ?: [1]);
            ?>
            <div class="hour-chart">
              <?php foreach ($logStats['horarios_pico'] as $hora):
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
      <?php endif; /* logTab */ ?>

      <?php endif; /* logs tab */ ?>

      <?php if ($currentTab === 'pix'): ?>
      <!-- ══ PIX REQUEST LOG ═══════════════════════════════════════════════ -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-title-icon si-cyan" style="background:rgba(6,182,212,.1)">📋</div>
            Últimas 100 Requisições PIX
            <span class="badge badge-info" style="font-size:11px"><?php echo count($pixLogs) ?> registros</span>
          </div>
          <span class="live-badge">AO VIVO</span>
        </div>
        <div class="panel-body" style="overflow-x:auto">
          <?php if (empty($pixLogs)): ?>
          <div class="empty">
            <div class="empty-icon">🕐</div>
            <div class="empty-text">Nenhuma requisição registrada ainda</div>
          </div>
          <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Data/Hora</th>
                <th>IP</th>
                <th>Placa</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Transaction ID</th>
                <th>Detalhes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pixLogs as $log): ?>
              <tr>
                <td class="mono" style="white-space:nowrap"><?php echo htmlspecialchars($log['date']) ?></td>
                <td><span class="mono" style="color:var(--cyan)"><?php echo htmlspecialchars($log['ip']) ?></span></td>
                <td><strong><?php echo htmlspecialchars($log['plate'] !== 'N/A' ? $log['plate'] : '—') ?></strong></td>
                <td style="font-weight:700;color:var(--accent)">R$&nbsp;<?php echo number_format($log['amount'], 2, ',', '.') ?></td>
                <td>
                  <?php if ($log['status'] === 'SUCCESS'): ?>
                    <span class="badge badge-low">✅ Sucesso</span>
                  <?php else: ?>
                    <span class="badge badge-critical">❌ Falha</span>
                  <?php endif ?>
                </td>
                <td class="mono" style="font-size:11px;color:var(--muted)"><?php echo htmlspecialchars($log['txid']) ?>…</td>
                <td style="font-size:12px;color:var(--muted)"><?php echo htmlspecialchars(substr($log['error'], 0, 40)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ── Overlay (mobile) ─────────────────────────────────────────────────── -->
<div id="overlay" onclick="toggleSidebar()"
  style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:90;backdrop-filter:blur(2px)"></div>

<script>
// Clock
function updateClock(){
  const n=new Date();
  document.getElementById('clock').textContent=
    n.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
updateClock();
setInterval(updateClock,1000);

// Sidebar toggle
function toggleSidebar(){
  const s=document.getElementById('sidebar');
  const o=document.getElementById('overlay');
  s.classList.toggle('open');
  o.style.display=s.classList.contains('open')?'block':'none';
}

// Counter animation
function animateCounters(){
  document.querySelectorAll('.stat-value').forEach(el=>{
    const raw=el.textContent.replace(/[^0-9]/g,'');
    if(!raw||raw.length>6)return;
    const target=parseInt(raw,10);
    let current=0;
    const step=Math.max(1,Math.ceil(target/40));
    const id=setInterval(()=>{
      current=Math.min(current+step,target);
      el.textContent=current.toLocaleString('pt-BR');
      if(current>=target)clearInterval(id);
    },18);
  });
}
animateCounters();

// Event filters
function applyEventFilters(){
  const ip=document.getElementById('fi_ip')?.value??'';
  const ev=document.getElementById('fi_ev')?.value??'';
  const th=document.getElementById('fi_threat')?.value??'';
  let url='?tab=events';
  if(ip)url+='&ip='+encodeURIComponent(ip);
  if(ev)url+='&event='+encodeURIComponent(ev);
  if(th)url+='&threat='+encodeURIComponent(th);
  location.href=url;
}

// Auto-refresh every 30s
const autoRefresh=setTimeout(()=>location.reload(),30000);

// ── Log filter toggle ──────────────────────────────────────────────────────
function toggleLogFilter(){
  const body=document.getElementById('logFilterBody');
  const icon=document.getElementById('logFilterIcon');
  if(!body)return;
  const open=body.style.display!=='none';
  body.style.display=open?'none':'flex';
  icon.classList.toggle('open',!open);
}

// ── Apply log filters ──────────────────────────────────────────────────────
function applyLogFilters(){
  const placa=document.getElementById('fPlaca')?.value.trim()||'';
  const ip   =document.getElementById('fIP')?.value.trim()||'';
  const de   =document.getElementById('fDe')?.value||'';
  const ate  =document.getElementById('fAte')?.value||'';
  const lt   ='<?php echo htmlspecialchars($logTab) ?>';
  let url='?tab=logs&logtab='+lt+'&page=1';
  if(placa)url+='&placa='+encodeURIComponent(placa);
  if(ip)   url+='&ip='+encodeURIComponent(ip);
  if(de)   url+='&data_inicio='+de;
  if(ate)  url+='&data_fim='+ate;
  window.location.href=url;
}

// ── Toast helper ───────────────────────────────────────────────────────────
function showToast(msg,type='success'){
  const wrap=document.getElementById('toastWrap');
  if(!wrap)return;
  const t=document.createElement('div');
  t.className='toast '+type;
  t.innerHTML=(type==='success'?'✅':'❌')+' '+msg;
  wrap.appendChild(t);
  setTimeout(()=>{t.classList.add('fade-out');setTimeout(()=>t.remove(),350);},3500);
}

// ── Block IP ───────────────────────────────────────────────────────────────
function blockIP(ip){
  if(!confirm('Bloquear o IP '+ip+' por 60 minutos?'))return;
  fetch('../ajax_block_ip.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({ip})
  })
  .then(r=>r.json())
  .then(d=>{
    if(d.success)showToast('IP '+ip+' bloqueado com sucesso!','success');
    else showToast('Erro: '+(d.error||'Falha ao bloquear'),'error');
  })
  .catch(()=>showToast('Erro de conexão ao bloquear IP','error'));
}

// ── Enter key for log filters ──────────────────────────────────────────────
['fPlaca','fIP'].forEach(id=>{
  const el=document.getElementById(id);
  if(el)el.addEventListener('keydown',e=>{if(e.key==='Enter')applyLogFilters();});
});

// Highlight rows with critical threat on hover
document.querySelectorAll('.event-row').forEach(row=>{
  if(row.querySelector('.tdot-critical')){
    row.style.borderLeft='3px solid var(--red)';
    row.style.paddingLeft='17px';
  }
});
</script>
</body>
</html>
