<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';

/**
 * Fonction exécutée lors de l'installation du plugin
 */
function jellyfin_install() {
    // Initialisation des variables de configuration par défaut si nécessaire
    // Ex: if(is_null(config::byKey('jellyfin_port', 'jellyfin'))) config::save('jellyfin_port', '8096', 'jellyfin');
}

/**
 * Fonction exécutée lors de la mise à jour du plugin
 */
function jellyfin_update() {
    // Nettoyage des caches si nécessaire
    // On s'assure que les listeners et crons sont propres
    try {
        // Suppression des crons fantômes si jamais on change de nom de classe/fonction
        foreach (cron::byClassAndFunction('jellyfin', 'pull') as $cron) {
            $cron->remove();
        }
    } catch (Exception $e) {
        log::add('jellyfin', 'error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
    }
}

/**
 * Fonction exécutée lors de la suppression du plugin
 * IMPORTANT : On ne supprime PAS les équipements (eqLogic) pour permettre
 * une réinstallation sans perte de données (Favoris, Scénarios, IDs).
 */
function jellyfin_remove() {
    // 1. Suppression des CRONS système associés
    foreach (cron::byClassAndFunction('jellyfin', 'pull') as $cron) {
        $cron->remove();
    }
    
    // 2. Suppression des LISTENERS (Evènements Jeedom)
    foreach (listener::byClassAndFunction('jellyfin', 'pull') as $listener) {
        $listener->remove();
    }
    
    // 3. Nettoyage des fichiers temporaires du Démon (PID, Socket)
    $tmpFolder = jeedom::getTmpFolder('jellyfin');
    if(file_exists($tmpFolder . '/jellyfin.pid')) unlink($tmpFolder . '/jellyfin.pid');
    if(file_exists($tmpFolder . '/jellyfin.sock')) unlink($tmpFolder . '/jellyfin.sock');

    // NOTE : On ne touche pas à la table eqLogic ni cmd.
    // Si l'utilisateur réinstalle le plugin, il retrouvera tout.
}
?>