<?php
// includes/security_init.php - Arquivo central de segurança

// Prevenir acesso direto
if (!defined('SECURITY_INIT')) {
    die('Acesso direto não permitido');
}

// Carregar SecurityStats se disponível
if (file_exists(__DIR__ . '/SecurityStats.php')) {
    require_once __DIR__ . '/SecurityStats.php';
}

// Carregar AntiCopy se disponível e não foi carregado ainda
if (!class_exists('AntiCopy') && file_exists(__DIR__ . '/AntiCopy.php')) {
    require_once __DIR__ . '/AntiCopy.php';
}

// Iniciar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Instanciar SecurityStats se disponível
$securityStats = null;
if (class_exists('SecurityStats')) {
    $securityStats = new SecurityStats();
}

// Função para aplicar proteção completa em uma página
function applySecurity($pageName = 'unknown') {
    global $securityStats;
    
    // Log de acesso (se SecurityStats disponível)
    if ($securityStats && method_exists($securityStats, 'logEvent')) {
        $securityStats->logEvent($pageName . '_access', 'Page accessed from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // Verificar se é bot (apenas log, não bloqueia)
    if ($securityStats && method_exists($securityStats, 'isBot') && $securityStats->isBot()) {
        if ($securityStats && method_exists($securityStats, 'logEvent')) {
            $securityStats->logEvent('bot_detected', $pageName . ' - ' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        }
    }
    
    // Verificar rate limit (apenas log, não bloqueia por padrão)
    if ($securityStats && method_exists($securityStats, 'checkRateLimit')) {
        if (!$securityStats->checkRateLimit(200, 60)) {
            if ($securityStats && method_exists($securityStats, 'logEvent')) {
                $securityStats->logEvent('rate_limit_warning', $pageName . ' - Rate limit exceeded');
            }
        }
    }
    
    // Aplicar proteção Anti-Copy se disponível
    if (class_exists('AntiCopy') && method_exists('AntiCopy', 'protect')) {
        AntiCopy::protect();
    }
}

// Função para aplicar proteção em APIs (mais rigorosa)
function applyApiSecurity($pageName = 'api') {
    global $securityStats;
    
    // Log de acesso à API
    if ($securityStats && method_exists($securityStats, 'logEvent')) {
        $securityStats->logEvent($pageName . '_call', $_SERVER['REQUEST_URI'] ?? '');
    }
    
    // Verificar bot (pode bloquear)
    if ($securityStats && method_exists($securityStats, 'isBot') && $securityStats->isBot()) {
        if ($securityStats && method_exists($securityStats, 'logEvent')) {
            $securityStats->logEvent('api_bot_blocked', $pageName);
        }
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado']);
        exit;
    }
    
    // Rate limit mais rigoroso para API
    if ($securityStats && method_exists($securityStats, 'checkRateLimit')) {
        if (!$securityStats->checkRateLimit(50, 60)) {
            if ($securityStats && method_exists($securityStats, 'logEvent')) {
                $securityStats->logEvent('api_rate_limit_exceeded', $pageName);
            }
            http_response_code(429);
            echo json_encode(['error' => 'Muitas requisições. Aguarde um momento.']);
            exit;
        }
    }
    
    return true;
}

// Função para gerar token CSRF
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para validar token CSRF
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Gerar token de segurança geral
if (!defined('SECURITY_TOKEN')) {
    if (empty($_SESSION['security_token'])) {
        $_SESSION['security_token'] = bin2hex(random_bytes(32));
    }
    define('SECURITY_TOKEN', $_SESSION['security_token']);
}

// Headers de segurança (se não foram enviados ainda)
if (!headers_sent()) {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
}

// Se chamado diretamente, aplicar proteção
if (basename($_SERVER['PHP_SELF']) === 'security_init.php') {
    applySecurity('direct');
}
?>