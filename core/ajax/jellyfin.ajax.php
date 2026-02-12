<?php
try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    if (init('action') == 'scanClients') {
        // Appelle la fonction de scan définie dans la classe
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

    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
    /*     * *************************Catch***************************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>