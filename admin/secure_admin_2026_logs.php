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
$stats = $logger->getStats();

// Paginação
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filtros
$filtros = [
    'placa' => $_GET['placa'] ?? '',
    'ip' => $_GET['ip'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? ''
];

// Buscar logs
$searchLogs = $logger->getPlateSearchLogs($limit, $offset, $filtros);
$pageLogs = $logger->getPageAccessLogs($limit, $offset, $filtros);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Ubuntu', sans-serif;
            background: #f5f5f5;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: #1a1a2e;
            color: white;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            color: #e5ff51;
        }
        
        .sidebar nav ul {
            list-style: none;
        }
        
        .sidebar nav ul li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar nav ul li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar nav ul li a:hover,
        .sidebar nav ul li a.active {
            background: rgba(229, 255, 81, 0.1);
            color: #e5ff51;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .filter-bar {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-bar input, .filter-bar select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .data-table {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
        }
        
        .data-table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        .ip-block-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }
        
        .ip-block-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar" id="sidebar">
            <h2>🚗 Toll System</h2>
            <nav>
                <ul>
                    <li><a href="secure_admin_2026.php">📊 Dashboard</a></li>
                    <li><a href="secure_admin_2026_logs.php" class="active">📋 Logs de Consultas</a></li>
                    <li><a href="secure_admin_2026_logs.php?tab=pages">🌐 Acessos</a></li>
                    <li><a href="?tab=stats">📈 Estatísticas</a></li>
                    <li><a href="secure_admin_2026.php?action=logout">🚪 Sair</a></li>
                </ul>
            </nav>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Logs do Sistema</h1>
                <div>👤 <?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Consultas Hoje</h3>
                    <div class="value"><?php echo number_format($stats['consultas_hoje'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Consultas no Mês</h3>
                    <div class="value"><?php echo number_format($stats['consultas_mes'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>IPs Únicos</h3>
                    <div class="value"><?php echo number_format($stats['ips_unicos'] ?? 0); ?></div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filter-bar">
                <input type="text" id="filterPlaca" placeholder="Filtrar por placa" value="<?php echo htmlspecialchars($filtros['placa']); ?>">
                <input type="text" id="filterIP" placeholder="Filtrar por IP" value="<?php echo htmlspecialchars($filtros['ip']); ?>">
                <input type="date" id="filterDataInicio" placeholder="Data início" value="<?php echo $filtros['data_inicio']; ?>">
                <input type="date" id="filterDataFim" placeholder="Data fim" value="<?php echo $filtros['data_fim']; ?>">
                <button class="btn" onclick="applyFilters()">Filtrar</button>
                <button class="btn" onclick="resetFilters()">Limpar</button>
            </div>
            
            <?php $tab = $_GET['tab'] ?? 'plate_search'; ?>
            
            <!-- Tabs -->
            <div class="filter-bar" style="background: transparent; padding: 0 0 1rem 0;">
                <a href="?tab=plate_search" class="btn <?php echo $tab == 'plate_search' ? 'btn-primary' : ''; ?>" style="background: <?php echo $tab == 'plate_search' ? '#667eea' : '#ccc'; ?>">Consultas de Placas</a>
                <a href="?tab=pages" class="btn <?php echo $tab == 'pages' ? 'btn-primary' : ''; ?>" style="background: <?php echo $tab == 'pages' ? '#667eea' : '#ccc'; ?>">Acessos às Páginas</a>
                <a href="?tab=stats" class="btn <?php echo $tab == 'stats' ? 'btn-primary' : ''; ?>" style="background: <?php echo $tab == 'stats' ? '#667eea' : '#ccc'; ?>">Estatísticas Avançadas</a>
            </div>
            
            <?php if ($tab == 'plate_search'): ?>
            <!-- Tabela de Logs de Placas -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Placa</th>
                            <th>IP</th>
                            <th>Resultado</th>
                            <th>Veículo Encontrado</th>
                            <th>Tempo (ms)</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchLogs['data'] as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($log['placa']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($log['ip']); ?>
                                <button class="ip-block-btn" onclick="blockIP('<?php echo $log['ip']; ?>')">🚫 Bloquear</button>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $log['resultado'] == 'found' ? 'success' : 'error'; ?>">
                                    <?php echo $log['resultado'] == 'found' ? '✓ Encontrado' : ($log['resultado'] == 'error' ? '✗ Erro' : '⚠️ Não encontrado'); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($log['dados_veiculo']) {
                                    $dados = json_decode($log['dados_veiculo'], true);
                                    echo htmlspecialchars(($dados['marca'] ?? '') . ' ' . ($dados['modelo'] ?? ''));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo $log['tempo_resposta'] ?? '-'; ?></td>
                            <td>
                                <a href="?placa=<?php echo urlencode($log['placa']); ?>&tab=plate_search" class="btn" style="padding: 2px 8px; font-size: 11px;">Ver detalhes</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($searchLogs['data'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Nenhum log encontrado</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if ($tab == 'pages'): ?>
            <!-- Tabela de Logs de Acesso às Páginas -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>IP</th>
                            <th>Página</th>
                            <th>Método</th>
                            <th>Referer</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageLogs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($log['ip']); ?>
                                <button class="ip-block-btn" onclick="blockIP('<?php echo $log['ip']; ?>')">🚫 Bloquear</button>
                            </td>
                            <td><?php echo htmlspecialchars($log['pagina']); ?></td>
                            <td><?php echo htmlspecialchars($log['metodo']); ?></td>
                            <td><?php echo htmlspecialchars($log['referer'] ?? '-'); ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars(substr($log['user_agent'] ?? '-', 0, 50)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pageLogs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Nenhum acesso registrado</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if ($tab == 'stats'): ?>
            <!-- Estatísticas Avançadas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Top 10 Placas Mais Consultadas</h3>
                    <ul style="margin-top: 10px;">
                        <?php foreach ($stats['placas_top'] as $placa): ?>
                        <li><?php echo htmlspecialchars($placa['placa']); ?> - <?php echo $placa['total']; ?> consultas</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="stat-card">
                    <h3>Top 10 IPs Mais Ativos</h3>
                    <ul style="margin-top: 10px;">
                        <?php foreach ($stats['ips_top'] as $ip): ?>
                        <li><?php echo htmlspecialchars($ip['ip']); ?> - <?php echo $ip['total']; ?> consultas</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="stat-card">
                    <h3>Horários de Pico</h3>
                    <ul style="margin-top: 10px;">
                        <?php foreach ($stats['horarios_pico'] as $hora): ?>
                        <li><?php echo str_pad($hora['hora'], 2, '0', STR_PAD_LEFT); ?>:00 - <?php echo $hora['total']; ?> consultas</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function applyFilters() {
            const placa = document.getElementById('filterPlaca').value;
            const ip = document.getElementById('filterIP').value;
            const dataInicio = document.getElementById('filterDataInicio').value;
            const dataFim = document.getElementById('filterDataFim').value;
            const tab = '<?php echo $tab; ?>';
            
            let url = '?tab=' + tab;
            if (placa) url += '&placa=' + encodeURIComponent(placa);
            if (ip) url += '&ip=' + encodeURIComponent(ip);
            if (dataInicio) url += '&data_inicio=' + dataInicio;
            if (dataFim) url += '&data_fim=' + dataFim;
            
            window.location.href = url;
        }
        
        function resetFilters() {
            window.location.href = '?tab=<?php echo $tab; ?>';
        }
        
        function blockIP(ip) {
            if (confirm('Tem certeza que deseja bloquear o IP ' + ip + '?')) {
                fetch('ajax_block_ip.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ip: ip})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('IP bloqueado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao bloquear IP: ' + data.error);
                    }
                });
            }
        }
        
        // Auto-refresh a cada 30 segundos (opcional)
        // setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>