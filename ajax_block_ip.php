<?php
// ajax_block_ip.php
require_once 'config.php';
require_once 'includes/Logger.php';

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ip = $data['ip'] ?? '';
$minutos = $data['minutos'] ?? 60;

if (empty($ip)) {
    echo json_encode(['success' => false, 'error' => 'IP não informado']);
    exit;
}

$logger = new Logger();
$result = $logger->blockIP($ip, 'Bloqueado pelo administrador', $minutos);

echo json_encode(['success' => true]);
?>