<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

$file = init('file');
if (empty($file)) {
    header("HTTP/1.0 404 Not Found");
    die('File parameter missing');
}

// Sécurité : uniquement les fichiers dans le répertoire data du plugin
$dataDir = realpath(__DIR__ . '/../../data');
$filePath = realpath($dataDir . '/' . basename($file));

if (!$filePath || strpos($filePath, $dataDir) !== 0 || !file_exists($filePath)) {
    header("HTTP/1.0 404 Not Found");
    die('File not found');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');
ob_clean();
flush();
readfile($filePath);
exit;
?>