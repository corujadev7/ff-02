<?php
// secure_admin_2026.php
require_once '../config.php';

session_start();

// Verificar autenticação
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: secure_admin_2026_login.php');
    exit;
}

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: secure_admin_2026_login.php');
    exit;
}

// Aplicar proteção anti-cópia (opcional para o admin)
// AntiCopy::protect();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Toll System</title>
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
            font-size: 1.2rem;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .welcome-card h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
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
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: #667eea;
                color: white;
                border: none;
                padding: 0.5rem 1rem;
                border-radius: 6px;
                cursor: pointer;
            }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>
    <div class="admin-container">
        <div class="sidebar" id="sidebar">
            <h2>🚗 Toll System Admin</h2>
            <nav>
                <ul>
                    <li><a href="?page=dashboard" class="active">📊 Dashboard</a></li>
                    <li><a href="secure_admin_2026_logs.php">📋 Logs do Sistema</a></li>
                    <li><a href="admin_pix_stats.php">📋 Pix gerados</a></li>
                    <li><a href="?page=vehicles">🚘 Veículos</a></li>
                    <li><a href="?page=transactions">💰 Transações</a></li>
                    <li><a href="?page=users">👥 Usuários</a></li>
                    <li><a href="?page=settings">⚙️ Configurações</a></li>
                    <li><a href="?action=logout">🚪 Sair</a></li>
                </ul>
            </nav>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Painel Administrativo</h1>
                <div>Bem-vindo, <?php echo htmlspecialchars($_SESSION['admin_username']); ?> 👋</div>
            </div>
            
            <div class="welcome-card">
                <h1>Bem-vindo ao Painel de Controle</h1>
                <p>Gerencie veículos, transações e usuários do sistema de pedágio digital.</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Veículos</h3>
                    <div class="value">--</div>
                </div>
                <div class="stat-card">
                    <h3>Total de Transações</h3>
                    <div class="value">--</div>
                </div>
                <div class="stat-card">
                    <h3>Usuários Ativos</h3>
                    <div class="value">--</div>
                </div>
                <div class="stat-card">
                    <h3>Consultas Hoje</h3>
                    <div class="value">--</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>