<?php
try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    if (init('action') == 'scanClients') {
        $result = jellyfin::scanClients();
        ajax::success($result);
    }

    if (init('action') == 'add') {
        $jellyfin = new jellyfin();
        $jellyfin->setName('Nouveau Jellyfin');
        $jellyfin->setEqType_name('jellyfin');
        $jellyfin->setIsEnable(1);
        $jellyfin->setIsVisible(1);
        $jellyfin->save();
        ajax::success($jellyfin->getId());
    }

    if (init('action') == 'remove') {
        $jellyfin = eqLogic::byId(init('id'));
        if (is_object($jellyfin)) {
            $jellyfin->remove();
            ajax::success();
        }
        throw new Exception(__('Equipement introuvable', __FILE__));
    }

    if (init('action') == 'all') {
        $jellyfins = jellyfin::all();
        $result = array();
        foreach ($jellyfins as $jellyfin) {
            $result[] = $jellyfin->toArray();
        }
        ajax::success($result);
    }

    /* Action: Explorateur de Bibliothèque */

    if (init('action') == 'getLibrary') {
        $parentId = init('parentId');
        // AJOUT ICI : On récupère le terme de recherche
        $searchTerm = init('search'); 
        
        // MODIFICATION ICI : On passe les deux paramètres à la fonction
        $result = jellyfin::getLibraryItems($parentId, $searchTerm);
        
        // ON GARDE LE TRAITEMENT EXISTANT (Important pour les images !)
        if (is_array($result) && isset($result['Items'])) {
            foreach ($result['Items'] as &$item) {
                $imgTag = isset($item['ImageTags']['Primary']) ? $item['ImageTags']['Primary'] : null;
                $item['_full_img_url'] = jellyfin::getItemImageUrl($item['Id'], $imgTag);
                $item['_img_tag'] = $imgTag;
                $item['_is_folder'] = ($item['Type'] == 'CollectionFolder' || $item['IsFolder'] === true);
            }
        }
        ajax::success($result);
    }
    
    /* Action: Lancer un média */
    if (init('action') == 'play_media') {
         $eqLogicId = init('id');
         $mediaId = init('mediaId');
         $mode = init('mode', 'play_now'); 
         
         $eqLogic = jellyfin::byId($eqLogicId);
         if (!is_object($eqLogic)) throw new Exception(__('Equipement introuvable', __FILE__));
         
         $result = $eqLogic->playMedia($mediaId, $mode);
         ajax::success($result);
    }

    /* Action: Créer un favori */
    if (init('action') == 'create_command') {
        $eqLogicId = init('id');
        $mediaId = init('mediaId');
        $name = init('name');
        $imgTag = init('imgTag');

        $eqLogic = jellyfin::byId($eqLogicId);
        if (!is_object($eqLogic)) throw new Exception(__('Equipement introuvable', __FILE__));

        $result = $eqLogic->createMediaCommand($mediaId, $name, $imgTag);
        ajax::success($result);
    }

/* Action: Supprimer un favori (Sécurisé) */
    if (init('action') == 'remove_command') {
        $cmdId = init('cmdId');
        $cmd = cmd::byId($cmdId);
        
        if (is_object($cmd)) {
            // Vérification de sécurité : l'équipement parent doit être de type 'jellyfin'
            $eqLogic = $cmd->getEqLogic();
            if (is_object($eqLogic) && $eqLogic->getEqType_name() == 'jellyfin') {
                $cmd->remove();
                ajax::success();
            } else {
                // Ce n'est pas une commande Jellyfin !
                throw new Exception(__('Action non autorisée', __FILE__));
            }
        } else {
            throw new Exception(__('Commande introuvable', __FILE__));
        }
    }

    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
    /* * *************************Catch***************************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>