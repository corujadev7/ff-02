<?php
// config.php - Main configuration file
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'toll_system');
define('DB_USER', 'root');
define('DB_PASS', 'johny@123');
define('DB_CHARSET', 'utf8mb4');

// Security Configuration
define('SALT', 'Y0urS3cur3S@ltStr1ng!@#$');
define('JWT_SECRET', 'JWT_S3cr3tK3yF0rT0llSystem2026');
define('ADMIN_PREFIX', 'secure_admin_2026');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // 1 minute

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_DIR', __DIR__ . '/cache/');
define('CACHE_DURATION', 3600); // 1 hour
define('CACHE_DRIVER', 'file'); // file, redis, memcached

// Anti-Bot Configuration
define('ANTI_BOT_ENABLED', true);
define('HONEYPOT_ENABLED', true);
define('REQUEST_TIMESTAMP_CHECK', true);
define('MAX_REQUESTS_PER_IP', 50);
define('BLOCK_DURATION', 3600); // 1 hour

// Encryption Configuration
define('ENCRYPTION_KEY', '32byt3s3cr3t3ncrypt10nkeyf0rt0ll!');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// Create necessary directories
$directories = [CACHE_DIR, 'logs/', 'uploads/', 'backups/'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Database Connection Class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES ($placeholders)";
        $stmt = $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $where";
        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params)->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params)->rowCount();
    }
}

// Cache Class
class Cache {
    private static $instance = null;
    private $driver;
    
    private function __construct() {
        $this->driver = CACHE_DRIVER;
        if (CACHE_DRIVER == 'file' && !is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function set($key, $data, $duration = CACHE_DURATION) {
        $key = md5($key);
        
        switch ($this->driver) {
            case 'file':
                $file = CACHE_DIR . $key . '.cache';
                $content = json_encode([
                    'expires' => time() + $duration,
                    'data' => $data
                ]);
                return file_put_contents($file, $content, LOCK_EX);
                
            case 'redis':
                // Redis implementation placeholder
                // $redis = new Redis();
                // $redis->connect('127.0.0.1', 6379);
                // return $redis->setex($key, $duration, serialize($data));
                break;
        }
        return false;
    }
    
    public function get($key) {
        $key = md5($key);
        
        switch ($this->driver) {
            case 'file':
                $file = CACHE_DIR . $key . '.cache';
                if (!file_exists($file)) return false;
                
                $content = json_decode(file_get_contents($file), true);
                if (!$content || $content['expires'] < time()) {
                    @unlink($file);
                    return false;
                }
                return $content['data'];
                
            case 'redis':
                // Redis implementation placeholder
                // $redis = new Redis();
                // $redis->connect('127.0.0.1', 6379);
                // $data = $redis->get($key);
                // return $data ? unserialize($data) : false;
                break;
        }
        return false;
    }
    
    public function delete($key) {
        $key = md5($key);
        $file = CACHE_DIR . $key . '.cache';
        if (file_exists($file)) {
            @unlink($file);
        }
        return true;
    }
    
    public function clear() {
        $files = glob(CACHE_DIR . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }
}

// Security and Anti-Bot Class
class Security {
    private $db;
    private $ip;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ip = $this->getClientIP();
    }
    
    private function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    public function checkRateLimit($action = 'default') {
        if (!ANTI_BOT_ENABLED) return true;
        
        $window = time() - RATE_LIMIT_WINDOW;
        $sql = "SELECT COUNT(*) as count FROM rate_limits 
                WHERE ip = :ip AND action = :action AND created_at > :window";
        $result = $this->db->query($sql, [
            ':ip' => $this->ip,
            ':action' => $action,
            ':window' => date('Y-m-d H:i:s', $window)
        ])->fetch();
        
        if ($result['count'] >= RATE_LIMIT_REQUESTS) {
            $this->logSecurityEvent('rate_limit_exceeded');
            return false;
        }
        
        // Record request
        $this->db->insert('rate_limits', [
            'ip' => $this->ip,
            'action' => $action,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    public function checkLoginAttempts($username) {
        $window = time() - LOCKOUT_TIME;
        $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                WHERE (ip = :ip OR username = :username) 
                AND created_at > :window AND success = 0";
        $result = $this->db->query($sql, [
            ':ip' => $this->ip,
            ':username' => $username,
            ':window' => date('Y-m-d H:i:s', $window)
        ])->fetch();
        
        return $result['attempts'] < MAX_LOGIN_ATTEMPTS;
    }
    
    public function recordLoginAttempt($username, $success) {
        $this->db->insert('login_attempts', [
            'ip' => $this->ip,
            'username' => $username,
            'success' => $success ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function isBlocked() {
        $sql = "SELECT * FROM blocked_ips WHERE ip = :ip AND expires_at > NOW()";
        $result = $this->db->query($sql, [':ip' => $this->ip])->fetch();
        return !empty($result);
    }
    
    public function blockIP($reason = 'Suspicious activity') {
        $this->db->insert('blocked_ips', [
            'ip' => $this->ip,
            'reason' => $reason,
            'expires_at' => date('Y-m-d H:i:s', time() + BLOCK_DURATION),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $this->logSecurityEvent('ip_blocked');
    }
    
    public function validateHoneypot($honeypotField) {
        if (!HONEYPOT_ENABLED) return true;
        return empty($honeypotField);
    }
    
    public function validateTimestamp($timestamp) {
        if (!REQUEST_TIMESTAMP_CHECK) return true;
        $time = time();
        return abs($time - $timestamp) <= 300; // 5 minute tolerance
    }
    
    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function logSecurityEvent($event, $details = null) {
        $this->db->insert('security_logs', [
            'ip' => $this->ip,
            'event' => $event,
            'details' => $details,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function encrypt($data) {
        $iv = random_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($data) {
        $data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    }
}

// Anti-Copy Protection
class AntiCopy {
    public static function protect() {
        // Disable right click
        echo "<script>
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable keyboard shortcuts for copy
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C' || 
                e.key === 'u' || e.key === 'U' || e.key === 's' || e.key === 'S' ||
                e.key === 'v' || e.key === 'V' || e.key === 'x' || e.key === 'X')) {
                e.preventDefault();
                return false;
            }
            
            // Disable F12 and other dev tools shortcuts
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.shiftKey && e.key === 'J') || (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Disable text selection
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable copy via CSS
        document.body.style.userSelect = 'none';
        document.body.style.webkitUserSelect = 'none';
        </script>";
        
        // Add CSS to prevent selection
        echo "<style>
        * {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        </style>";
    }
}

// Admin Panel Class
class AdminPanel {
    private $db;
    private $security;
    private $cache;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->security = new Security();
        $this->cache = Cache::getInstance();
        $this->checkAuth();
    }
    
    private function checkAuth() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            if (basename($_SERVER['PHP_SELF']) !== 'admin_login.php') {
                header('Location: ' . ADMIN_PREFIX . '_login.php');
                exit;
            }
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            $this->logout();
        }
        $_SESSION['last_activity'] = time();
    }
    
    public function login($username, $password, $remember = false) {
        if (!$this->security->checkRateLimit('admin_login')) {
            return ['success' => false, 'message' => 'Rate limit exceeded. Please try again later.'];
        }
        
        if (!$this->security->checkLoginAttempts($username)) {
            return ['success' => false, 'message' => 'Too many failed attempts. Account temporarily locked.'];
        }
        
        $sql = "SELECT * FROM admin_users WHERE username = :username AND status = 1";
        $user = $this->db->query($sql, [':username' => $username])->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 2592000, '/', '', true, true);
                $this->db->update('admin_users', ['remember_token' => $token], 'id = :id', [':id' => $user['id']]);
            }
            
            $this->security->recordLoginAttempt($username, true);
            $this->security->logSecurityEvent('admin_login_success', "User: $username");
            
            return ['success' => true, 'message' => 'Login successful!'];
        } else {
            $this->security->recordLoginAttempt($username, false);
            $this->security->logSecurityEvent('admin_login_failed', "User: $username");
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
    }
    
    public function logout() {
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        header('Location: ' . ADMIN_PREFIX . '_login.php');
        exit;
    }
    
    public function getDashboardStats() {
        $cacheKey = 'admin_dashboard_stats';
        $stats = $this->cache->get($cacheKey);
        
        if ($stats === false) {
            $stats = [];
            
            // Total users
            $result = $this->db->query("SELECT COUNT(*) as count FROM users")->fetch();
            $stats['total_users'] = $result['count'];
            
            // Total transactions
            $result = $this->db->query("SELECT COUNT(*) as count, SUM(amount) as total FROM transactions")->fetch();
            $stats['total_transactions'] = $result['count'];
            $stats['total_amount'] = $result['total'] ?? 0;
            
            // Pending transactions
            $result = $this->db->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'")->fetch();
            $stats['pending_transactions'] = $result['count'];
            
            // Today's transactions
            $result = $this->db->query("SELECT COUNT(*) as count, SUM(amount) as total FROM transactions WHERE DATE(created_at) = CURDATE()")->fetch();
            $stats['today_transactions'] = $result['count'];
            $stats['today_amount'] = $result['total'] ?? 0;
            
            // Recent activity
            $stats['recent_activity'] = $this->db->query("SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
            
            // Blocked IPs
            $result = $this->db->query("SELECT COUNT(*) as count FROM blocked_ips WHERE expires_at > NOW()")->fetch();
            $stats['blocked_ips'] = $result['count'];
            
            $this->cache->set($cacheKey, $stats, 300); // Cache for 5 minutes
        }
        
        return $stats;
    }
    
    public function getVehicles($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM vehicles ORDER BY created_at DESC LIMIT $offset, $limit";
        $vehicles = $this->db->query($sql)->fetchAll();
        
        $total = $this->db->query("SELECT COUNT(*) as count FROM vehicles")->fetch();
        
        return [
            'data' => $vehicles,
            'total' => $total['count'],
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total['count'] / $limit)
        ];
    }
    
    public function getTransactions($page = 1, $limit = 20, $status = null) {
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = "";
        
        if ($status) {
            $where = "WHERE status = :status";
            $params[':status'] = $status;
        }
        
        $sql = "SELECT t.*, u.name as user_name, v.plate as vehicle_plate 
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN vehicles v ON t.vehicle_id = v.id
                $where
                ORDER BY t.created_at DESC 
                LIMIT $offset, $limit";
        
        $transactions = $this->db->query($sql, $params)->fetchAll();
        
        $total = $this->db->query("SELECT COUNT(*) as count FROM transactions $where", $params)->fetch();
        
        return [
            'data' => $transactions,
            'total' => $total['count'],
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total['count'] / $limit)
        ];
    }
    
    public function getUsers($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT $offset, $limit";
        $users = $this->db->query($sql)->fetchAll();
        
        $total = $this->db->query("SELECT COUNT(*) as count FROM users")->fetch();
        
        return [
            'data' => $users,
            'total' => $total['count'],
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total['count'] / $limit)
        ];
    }
    
    public function updateVehicle($id, $data) {
        $result = $this->db->update('vehicles', $data, 'id = :id', [':id' => $id]);
        if ($result) {
            $this->cache->delete('vehicle_' . $id);
            $this->security->logSecurityEvent('vehicle_updated', "Vehicle ID: $id");
        }
        return $result;
    }
    
    public function deleteVehicle($id) {
        $result = $this->db->delete('vehicles', 'id = :id', [':id' => $id]);
        if ($result) {
            $this->cache->delete('vehicle_' . $id);
            $this->security->logSecurityEvent('vehicle_deleted', "Vehicle ID: $id");
        }
        return $result;
    }
    
    public function getSettings() {
        $cacheKey = 'admin_settings';
        $settings = $this->cache->get($cacheKey);
        
        if ($settings === false) {
            $settings = [];
            $result = $this->db->query("SELECT * FROM settings")->fetchAll();
            foreach ($result as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $this->cache->set($cacheKey, $settings, 3600);
        }
        
        return $settings;
    }
    
    public function updateSetting($key, $value) {
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) 
                ON DUPLICATE KEY UPDATE setting_value = :value";
        $result = $this->db->query($sql, [':key' => $key, ':value' => $value]);
        if ($result) {
            $this->cache->delete('admin_settings');
            $this->security->logSecurityEvent('setting_updated', "Key: $key");
        }
        return $result;
    }
    
    public function backupDatabase() {
        $backupFile = 'backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
        $command = sprintf('mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_NAME),
            escapeshellarg($backupFile)
        );
        
        system($command, $output);
        
        if (file_exists($backupFile)) {
            $this->security->logSecurityEvent('database_backup_created', "File: $backupFile");
            return $backupFile;
        }
        
        return false;
    }
    
    public function generateAdminHTML() {
        $stats = $this->getDashboardStats();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Painel Administrativo - Toll System</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Ubuntu', sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                }
                
                .admin-container {
                    display: flex;
                    min-height: 100vh;
                }
                
                .sidebar {
                    width: 280px;
                    background: rgba(0, 0, 0, 0.9);
                    backdrop-filter: blur(10px);
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
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 1.5rem;
                    margin-bottom: 2rem;
                }
                
                .stat-card {
                    background: white;
                    padding: 1.5rem;
                    border-radius: 12px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    transition: transform 0.3s ease;
                }
                
                .stat-card:hover {
                    transform: translateY(-5px);
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
                
                .stat-card .label {
                    color: #999;
                    font-size: 0.8rem;
                    margin-top: 0.5rem;
                }
                
                .data-table {
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                }
                
                .data-table table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .data-table th,
                .data-table td {
                    padding: 1rem;
                    text-align: left;
                    border-bottom: 1px solid #eee;
                }
                
                .data-table th {
                    background: #f5f5f5;
                    font-weight: 600;
                    color: #333;
                }
                
                .data-table tr:hover {
                    background: #f9f9f9;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 0.25rem 0.75rem;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    font-weight: 600;
                }
                
                .status-success {
                    background: #d4edda;
                    color: #155724;
                }
                
                .status-pending {
                    background: #fff3cd;
                    color: #856404;
                }
                
                .status-failed {
                    background: #f8d7da;
                    color: #721c24;
                }
                
                .btn {
                    display: inline-block;
                    padding: 0.5rem 1rem;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 0.9rem;
                    transition: all 0.3s ease;
                    text-decoration: none;
                }
                
                .btn-primary {
                    background: #667eea;
                    color: white;
                }
                
                .btn-primary:hover {
                    background: #5a67d8;
                }
                
                .btn-danger {
                    background: #dc3545;
                    color: white;
                }
                
                .btn-danger:hover {
                    background: #c82333;
                }
                
                .pagination {
                    display: flex;
                    justify-content: center;
                    gap: 0.5rem;
                    margin-top: 1.5rem;
                }
                
                .pagination a,
                .pagination span {
                    padding: 0.5rem 1rem;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    text-decoration: none;
                    color: #333;
                }
                
                .pagination .active {
                    background: #667eea;
                    color: white;
                    border-color: #667eea;
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
            <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            <div class="admin-container">
                <div class="sidebar" id="sidebar">
                    <h2>🚗 Toll System</h2>
                    <nav>
                        <ul>
                            <li><a href="?page=dashboard" class="active">Dashboard</a></li>
                            <li><a href="?page=vehicles">Veículos</a></li>
                            <li><a href="?page=transactions">Transações</a></li>
                            <li><a href="?page=users">Usuários</a></li>
                            <li><a href="?page=settings">Configurações</a></li>
                            <li><a href="?page=security">Segurança</a></li>
                            <li><a href="?page=backup">Backup</a></li>
                            <li><a href="?action=logout">Sair</a></li>
                        </ul>
                    </nav>
                </div>
                
                <div class="main-content">
                    <div class="header">
                        <h1>Painel Administrativo</h1>
                        <div>Bem-vindo, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total de Usuários</h3>
                            <div class="value"><?php echo number_format($stats['total_users'], 0, ',', '.'); ?></div>
                            <div class="label">Usuários registrados</div>
                        </div>
                        <div class="stat-card">
                            <h3>Total de Transações</h3>
                            <div class="value"><?php echo number_format($stats['total_transactions'], 0, ',', '.'); ?></div>
                            <div class="label">R$ <?php echo number_format($stats['total_amount'], 2, ',', '.'); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Transações Pendentes</h3>
                            <div class="value"><?php echo number_format($stats['pending_transactions'], 0, ',', '.'); ?></div>
                            <div class="label">Aguardando pagamento</div>
                        </div>
                        <div class="stat-card">
                            <h3>Hoje</h3>
                            <div class="value"><?php echo number_format($stats['today_transactions'], 0, ',', '.'); ?></div>
                            <div class="label">R$ <?php echo number_format($stats['today_amount'], 2, ',', '.'); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>IPs Bloqueados</h3>
                            <div class="value"><?php echo number_format($stats['blocked_ips'], 0, ',', '.'); ?></div>
                            <div class="label">Endereços bloqueados</div>
                        </div>
                    </div>
                    
                    <div class="data-table">
                        <h3 style="padding: 1rem;">Atividade Recente</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Evento</th>
                                    <th>IP</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_activity'] as $activity): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($activity['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($activity['event']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['ip']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <script>
                function toggleSidebar() {
                    document.getElementById('sidebar').classList.toggle('active');
                }
                
                // Auto-refresh stats every 30 seconds
                setInterval(function() {
                    location.reload();
                }, 30000);
            </script>
        </body>
        </html>
        <?php
    }
}

// Database Installation Script
function installDatabase() {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Create users table
        $conn->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            cpf_cnpj VARCHAR(20) UNIQUE NOT NULL,
            phone VARCHAR(20),
            password VARCHAR(255) NOT NULL,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_cpf_cnpj (cpf_cnpj)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create vehicles table
        $conn->exec("CREATE TABLE IF NOT EXISTS vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            plate VARCHAR(10) UNIQUE NOT NULL,
            brand VARCHAR(100),
            model VARCHAR(100),
            color VARCHAR(50),
            year INT,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_plate (plate),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create transactions table
        $conn->exec("CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            vehicle_id INT,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            payment_method VARCHAR(50),
            transaction_id VARCHAR(255),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create admin_users table
        $conn->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            role ENUM('admin', 'manager', 'viewer') DEFAULT 'viewer',
            remember_token VARCHAR(255),
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create settings table
        $conn->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create security_logs table
        $conn->exec("CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45),
            event VARCHAR(100),
            details TEXT,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip),
            INDEX idx_event (event),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create login_attempts table
        $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45),
            username VARCHAR(100),
            success TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip),
            INDEX idx_username (username),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create rate_limits table
        $conn->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45),
            action VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create blocked_ips table
        // expires_at → DATETIME NULL: TIMESTAMP sem DEFAULT explícito recebe
        // '0000-00-00' no MySQL, proibido pelo NO_ZERO_DATE do strict mode padrão.
        $conn->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
            id         INT         NOT NULL AUTO_INCREMENT,
            ip         VARCHAR(45) NOT NULL,
            reason     TEXT        NULL,
            expires_at DATETIME    NULL,
            created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   uniq_ip    (ip),
            INDEX        idx_ip     (ip),
            INDEX        idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ── Logger tables ──────────────────────────────────────────────────
        // Regra MySQL ≤ 5.6 / strict mode 5.7+:
        //   • Máx. 1 TIMESTAMP com DEFAULT CURRENT_TIMESTAMP por tabela.
        //   • Colunas de data não-auto → DATETIME NULL (evita NO_ZERO_DATE).

        // Consultas de placas feitas pelos usuários
        $conn->exec("CREATE TABLE IF NOT EXISTS plate_search_logs (
            id             INT          NOT NULL AUTO_INCREMENT,
            placa          VARCHAR(20)  NOT NULL,
            ip             VARCHAR(45)  NULL,
            user_agent     TEXT         NULL,
            resultado      VARCHAR(20)  NOT NULL DEFAULT 'found',
            dados_veiculo  TEXT         NULL,
            tempo_resposta INT          NULL,
            created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX idx_placa   (placa),
            INDEX idx_ip      (ip),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Acessos às páginas do sistema
        $conn->exec("CREATE TABLE IF NOT EXISTS page_access_logs (
            id                 INT          NOT NULL AUTO_INCREMENT,
            ip                 VARCHAR(45)  NULL,
            pagina             VARCHAR(500) NULL,
            metodo             VARCHAR(10)  NULL,
            user_agent         TEXT         NULL,
            referer            TEXT         NULL,
            query_string       TEXT         NULL,
            tempo_carregamento INT          NULL,
            created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX idx_ip      (ip),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Estatísticas por IP (visitas + consultas de placa)
        $conn->exec("CREATE TABLE IF NOT EXISTS ip_stats (
            id                    INT         NOT NULL AUTO_INCREMENT,
            ip                    VARCHAR(45) NOT NULL,
            total_visitas         INT         NOT NULL DEFAULT 0,
            total_consultas_placa INT         NOT NULL DEFAULT 0,
            primeira_visita       DATETIME    NULL,
            ultima_visita         DATETIME    NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY   uniq_ip (ip),
            INDEX        idx_ip  (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // IPs bloqueados pelo módulo Logger/admin
        $conn->exec("CREATE TABLE IF NOT EXISTS ip_blocks (
            id         INT         NOT NULL AUTO_INCREMENT,
            ip         VARCHAR(45) NOT NULL,
            motivo     TEXT        NULL,
            expires_at DATETIME    NULL,
            created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   uniq_ip     (ip),
            INDEX        idx_ip      (ip),
            INDEX        idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Insert default admin user if not exists
        $check = $conn->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        if ($check == 0) {
            $defaultPassword = password_hash('admin123', PASSWORD_BCRYPT);
            $conn->prepare("INSERT INTO admin_users (username, password, email, role) VALUES (?, ?, ?, 'admin')")
                 ->execute(['admin', $defaultPassword, 'admin@tollsystem.com']);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database installation failed: " . $e->getMessage());
        return false;
    }
}

// Run installation if needed
//installDatabase();
?>