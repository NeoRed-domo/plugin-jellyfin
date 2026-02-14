<?php
/*
 * Proxy d'image pour Jellyfin
 * Permet de contourner le problème de Mixed Content (HTTPS vs HTTP)
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

// 1. Récupération des paramètres
$itemId = init('itemId');
$tag = init('tag'); // Utilisé par Jellyfin pour le cache
$maxWidth = init('maxWidth', 400);

if (empty($itemId)) {
    header("HTTP/1.0 404 Not Found");
    die('Item ID missing');
}

// 2. Récupération de la config Jellyfin via Jeedom
$ip = config::byKey('jellyfin_ip', 'jellyfin');
$port = config::byKey('jellyfin_port', 'jellyfin');
$apikey = config::byKey('jellyfin_apikey', 'jellyfin');

if (empty($ip) || empty($port) || empty($apikey)) {
    header("HTTP/1.0 500 Internal Server Error");
    die('Plugin configuration missing');
}

// 3. Construction de l'URL interne (HTTP local)
$baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
$jellyfinUrl = $baseUrl . "/Items/" . $itemId . "/Images/Primary?maxWidth=" . $maxWidth . "&api_key=" . $apikey;

if ($tag) {
    $jellyfinUrl .= "&tag=" . $tag;
}

// 4. Initialisation du cache (Important pour la fluidité)
// Si le navigateur a déjà l'image en cache (basé sur le Tag), on renvoie 304
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == '"'.$tag.'"') {
    header("HTTP/1.1 304 Not Modified");
    exit;
}

// 5. Récupération de l'image via CURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $jellyfinUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, false);
// On suit les redirections si besoin
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// 6. Envoi de l'image au navigateur
if ($httpCode == 200 && $data) {
    // Headers de cache
    header("Content-Type: " . $contentType);
    header("Content-Length: " . strlen($data));
    header("Cache-Control: public, max-age=31536000"); // Cache 1 an
    if($tag) header('ETag: "'.$tag.'"');
    
    echo $data;
} else {
    // Si pas d'image, on renvoie une 404 ou une image vide
    header("HTTP/1.0 404 Not Found");
}
?>