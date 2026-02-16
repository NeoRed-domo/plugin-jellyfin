<?php
try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

if (init('action') == 'add') {
        $name = init('name'); // On récupère le nom envoyé par le JS
        
        $jellyfin = new jellyfin();
        
        // Si le nom est vide (annulation ou bug), on met un nom par défaut
        if (empty($name)) {
            $name = 'Nouveau Jellyfin';
        }
        
        $jellyfin->setName($name);
        $jellyfin->setEqType_name('jellyfin');
        $jellyfin->setIsEnable(1);
        $jellyfin->setIsVisible(1);
        $jellyfin->save();
        
        // IMPORTANT : On renvoie l'objet complet sous forme de tableau
        // pour que le JS puisse lire data.result.id
        ajax::success($jellyfin->toArray());
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
        $searchTerm = init('search'); 
        
        $result = jellyfin::getLibraryItems($parentId, $searchTerm);
        
        if (is_array($result) && isset($result['Items'])) {
            foreach ($result['Items'] as &$item) {
                // --- GESTION IMAGES ---
                $imgTag = isset($item['ImageTags']['Primary']) ? $item['ImageTags']['Primary'] : null;
                $item['_full_img_url'] = jellyfin::getItemImageUrl($item['Id'], $imgTag);
                $item['_img_tag'] = $imgTag;
                $item['_is_folder'] = ($item['Type'] == 'CollectionFolder' || $item['IsFolder'] === true);

                // --- GESTION VIDEO & AUDIO (DYNAMIQUE) ---
                $item['_video_res'] = '';
                $item['_audio_info'] = '';

                // On vérifie si on a des sources médias
                if (isset($item['MediaSources'][0]['MediaStreams'])) {
                    $streams = $item['MediaSources'][0]['MediaStreams'];
                    $videoFound = false;
                    $audioSelected = null;
                    $firstAudio = null;

                    // 1. Analyse des pistes
                    foreach ($streams as $stream) {
                        // VIDEO
                        if ($stream['Type'] == 'Video' && !$videoFound) {
                            $w = isset($stream['Width']) ? $stream['Width'] : 0;
                            // Détection simplifiée de la résolution
                            if ($w >= 3800) $item['_video_res'] = '4K';       // UHD souvent trop long, 4K est clair
                            elseif ($w >= 1900) $item['_video_res'] = '1080p';
                            elseif ($w >= 1200) $item['_video_res'] = '720p';
                            elseif ($w > 0)     $item['_video_res'] = 'SD';
                            $videoFound = true;
                        }

                        // AUDIO
                        if ($stream['Type'] == 'Audio') {
                            // On garde la toute première piste audio trouvée "au cas où" aucune n'est par défaut
                            if ($firstAudio === null) {
                                $firstAudio = $stream;
                            }

                            // LA LOGIQUE DYNAMIQUE : On cherche celle que Jellyfin a décidé de lire (IsDefault)
                            if (isset($stream['IsDefault']) && $stream['IsDefault'] === true) {
                                $audioSelected = $stream;
                                // On a trouvé la piste par défaut, on peut arrêter de chercher
                                // (Sauf si on veut gérer les pistes "Forced", mais Default suffit à 99%)
                                break; 
                            }
                        }
                    }

                    // Si aucune piste n'est marquée "Default" (cas rare), on prend la première piste audio du fichier
                    if ($audioSelected === null && $firstAudio !== null) {
                        $audioSelected = $firstAudio;
                    }

                    // 2. Formatage de l'Audio
                    if ($audioSelected) {
                        // Nettoyage du nom du codec
                        $codec = isset($audioSelected['Codec']) ? strtoupper($audioSelected['Codec']) : '';
                        
                        // Mapping simple et court pour l'affichage mobile/widget
                        $codecDisplay = $codec;
                        if (strpos($codec, 'DTS') !== false) {
                            $codecDisplay = 'DTS';
                            if (strpos($codec, 'HD') !== false || isset($audioSelected['Profile']) && strpos($audioSelected['Profile'], 'MA') !== false) {
                                $codecDisplay = 'DTS-HD';
                            }
                        }
                        if ($codec == 'AC3') $codecDisplay = 'DD';
                        if ($codec == 'EAC3') $codecDisplay = 'DD+';
                        if ($codec == 'TRUEHD') $codecDisplay = 'TrueHD';
                        if ($codec == 'AAC') $codecDisplay = 'AAC';
                        if ($codec == 'MP3') $codecDisplay = 'MP3';

                        // Canaux
                        $channels = isset($audioSelected['Channels']) ? $audioSelected['Channels'] : 2;
                        $chDisplay = '2.0';
                        if ($channels >= 8) $chDisplay = '7.1';
                        elseif ($channels >= 6) $chDisplay = '5.1';
                        elseif ($channels == 1) $chDisplay = 'Mono';
                        
                        // Résultat final : "DTS-HD 5.1" ou "DD+ 7.1"
                        $item['_audio_info'] = $codecDisplay . ' ' . $chDisplay;
                    }
                }
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