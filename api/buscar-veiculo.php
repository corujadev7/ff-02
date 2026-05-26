<?php
// /free/api/buscar-veiculo.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Incluir logger
if (file_exists('../../includes/Logger.php')) {
    require_once '../../includes/Logger.php';
    $logger = new Logger();
} else {
    // Fallback se o arquivo não existir
    $logger = null;
}

$startTime = microtime(true);
$placa = $_GET['placa'] ?? '';
$placa = strtoupper(trim($placa));
$placa = preg_replace('/[^A-Z0-9]/', '', $placa);

// Validação Mercosul + antiga
if (
    !preg_match('/^[A-Z]{3}[0-9][A-Z]{1}[0-9]{2}$/', $placa) &&
    !preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)
) {
    http_response_code(400);
    echo json_encode(['error' => 'Placa inválida']);
    
    // Log da tentativa inválida
    if ($logger) {
        $logger->logPlateSearch($placa, 'error', null, round((microtime(true) - $startTime) * 1000));
    }
    exit;
}

$url = "https://placaipva.com.br/placa/" . $placa;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0']
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($html)) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar']);
    
    if ($logger) {
        $logger->logPlateSearch($placa, 'error', null, round((microtime(true) - $startTime) * 1000));
    }
    exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

$rows = $xpath->query('//table[contains(@class,"fipeTablePriceDetail")]//tr');
$logo = null;
$img = $xpath->query('//img[contains(@class,"fipeLogoDIV fipeLogoIMG ls-is-cached lazyloaded")]');

if ($img->length > 0) {
    $logo = $img->item(0)->getAttribute('src');
    if (!$logo) {
        $logo = $img->item(0)->getAttribute('data-src');
    }
}

$dados = [];
foreach ($rows as $row) {
    $tds = $row->getElementsByTagName('td');
    if ($tds->length >= 2) {
        $chave = trim(str_replace(':', '', $tds->item(0)->textContent));
        $valor = trim($tds->item(1)->textContent);
        $chave = mb_strtolower($chave);
        $chave = str_replace(' ', '_', $chave);
        $dados[$chave] = $valor;
    }
}

$response = [
    'code' => 200,
    'data' => [...$dados, 'logo' => $logo]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Log da consulta bem-sucedida
if ($logger && !empty($dados)) {
    $logger->logPlateSearch($placa, 'found', $dados, round((microtime(true) - $startTime) * 1000));
}
?>