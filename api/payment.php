<?php
// /free/api/payment.php
header('Content-Type: application/json');

// ========== PROTEÇÃO CONTRA ATAQUES DO PRÓPRIO DOMÍNIO ==========

// 1. Verificar se a requisição é AJAX (essencial)
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida', 'code' => 'INVALID_REQUEST']);
    exit;
}

// 2. Validar Origin (só aceita do seu domínio)
$allowedOrigins = [
    'http://localhost',
    'https://localhost',
    'http://' . $_SERVER['HTTP_HOST'],
    'https://' . $_SERVER['HTTP_HOST']
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$isValidOrigin = false;
foreach ($allowedOrigins as $allowed) {
    if ($origin === $allowed) {
        $isValidOrigin = true;
        break;
    }
}

if (!$isValidOrigin) {
    http_response_code(403);
    echo json_encode(['error' => 'Origem não autorizada', 'code' => 'INVALID_ORIGIN']);
    exit;
}

header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, X-Security-Token, X-Request-Time');

// Responder OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Validar Referer (evita requisições sem referer)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isValidReferer = false;
foreach ($allowedOrigins as $allowed) {
    if (strpos($referer, $allowed) === 0) {
        $isValidReferer = true;
        break;
    }
}

if (!$isValidReferer) {
    http_response_code(403);
    echo json_encode(['error' => 'Referer inválido', 'code' => 'INVALID_REFERER']);
    exit;
}

// ========== SEGURANÇA AVANÇADA ==========
session_start();

// 4. Rate limit AGRESSIVO (máximo 3 PIX por hora por IP)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = sys_get_temp_dir() . '/pix_limit_' . md5($ip . date('Y-m-d-H'));
$rateData = [];

if (file_exists($rateFile)) {
    $rateData = json_decode(file_get_contents($rateFile), true);
    $count = $rateData['count'] ?? 0;
    $firstAttempt = $rateData['first_attempt'] ?? time();
    
    // Reset após 1 hora
    if (time() - $firstAttempt > 3600) {
        $count = 0;
        $firstAttempt = time();
    }
    
    if ($count >= 3) { // Máximo 3 PIX por hora
        http_response_code(429);
        echo json_encode(['error' => 'Limite de PIX excedido. Aguarde 1 hora.', 'code' => 'RATE_LIMIT']);
        exit;
    }
    
    $rateData['count'] = $count + 1;
} else {
    $rateData = ['count' => 1, 'first_attempt' => time()];
}
file_put_contents($rateFile, json_encode($rateData));

// 5. Validar CSRF Token (obrigatório)
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido', 'code' => 'INVALID_CSRF']);
    exit;
}

// 6. Validar Security Token
$securityToken = $headers['X-Security-Token'] ?? $_SERVER['HTTP_X_SECURITY_TOKEN'] ?? '';
if (!isset($_SESSION['checkout_token']) || !hash_equals($_SESSION['checkout_token'], $securityToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de segurança inválido', 'code' => 'INVALID_SECURITY_TOKEN']);
    exit;
}

// 7. Validar timestamp (requisição expira em 5 minutos)
$timestamp = $headers['X-Request-Time'] ?? $_SERVER['HTTP_X_REQUEST_TIME'] ?? 0;
if (abs(time() - $timestamp) > 300) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição expirada', 'code' => 'REQUEST_EXPIRED']);
    exit;
}

// 8. Nonce (número usado uma única vez - previne replay attacks)
$receivedNonce = $body['nonce'] ?? '';
if (!isset($_SESSION['pix_nonce']) || $_SESSION['pix_nonce'] !== $receivedNonce) {
    http_response_code(403);
    echo json_encode(['error' => 'Nonce inválido', 'code' => 'INVALID_NONCE']);
    exit;
}

if (time() > ($_SESSION['pix_nonce_expires'] ?? 0)) {
    http_response_code(403);
    echo json_encode(['error' => 'Nonce expirado', 'code' => 'NONCE_EXPIRED']);
    exit;
}

// Remover nonce após uso (não pode ser reutilizado)
unset($_SESSION['pix_nonce']);
unset($_SESSION['pix_nonce_expires']);

// ========== INTEGRAR SECURITY STATS ==========
$security = null;
if (file_exists('../../includes/SecurityStats.php')) {
    require_once '../../includes/SecurityStats.php';
    try {
        $security = new SecurityStats();
    } catch (Exception $e) {
        error_log("SecurityStats init failed: " . $e->getMessage());
    }
}

// ========== RECEBER DADOS ==========
$body = json_decode(file_get_contents('php://input'), true);

$productTitle = $body['productTitle'] ?? null;
$amount = floatval($body['amount'] ?? 0);
$plate = $body['plate'] ?? $_GET['plate'] ?? '';

// Validações básicas
if (!$productTitle) {
    if ($security) $security->logPixAttempt($plate, $amount, false, null, 'Missing productTitle');
    http_response_code(400);
    echo json_encode(['error' => 'productTitle obrigatório', 'code' => 'MISSING_TITLE']);
    exit;
}

if ($amount <= 0) {
    if ($security) $security->logPixAttempt($plate, $amount, false, null, 'Invalid amount');
    http_response_code(400);
    echo json_encode(['error' => 'amount inválido', 'code' => 'INVALID_AMOUNT']);
    exit;
}

if ($amount > 1000) {
    if ($security) $security->logPixAttempt($plate, $amount, false, null, 'Amount exceeds limit');
    http_response_code(400);
    echo json_encode(['error' => 'Valor máximo é R$ 1.000', 'code' => 'AMOUNT_EXCEEDED']);
    exit;
}

// ========== CHAMAR API EXTERNA ==========
$payload = [
    'productTitle' => $productTitle,
    'amount' => (string)$amount
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://security-api-ten.vercel.app/criar-pix',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    if ($security) $security->logPixAttempt($plate, $amount, false, null, "CURL Error: {$error}");
    http_response_code(500);
    echo json_encode(['error' => 'Erro de comunicação', 'code' => 'CURL_ERROR']);
    exit;
}

$data = json_decode($response, true);

// Log de sucesso
if ($security && $data && isset($data['txid'])) {
    $security->logPixAttempt($plate, $amount, true, $data['txid'], null);
}

echo json_encode([
    'success' => true,
    'code' => $httpCode,
    'data' => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);