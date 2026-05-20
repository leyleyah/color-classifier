<?php
header('Content-Type: application/json; charset=utf-8');

$modelServerUp = false;
$fp = @fsockopen('127.0.0.1', 8765, $errno, $errstr, 1);
if ($fp !== false) {
    fclose($fp);
    $modelServerUp = true;
}

echo json_encode([
    'ok' => true,
    'php' => PHP_VERSION,
    'model_server_running' => $modelServerUp,
], JSON_UNESCAPED_SLASHES);
