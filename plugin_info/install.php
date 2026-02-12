<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';

/**
 * Fonction exécutée lors de l'installation du plugin
 * Utilisation : Création de tables SQL dédiées (si nécessaire), initialisation de variables
 */
function jellyfin_install() {
    // Pour l'instant, le plugin utilise les tables standard de Jeedom (eqLogic, cmd)
    // Pas d'action spécifique requise à l'installation.
}

/**
 * Fonction exécutée lors de la mise à jour du plugin
 * Utilisation : Migration de données, correction de configuration lors d'un changement de version
 */
function jellyfin_update() {
    // Exemple de gestion de version pour le futur :
    // $currentVersion = config::byKey('version', 'jellyfin');
    // if (version_compare($currentVersion, '1.1', '<')) {
    //    // Actions de migration vers la 1.1
    // }
    
    // On s'assure que les crons sont bien gérés (même si le plugin n'utilise pas de cron spécifique pour le moment)
    // Cela permet de nettoyer d'éventuels crons orphelins d'anciennes versions de développement
    try {
        log::add('jellyfin', 'info', 'Nettoyage et mise à jour des tâches planifiées...');
        // Si tu ajoutes un cron plus tard, il faudra peut-être le relancer ici
    } catch (Exception $e) {
        log::add('jellyfin', 'error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
    }
}

/**
 * Fonction exécutée lors de la suppression du plugin
 * Utilisation : Nettoyage complet (suppression des équipements, des crons, des variables de config)
 */
function jellyfin_remove() {
    // Suppression des crons associés au plugin
    foreach (cron::byClassAndFunction('jellyfin', 'pull') as $cron) {
        $cron->remove();
    }
    
    // Remise à zéro des listeners (si utilisés)
    foreach (listener::byClassAndFunction('jellyfin', 'pull') as $listener) {
        $listener->remove();
    }
    
    // OPTIONNEL : Suppression des équipements
    // En général, on laisse le choix à l'utilisateur, mais pour un plugin propre
    // on peut vouloir supprimer les eqLogics pour ne pas polluer la DB.
    // Décommente les lignes ci-dessous si tu veux que la suppression du plugin supprime aussi les équipements.
    
    
    $eqLogics = eqLogic::byType('jellyfin');
    foreach ($eqLogics as $eqLogic) {
        $eqLogic->remove();
    }
    
}
?>