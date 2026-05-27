<?php
// /free/api/payment.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? ''));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Security-Token, X-Requested-With, X-Request-Time');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// 1. Verificar AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida', 'code' => 'INVALID_REQUEST']);
    exit;
}

// 2. Validar Origin
$allowedHost = $_SERVER['HTTP_HOST'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== "http://{$allowedHost}" && $origin !== "https://{$allowedHost}") {
    if ($origin !== 'http://localhost' && $origin !== 'https://localhost') {
        http_response_code(403);
        echo json_encode(['error' => 'Origem não autorizada', 'code' => 'INVALID_ORIGIN']);
        exit;
    }
}

// 3. Validar CSRF Token
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido', 'code' => 'INVALID_CSRF']);
    exit;
}

// 4. Validar Security Token
$securityToken = $headers['X-Security-Token'] ?? $_SERVER['HTTP_X_SECURITY_TOKEN'] ?? '';
if (!isset($_SESSION['checkout_token']) || !hash_equals($_SESSION['checkout_token'], $securityToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de segurança inválido', 'code' => 'INVALID_SECURITY_TOKEN']);
    exit;
}

// 5. Validar NONCE (proteção contra replay attack)
$body = json_decode(file_get_contents('php://input'), true);
$receivedNonce = $body['nonce'] ?? '';

if (!isset($_SESSION['pix_nonce']) || empty($receivedNonce)) {
    http_response_code(403);
    echo json_encode(['error' => 'Nonce não encontrado', 'code' => 'INVALID_NONCE']);
    exit;
}

// Verificar se o nonce é válido
if ($_SESSION['pix_nonce'] !== $receivedNonce) {
    http_response_code(403);
    echo json_encode(['error' => 'Nonce inválido', 'code' => 'INVALID_NONCE']);
    exit;
}

// Verificar se o nonce já foi usado
if (isset($_SESSION['pix_nonce_used']) && $_SESSION['pix_nonce_used'] === true) {
    http_response_code(403);
    echo json_encode(['error' => 'Nonce já utilizado', 'code' => 'NONCE_ALREADY_USED']);
    exit;
}

// Verificar se o nonce expirou
if (time() > ($_SESSION['pix_nonce_expires'] ?? 0)) {
    http_response_code(403);
    echo json_encode(['error' => 'Nonce expirado', 'code' => 'NONCE_EXPIRED']);
    exit;
}

// Marcar nonce como usado
$_SESSION['pix_nonce_used'] = true;

// 6. Rate limit
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = sys_get_temp_dir() . '/pix_limit_' . md5($ip);
$rateData = [];

if (file_exists($rateFile)) {
    $rateData = json_decode(file_get_contents($rateFile), true);
    if (($rateData['count'] ?? 0) >= 3) {
        http_response_code(429);
        echo json_encode(['error' => 'Limite de PIX excedido. Aguarde.', 'code' => 'RATE_LIMIT']);
        exit;
    }
    $rateData['count'] = ($rateData['count'] ?? 0) + 1;
} else {
    $rateData = ['count' => 1, 'time' => time()];
}
file_put_contents($rateFile, json_encode($rateData));

// 7. Processar pagamento
$productTitle = $body['productTitle'] ?? null;
$amount = floatval($body['amount'] ?? 0);
$plate = $body['plate'] ?? '';

if (!$productTitle || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

if ($amount > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Valor máximo R$ 1.000']);
    exit;
}

// Chamar API externa
$payload = ['productTitle' => $productTitle, 'amount' => (string)$amount];
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
curl_close($ch);

$data = json_decode($response, true);

echo json_encode([
    'success' => true,
    'code' => $httpCode,
    'data' => $data
]);