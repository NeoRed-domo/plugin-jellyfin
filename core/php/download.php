<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

$file = init('file');
if (empty($file)) {
    header("HTTP/1.0 404 Not Found");
    die('File parameter missing');
}

// Sécurité : fichiers dans data/ ou resources/audio/ du plugin
$dataDir = realpath(__DIR__ . '/../../data');
$audioDir = realpath(__DIR__ . '/../../resources/audio');
$filePath = null;
$candidate = realpath($dataDir . '/' . basename($file));
if ($candidate && strpos($candidate, $dataDir) === 0) {
    $filePath = $candidate;
} else {
    $candidate = realpath($audioDir . '/' . basename($file));
    if ($candidate && strpos($candidate, $audioDir) === 0) {
        $filePath = $candidate;
    }
}

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