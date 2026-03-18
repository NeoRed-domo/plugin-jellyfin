<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

$file = init('file');
if (empty($file)) {
    header("HTTP/1.0 404 Not Found");
    die('File parameter missing');
}

// Sécurité : fichiers dans data/ ou resources/audio/ du plugin uniquement
$allowedDirs = [
    __DIR__ . '/../../data',
    __DIR__ . '/../../resources/audio'
];
$filePath = null;
$safeFile = basename($file); // Empêche les traversées de répertoire
foreach ($allowedDirs as $dir) {
    $resolved = realpath($dir . '/' . $safeFile);
    if ($resolved && file_exists($resolved)) {
        $filePath = $resolved;
        break;
    }
}

if (!$filePath || !file_exists($filePath)) {
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