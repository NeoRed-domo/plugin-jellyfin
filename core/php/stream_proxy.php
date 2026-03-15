<?php
/*
 * Proxy de streaming vidéo pour Jellyfin
 * Contourne le problème de Mixed Content (HTTPS Jeedom vs HTTP Jellyfin)
 * Supporte les Range requests pour le seek dans le lecteur HTML5
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

$itemId = init('itemId');

if (empty($itemId)) {
    header("HTTP/1.0 404 Not Found");
    die('Item ID missing');
}

$ip = config::byKey('jellyfin_ip', 'jellyfin');
$port = config::byKey('jellyfin_port', 'jellyfin');
$apikey = config::byKey('jellyfin_apikey', 'jellyfin');

if (empty($ip) || empty($port) || empty($apikey)) {
    header("HTTP/1.0 500 Internal Server Error");
    die('Plugin configuration missing');
}

$baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
$jellyfinUrl = $baseUrl . '/Videos/' . $itemId . '/stream?static=true&api_key=' . $apikey;

// Transférer le header Range du navigateur vers Jellyfin (seek)
$headers = [];
if (isset($_SERVER['HTTP_RANGE'])) {
    $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $jellyfinUrl);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Lire les headers de réponse Jellyfin pour les transférer au navigateur
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $headerLine) {
    $len = strlen($headerLine);
    $parts = explode(':', $headerLine, 2);
    if (count($parts) < 2) return $len;

    $name = strtolower(trim($parts[0]));
    $value = trim($parts[1]);

    // Transférer les headers pertinents
    if (in_array($name, ['content-type', 'content-length', 'content-range', 'accept-ranges'])) {
        header($parts[0] . ': ' . $value);
    }
    return $len;
});

// Streamer directement la réponse (pas de mise en mémoire)
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
    echo $data;
    flush();
    return strlen($data);
});

// Pas de timeout (streaming long)
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// Lancer le streaming
$httpCode = null;
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $headerLine) use (&$httpCode) {
    $len = strlen($headerLine);

    // Capturer le code HTTP
    if (strpos($headerLine, 'HTTP/') === 0) {
        preg_match('/HTTP\/\S+\s+(\d+)/', $headerLine, $matches);
        if (isset($matches[1])) {
            $httpCode = (int)$matches[1];
            // 206 = Partial Content (range request)
            http_response_code($httpCode);
        }
        return $len;
    }

    $parts = explode(':', $headerLine, 2);
    if (count($parts) < 2) return $len;

    $name = strtolower(trim($parts[0]));
    $value = trim($parts[1]);

    if (in_array($name, ['content-type', 'content-length', 'content-range', 'accept-ranges'])) {
        header(trim($parts[0]) . ': ' . $value);
    }
    return $len;
});

// Headers pour le navigateur
header('Accept-Ranges: bytes');
header('Cache-Control: no-cache');

curl_exec($ch);
curl_close($ch);
?>