<?php
// includes/SecurityStats.php
class SecurityStats {
    private $logFile;
    private $statsFile;
    private $ip;
    
    public function __construct() {
        $this->ip = $this->getClientIP();
        $logDir = dirname(__DIR__) . '/logs';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $this->logFile = $logDir . '/pix_requests.log';
        $this->statsFile = $logDir . '/pix_stats.json';
    }
    
    // Método público para obter IP
    public function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    // Log de tentativa de geração de PIX
    public function logPixAttempt($plate, $amount, $success, $txid = null, $error = null) {
        $log = date('Y-m-d H:i:s') . "\t";
        $log .= $this->ip . "\t";
        $log .= ($plate ?: 'N/A') . "\t";
        $log .= $amount . "\t";
        $log .= ($success ? 'SUCCESS' : 'FAILED') . "\t";
        $log .= ($txid ?? '-') . "\t";
        $log .= ($error ?? '-') . "\t";
        $log .= $_SERVER['HTTP_USER_AGENT'] ?? '-';
        $log .= PHP_EOL;
        
        file_put_contents($this->logFile, $log, FILE_APPEND);
        
        // Atualizar estatísticas
        $this->updateStats($success, $amount);
        
        return true;
    }
    
    // Atualizar estatísticas agregadas
    private function updateStats($success, $amount) {
        $stats = $this->getStats();
        $today = date('Y-m-d');
        
        if (!isset($stats['daily'][$today])) {
            $stats['daily'][$today] = [
                'total_attempts' => 0,
                'successful' => 0,
                'failed' => 0,
                'total_amount' => 0
            ];
        }
        
        $stats['daily'][$today]['total_attempts']++;
        
        if ($success) {
            $stats['daily'][$today]['successful']++;
            $stats['daily'][$today]['total_amount'] += $amount;
            $stats['total_successful'] = ($stats['total_successful'] ?? 0) + 1;
            $stats['total_amount'] = ($stats['total_amount'] ?? 0) + $amount;
        } else {
            $stats['daily'][$today]['failed']++;
            $stats['total_failed'] = ($stats['total_failed'] ?? 0) + 1;
        }
        
        $stats['total_attempts'] = ($stats['total_attempts'] ?? 0) + 1;
        $stats['last_update'] = date('Y-m-d H:i:s');
        
        file_put_contents($this->statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    }
    
    // Obter estatísticas
    public function getStats() {
        if (file_exists($this->statsFile)) {
            $stats = json_decode(file_get_contents($this->statsFile), true);
            if ($stats) return $stats;
        }
        
        return [
            'total_attempts' => 0,
            'total_successful' => 0,
            'total_failed' => 0,
            'total_amount' => 0,
            'daily' => [],
            'last_update' => date('Y-m-d H:i:s')
        ];
    }
    
    // Verificar rate limit por IP
    public function checkRateLimit($limitPerHour = 5) {
        $today = date('Y-m-d');
        $limitFile = dirname(__DIR__) . '/logs/rate_limit_' . md5($this->ip . $today);
        
        if (file_exists($limitFile)) {
            $data = json_decode(file_get_contents($limitFile), true);
            $count = $data['count'] ?? 0;
            $firstAttempt = $data['first_attempt'] ?? time();
            
            // Reset após 1 hora
            if (time() - $firstAttempt > 3600) {
                $count = 0;
                $firstAttempt = time();
            }
            
            if ($count >= $limitPerHour) {
                return false;
            }
            
            $data['count'] = $count + 1;
            file_put_contents($limitFile, json_encode($data));
        } else {
            file_put_contents($limitFile, json_encode([
                'count' => 1,
                'first_attempt' => time(),
                'ip' => $this->ip
            ]));
        }
        
        return true;
    }
    
    // Verificar se é bot por user agent
    public function isBot() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'perl', 'ruby', 'go-http', 'php',
            'node-fetch', 'axios', 'okhttp', 'httpclient',
            'headless', 'selenium', 'puppeteer', 'phantomjs'
        ];
        
        $userAgentLower = strtolower($userAgent);
        foreach ($botPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    // Validar honeypot
    public function validateHoneypot($honeypot) {
        return empty($honeypot);
    }
    
    // Validar timestamp
    public function validateTimestamp($timestamp) {
        $time = time();
        return abs($time - $timestamp) < 300; // 5 minutos de tolerância
    }
    
    // Gerar token único para cada sessão
    public function generateSessionToken() {
        if (empty($_SESSION['pix_token'])) {
            $_SESSION['pix_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['pix_token'];
    }
    
    // Validar token da sessão
    public function validateSessionToken($token) {
        return isset($_SESSION['pix_token']) && hash_equals($_SESSION['pix_token'], $token);
    }

        // Log de eventos gerais de segurança
    public function logEvent($event, $details = null) {
        $logFile = dirname(__DIR__) . '/logs/security.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $log = date('Y-m-d H:i:s') . " | ";
        $log .= $this->ip . " | ";
        $log .= $event . " | ";
        $log .= ($details ?? '-') . " | ";
        $log .= $_SERVER['REQUEST_URI'] ?? '-';
        $log .= PHP_EOL;
        
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}
?>