<?php
// admin_stats.php
session_start();

// Autenticação simples
$auth_user = 'admin';
$auth_pass = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] != $auth_user || 
    $_SERVER['PHP_AUTH_PW'] != $auth_pass) {
    header('WWW-Authenticate: Basic realm="Admin Statistics"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Acesso negado';
    exit;
}

require_once '../includes/SecurityStats.php';
$security = new SecurityStats();
$stats = $security->getStats();

// Ler logs
$logFile = dirname(__FILE__) . '/logs/pix_requests.log';
$logs = [];
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lines = array_reverse($lines); // Mais recentes primeiro
    foreach ($lines as $line) {
        $parts = explode("\t", trim($line));
        if (count($parts) >= 5) {
            $logs[] = [
                'date' => $parts[0],
                'ip' => $parts[1],
                'plate' => $parts[2],
                'amount' => floatval($parts[3]),
                'status' => $parts[4],
                'txid' => isset($parts[5]) ? substr($parts[5], 0, 20) : '-',
                'error' => $parts[6] ?? '-'
            ];
        }
        if (count($logs) >= 100) break;
    }
}

$today = date('Y-m-d');
$todayStats = $stats['daily'][$today] ?? ['total_attempts' => 0, 'successful' => 0, 'total_amount' => 0];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Estatísticas de Pagamentos PIX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Ubuntu', -apple-system, system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header h1 { color: #1a1a2e; margin-bottom: 8px; font-size: 28px; }
        .header p { color: #6b7280; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-card h3 { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .stat-card .value { font-size: 32px; font-weight: 800; color: #1a1a2e; }
        .stat-card.success .value { color: #16a34a; }
        .stat-card.danger .value { color: #dc2626; }
        .stat-card .small { font-size: 13px; color: #9ca3af; margin-top: 8px; }
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .table-header h2 { font-size: 18px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background: #fafafa; font-weight: 600; color: #4b5563; font-size: 13px; }
        td { font-size: 13px; color: #374151; }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-failed { background: #fee2e2; color: #dc2626; }
        .refresh-btn {
            background: #1a1a2e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            margin-bottom: 20px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .refresh-btn:hover { background: #2d2d44; }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            th, td { padding: 8px 12px; font-size: 11px; }
        }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Estatísticas de Pagamentos PIX</h1>
            <p>Monitoramento em tempo real - Última atualização: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
        
        <button class="refresh-btn" onclick="location.reload()">🔄 Atualizar dados</button>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Tentativas</h3>
                <div class="value"><?php echo number_format($stats['total_attempts'] ?? 0); ?></div>
                <div class="small">Hoje: <?php echo number_format($todayStats['total_attempts']); ?></div>
            </div>
            <div class="stat-card success">
                <h3>PIX Gerados com Sucesso</h3>
                <div class="value"><?php echo number_format($stats['total_successful'] ?? 0); ?></div>
                <div class="small">Hoje: <?php echo number_format($todayStats['successful']); ?></div>
            </div>
            <div class="stat-card danger">
                <h3>Tentativas Falhas</h3>
                <div class="value"><?php echo number_format($stats['total_failed'] ?? 0); ?></div>
                <div class="small">Taxa de erro: <?php 
                    $total = ($stats['total_attempts'] ?? 1);
                    $rate = (($stats['total_failed'] ?? 0) / $total) * 100;
                    echo number_format($rate, 1) . '%';
                ?></div>
            </div>
            <div class="stat-card">
                <h3>Valor Total Gerado</h3>
                <div class="value">R$ <?php echo number_format($stats['total_amount'] ?? 0, 2, ',', '.'); ?></div>
                <div class="small">Hoje: R$ <?php echo number_format($todayStats['total_amount'] ?? 0, 2, ',', '.'); ?></div>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h2>📋 Últimas 100 Requisições</h2>
            </div>
            <div style="overflow-x: auto;">
                <table>
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
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px;">
                                🕐 Nenhuma requisição registrada ainda
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars($log['date']); ?></td>
                                <td><code><?php echo htmlspecialchars($log['ip']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($log['plate'] !== 'N/A' ? $log['plate'] : '—'); ?></strong></td>
                                <td>R$ <?php echo number_format($log['amount'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($log['status']); ?>">
                                        <?php echo $log['status'] === 'SUCCESS' ? '✅ Sucesso' : '❌ Falha'; ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($log['txid']); ?>...</small></td>
                                <td><small><?php echo htmlspecialchars(substr($log['error'], 0, 30)); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>