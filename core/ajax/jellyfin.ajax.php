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

    /* ************************* Actions Séances ************************* */

    if (init('action') == 'create_session') {
        $name = init('name');
        $sessionType = init('session_type');
        if (empty($name) || !in_array($sessionType, ['cinema', 'commercial'])) {
            throw new Exception(__('Paramètres invalides', __FILE__));
        }

        $eqLogic = new jellyfin();
        $eqLogic->setName($name);
        $eqLogic->setEqType_name('jellyfin');
        $eqLogic->setConfiguration('session_type', $sessionType);
        $eqLogic->setIsEnable(1);
        $eqLogic->setIsVisible(1);

        if ($sessionType == 'cinema') {
            $sections = [];
            foreach (jellyfin::SECTION_ORDER as $key) {
                $sections[$key] = ['triggers' => []];
            }
            $sections['film']['marks'] = array_fill_keys(jellyfin::MARK_ORDER, null);
            $lighting = array_fill_keys(array_merge(jellyfin::SECTION_ORDER, jellyfin::MARK_ORDER, ['pause']), null);
            $eqLogic->setConfiguration('session_data', [
                'player_id' => null,
                'sections'  => $sections,
                'lighting'  => $lighting
            ]);
        } else {
            $eqLogic->setConfiguration('session_data', [
                'player_id' => null,
                'loop'      => true,
                'playlist'  => []
            ]);
        }

        $eqLogic->save();
        ajax::success($eqLogic->toArray());
    }

    if (init('action') == 'save_session_data') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
            throw new Exception(__('Séance introuvable', __FILE__));
        }
        $sessionData = json_decode(init('session_data'), true);
        if (!is_array($sessionData)) {
            throw new Exception(__('Données invalides', __FILE__));
        }
        $eqLogic->setConfiguration('session_data', $sessionData);
        $eqLogic->save();
        ajax::success();
    }

    if (init('action') == 'get_session_data') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
            throw new Exception(__('Séance introuvable', __FILE__));
        }
        ajax::success([
            'session_type' => $eqLogic->getConfiguration('session_type'),
            'session_data' => $eqLogic->getConfiguration('session_data'),
            'sections_meta' => [
                'order'  => jellyfin::SECTION_ORDER,
                'labels' => jellyfin::SECTION_LABELS,
                'colors' => jellyfin::SECTION_COLORS
            ],
            'marks_meta' => [
                'order'  => jellyfin::MARK_ORDER,
                'labels' => jellyfin::MARK_LABELS
            ]
        ]);
    }

    if (init('action') == 'start_session') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
            throw new Exception(__('Séance introuvable', __FILE__));
        }
        $result = $eqLogic->startSession();
        if (isset($result['error'])) throw new Exception($result['error']);
        ajax::success($result);
    }

    if (init('action') == 'stop_session') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic)) throw new Exception(__('Séance introuvable', __FILE__));
        ajax::success($eqLogic->stopSession());
    }

    if (init('action') == 'pause_session') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic)) throw new Exception(__('Séance introuvable', __FILE__));
        ajax::success($eqLogic->pauseSession());
    }

    if (init('action') == 'resume_session') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic)) throw new Exception(__('Séance introuvable', __FILE__));
        ajax::success($eqLogic->resumeSession());
    }

    if (init('action') == 'schedule_session') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
            throw new Exception(__('Séance introuvable', __FILE__));
        }
        $datetime = init('datetime');
        if (empty($datetime)) throw new Exception(__('Date/heure requise', __FILE__));

        $cron = new cron();
        $cron->setClass('jellyfin');
        $cron->setFunction('executeSession');
        $cron->setOption(['session_id' => $eqLogic->getId()]);
        $cron->setOnce(1);
        $dt = new DateTime($datetime);
        $cron->setSchedule($dt->format('i') . ' ' . $dt->format('H') . ' ' . $dt->format('d') . ' ' . $dt->format('m') . ' * ' . $dt->format('Y'));
        $cron->save();
        ajax::success(['scheduled' => $datetime]);
    }

    if (init('action') == 'get_player_cmd_ids') {
        $player = jellyfin::byId(init('player_id'));
        if (!is_object($player) || $player->getConfiguration('session_type') != '') {
            throw new Exception(__('Lecteur introuvable', __FILE__));
        }
        $cmds = [];
        foreach ($player->getCmd() as $cmd) {
            $cmds[$cmd->getLogicalId()] = $cmd->getId();
        }
        ajax::success($cmds);
    }

    if (init('action') == 'get_player_position') {
        $player = jellyfin::byId(init('player_id'));
        if (!is_object($player) || $player->getConfiguration('session_type') != '') {
            throw new Exception(__('Lecteur introuvable', __FILE__));
        }
        $posCmd = $player->getCmd('info', 'position');
        $durCmd = $player->getCmd('info', 'duration');
        ajax::success([
            'position' => is_object($posCmd) ? $posCmd->execCmd() : '--:--',
            'duration' => is_object($durCmd) ? $durCmd->execCmd() : '--:--'
        ]);
    }

    if (init('action') == 'calibrate_start') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') != 'cinema') {
            throw new Exception(__('Séance cinéma introuvable', __FILE__));
        }
        $mediaId = init('mediaId');
        if (empty($mediaId)) throw new Exception(__('ID média requis', __FILE__));

        // URL via proxy local (contourne le mixed content HTTPS/HTTP)
        $streamUrl = 'plugins/jellyfin/core/php/stream_proxy.php?itemId=' . $mediaId;
        ajax::success(['stream_url' => $streamUrl]);
    }

    if (init('action') == 'get_session_status') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
            throw new Exception(__('Séance introuvable', __FILE__));
        }
        $sessionData = $eqLogic->getConfiguration('session_data');
        $playerId = $sessionData['player_id'] ?? null;
        $engineState = null;
        if ($playerId) {
            $raw = cache::byKey('jellyfin::active_session::' . $playerId)->getValue(null);
            if ($raw) $engineState = json_decode($raw, true);
        }
        // Lire les commandes
        $stateCmd = $eqLogic->getCmd('info', 'state');
        $sectionCmd = $eqLogic->getCmd('info', 'current_section');
        $progressCmd = $eqLogic->getCmd('info', 'progress');

        $result = [
            'state' => is_object($stateCmd) ? $stateCmd->execCmd() : 'stopped',
            'current_section' => is_object($sectionCmd) ? $sectionCmd->execCmd() : '',
            'progress' => is_object($progressCmd) ? $progressCmd->execCmd() : 0,
            'engine_state' => $engineState
        ];

        // Infos lecteur si en cours
        if ($engineState && $playerId) {
            $player = jellyfin::byId($playerId);
            if (is_object($player)) {
                $posCmd = $player->getCmd('info', 'position');
                $durCmd = $player->getCmd('info', 'duration');
                $titleCmd = $player->getCmd('info', 'title');
                $statusCmd = $player->getCmd('info', 'status');
                $result['player'] = [
                    'name' => $player->getName(),
                    'position' => is_object($posCmd) ? $posCmd->execCmd() : '--:--',
                    'duration' => is_object($durCmd) ? $durCmd->execCmd() : '--:--',
                    'title' => is_object($titleCmd) ? $titleCmd->execCmd() : '',
                    'status' => is_object($statusCmd) ? $statusCmd->execCmd() : 'Stopped'
                ];
            }
        }
        ajax::success($result);
    }

    if (init('action') == 'refresh_session_durations') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
            throw new Exception(__('Séance introuvable', __FILE__));
        }
        $config = jellyfin::getBaseConfig();
        if (!$config) throw new Exception(__('Configuration Jellyfin incomplète', __FILE__));
        $userId = jellyfin::getPrimaryUserId();
        if (!$userId) throw new Exception(__('Aucun utilisateur Jellyfin trouvé', __FILE__));

        $sessionData = $eqLogic->getConfiguration('session_data');
        $sessionType = $eqLogic->getConfiguration('session_type');
        $updated = 0;

        // Fonction helper pour enrichir un trigger média
        $enrichTrigger = function(&$trigger) use ($config, $userId, &$updated) {
            if ($trigger['type'] != 'media' || empty($trigger['media_id'])) return;
            $url = $config['baseUrl'] . '/Users/' . $userId . '/Items/' . $trigger['media_id'] . '?api_key=' . $config['apikey'] . '&Fields=RunTimeTicks,MediaSources,MediaStreams';
            $itemData = jellyfin::requestApi($url);
            if (!$itemData) return;
            if (isset($itemData['RunTimeTicks'])) $trigger['duration_ticks'] = $itemData['RunTimeTicks'];
            // Résolution vidéo
            if (isset($itemData['MediaSources'][0]['MediaStreams'])) {
                foreach ($itemData['MediaSources'][0]['MediaStreams'] as $stream) {
                    if ($stream['Type'] == 'Video' && isset($stream['Width'])) {
                        $w = $stream['Width'];
                        if ($w >= 3800) $trigger['video_res'] = '4K';
                        elseif ($w >= 1900) $trigger['video_res'] = '1080p';
                        elseif ($w >= 1200) $trigger['video_res'] = '720p';
                        elseif ($w > 0) $trigger['video_res'] = 'SD';
                        break;
                    }
                }
                // Audio
                $audioSelected = null;
                foreach ($itemData['MediaSources'][0]['MediaStreams'] as $stream) {
                    if ($stream['Type'] == 'Audio') {
                        if (isset($stream['IsDefault']) && $stream['IsDefault']) { $audioSelected = $stream; break; }
                        if (!$audioSelected) $audioSelected = $stream;
                    }
                }
                if ($audioSelected) {
                    $codec = strtoupper($audioSelected['Codec'] ?? '');
                    $codecDisplay = $codec;
                    if (strpos($codec, 'DTS') !== false) { $codecDisplay = 'DTS'; if (strpos($codec, 'HD') !== false || (isset($audioSelected['Profile']) && strpos($audioSelected['Profile'], 'MA') !== false)) $codecDisplay = 'DTS-HD'; }
                    if ($codec == 'AC3') $codecDisplay = 'DD';
                    if ($codec == 'EAC3') $codecDisplay = 'DD+';
                    if ($codec == 'TRUEHD') $codecDisplay = 'TrueHD';
                    $channels = $audioSelected['Channels'] ?? 2;
                    $chDisplay = '2.0';
                    if ($channels >= 8) $chDisplay = '7.1';
                    elseif ($channels >= 6) $chDisplay = '5.1';
                    elseif ($channels == 1) $chDisplay = 'Mono';
                    $trigger['audio_info'] = $codecDisplay . ' ' . $chDisplay;
                }
            }
            $updated++;
        };

        if ($sessionType == 'cinema') {
            foreach ($sessionData['sections'] as $sectionKey => &$section) {
                foreach ($section['triggers'] as &$trigger) {
                    $enrichTrigger($trigger);
                }
            }
            unset($section, $trigger);
        } else {
            foreach ($sessionData['playlist'] as &$trigger) {
                $enrichTrigger($trigger);
            }
            unset($trigger);
        }

        $eqLogic->setConfiguration('session_data', $sessionData);
        $eqLogic->save();
        ajax::success(['updated' => $updated]);
    }

    if (init('action') == 'calibrate_set_mark') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') != 'cinema') {
            throw new Exception(__('Séance cinéma introuvable', __FILE__));
        }
        $markName = init('mark_name');
        $position = init('position');

        if (!in_array($markName, jellyfin::MARK_ORDER)) {
            throw new Exception(__('Marqueur invalide', __FILE__));
        }

        $sessionData = $eqLogic->getConfiguration('session_data');
        $sessionData['sections']['film']['marks'][$markName] = (int)$position;
        $eqLogic->setConfiguration('session_data', $sessionData);
        $eqLogic->save();
        ajax::success();
    }

    if (init('action') == 'get_sessions_for_player') {
        $playerId = init('player_id');
        $sessions = jellyfin::getSessionsForPlayer($playerId);
        $result = [];
        foreach ($sessions as $session) {
            $sd = $session->getConfiguration('session_data');
            $type = $session->getConfiguration('session_type');
            $info = [
                'id'   => $session->getId(),
                'name' => $session->getName(),
                'type' => $type,
                'player_id' => $sd['player_id'] ?? null
            ];

            if ($type == 'cinema' && isset($sd['sections'])) {
                $totalTicks = 0;
                $counts = ['media' => 0, 'pubs' => 0, 'trailers' => 0, 'short_film' => 0];
                foreach ($sd['sections'] as $sectionKey => $section) {
                    foreach ($section['triggers'] ?? [] as $trigger) {
                        if ($trigger['type'] == 'media') {
                            $totalTicks += $trigger['duration_ticks'] ?? 0;
                            if ($sectionKey == 'pubs') $counts['pubs']++;
                            elseif ($sectionKey == 'trailers') $counts['trailers']++;
                            elseif ($sectionKey == 'short_film') $counts['short_film']++;
                            $counts['media']++;
                        }
                    }
                }
                $info['total_duration'] = floor($totalTicks / 10000000);
                $info['counts'] = $counts;

                $filmTriggers = $sd['sections']['film']['triggers'] ?? [];
                $info['poster'] = null;
                $info['film_name'] = '';
                foreach ($filmTriggers as $t) {
                    if ($t['type'] == 'media') {
                        $info['poster'] = jellyfin::getItemImageUrl($t['media_id'], $t['img_tag'] ?? null);
                        $info['film_name'] = $t['name'] ?? '';
                        break;
                    }
                }
            } elseif ($type == 'commercial' && isset($sd['playlist'])) {
                $totalTicks = 0;
                foreach ($sd['playlist'] as $trigger) {
                    $totalTicks += $trigger['duration_ticks'] ?? 0;
                }
                $info['total_duration'] = floor($totalTicks / 10000000);
                $info['counts'] = ['media' => count($sd['playlist'])];
                $info['poster'] = null;
                if (!empty($sd['playlist'])) {
                    $first = $sd['playlist'][0];
                    $info['poster'] = jellyfin::getItemImageUrl($first['media_id'], $first['img_tag'] ?? null);
                }
            }

            $result[] = $info;
        }
        ajax::success($result);
    }

    /* ************************* Actions Audio ************************* */

    if (init('action') == 'generate_pink_noise') {
        if (!jellyfin::isFfmpegAvailable()) {
            throw new Exception(__('ffmpeg non installé', __FILE__));
        }
        $dataDir = __DIR__ . '/../../data';
        if (!is_dir($dataDir)) mkdir($dataDir, 0775, true);
        $outputFile = $dataDir . '/reference_pink_noise_-24LUFS.wav';

        // Générer 30s de bruit rose normalisé à -24 LUFS (standard broadcast EBU R128)
        $cmd = 'ffmpeg -y -f lavfi -i "anoisesrc=d=30:c=pink:a=0.1" '
             . '-af "loudnorm=I=-24:TP=-1:LRA=7:print_format=summary" '
             . '-ar 48000 -c:a pcm_s16le '
             . escapeshellarg($outputFile) . ' 2>&1';
        exec($cmd, $output, $returnVar);

        if ($returnVar != 0 || !file_exists($outputFile)) {
            throw new Exception(__('Erreur génération bruit rose', __FILE__) . ': ' . implode("\n", array_slice($output, -5)));
        }

        // Vérifier le LUFS du fichier généré
        $lufsCmd = 'ffmpeg -i ' . escapeshellarg($outputFile) . ' -af loudnorm=print_format=json -f null - 2>&1';
        $lufsOutput = [];
        exec($lufsCmd, $lufsOutput);
        $lufsText = implode("\n", $lufsOutput);
        $lufs = -24.0;
        if (preg_match('/"input_i"\s*:\s*"([^"]+)"/', $lufsText, $matches)) {
            $lufs = (float)$matches[1];
        }

        $fileSize = round(filesize($outputFile) / 1024);
        ajax::success([
            'file' => 'plugins/jellyfin/core/php/download.php?file=reference_pink_noise_-24LUFS.wav',
            'lufs' => $lufs,
            'size' => $fileSize . ' Ko',
            'message' => __('Fichier généré. Importez-le dans votre bibliothèque Jellyfin.', __FILE__)
        ]);
    }

    if (init('action') == 'check_ffmpeg') {
        ajax::success(['available' => jellyfin::isFfmpegAvailable()]);
    }

    if (init('action') == 'save_calibration') {
        $playerId = init('player_id');
        $player = jellyfin::byId($playerId);
        if (!is_object($player) || $player->getConfiguration('session_type') != '') {
            throw new Exception(__('Lecteur introuvable', __FILE__));
        }
        $player->setConfiguration('audio_ref_volume', init('ref_volume'));
        $player->setConfiguration('audio_ref_lufs', init('ref_lufs'));
        $player->setConfiguration('audio_ref_media_id', init('ref_media_id'));
        $player->save();
        ajax::success();
    }

    if (init('action') == 'capture_amp_volume') {
        $playerId = init('player_id');
        $player = jellyfin::byId($playerId);
        if (!is_object($player)) throw new Exception(__('Lecteur introuvable', __FILE__));
        $infoCmdId = $player->getConfiguration('amp_volume_info_cmd_id');
        if (empty($infoCmdId) || !is_numeric($infoCmdId)) {
            throw new Exception(__('Commande info volume non configurée', __FILE__));
        }
        $cmd = cmd::byId($infoCmdId);
        if (!is_object($cmd)) throw new Exception(__('Commande introuvable', __FILE__));
        $volume = $cmd->execCmd();
        ajax::success(['volume' => $volume]);
    }

    if (init('action') == 'analyze_lufs') {
        $mediaId = init('mediaId');
        $mode = init('mode', 'quick');
        $force = init('force', 0);
        if (empty($mediaId)) throw new Exception(__('ID média requis', __FILE__));
        $result = jellyfin::analyzeLufs($mediaId, $force ? 'force' : $mode);
        if (isset($result['error'])) throw new Exception($result['error']);
        ajax::success($result);
    }

    if (init('action') == 'analyze_session_audio') {
        $eqLogic = jellyfin::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
            throw new Exception(__('Séance introuvable', __FILE__));
        }
        $mode = init('mode', 'quick');
        set_time_limit(0);
        $sessionData = $eqLogic->getConfiguration('session_data');
        $sessionType = $eqLogic->getConfiguration('session_type');
        $playerId = $sessionData['player_id'] ?? null;
        $playerEq = jellyfin::byId($playerId);
        if (!is_object($playerEq)) throw new Exception(__('Lecteur introuvable', __FILE__));

        // Collecter les triggers média
        $mediaList = [];
        if ($sessionType == 'cinema') {
            foreach (jellyfin::SECTION_ORDER as $secKey) {
                $sec = $sessionData['sections'][$secKey] ?? [];
                if (isset($sec['enabled']) && $sec['enabled'] === false) continue;
                foreach ($sec['triggers'] ?? [] as $idx => $trigger) {
                    if ($trigger['type'] == 'media' && (!isset($trigger['enabled']) || $trigger['enabled'] !== false)) {
                        $mediaList[] = ['section' => $secKey, 'index' => $idx, 'media_id' => $trigger['media_id'], 'name' => $trigger['name'] ?? $trigger['media_id']];
                    }
                }
            }
        } else {
            foreach ($sessionData['playlist'] ?? [] as $idx => $trigger) {
                if ($trigger['type'] == 'media' && (!isset($trigger['enabled']) || $trigger['enabled'] !== false)) {
                    $mediaList[] = ['section' => 'commercial', 'index' => $idx, 'media_id' => $trigger['media_id'], 'name' => $trigger['name'] ?? $trigger['media_id']];
                }
            }
        }

        $progressKey = 'jellyfin::audio_analysis::' . $eqLogic->getId();
        cache::set($progressKey, json_encode([
            'status' => 'analyzing', 'current_clip' => '', 'current_index' => 0,
            'total_clips' => count($mediaList), 'results' => [], 'errors' => []
        ]));

        $results = [];
        $errors = [];
        foreach ($mediaList as $i => $item) {
            cache::set($progressKey, json_encode([
                'status' => 'analyzing', 'current_clip' => $item['name'],
                'current_index' => $i + 1, 'total_clips' => count($mediaList),
                'results' => $results, 'errors' => $errors
            ]));

            $startTime = microtime(true);
            $lufsResult = jellyfin::analyzeLufs($item['media_id'], $mode);
            $elapsed = microtime(true) - $startTime;
            if ($elapsed < 1.0) usleep((int)((1.0 - $elapsed) * 1000000));

            if (isset($lufsResult['error'])) {
                $errors[] = ['media_id' => $item['media_id'], 'name' => $item['name'], 'error' => $lufsResult['error']];
            } else {
                $lufs = $lufsResult['lufs'];
                $volumeAuto = jellyfin::calculateAutoVolume($playerEq, $lufs, $item['section']);
                $results[] = ['media_id' => $item['media_id'], 'lufs' => $lufs, 'volume_auto' => $volumeAuto];

                if ($sessionType == 'cinema') {
                    $sessionData['sections'][$item['section']]['triggers'][$item['index']]['lufs'] = $lufs;
                    $sessionData['sections'][$item['section']]['triggers'][$item['index']]['volume_auto'] = $volumeAuto;
                } else {
                    $sessionData['playlist'][$item['index']]['lufs'] = $lufs;
                    $sessionData['playlist'][$item['index']]['volume_auto'] = $volumeAuto;
                }
            }
        }

        $sessionData['audio_calibrated'] = (count($results) > 0);
        $eqLogic->setConfiguration('session_data', $sessionData);
        $eqLogic->save();

        cache::set($progressKey, json_encode([
            'status' => 'done', 'current_clip' => '', 'current_index' => count($mediaList),
            'total_clips' => count($mediaList), 'results' => $results, 'errors' => $errors
        ]));

        ajax::success(['analyzed' => count($results), 'errors' => count($errors)]);
    }

    if (init('action') == 'get_analysis_progress') {
        $sessionId = init('id');
        $progressKey = 'jellyfin::audio_analysis::' . $sessionId;
        $progress = cache::byKey($progressKey)->getValue(null);
        ajax::success($progress ? json_decode($progress, true) : ['status' => 'idle']);
    }

    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
    /* * *************************Catch***************************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>