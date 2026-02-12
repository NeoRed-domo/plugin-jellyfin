<?php
/*
 * Script de réception des données du démon Python Jellyfin
 */

try {
    // 1. Chargement du coeur de Jeedom
    require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

    if (!jeedom::apiAccess(init('apikey'), 'jellyfin')) {
        http_response_code(403);
        die(__('Clé API invalide', __FILE__));
    }

    // 2. Récupération des données brutes (JSON)
    $content = file_get_contents("php://input");
    $payload = json_decode($content, true);

    // Debug pour être sûr qu'on reçoit quelque chose
    if (log::getLogLevel('jellyfin') <= 100) { // Si niveau Debug
        log::add('jellyfin', 'debug', 'Données reçues du démon : ' . print_r($payload, true));
    }

    // 3. Appel de la fonction de traitement dans la classe principale
    // On vérifie que la classe et la méthode existent pour éviter le Fatal Error 500
    if (class_exists('jellyfin') && method_exists('jellyfin', 'processSessions')) {
        jellyfin::processSessions($payload);
    } else {
        throw new Exception("La méthode jellyfin::processSessions n'existe pas !");
    }

    // 4. Réponse OK pour le Python
    echo "OK";

} catch (Exception $e) {
    // En cas d'erreur, on logue et on renvoie une 500 propre
    log::add('jellyfin', 'error', 'Erreur dans jeeJellyfin.php : ' . $e->getMessage());
    http_response_code(500);
    echo $e->getMessage();
}
?>