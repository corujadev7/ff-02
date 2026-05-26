<?php
// test_logger.php
require_once './includes/Logger.php';

$logger = new Logger();
$logger->logPlateSearch('TEST123', 'found', ['marca' => 'TESTE', 'modelo' => 'TESTE'], 100);

echo "Log criado! Verifique o banco de dados.";
?>