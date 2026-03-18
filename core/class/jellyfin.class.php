<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class jellyfin extends eqLogic {

    /* ************************* Constantes Séances ******************* */
    const SECTION_ORDER = ['preparation', 'intro', 'pubs', 'trailers', 'short_film', 'audio_trailer', 'film'];

    const SECTION_LABELS = [
        'preparation'   => 'Préparation',
        'intro'         => 'Intro',
        'pubs'          => 'Publicités',
        'trailers'      => 'Bandes annonces',
        'short_film'    => 'Court métrage',
        'audio_trailer' => 'Trailer audio',
        'film'          => 'Film'
    ];

    const SECTION_COLORS = [
        'preparation'   => '#f39c12',
        'intro'         => '#9b59b6',
        'pubs'          => '#e74c3c',
        'trailers'      => '#e67e22',
        'short_film'    => '#2ecc71',
        'audio_trailer' => '#3498db',
        'film'          => '#1DB954'
    ];

    const MARK_ORDER = ['pre_generique', 'generique_1', 'post_film_1', 'generique_2', 'post_film_2', 'fin'];

    const MARK_LABELS = [
        'pre_generique' => 'Pré-générique',
        'generique_1'   => 'Générique 1',
        'post_film_1'   => 'Post film 1',
        'generique_2'   => 'Générique 2',
        'post_film_2'   => 'Post film 2',
        'fin'           => 'Fin'
    ];

    const TRIGGER_TYPES = ['media', 'pause', 'command', 'scenario'];

    /* ************************* Helpers ******************* */
    public static function isFfmpegAvailable() {
        exec('which ffmpeg 2>/dev/null', $output, $returnVar);
        return ($returnVar == 0);
    }

    public static function getBaseConfig() {
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        if (empty($ip) || empty($port) || empty($apikey)) return null;
        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        return ['baseUrl' => $baseUrl, 'apikey' => $apikey, 'ip' => $ip, 'port' => $port];
    }

    /* ************************* Gestion des Dépendances ******************* */
    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'jellyfin_dep';
        $return['progress_file'] = jeedom::getTmpFolder('jellyfin') . '/dependancy';
        $return['state'] = 'nok';
        $cmd = 'python3 -c "import requests; print(1)"';
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) $return['state'] = 'ok';
        return $return;
    }

    public static function dependancy_install() {
        log::remove('jellyfin_dep');
        log::add('jellyfin_dep', 'info', 'Lancement de l\'installation des dépendances...');
        $script_path = realpath(__DIR__ . '/../../resources/install_apt.sh');
        $log_path = log::getPathToLog('jellyfin_dep');
        $cmd = 'sudo /bin/bash ' . $script_path . ' >> ' . $log_path . ' 2>&1 &';
        exec($cmd);
        return true;
    }

    /* ************************* Gestion du Démon ******************* */
    public static function deamon_info() {
        $return = array();
        $return['log'] = 'jellyfin_daemon';
        $return['launchable'] = 'ok';
        $return['state'] = 'nok';
        $dep = self::dependancy_info();
        if ($dep['state'] != 'ok') {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Les dépendances ne sont pas installées', __FILE__);
        }
        $pid_file = jeedom::getTmpFolder('jellyfin') . '/jellyfin.pid';
        if (file_exists($pid_file)) {
            if (@posix_kill(trim(file_get_contents($pid_file)), 0)) $return['state'] = 'ok';
            else unlink($pid_file);
        }
        return $return;
    }

    public static function deamon_start() {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') throw new Exception(__('Veuillez vérifier la configuration ou les dépendances', __FILE__));

        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');

        if (empty($ip) || empty($port) || empty($apikey)) {
            log::add('jellyfin', 'error', 'Configuration incomplète.');
            return;
        }

        $jellyfin_full_url = (strpos($ip, 'http') === false) ? 'http://' . $ip . ':' . $port : $ip . ':' . $port;
        $path = realpath(__DIR__ . '/../../resources/daemon');
        $script = $path . '/jellyfind.py';
        if (!file_exists($script)) throw new Exception(__('Script Python introuvable', __FILE__));

        $jeedomLogLevel = log::getLogLevel('jellyfin_daemon');
        $pythonLogLevel = 'ERROR';
        if ($jeedomLogLevel <= 100 || $jeedomLogLevel === 'debug') $pythonLogLevel = 'DEBUG';
        elseif ($jeedomLogLevel <= 200 || $jeedomLogLevel === 'info') $pythonLogLevel = 'INFO';
        elseif ($jeedomLogLevel <= 300 || $jeedomLogLevel === 'warning') $pythonLogLevel = 'WARNING';

        $callback = network::getNetworkAccess('internal') . '/plugins/jellyfin/core/php/jeeJellyfin.php';
        
        $cmd = 'python3 ' . escapeshellarg($script);
        $cmd .= ' --loglevel ' . escapeshellarg($pythonLogLevel);
        $cmd .= ' --callback ' . escapeshellarg($callback);
        $cmd .= ' --apikey ' . escapeshellarg(jeedom::getApiKey('jellyfin'));
        $cmd .= ' --pid ' . escapeshellarg(jeedom::getTmpFolder('jellyfin') . '/jellyfin.pid');
        $cmd .= ' --jellyfin_url ' . escapeshellarg($jellyfin_full_url);
        $cmd .= ' --jellyfin_token ' . escapeshellarg($apikey);
        $cmd .= ' --socket ' . escapeshellarg(jeedom::getTmpFolder('jellyfin') . '/jellyfin.sock');

        log::add('jellyfin', 'info', 'Lancement du démon: ' . $cmd);
        exec($cmd . ' >> ' . log::getPathToLog('jellyfin_daemon') . ' 2>&1 &');
    }

    public static function deamon_stop() {
        $pid_file = jeedom::getTmpFolder('jellyfin') . '/jellyfin.pid';
        if (file_exists($pid_file)) {
            $pid = trim(file_get_contents($pid_file));
            if ($pid != '' && @posix_kill($pid, 0)) {
                posix_kill($pid, 15); // SIGTERM : arrêt propre
                // On attend jusqu'à 2 secondes que le process se termine
                for ($i = 0; $i < 20; $i++) {
                    usleep(100000); // 100ms
                    if (!@posix_kill($pid, 0)) break; // Process terminé
                }
                // Si toujours vivant, on force
                if (@posix_kill($pid, 0)) {
                    posix_kill($pid, 9); // SIGKILL
                }
            }
            unlink($pid_file);
        }
    }

    /* ************************* Traitement des Données ******************* */
    public static function processSessions($sessions) {
        if (!is_array($sessions)) return;

        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        $baseUrl = (!empty($ip) && !empty($port)) ? ((strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port) : '';

        $activeDevices = array();

        foreach ($sessions as $sessionData) {
            $deviceId = '';
            if (isset($sessionData['device_id'])) $deviceId = $sessionData['device_id'];
            elseif (isset($sessionData['DeviceId'])) $deviceId = $sessionData['DeviceId'];
            
            $clientName = isset($sessionData['client']) ? $sessionData['client'] : (isset($sessionData['Client']) ? $sessionData['Client'] : 'Jellyfin Device');
            
            if (empty($deviceId)) continue;

            $activeDevices[] = (string)$deviceId;

            $isControllable = false;
            if (isset($sessionData['SupportsRemoteControl']) && $sessionData['SupportsRemoteControl'] === true) $isControllable = true;
            if (isset($sessionData['supports_remote_control']) && $sessionData['supports_remote_control'] === true) $isControllable = true;

            $logicalId = (strlen($deviceId) > 120) ? md5($deviceId) : $deviceId;
            $eqLogic = self::byLogicalId($logicalId, 'jellyfin');
            
            if (!$isControllable) {
                if (!is_object($eqLogic)) {
                    continue; 
                }
            }

            if (!is_object($eqLogic)) {
                $eqLogic = new jellyfin();
                $eqLogic->setName($clientName . ' - Jellyfin');
                $eqLogic->setLogicalId($logicalId);
                $eqLogic->setEqType_name('jellyfin');
                $eqLogic->setConfiguration('device_id', $deviceId);
                $eqLogic->setConfiguration('widget_border_enable', 0);
                $eqLogic->setConfiguration('widget_border_color', '#e5e5e5');
                $eqLogic->setIsEnable(1);
                $eqLogic->setIsVisible(1);
                $eqLogic->save();
            }

            $hasMedia = false;
            $npItem = isset($sessionData['NowPlayingItem']) ? $sessionData['NowPlayingItem'] : array();
            if (!empty($npItem)) $hasMedia = true;
            elseif (isset($sessionData['item_id']) && !empty($sessionData['item_id'])) $hasMedia = true;

            $statusStr = isset($sessionData['status']) ? $sessionData['status'] : 'Stopped';

            if (!$hasMedia) {
                $statusStr = 'Stopped';
                $sessionData['title'] = '';
                $eqLogic->checkAndUpdateCmd('media_type', '');
            } else {
                $mediaPath = isset($npItem['Path']) ? $npItem['Path'] : '';
                if (empty($mediaPath) && isset($sessionData['full_now_playing_item']['Path'])) $mediaPath = $sessionData['full_now_playing_item']['Path'];
                $detectedType = self::determineMediaType($mediaPath);
                $eqLogic->checkAndUpdateCmd('media_type', $detectedType);
            }

            $eqLogic->checkAndUpdateCmd('title', isset($sessionData['title']) ? $sessionData['title'] : '');
            $eqLogic->checkAndUpdateCmd('status', $statusStr);

            $runTimeTicks = isset($sessionData['run_time_ticks']) ? (int)$sessionData['run_time_ticks'] : 0;
            $positionTicks = isset($sessionData['position_ticks']) ? (int)$sessionData['position_ticks'] : 0;

            if ($statusStr == 'Stopped') {
                // MODIFICATION ICI : On envoie --:-- au lieu de 00:00:00
                $eqLogic->checkAndUpdateCmd('duration', '--:--');
                $eqLogic->checkAndUpdateCmd('position', '--:--');
                $eqLogic->checkAndUpdateCmd('remaining', '--:--');
                
                $eqLogic->checkAndUpdateCmd('duration_num', '000000');
                $eqLogic->checkAndUpdateCmd('position_num', '000000');
                $eqLogic->checkAndUpdateCmd('remaining_num', '000000');

                if ($eqLogic->getConfiguration('last_image_id') !== 'STOPPED') {
                    $eqLogic->checkAndUpdateCmd('cover', '');
                    $eqLogic->setConfiguration('last_image_id', 'STOPPED');
                    $eqLogic->save();
                }
            } else {
                $totalSeconds = floor($runTimeTicks / 10000000);
                $currentSeconds = floor($positionTicks / 10000000);
                $remainingSeconds = $totalSeconds - $currentSeconds;
                if ($remainingSeconds < 0) $remainingSeconds = 0;

                $eqLogic->checkAndUpdateCmd('duration', gmdate("H:i:s", $totalSeconds));
                $eqLogic->checkAndUpdateCmd('position', gmdate("H:i:s", $currentSeconds));
                $eqLogic->checkAndUpdateCmd('remaining', gmdate("H:i:s", $remainingSeconds));
                
                $eqLogic->checkAndUpdateCmd('duration_num', gmdate("His", $totalSeconds));
                $eqLogic->checkAndUpdateCmd('position_num', gmdate("His", $currentSeconds));
                $eqLogic->checkAndUpdateCmd('remaining_num', gmdate("His", $remainingSeconds));

                // Gestion Cover
                $itemType = isset($npItem['Type']) ? $npItem['Type'] : 'Unknown';
                $imageItemId = '';
                if (isset($npItem['SeriesId'])) $imageItemId = $npItem['SeriesId'];
                elseif (isset($npItem['AlbumId'])) $imageItemId = $npItem['AlbumId'];
                elseif (isset($npItem['PrimaryImageItemId'])) $imageItemId = $npItem['PrimaryImageItemId'];
                elseif (isset($npItem['Id'])) $imageItemId = $npItem['Id'];
                elseif (isset($sessionData['item_id'])) $imageItemId = $sessionData['item_id'];

                if ($itemType == 'Audio' && !isset($npItem['AlbumId']) && !isset($npItem['PrimaryImageItemId'])) $imageItemId = '';

                $storedImageId = $eqLogic->getConfiguration('last_image_id', '');
                if (!empty($baseUrl) && !empty($imageItemId) && $imageItemId !== $storedImageId) {
                    $imgUrl = $baseUrl . '/Items/' . $imageItemId . '/Images/Primary?fillWidth=400&quality=90&api_key=' . $apikey;
                    $imgData = self::requestApi($imgUrl, 'GET', null, true);
                    if ($imgData && strlen($imgData) > 500) {
                        $headerHex = bin2hex(substr($imgData, 0, 2));
                        $mimeType = ($headerHex == '8950') ? 'image/png' : 'image/jpeg';
                        
                        $htmlImg = '<img class="img-responsive" data-media-id="' . $imageItemId . '" style="border-radius: 10px;" src="data:' . $mimeType . ';base64,' . base64_encode($imgData) . '">';
                        
                        $eqLogic->checkAndUpdateCmd('cover', $htmlImg);
                        $eqLogic->setConfiguration('last_image_id', $imageItemId);
                        $eqLogic->save();
                    }
                }
            }
            $cmdToggle = $eqLogic->getCmd(null, 'play_pause');
            if (is_object($cmdToggle)) {
                $iconPlay = '<i class="fas fa-play-circle"></i>';
                $iconPause = '<i class="fas fa-pause-circle"></i>';
                $newIcon = ($statusStr == 'Playing') ? $iconPause : $iconPlay;
                if ($cmdToggle->getDisplay('icon') != $newIcon) {
                    $cmdToggle->setDisplay('icon', $newIcon);
                    $cmdToggle->save();
                }
            }
        }

        // --- NETTOYAGE DES SESSIONS ABSENTES ---
        $allJellyfins = self::byType('jellyfin');
        foreach ($allJellyfins as $jellyfinEq) {
            // Skip les séances (pas des appareils)
            if ($jellyfinEq->getConfiguration('session_type') != '') continue;

            if ($jellyfinEq->getIsEnable() == 1) {
                $confDevId = (string)$jellyfinEq->getConfiguration('device_id');

                if (!in_array($confDevId, $activeDevices)) {
                    $statusCmd = $jellyfinEq->getCmd(null, 'status');
                    if (!is_object($statusCmd)) continue;
                    $currentStatus = $statusCmd->execCmd();
                    if ($currentStatus != 'Stopped') {
                        $jellyfinEq->checkAndUpdateCmd('status', 'Stopped');
                        $jellyfinEq->checkAndUpdateCmd('title', '');
                        $jellyfinEq->checkAndUpdateCmd('media_type', '');
                        
                        // MODIFICATION ICI : On envoie --:-- aussi
                        $jellyfinEq->checkAndUpdateCmd('duration', '--:--');
                        $jellyfinEq->checkAndUpdateCmd('position', '--:--');
                        $jellyfinEq->checkAndUpdateCmd('remaining', '--:--');
                        
                        $jellyfinEq->checkAndUpdateCmd('cover', '');
                        $jellyfinEq->setConfiguration('last_image_id', 'STOPPED');
                        $jellyfinEq->save();
                        
                        $cmdToggle = $jellyfinEq->getCmd(null, 'play_pause');
                        if (is_object($cmdToggle)) {
                            $iconPlay = '<i class="fas fa-play-circle"></i>';
                            $cmdToggle->setDisplay('icon', $iconPlay);
                            $cmdToggle->save();
                        }
                    }
                }
            }
        }
    }

    private static function determineMediaType($path) {
        if (empty($path)) return 'Autre'; 
        $path = strtolower($path);
        $checkOrder = ['filter_ad' => 'Publicité', 'filter_sound_trailer' => 'Sound Trailer', 'filter_trailer' => 'Bande Annonce', 'filter_movie' => 'Film', 'filter_series' => 'Série', 'filter_audio' => 'Audio'];
        foreach ($checkOrder as $configKey => $typeLabel) {
            $keywords = config::byKey($configKey, 'jellyfin');
            if (empty($keywords)) continue;
            $keywordArray = explode(',', $keywords);
            foreach ($keywordArray as $word) {
                if (!empty(trim(strtolower($word))) && strpos($path, trim(strtolower($word))) !== false) return $typeLabel;
            }
        }
        return 'Autre';
    }

    /* ************************* Commandes *******************************/
    public function postSave() {
        if ($this->getConfiguration('session_type') != '') {
            $this->createSessionCommands();
        } else {
            $this->createCommands();
        }
    }

    public function createCommands() {
        $commands = [
            'Prev' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-step-backward"></i>', 'order' => 1],
            'Play' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-play"></i>', 'order' => 2],
            'Pause' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-pause"></i>', 'order' => 3],
            'Play_Pause' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-play-circle"></i>', 'order' => 4, 'name' => __('Toggle Play/Pause', __FILE__)],
            'Next' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-step-forward"></i>', 'order' => 5],
            'Stop' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-stop"></i>', 'order' => 6],
            'Title' => ['type' => 'info', 'subtype' => 'string', 'order' => 7],
            'Status' => ['type' => 'info', 'subtype' => 'string', 'order' => 8],
            'Duration' => ['type' => 'info', 'subtype' => 'string', 'order' => 9],
            'Position' => ['type' => 'info', 'subtype' => 'string', 'order' => 10],
            'Remaining' => ['type' => 'info', 'subtype' => 'string', 'order' => 11],
            'Cover' => ['type' => 'info', 'subtype' => 'string', 'order' => 12],
            'Duration_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 13, 'name' => __('Duration (Scenario)', __FILE__)],
            'Position_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 14, 'name' => __('Position (Scenario)', __FILE__)],
            'Remaining_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 15, 'name' => __('Remaining (Scenario)', __FILE__)],
            'Media_Type' => ['type' => 'info', 'subtype' => 'string', 'order' => 16, 'name' => __('Media Type', __FILE__)],
            'Set_Position' => ['type' => 'action', 'subtype' => 'slider', 'order' => 99, 'name' => __('Set Position', __FILE__), 'isVisible' => 0],
            'Audio_Profile' => ['type' => 'info', 'subtype' => 'string', 'order' => 17, 'name' => __('Profil audio', __FILE__), 'isVisible' => 0],
            'Set_Audio_Profile' => ['type' => 'action', 'subtype' => 'select', 'order' => 18, 'name' => __('Changer profil audio', __FILE__), 'isVisible' => 0],
        ];
        foreach ($commands as $name => $options) {
            $logicalId = strtolower($name);
            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new jellyfinCmd();
                $cmd->setName(isset($options['name']) ? $options['name'] : $name);
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId($logicalId);
                $cmd->setType($options['type']);
                $cmd->setSubType($options['subtype']);
                if (isset($options['order'])) $cmd->setOrder($options['order']);
                if (isset($options['icon'])) $cmd->setDisplay('icon', $options['icon']);
                if (isset($options['isVisible'])) $cmd->setIsVisible($options['isVisible']);
                $cmd->save();
            }
        }
        // Config du select profil audio + init valeur par défaut
        $setProfile = $this->getCmd('action', 'set_audio_profile');
        if (is_object($setProfile)) {
            $profileInfo = $this->getCmd('info', 'audio_profile');
            if (is_object($profileInfo)) {
                $setProfile->setValue($profileInfo->getId());
            }
            $setProfile->setConfiguration('listValue', 'night|Nuit;cinema|Cinéma;thx|THX');
            $setProfile->save();
            if (is_object($profileInfo) && $profileInfo->execCmd() == '') {
                $this->checkAndUpdateCmd('audio_profile', 'cinema');
            }
        }
    }

    public function createSessionCommands() {
        $commands = [
            'Start'           => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-play"></i>', 'order' => 1],
            'Stop'            => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-stop"></i>', 'order' => 2],
            'Pause'           => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-pause"></i>', 'order' => 3],
            'Resume'          => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-redo"></i>', 'order' => 4],
            'State'           => ['type' => 'info', 'subtype' => 'string', 'order' => 5],
            'Current_Section' => ['type' => 'info', 'subtype' => 'string', 'order' => 6, 'name' => __('Section en cours', __FILE__)],
            'Progress'        => ['type' => 'info', 'subtype' => 'numeric', 'order' => 7],
        ];
        foreach ($commands as $name => $options) {
            $logicalId = strtolower($name);
            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new jellyfinCmd();
                $cmd->setName(isset($options['name']) ? $options['name'] : $name);
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId($logicalId);
                $cmd->setType($options['type']);
                $cmd->setSubType($options['subtype']);
                if (isset($options['order'])) $cmd->setOrder($options['order']);
                if (isset($options['icon'])) $cmd->setDisplay('icon', $options['icon']);
                $cmd->save();
            }
        }
        $stateCmd = $this->getCmd('info', 'state');
        if (is_object($stateCmd) && $stateCmd->execCmd() == '') {
            $this->checkAndUpdateCmd('state', 'stopped');
        }
    }

public function remoteControl($commandName, $_options = null) {
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        $deviceId = $this->getConfiguration('device_id');
        if (empty($ip) || empty($deviceId)) return;
        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        
        // On récupère les infos de la session pour savoir où on en est (Position)
        $sessionData = self::getSessionDataFromDeviceId($baseUrl, $apikey, $deviceId);
        if (!$sessionData || !isset($sessionData['Id'])) return;
        $sessionId = $sessionData['Id'];
        
        // --- GESTION DU SLIDER DE POSITION ---
        if ($commandName == 'set_position') {
            $seconds = isset($_options['slider']) ? $_options['slider'] : (isset($_options['value']) ? $_options['value'] : null);
            if ($seconds !== null) {
                $ticks = $seconds * 10000000;
                $url = $baseUrl . '/Sessions/' . $sessionId . '/Playing/Seek?seekPositionTicks=' . $ticks . '&api_key=' . $apikey;
                self::requestApi($url, 'POST');
            }
            return;
        }

        // --- GESTION DES RACCOURCIS MEDIA ---
        $cmd = $this->getCmd(null, $commandName);
        if (is_object($cmd)) {
            $mediaId = $cmd->getConfiguration('media_id');
            if (!empty($mediaId)) {
                $this->playMedia($mediaId, 'play_now');
                return;
            }
        }

        // --- GESTION DES BOUTONS CLASSIQUES ---
        $action = '';
        
        switch ($commandName) {
            case 'play': $action = 'Unpause'; break;
            case 'pause': $action = 'Pause'; break;
            case 'play_pause': $action = 'PlayPause'; break;
            case 'stop': $action = 'Stop'; break;
            
            case 'next': 
                $action = 'NextTrack'; 
                break;
                
            case 'prev': 
                // LOGIQUE INTELLIGENTE POUR PREV
                // Si on a lu plus de 10 secondes (10 * 10 000 000 ticks) -> On rembobine au début
                $currentTicks = 0;
                if (isset($sessionData['PlayState']['PositionTicks'])) {
                    $currentTicks = $sessionData['PlayState']['PositionTicks'];
                }
                
                if ($currentTicks > 30000000) {
                    // Seek au début (0)
                    $url = $baseUrl . '/Sessions/' . $sessionId . '/Playing/Seek?seekPositionTicks=0&api_key=' . $apikey;
                    self::requestApi($url, 'POST');
                    return; // On arrête là, pas besoin d'envoyer PreviousTrack
                } else {
                    // Sinon (on est au tout début), on demande la piste précédente
                    $action = 'PreviousTrack';
                }
                break;
        }

        if ($action != '') {
            $url = $baseUrl . '/Sessions/' . $sessionId . '/Playing/' . $action . '?api_key=' . $apikey;
            self::requestApi($url, 'POST');
        }
    }

    public static function getSessionDataFromDeviceId($baseUrl, $apiKey, $deviceId) {
        $url = $baseUrl . '/Sessions?api_key=' . $apiKey;
        $sessions = self::requestApi($url);
        if (is_array($sessions)) {
            foreach ($sessions as $session) {
                if (isset($session['DeviceId']) && $session['DeviceId'] == $deviceId) return $session;
            }
        }
        return null;
    }

    public static function requestApi($url, $method = 'GET', $data = null, $binary = false, $timeout = 5) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $headers = [];
        if (!$binary) $headers[] = 'Accept: application/json';
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $headers[] = 'Content-Type: application/json';
            } else {
                $headers[] = 'Content-Length: 0';
            }
        }
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 400) return null;
        if ($binary) return $result;
        return json_decode($result, true);
    }
    
    /* ************************* Affichage ******************************* */
    public function toHtml($_version = 'dashboard') {
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) return $replace;
        
        $version = jeedom::versionAlias($_version);
        if ($this->getDisplay('hideOn' . $version) == 1) return '';
        
        if ($this->getConfiguration('widget_border_enable', 0) == 1) $replace['#widget_border_color#'] = $this->getConfiguration('widget_border_color', '#e5e5e5');
        else $replace['#widget_border_color#'] = 'transparent';

        $shortcutsHtml = '';

        foreach ($this->getCmd('info') as $cmd) {
            $replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
            $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
        }
        
        foreach ($this->getCmd('action') as $cmd) {
            if ($cmd->getConfiguration('is_media_shortcut') == 1) {
                $mediaId = $cmd->getConfiguration('media_id');
                $imgTag = $cmd->getConfiguration('image_tag');
                $name = str_replace('Lancer : ', '', $cmd->getName());
                $name = str_replace('Play : ', '', $name);
                
                $safeName = str_replace('"', '&quot;', $name);
                $imgUrl = self::getItemImageUrl($mediaId, $imgTag);
                
                $shortcutsHtml .= '<div class="shortcut-item cursor" onclick="jeedom.cmd.execute({id: \'' . $cmd->getId() . '\'});" title="' . $safeName . '" data-medianame="' . $safeName . '">';
                $shortcutsHtml .= '  <i class="fas fa-times-circle delete-shortcut-btn" data-cmd_id="' . $cmd->getId() . '" title=""></i>';
                $shortcutsHtml .= '  <img src="' . $imgUrl . '">'; 
                $shortcutsHtml .= '</div>';
            } else {
                $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
            }
        }
        
        $replace['#shortcuts_html#'] = $shortcutsHtml;

        $jsPath = __DIR__ . '/../../desktop/js/jellyfin.js';
        $injectedScript = "";
        if (file_exists($jsPath)) {
            $injectedScript = "<script>" . file_get_contents($jsPath) . "</script>";
        }

        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'jellyfin', 'jellyfin'))) . $injectedScript;
    }

    /* ************************* Gestion Explorateur (Library) ******************* */
    public static function getPrimaryUserId() {
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        if (empty($ip) || empty($apikey)) return null;
        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        $url = $baseUrl . '/Users?api_key=' . $apikey;
        $users = self::requestApi($url);
        if (is_array($users) && count($users) > 0) return $users[0]['Id'];
        return null;
    }

    public static function getLibraryItems($parentId = '', $searchTerm = '') {
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        if (empty($ip) || empty($apikey)) return ['error' => __('Configuration incomplète', __FILE__)];
        $userId = self::getPrimaryUserId();
        if (!$userId) return ['error' => __('Aucun utilisateur Jellyfin trouvé', __FILE__)];
        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        
        $url = $baseUrl . '/Users/' . $userId . '/Items?api_key=' . $apikey;
        
        if (!empty($searchTerm)) {
            $url .= '&SearchTerm=' . urlencode($searchTerm);
            $url .= '&Recursive=true';
            $url .= '&IncludeItemTypes=Movie,Series,MusicAlbum,Audio,BoxSet';
        } else {
            if (!empty($parentId)) $url .= '&ParentId=' . $parentId;
            $url .= '&SortBy=IsFolder,SortName&SortOrder=Descending,Ascending';
        }
        
        $url .= '&Fields=Overview,ProductionYear,CommunityRating,PremiereDate,RunTimeTicks,MediaSources,MediaStreams';
        
        return self::requestApi($url);
    }

    public static function getItemImageUrl($itemId, $tag = null) {
        $url = "plugins/jellyfin/core/php/proxy.php?itemId=" . $itemId . "&maxWidth=400";
        if ($tag) {
            $url .= "&tag=" . $tag;
        }
        return $url; 
    }
    
    // --- GESTION DE LA LECTURE ---
    public function playMedia($mediaId, $mode = 'play_now') {
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        $deviceId = $this->getConfiguration('device_id');
        
        if (empty($ip) || empty($deviceId)) return ['error' => __('Configuration invalide', __FILE__)];

        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        $sessionData = self::getSessionDataFromDeviceId($baseUrl, $apikey, $deviceId);

        if (!$sessionData || !isset($sessionData['Id'])) {
            return ['error' => __('Session inactive (lecteur éteint ?)', __FILE__)];
        }
        
        $sessionId = $sessionData['Id'];
        
        log::add('jellyfin', 'debug', 'PlayMedia demandé. Mode: ' . $mode . ' - MediaId: ' . $mediaId);

        if ($mode == 'play_now' && isset($sessionData['NowPlayingItem'])) {
            log::add('jellyfin', 'debug', 'Lecture en cours détectée. Envoi du STOP forcé (Fix Android TV).');
            $urlStop = $baseUrl . '/Sessions/' . $sessionId . '/Playing/Stop?api_key=' . $apikey;
            self::requestApi($urlStop, 'POST');
            usleep(300000); 
        }

        if ($mode == 'queue_next') {
            $url = $baseUrl . '/Sessions/' . $sessionId . '/Queue?ItemIds=' . $mediaId . '&Mode=PlayNext&api_key=' . $apikey;
        } else {
            $playCommand = 'PlayNow';
            // MODIFICATION ICI : Ajout de &StartPositionTicks=0
            $url = $baseUrl . '/Sessions/' . $sessionId . '/Playing?ItemIds=' . $mediaId . '&PlayCommand=' . $playCommand . '&StartPositionTicks=0&api_key=' . $apikey;
        }
        
        log::add('jellyfin', 'debug', 'Appel URL: ' . $url);
        
        self::requestApi($url, 'POST');
        return ['state' => 'ok'];
    }

    /* ************************* Gestion Séances ******************* */
    public function startSession() {
        $sessionData = $this->getConfiguration('session_data');
        $sessionType = $this->getConfiguration('session_type');

        if (!is_array($sessionData)) return ['error' => __('Données de séance invalides', __FILE__)];

        $playerId = $sessionData['player_id'] ?? null;
        if (empty($playerId)) return ['error' => __('Aucun lecteur sélectionné', __FILE__)];

        $playerEq = self::byId($playerId);
        if (!is_object($playerEq)) return ['error' => __('Lecteur introuvable', __FILE__)];

        $config = self::getBaseConfig();
        if (!$config) return ['error' => __('Configuration Jellyfin incomplète', __FILE__)];

        // Vérifier lecteur en ligne
        $deviceId = $playerEq->getConfiguration('device_id');
        $playerSession = self::getSessionDataFromDeviceId($config['baseUrl'], $config['apikey'], $deviceId);
        if (!$playerSession) return ['error' => __('Lecteur hors ligne', __FILE__)];

        // Validation médias
        $missing = $this->validateSessionMedia($config);
        if (!empty($missing)) return ['error' => __('Médias introuvables: ', __FILE__) . implode(', ', $missing)];

        // Arrêter séance existante sur ce lecteur + forcer nettoyage cache
        $cacheKey = 'jellyfin::active_session::' . $playerId;
        $existingState = cache::byKey($cacheKey)->getValue(null);
        if ($existingState !== null) {
            $existingData = json_decode($existingState, true);
            if (isset($existingData['session_eqlogic_id'])) {
                $existingSession = self::byId($existingData['session_eqlogic_id']);
                if (is_object($existingSession)) $existingSession->stopSession();
            }
            cache::set($cacheKey, null); // Force nettoyage
        }

        // Stopper le lecteur pour partir d'un état propre
        if ($playerSession && isset($playerSession['Id'])) {
            $stopUrl = $config['baseUrl'] . '/Sessions/' . $playerSession['Id'] . '/Playing/Stop?api_key=' . $config['apikey'];
            self::requestApi($stopUrl, 'POST');
            usleep(500000); // 500ms pour laisser le lecteur se stabiliser
        }

        // Initialiser état moteur
        $firstSection = '';
        if ($sessionType == 'cinema') {
            foreach (self::SECTION_ORDER as $key) {
                $sec = $sessionData['sections'][$key] ?? [];
                if (isset($sec['enabled']) && $sec['enabled'] === false) continue;
                if (!empty($sec['triggers'])) {
                    $firstSection = $key;
                    break;
                }
            }
            if (empty($firstSection)) return ['error' => __('Séance vide', __FILE__)];
        }

        $engineState = [
            'session_eqlogic_id' => $this->getId(),
            'player_eqlogic_id' => $playerId,
            'current_section'    => ($sessionType == 'cinema') ? $firstSection : 'playlist',
            'current_trigger_index' => 0,
            'current_media_id'   => '',
            'expected_next_media_id' => '',
            'queued'             => false,
            'current_lighting'   => ($sessionType == 'cinema') ? $firstSection : '',
            'started_at'         => time(),
            'last_status'        => 'Playing'
        ];

        $this->checkAndUpdateCmd('state', 'playing');
        if ($sessionType == 'cinema') {
            $this->checkAndUpdateCmd('current_section', self::SECTION_LABELS[$firstSection] ?? $firstSection);
            self::triggerLighting($this->getSessionLighting($firstSection));
        }
        $this->checkAndUpdateCmd('progress', 0);

        $cacheKey = 'jellyfin::active_session::' . $playerId;
        cache::set($cacheKey, json_encode($engineState));
        self::executeNonMediaTriggers($this, $playerEq, $sessionData, $engineState, $cacheKey);

        log::add('jellyfin', 'info', 'Séance démarrée: ' . $this->getName());
        return ['state' => 'ok'];
    }

    public function validateSessionMedia($config) {
        $sessionData = $this->getConfiguration('session_data');
        $sessionType = $this->getConfiguration('session_type');
        $missing = [];
        $mediaIds = [];

        if ($sessionType == 'cinema') {
            foreach ($sessionData['sections'] ?? [] as $section) {
                foreach ($section['triggers'] ?? [] as $trigger) {
                    if ($trigger['type'] == 'media') $mediaIds[] = $trigger;
                }
            }
        } else {
            $mediaIds = $sessionData['playlist'] ?? [];
        }

        $userId = self::getPrimaryUserId();
        foreach ($mediaIds as $media) {
            $url = $config['baseUrl'] . '/Users/' . $userId . '/Items/' . $media['media_id'] . '?api_key=' . $config['apikey'];
            $result = self::requestApi($url);
            if (!$result || !isset($result['Id'])) {
                $missing[] = $media['name'] ?? $media['media_id'];
            }
        }
        return $missing;
    }

    public function stopSession() {
        log::add('jellyfin', 'info', 'Séance arrêtée: ' . $this->getName());
        $this->checkAndUpdateCmd('state', 'stopped');
        $this->checkAndUpdateCmd('current_section', '');
        $this->checkAndUpdateCmd('progress', 0);
        $sessionData = $this->getConfiguration('session_data');
        $playerId = is_array($sessionData) ? ($sessionData['player_id'] ?? null) : null;
        if ($playerId) {
            cache::set('jellyfin::active_session::' . $playerId, null);
        }
        return ['state' => 'ok'];
    }

    public function pauseSession() {
        log::add('jellyfin', 'info', 'Séance en pause: ' . $this->getName());
        $this->checkAndUpdateCmd('state', 'paused');
        return ['state' => 'ok'];
    }

    public function resumeSession() {
        log::add('jellyfin', 'info', 'Séance reprise: ' . $this->getName());
        $this->checkAndUpdateCmd('state', 'playing');
        $sessionData = $this->getConfiguration('session_data');
        $playerId = is_array($sessionData) ? ($sessionData['player_id'] ?? null) : null;
        if ($playerId) {
            $cacheKey = 'jellyfin::active_session::' . $playerId;
            $engineState = json_decode(cache::byKey($cacheKey)->getValue('{}'), true);
            if (is_array($engineState)) {
                unset($engineState['waiting_resume']);
                unset($engineState['pause_until']);
                $engineState['current_trigger_index'] = ($engineState['current_trigger_index'] ?? 0) + 1;
                cache::set($cacheKey, json_encode($engineState));
            }
        }
        return ['state' => 'ok'];
    }

    public function preRemove() {
        if ($this->getConfiguration('session_type') != '') {
            $stateCmd = $this->getCmd('info', 'state');
            if (is_object($stateCmd) && $stateCmd->execCmd() != 'stopped') {
                $this->stopSession();
            }
            $crons = cron::searchClassAndFunction('jellyfin', 'executeSession', '"session_id":' . $this->getId());
            if (is_array($crons)) {
                foreach ($crons as $cron) {
                    $cron->remove();
                }
            }
        }
    }

    public static function executeSession($_options) {
        $sessionId = $_options['session_id'] ?? null;
        if (empty($sessionId)) return;
        $eqLogic = self::byId($sessionId);
        if (is_object($eqLogic) && $eqLogic->getConfiguration('session_type') != '') {
            $eqLogic->startSession();
        }
    }

    public static function getSessionsForPlayer($playerId) {
        $result = [];
        $allEq = self::byType('jellyfin');
        foreach ($allEq as $eq) {
            if ($eq->getConfiguration('session_type') == '') continue;
            $sessionData = $eq->getConfiguration('session_data');
            if (is_array($sessionData) && isset($sessionData['player_id']) && $sessionData['player_id'] == $playerId) {
                $result[] = $eq;
            }
        }
        return $result;
    }

    public function getSessionLighting($slot) {
        $sessionData = $this->getConfiguration('session_data');
        if (is_array($sessionData) && isset($sessionData['lighting'][$slot]) && !empty($sessionData['lighting'][$slot])) {
            return $sessionData['lighting'][$slot];
        }
        return config::byKey('lighting_' . $slot, 'jellyfin', null);
    }

    public static function triggerLighting($scenarioId) {
        if (empty($scenarioId)) return;
        $scenario = scenario::byId($scenarioId);
        if (is_object($scenario)) {
            $scenario->launch();
            log::add('jellyfin', 'info', 'Ambiance lumineuse déclenchée: scénario #' . $scenarioId);
        } else {
            log::add('jellyfin', 'warning', 'Scénario ambiance introuvable: #' . $scenarioId);
        }
    }

    /* ************************* Moteur d'exécution Séances ******************* */
    public static function tickSessionEngine($sessions) {
        if (!is_array($sessions)) return;

        $deviceIndex = [];
        foreach ($sessions as $s) {
            $devId = $s['device_id'] ?? ($s['DeviceId'] ?? '');
            if (!empty($devId)) $deviceIndex[$devId] = $s;
        }

        $allEq = self::byType('jellyfin');
        foreach ($allEq as $eq) {
            if ($eq->getConfiguration('session_type') != '') continue;
            $playerId = $eq->getId();
            $cacheKey = 'jellyfin::active_session::' . $playerId;
            $engineState = cache::byKey($cacheKey)->getValue(null);
            if ($engineState === null) continue;

            $engineState = json_decode($engineState, true);
            if (!is_array($engineState)) continue;

            $deviceId = $eq->getConfiguration('device_id');
            $playerData = $deviceIndex[$deviceId] ?? null;

            self::tickPlayer($eq, $playerData, $engineState, $cacheKey);
        }
    }

    private static function tickPlayer($playerEq, $playerData, $engineState, $cacheKey) {
        $sessionEq = self::byId($engineState['session_eqlogic_id']);
        if (!is_object($sessionEq)) {
            cache::set($cacheKey, null);
            return;
        }

        $sessionType = $sessionEq->getConfiguration('session_type');
        $sessionData = $sessionEq->getConfiguration('session_data');

        if ($playerData === null) {
            // Le lecteur n'apparaît plus dans les données du daemon.
            // Si on avait un média en cours, c'est probablement qu'il vient de finir
            // (le daemon ne remonte que les sessions avec NowPlayingItem).
            // On synthétise un status "Stopped" pour que le moteur enchaîne.
            $currentMediaId = $engineState['current_media_id'] ?? '';
            if (!empty($currentMediaId)) {
                $playerData = [
                    'status' => 'Stopped',
                    'item_id' => '',
                    'position_ticks' => 0,
                    'run_time_ticks' => 0
                ];
                log::add('jellyfin', 'debug', 'Lecteur absent du daemon mais média attendu — traitement comme Stopped');
            } else {
                self::handlePlayerLost($sessionEq, $engineState, $cacheKey);
                return;
            }
        }

        if (isset($engineState['player_lost_since'])) {
            unset($engineState['player_lost_since']);
            unset($engineState['player_lost']);
        }

        // Pause de séance (trigger pause) — ne rien faire tant qu'on attend
        if (isset($engineState['waiting_resume']) && $engineState['waiting_resume']) {
            cache::set($cacheKey, json_encode($engineState));
            return;
        }
        if (isset($engineState['pause_until'])) {
            if (time() < $engineState['pause_until']) {
                cache::set($cacheKey, json_encode($engineState));
                return;
            }
            unset($engineState['pause_until']);
            $engineState['current_trigger_index'] = ($engineState['current_trigger_index'] ?? 0) + 1;
            self::executeNonMediaTriggers($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);
            return;
        }

        $status = $playerData['status'] ?? 'Stopped';
        $itemId = $playerData['item_id'] ?? '';
        $positionTicks = (int)($playerData['position_ticks'] ?? 0);
        $runTimeTicks = (int)($playerData['run_time_ticks'] ?? 0);

        // Correction status daemon : il envoie "Playing" même quand rien ne joue
        // (session idle sans NowPlayingItem → IsPaused=false → "Playing")
        // Si item_id est vide et qu'on attendait un média, c'est en réalité "Stopped"
        $currentMediaId = $engineState['current_media_id'] ?? '';
        if (($status == 'Playing' || $status == 'Paused') && empty($itemId) && !empty($currentMediaId)) {
            $status = 'Stopped';
            log::add('jellyfin', 'debug', 'Status corrigé: Playing→Stopped (session idle, item_id vide)');
        }

        // Gestion pause télécommande
        $lastStatus = $engineState['last_status'] ?? 'Playing';
        if ($status == 'Paused' && $lastStatus == 'Playing') {
            self::triggerLighting($sessionEq->getSessionLighting('pause'));
        } elseif ($status == 'Playing' && $lastStatus == 'Paused') {
            $currentLighting = $engineState['current_lighting'] ?? '';
            if (!empty($currentLighting)) {
                self::triggerLighting($sessionEq->getSessionLighting($currentLighting));
            }
        }
        $engineState['last_status'] = $status;

        // Résoudre le sessionId Jellyfin une seule fois
        $config = self::getBaseConfig();
        $jellyfinSessionId = null;
        if ($config) {
            $deviceId = $playerEq->getConfiguration('device_id');
            $jellyfinSession = self::getSessionDataFromDeviceId($config['baseUrl'], $config['apikey'], $deviceId);
            $jellyfinSessionId = $jellyfinSession['Id'] ?? null;
        }

        if ($sessionType == 'cinema') {
            self::tickCinema($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $status, $itemId, $positionTicks, $runTimeTicks, $config, $jellyfinSessionId);
        } else {
            self::tickCommercial($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $status, $itemId, $positionTicks, $runTimeTicks, $config, $jellyfinSessionId);
        }
    }

    const MAX_LAUNCH_RETRIES = 1;
    const LAUNCH_TIMEOUT = 15;    // secondes pour qu'un média démarre
    const STUCK_TIMEOUT = 30;     // secondes sans changement de position = bloqué
    const QUEUE_GRACE_PERIOD = 2; // secondes d'attente pour l'auto-avancement Jellyfin après queue

    private static function tickCinema($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey, $status, $itemId, $positionTicks, $runTimeTicks, $config, $jellyfinSessionId) {
        if (!$config) return;

        $sections = $sessionData['sections'] ?? [];
        $currentSection = $engineState['current_section'] ?? '';
        $triggerIndex = $engineState['current_trigger_index'] ?? 0;
        $currentMediaId = $engineState['current_media_id'] ?? '';
        $now = time();

        // --- GARDE-FOU 1 : Vérif bounds ---
        $triggers = $sections[$currentSection]['triggers'] ?? [];
        if ($triggerIndex >= count($triggers) || !isset($triggers[$triggerIndex])) {
            log::add('jellyfin', 'debug', 'Index hors limites (section: ' . $currentSection . ', idx: ' . $triggerIndex . '). Avancement.');
            self::advanceToNextSection($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);
            return;
        }

        $currentTrigger = $triggers[$triggerIndex];
        if ($currentTrigger['type'] != 'media') {
            // Trigger non-média au tick — ne devrait pas arriver (traité par executeNonMediaTriggers)
            // Sécurité : on avance
            $engineState['current_trigger_index']++;
            self::executeNonMediaTriggers($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);
            return;
        }

        // === ÉTAT 3 (vérifié EN PREMIER) : MÉDIA TERMINÉ → ENCHAÎNER ===
        // Condition : status Stopped + currentMediaId set + PAS de lancement en attente
        $launchAt = $engineState['media_launch_at'] ?? 0;
        if ($status == 'Stopped' && !empty($currentMediaId) && $launchAt == 0) {
            // Si playlist active, laisser le temps au client d'auto-avancer
            if ($engineState['queued'] ?? false) {
                if (!isset($engineState['stopped_since'])) {
                    $engineState['stopped_since'] = $now;
                    cache::set($cacheKey, json_encode($engineState));
                    return;
                }
                if ($now - $engineState['stopped_since'] < 2) {
                    cache::set($cacheKey, json_encode($engineState));
                    return; // Attente auto-avancement client (max 2s)
                }
                log::add('jellyfin', 'debug', 'Playlist auto-avancement non détecté, fallback');
            }
            log::add('jellyfin', 'debug', 'Média terminé, enchaînement: ' . $currentMediaId);
            unset($engineState['stopped_since']);
            unset($engineState['stuck_since']);
            unset($engineState['last_position_ticks']);
            self::skipToNextTrigger($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $config);
            return;
        }

        // === ÉTAT 1 : ON A LANCÉ UN MÉDIA, IL N'A PAS ENCORE DÉMARRÉ ===
        // Condition : media_launch_at est SET (on a envoyé un PlayNow) + status pas Playing
        if (!empty($currentMediaId) && $launchAt > 0 && $status != 'Playing' && $status != 'Paused') {
            $retries = $engineState['launch_retries'] ?? 0;
            $elapsed = $now - $launchAt;
            log::add('jellyfin', 'debug', 'STATE1: attente lancement ' . $currentMediaId . ' (elapsed=' . $elapsed . 's, retries=' . $retries . ', status=' . $status . ')');

            if ($elapsed < self::LAUNCH_TIMEOUT) {
                cache::set($cacheKey, json_encode($engineState));
                return;
            }

            if ($retries < self::MAX_LAUNCH_RETRIES) {
                $engineState['launch_retries'] = $retries + 1;
                $engineState['media_launch_at'] = $now;
                log::add('jellyfin', 'warning', 'Média ne démarre pas, retry ' . ($retries + 1) . '/' . self::MAX_LAUNCH_RETRIES . ': ' . $currentMediaId);
                // PlayNow direct SANS STOP (le STOP tue le chargement en cours)
                $deviceId = $playerEq->getConfiguration('device_id');
                $jellySession = self::getSessionDataFromDeviceId($config['baseUrl'], $config['apikey'], $deviceId);
                if ($jellySession && isset($jellySession['Id'])) {
                    $url = $config['baseUrl'] . '/Sessions/' . $jellySession['Id'] . '/Playing?ItemIds=' . $currentMediaId . '&PlayCommand=PlayNow&StartPositionTicks=0&api_key=' . $config['apikey'];
                    self::requestApi($url, 'POST', null, false, 2);
                }
                cache::set($cacheKey, json_encode($engineState));
                return;
            }

            log::add('jellyfin', 'error', 'Média en échec après ' . self::MAX_LAUNCH_RETRIES . ' tentatives, SKIP: ' . $currentMediaId);
            self::skipToNextTrigger($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $config);
            return;
        }

        // === ÉTAT 2 : LE MÉDIA EST EN LECTURE ===
        if ($status == 'Playing' || $status == 'Paused') {
            // Réinitialiser les compteurs SEULEMENT après confirmation stable (>3s)
            // Évite le cas où le média apparaît brièvement "Playing" puis échoue
            $launchAtState2 = $engineState['media_launch_at'] ?? 0;
            if ($launchAtState2 > 0) {
                $playingSince = $now - $launchAtState2;
                if ($playingSince > 3) {
                    unset($engineState['media_launch_at']);
                    unset($engineState['launch_retries']);
                }
            }
            unset($engineState['stopped_since']);

            // Resync : quand Jellyfin auto-avance via la queue, on détecte le nouvel item_id
            // et on met à jour le moteur pour suivre la bonne section/index
            if ($status == 'Playing' && !empty($itemId) && !empty($currentMediaId) && $itemId != $currentMediaId) {
                $found = self::findTriggerByMediaId($sections, $itemId);
                if ($found) {
                    $previousSection = $engineState['current_section'];
                    $engineState['current_section'] = $found['section'];
                    $engineState['current_trigger_index'] = $found['index'];
                    $engineState['current_media_id'] = $itemId;
                    $engineState['queued'] = true; // La playlist client est toujours active — ne PAS re-queue
                    unset($engineState['media_launch_at']);
                    unset($engineState['stopped_since']);
                    // Ambiance si changement de section
                    if ($found['section'] != $previousSection) {
                        self::triggerLighting($sessionEq->getSessionLighting($found['section']));
                        $engineState['current_lighting'] = $found['section'];
                        $sessionEq->checkAndUpdateCmd('current_section', self::SECTION_LABELS[$found['section']] ?? $found['section']);
                    }
                    log::add('jellyfin', 'info', 'Auto-avancement détecté: ' . $itemId . ' → section ' . $found['section'] . '[' . $found['index'] . ']');
                    // Volume ampli pour le nouveau clip
                    $foundTriggers = $sections[$found['section']]['triggers'] ?? [];
                    if (isset($foundTriggers[$found['index']])) {
                        self::applyVolume($playerEq, $foundTriggers[$found['index']], $found['section']);
                    }
                }
            }

            // Queue initial (seulement si aucune playlist n'est active — premier clip uniquement)
            if ($status == 'Playing' && !($engineState['queued'] ?? false)) {
                self::queueAllRemainingMedia($playerEq, $sessionData, $engineState, $config);
            }

            // Détection stuck (position ne bouge plus pendant 30s en Playing)
            if ($status == 'Playing' && $positionTicks > 0) {
                $lastPos = $engineState['last_position_ticks'] ?? 0;
                if ($positionTicks == $lastPos) {
                    $stuckSince = $engineState['stuck_since'] ?? $now;
                    if ($now - $stuckSince >= self::STUCK_TIMEOUT) {
                        log::add('jellyfin', 'error', 'Média bloqué depuis ' . self::STUCK_TIMEOUT . 's, SKIP: ' . $currentMediaId);
                        self::skipToNextTrigger($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $config);
                        return;
                    }
                    $engineState['stuck_since'] = $stuckSince;
                } else {
                    unset($engineState['stuck_since']);
                }
                $engineState['last_position_ticks'] = $positionTicks;
            }

            // Pré-chauffage du prochain clip (~10s avant la fin)
            // Déclenche la préparation du stream côté Jellyfin (transcode/cache)
            if ($runTimeTicks > 0 && $status == 'Playing' && !($engineState['warmed_up'] ?? false)) {
                $remainingSeconds = ($runTimeTicks - $positionTicks) / 10000000;
                if ($remainingSeconds <= 10 && $remainingSeconds > 0) {
                    $next = self::findNextMediaTrigger($sections, $engineState['current_section'], $engineState['current_trigger_index']);
                    if ($next) {
                        self::warmUpMedia($next['trigger']['media_id'], $config);
                        $engineState['warmed_up'] = true;
                    }
                }
            }

            // Tops film (uniquement en section film)
            if ($engineState['current_section'] == 'film' && isset($sections['film']['marks']) && $status == 'Playing') {
                $positionSeconds = $positionTicks / 10000000;
                foreach (self::MARK_ORDER as $mark) {
                    $markTime = $sections['film']['marks'][$mark] ?? null;
                    if ($markTime === null) continue;
                    $lastMark = $engineState['last_mark_triggered'] ?? '';
                    $markIdx = array_search($mark, self::MARK_ORDER);
                    $lastIdx = ($lastMark != '') ? array_search($lastMark, self::MARK_ORDER) : -1;
                    if ($positionSeconds >= $markTime && $markIdx > $lastIdx) {
                        self::triggerLighting($sessionEq->getSessionLighting($mark));
                        $engineState['last_mark_triggered'] = $mark;
                        $engineState['current_lighting'] = $mark;
                        log::add('jellyfin', 'info', 'Top film déclenché: ' . $mark . ' à ' . round($positionSeconds) . 's');
                    }
                    if ($mark == 'fin' && $positionSeconds >= $markTime) {
                        log::add('jellyfin', 'info', 'Fin du film atteinte. Arrêt séance.');
                        $sessionEq->stopSession();
                        return;
                    }
                }
            }
        }


        self::updateSessionProgress($sessionEq, $sessionData, $engineState);
        cache::set($cacheKey, json_encode($engineState));
    }

    /**
     * Transition vers le prochain trigger trouvé par findNextMediaTrigger
     */
    private static function transitionTo($sessionEq, &$engineState, $next, $previousSection) {
        $engineState['current_section'] = $next['section'];
        $engineState['current_trigger_index'] = $next['index'];
        $engineState['current_media_id'] = $next['trigger']['media_id'];
        $engineState['queued'] = false;
        unset($engineState['media_launch_at']);
        unset($engineState['launch_retries']);
        unset($engineState['stopped_since']);
        unset($engineState['stuck_since']);
        unset($engineState['last_position_ticks']);
        unset($engineState['warmed_up']);
        if ($next['section'] != $previousSection) {
            self::triggerLighting($sessionEq->getSessionLighting($next['section']));
            $engineState['current_lighting'] = $next['section'];
            $sessionEq->checkAndUpdateCmd('current_section', self::SECTION_LABELS[$next['section']] ?? $next['section']);
        }
    }

    /**
     * Skip le trigger courant et passe au suivant (média ou non-média)
     */
    private static function skipToNextTrigger($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey, $config) {
        $sessionType = $sessionEq->getConfiguration('session_type');
        $currentSection = $engineState['current_section'];
        $triggerIndex = $engineState['current_trigger_index'];

        // Nettoyer les flags
        unset($engineState['media_launch_at']);
        unset($engineState['launch_retries']);
        unset($engineState['stopped_since']);
        unset($engineState['stuck_since']);
        unset($engineState['last_position_ticks']);

        // Chercher le prochain média
        $next = null;
        if ($sessionType == 'commercial') {
            // Commercial : chercher dans la playlist (avec support boucle)
            $playlist = $sessionData['playlist'] ?? [];
            $loop = $sessionData['loop'] ?? true;
            for ($i = $triggerIndex + 1; $i < count($playlist); $i++) {
                if ($playlist[$i]['type'] == 'media' && (!isset($playlist[$i]['enabled']) || $playlist[$i]['enabled'] !== false)) {
                    $next = ['trigger' => $playlist[$i], 'section' => 'playlist', 'index' => $i];
                    break;
                }
            }
            // Boucle : retourner au début
            if (!$next && $loop && count($playlist) > 0) {
                for ($i = 0; $i < count($playlist); $i++) {
                    if ($playlist[$i]['type'] == 'media' && (!isset($playlist[$i]['enabled']) || $playlist[$i]['enabled'] !== false)) {
                        $next = ['trigger' => $playlist[$i], 'section' => 'playlist', 'index' => $i];
                        break;
                    }
                }
                if ($next) log::add('jellyfin', 'info', 'Commercial: boucle, retour au début');
            }
        } else {
            $sections = $sessionData['sections'] ?? [];
            $next = self::findNextMediaTrigger($sections, $currentSection, $triggerIndex);
        }
        if ($next) {
            $previousSection = $engineState['current_section'];
            self::transitionTo($sessionEq, $engineState, $next, $previousSection);

            // Volume ampli si configuré
            $sectionKey = ($next['section'] == 'playlist') ? 'commercial' : $next['section'];
            self::applyVolume($playerEq, $next['trigger'], $sectionKey);
            // Lancer le média
            $launched = $playerEq->playMedia($next['trigger']['media_id'], 'play_now');
            if (isset($launched['error'])) {
                log::add('jellyfin', 'error', 'playMedia a échoué: ' . $next['trigger']['media_id'] . ' — ' . $launched['error']);
                $engineState['launch_retries'] = ($engineState['launch_retries'] ?? 0) + 1;
                if (($engineState['launch_retries'] ?? 0) <= self::MAX_LAUNCH_RETRIES) {
                    self::skipToNextTrigger($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $config);
                } else {
                    log::add('jellyfin', 'error', 'Trop d\'échecs consécutifs. Arrêt séance.');
                    $sessionEq->stopSession();
                }
                return;
            }

            $engineState['media_launch_at'] = time();
            $engineState['queued'] = false;
            log::add('jellyfin', 'info', 'Lancement: ' . $next['trigger']['media_id'] . ' (section: ' . $next['section'] . '[' . $next['index'] . '])');
            cache::set($cacheKey, json_encode($engineState));
        } else {
            log::add('jellyfin', 'info', 'Plus de médias à jouer. Fin de séance.');
            $sessionEq->stopSession();
        }
    }

    /**
     * Queue TOUS les médias restants après le média courant.
     * Jellyfin reçoit la playlist complète → transitions internes fluides.
     */
    private static function queueAllRemainingMedia($playerEq, $sessionData, &$engineState, $config) {
        $sections = $sessionData['sections'] ?? [];
        $currentSection = $engineState['current_section'] ?? '';
        $currentIndex = $engineState['current_trigger_index'] ?? 0;

        // Résoudre la session Jellyfin
        $deviceId = $playerEq->getConfiguration('device_id');
        $jellyfinSession = self::getSessionDataFromDeviceId($config['baseUrl'], $config['apikey'], $deviceId);
        $jellyfinSessionId = $jellyfinSession['Id'] ?? null;
        if (!$jellyfinSessionId) {
            log::add('jellyfin', 'warning', 'Impossible de queue: session Jellyfin introuvable');
            return;
        }

        // Collecter tous les médias restants
        $mediaIds = [];
        $sectionOrder = self::SECTION_ORDER;
        $sectionIdx = array_search($currentSection, $sectionOrder);

        // Même section, après le trigger courant
        $triggers = $sections[$currentSection]['triggers'] ?? [];
        for ($i = $currentIndex + 1; $i < count($triggers); $i++) {
            if ($triggers[$i]['type'] == 'media' && (!isset($triggers[$i]['enabled']) || $triggers[$i]['enabled'] !== false)) {
                $mediaIds[] = $triggers[$i]['media_id'];
            }
        }

        // Sections suivantes
        for ($s = $sectionIdx + 1; $s < count($sectionOrder); $s++) {
            $secKey = $sectionOrder[$s];
            foreach ($sections[$secKey]['triggers'] ?? [] as $trigger) {
                if ($trigger['type'] == 'media' && (!isset($trigger['enabled']) || $trigger['enabled'] !== false)) {
                    $mediaIds[] = $trigger['media_id'];
                }
            }
        }

        if (empty($mediaIds)) return;

        // Queue tous les médias d'un coup (Jellyfin supporte les ItemIds multiples)
        $allIds = implode(',', $mediaIds);
        $url = $config['baseUrl'] . '/Sessions/' . $jellyfinSessionId . '/Queue?ItemIds=' . $allIds . '&Mode=PlayNext&api_key=' . $config['apikey'];
        self::requestApi($url, 'POST', null, false, 2);
        $engineState['queued'] = true;
        log::add('jellyfin', 'debug', 'Queue playlist: ' . count($mediaIds) . ' médias');
    }

    private static function tickCommercial($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey, $status, $itemId, $positionTicks, $runTimeTicks, $config, $jellyfinSessionId) {
        if (!$config) return;
        $now = time();

        $playlist = $sessionData['playlist'] ?? [];
        if (empty($playlist)) return;
        $loop = $sessionData['loop'] ?? true;

        $triggerIndex = $engineState['current_trigger_index'] ?? 0;
        $currentMedia = $playlist[$triggerIndex] ?? null;
        if (!$currentMedia) return;
        $currentMediaId = $engineState['current_media_id'] ?? '';

        // Média terminé → enchaîner (même logique que tickCinema STATE 3)
        $launchAt = $engineState['media_launch_at'] ?? 0;
        if ($status == 'Stopped' && !empty($currentMediaId) && $launchAt == 0) {
            log::add('jellyfin', 'debug', 'Commercial: média terminé, enchaînement');
            self::skipToNextTrigger($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $config);
            return;
        }

        // Attente lancement (même logique que tickCinema STATE 1)
        if (!empty($currentMediaId) && $launchAt > 0 && $status != 'Playing' && $status != 'Paused') {
            $retries = $engineState['launch_retries'] ?? 0;
            $elapsed = $now - $launchAt;
            if ($elapsed < self::LAUNCH_TIMEOUT) {
                cache::set($cacheKey, json_encode($engineState));
                return;
            }
            if ($retries < self::MAX_LAUNCH_RETRIES) {
                $engineState['launch_retries'] = $retries + 1;
                $engineState['media_launch_at'] = $now;
                log::add('jellyfin', 'warning', 'Commercial: média ne démarre pas, retry: ' . $currentMediaId);
                $deviceId = $playerEq->getConfiguration('device_id');
                $jellySession = self::getSessionDataFromDeviceId($config['baseUrl'], $config['apikey'], $deviceId);
                if ($jellySession && isset($jellySession['Id'])) {
                    $url = $config['baseUrl'] . '/Sessions/' . $jellySession['Id'] . '/Playing?ItemIds=' . $currentMediaId . '&PlayCommand=PlayNow&StartPositionTicks=0&api_key=' . $config['apikey'];
                    self::requestApi($url, 'POST', null, false, 2);
                }
                cache::set($cacheKey, json_encode($engineState));
                return;
            }
            log::add('jellyfin', 'error', 'Commercial: média en échec, skip: ' . $currentMediaId);
            self::skipToNextTrigger($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey, $config);
            return;
        }

        // En lecture : reset flags de lancement après confirmation 3s
        if ($status == 'Playing' || $status == 'Paused') {
            if ($launchAt > 0 && ($now - $launchAt) > 3) {
                unset($engineState['media_launch_at']);
                unset($engineState['launch_retries']);
            }
        }

        $nextAnticipation = (float)config::byKey('next_anticipation', 'jellyfin', 0.5);

        // NextTrack anticipé (le queue a été fait au lancement)
        if ($runTimeTicks > 0 && $status == 'Playing' && ($engineState['queued'] ?? false)) {
            $remainingSeconds = ($runTimeTicks - $positionTicks) / 10000000;
            $isLast = ($triggerIndex + 1 >= count($playlist));

            if (!($isLast && !$loop) && $remainingSeconds <= $nextAnticipation && $remainingSeconds > 0) {
                $nextIndex = $isLast ? 0 : $triggerIndex + 1;
                $nextMedia = $playlist[$nextIndex];
                self::sendNextTrackDirect($jellyfinSessionId, $config);
                $engineState['current_trigger_index'] = $nextIndex;
                $engineState['current_media_id'] = $nextMedia['media_id'];
                $engineState['queued'] = false;
                log::add('jellyfin', 'debug', 'Commercial NextTrack anticipé → index ' . $nextIndex);
                // Queue le suivant pour la prochaine transition
                if ($jellyfinSessionId) {
                    $nextNextIndex = ($nextIndex + 1 >= count($playlist)) ? ($loop ? 0 : -1) : $nextIndex + 1;
                    if ($nextNextIndex >= 0 && isset($playlist[$nextNextIndex])) {
                        self::queueMediaDirect($jellyfinSessionId, $playlist[$nextNextIndex]['media_id'], $config);
                        $engineState['queued'] = true;
                    }
                }
            }
        }

        self::updateSessionProgress($sessionEq, $sessionData, $engineState);
        cache::set($cacheKey, json_encode($engineState));
    }

    private static function findNextMediaTrigger($sections, $currentSection, $currentIndex) {
        $sectionOrder = self::SECTION_ORDER;
        $sectionIdx = array_search($currentSection, $sectionOrder);

        $triggers = $sections[$currentSection]['triggers'] ?? [];
        for ($i = $currentIndex + 1; $i < count($triggers); $i++) {
            if ($triggers[$i]['type'] == 'media' && (!isset($triggers[$i]['enabled']) || $triggers[$i]['enabled'] !== false)) {
                return ['trigger' => $triggers[$i], 'section' => $currentSection, 'index' => $i];
            }
        }

        for ($s = $sectionIdx + 1; $s < count($sectionOrder); $s++) {
            $secKey = $sectionOrder[$s];
            $sec = $sections[$secKey] ?? [];
            if (isset($sec['enabled']) && $sec['enabled'] === false) continue; // Section désactivée
            foreach ($sec['triggers'] ?? [] as $idx => $trigger) {
                if ($trigger['type'] == 'media' && (!isset($trigger['enabled']) || $trigger['enabled'] !== false)) {
                    return ['trigger' => $trigger, 'section' => $secKey, 'index' => $idx];
                }
            }
        }
        return null;
    }

    /**
     * Collecter tous les media_id restants après la position courante.
     */
    /**
     * Pré-chauffe un média côté Jellyfin : déclenche la résolution du stream
     * et le démarrage du transcodage si nécessaire, SANS lancer la lecture.
     */
    private static function warmUpMedia($mediaId, $config) {
        $userId = self::getPrimaryUserId();
        if (!$userId) return;
        // Appel PlaybackInfo — force Jellyfin à préparer le stream (transcode/direct play)
        $url = $config['baseUrl'] . '/Items/' . $mediaId . '/PlaybackInfo?UserId=' . $userId . '&api_key=' . $config['apikey'];
        self::requestApi($url, 'POST', [
            'DeviceProfile' => new \stdClass(), // Profil vide = Jellyfin prépare quand même
            'MaxStreamingBitrate' => 120000000,
            'EnableDirectPlay' => true,
            'EnableDirectStream' => true,
            'EnableTranscoding' => true,
            'AutoOpenLiveStream' => true
        ], false, 2);
        log::add('jellyfin', 'debug', 'Warm-up média: ' . $mediaId);
    }

    /**
     * Applique le volume ampli avant un clip si configuré.
     */
    private static function applyVolume($playerEq, $trigger, $sectionKey = '') {
        $ampCmdId = $playerEq->getConfiguration('amp_volume_cmd_id');
        if (empty($ampCmdId) || !is_numeric($ampCmdId)) return;

        $volume = null;

        // 1. Override manuel (priorité absolue)
        if (isset($trigger['volume']) && $trigger['volume'] !== '' && $trigger['volume'] !== null) {
            $volume = (int)$trigger['volume'];
        }
        // 2. Volume auto (calculé par normalisation LUFS) + profil dynamique
        elseif (isset($trigger['volume_auto']) && $trigger['volume_auto'] !== '' && $trigger['volume_auto'] !== null) {
            $profileCmd = $playerEq->getCmd('info', 'audio_profile');
            $profile = is_object($profileCmd) ? $profileCmd->execCmd() : 'cinema';
            $profileOffset = (float)config::byKey('audio_profile_' . $profile, 'jellyfin', 0);
            $volume = (int)max(0, min(100, (int)$trigger['volume_auto'] + $profileOffset));
        }
        // 3. Volume par défaut
        else {
            $defaultVol = $playerEq->getConfiguration('amp_default_volume');
            if ($defaultVol !== '' && $defaultVol !== null) {
                $volume = (int)$defaultVol;
            }
        }

        if ($volume === null) return;

        try {
            $cmd = cmd::byId($ampCmdId);
            if (is_object($cmd)) {
                $cmd->execCmd(['slider' => $volume]);
                log::add('jellyfin', 'info', 'Volume ampli: ' . $volume . ' (cmd #' . $ampCmdId . ')');
            }
        } catch (Exception $e) {
            log::add('jellyfin', 'warning', 'Erreur volume ampli: ' . $e->getMessage());
        }
    }

    public static function analyzeLufs($mediaId, $mode = 'quick') {
        if (!self::isFfmpegAvailable()) {
            return ['error' => 'ffmpeg non installé'];
        }
        $config = self::getBaseConfig();
        if (!$config) return ['error' => 'Configuration Jellyfin incomplète'];

        // Vérifier le cache (sauf mode force)
        if ($mode != 'force') {
            $cacheKey = 'jellyfin::lufs::' . $mediaId;
            $cached = cache::byKey($cacheKey)->getValue(null);
            if ($cached !== null) {
                return ['lufs' => (float)$cached, 'cached' => true];
            }
        }

        // URL de streaming
        $streamUrl = $config['baseUrl'] . '/Videos/' . $mediaId . '/stream?static=true&api_key=' . $config['apikey'];

        // Mode rapide : seek au milieu
        $timeLimit = '';
        if ($mode == 'quick') {
            $userId = self::getPrimaryUserId();
            if ($userId) {
                $itemUrl = $config['baseUrl'] . '/Users/' . $userId . '/Items/' . $mediaId . '?api_key=' . $config['apikey'];
                $itemData = self::requestApi($itemUrl);
                if ($itemData && isset($itemData['RunTimeTicks'])) {
                    $midpoint = (int)($itemData['RunTimeTicks'] / 2);
                    $streamUrl .= '&startTimeTicks=' . $midpoint;
                }
            }
            $timeLimit = '-t 60';
        }

        // Commande ffmpeg
        // Télécharger dans un fichier temp puis analyser (seule méthode fiable pour MP4 + MKV + tous formats)
        // Utilise le répertoire data du plugin (pas /tmp qui peut être en tmpfs avec taille limitée)
        $dataDir = __DIR__ . '/../../data';
        if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);
        $tmpFile = $dataDir . '/lufs_analysis_' . $mediaId . '.tmp';
        $dlCmd = 'curl -s -f --max-time 300 -o ' . escapeshellarg($tmpFile) . ' "' . $streamUrl . '" 2>&1';
        exec($dlCmd, $dlOutput, $dlReturn);
        $dlSize = file_exists($tmpFile) ? filesize($tmpFile) : 0;

        if ($dlSize < 10000) {
            // Fichier trop petit ou absent = vrai échec
            log::add('jellyfin', 'warning', 'LUFS download failed: media=' . $mediaId . ' curl_exit=' . $dlReturn . ' size=' . $dlSize . ' output=' . implode(' ', $dlOutput));
            @unlink($tmpFile);
            return ['error' => 'Impossible de télécharger le média (curl code ' . $dlReturn . ', size ' . $dlSize . ')'];
        }
        if ($dlReturn != 0) {
            // Curl a reporté une erreur mais le fichier est là — on tente l'analyse quand même
            log::add('jellyfin', 'debug', 'LUFS download curl warning (code ' . $dlReturn . ') mais fichier OK: ' . round($dlSize / 1024 / 1024, 1) . ' Mo');
        }
        log::add('jellyfin', 'debug', 'LUFS download OK: ' . round($dlSize / 1024 / 1024, 1) . ' Mo');

        // -drc_scale 0 : désactive la compression dynamique AC3 (le décodeur ffmpeg l'applique par défaut,
        // mais l'ampli en passthrough ne l'applique pas → fausse les mesures de 0 à 13dB)
        $cmd = 'ffmpeg -drc_scale 0 -i ' . escapeshellarg($tmpFile) . ' -vn ' . $timeLimit . ' -af loudnorm=print_format=json -f null - 2>&1';
        $output = [];
        exec($cmd, $output, $returnVar);
        @unlink($tmpFile);
        $fullOutput = implode("\n", $output);

        // Parser le LUFS
        if (preg_match('/"input_i"\s*:\s*"([^"]+)"/', $fullOutput, $matches)) {
            $lufs = (float)$matches[1];
            // Vérifier que le LUFS est valide (pas -inf, pas 0 aberrant)
            if (is_infinite($lufs) || is_nan($lufs)) {
                return ['error' => 'LUFS invalide (-inf). Le fichier est peut-être corrompu ou vide.'];
            }
            cache::set('jellyfin::lufs::' . $mediaId, $lufs);
            return ['lufs' => $lufs, 'cached' => false];
        }

        return ['error' => 'Impossible de mesurer le LUFS', 'output' => substr($fullOutput, -500)];
    }

    public static function calculateAutoVolume($playerEq, $clipLufs, $sectionKey) {
        $refVolume = (float)$playerEq->getConfiguration('audio_ref_volume', 0);
        $refLufs = (float)$playerEq->getConfiguration('audio_ref_lufs', -23);
        $sectionOffset = (float)config::byKey('audio_offset_' . $sectionKey, 'jellyfin', 0);
        $compensation = (float)config::byKey('audio_calibration_compensation', 'jellyfin', 4);
        $volume = $refVolume + $compensation + ($refLufs - $clipLufs) + $sectionOffset;
        return (int)max(0, min(100, $volume));
    }

    private static function collectAllRemainingMediaIds($sections, $currentSection, $currentIndex) {
        $mediaIds = [];
        $sectionOrder = self::SECTION_ORDER;
        $sectionIdx = array_search($currentSection, $sectionOrder);

        $triggers = $sections[$currentSection]['triggers'] ?? [];
        for ($i = $currentIndex + 1; $i < count($triggers); $i++) {
            if ($triggers[$i]['type'] == 'media' && (!isset($triggers[$i]['enabled']) || $triggers[$i]['enabled'] !== false)) {
                $mediaIds[] = $triggers[$i]['media_id'];
            }
        }

        for ($s = $sectionIdx + 1; $s < count($sectionOrder); $s++) {
            $secKey = $sectionOrder[$s];
            $sec = $sections[$secKey] ?? [];
            if (isset($sec['enabled']) && $sec['enabled'] === false) continue;
            foreach ($sec['triggers'] ?? [] as $trigger) {
                if ($trigger['type'] == 'media' && (!isset($trigger['enabled']) || $trigger['enabled'] !== false)) {
                    $mediaIds[] = $trigger['media_id'];
                }
            }
        }
        return $mediaIds;
    }

    /**
     * Lancer une playlist de médias via un seul PlayNow avec ItemIds multiples.
     * Jellyfin crée une playlist interne et auto-avance entre les clips.
     */
    private static function playMediaPlaylist($playerEq, $mediaIds, $config) {
        $deviceId = $playerEq->getConfiguration('device_id');
        $sessionData = self::getSessionDataFromDeviceId($config['baseUrl'], $config['apikey'], $deviceId);
        if (!$sessionData || !isset($sessionData['Id'])) {
            log::add('jellyfin', 'error', 'playMediaPlaylist: session Jellyfin introuvable');
            return false;
        }
        $sessionId = $sessionData['Id'];

        // Stopper le lecteur d'abord si quelque chose joue (fix Android TV)
        if (isset($sessionData['NowPlayingItem'])) {
            $stopUrl = $config['baseUrl'] . '/Sessions/' . $sessionId . '/Playing/Stop?api_key=' . $config['apikey'];
            self::requestApi($stopUrl, 'POST', null, false, 2);
            usleep(100000); // 100ms (réduit pour enchaînement rapide)
        }

        $ids = implode(',', $mediaIds);
        $url = $config['baseUrl'] . '/Sessions/' . $sessionId . '/Playing?ItemIds=' . $ids . '&PlayCommand=PlayNow&StartPositionTicks=0&api_key=' . $config['apikey'];
        self::requestApi($url, 'POST', null, false, 2);
        log::add('jellyfin', 'debug', 'PlayNow playlist (' . count($mediaIds) . ' items): ' . $ids);
        return true;
    }

    private static function queueMediaDirect($jellyfinSessionId, $mediaId, $config) {
        if (!$jellyfinSessionId) return;
        $url = $config['baseUrl'] . '/Sessions/' . $jellyfinSessionId . '/Queue?ItemIds=' . $mediaId . '&Mode=PlayNext&api_key=' . $config['apikey'];
        self::requestApi($url, 'POST', null, false, 2);
        log::add('jellyfin', 'debug', 'Queue média: ' . $mediaId);
    }

    private static function sendNextTrackDirect($jellyfinSessionId, $config) {
        if (!$jellyfinSessionId) return;
        $url = $config['baseUrl'] . '/Sessions/' . $jellyfinSessionId . '/Playing/NextTrack?api_key=' . $config['apikey'];
        self::requestApi($url, 'POST', null, false, 2);
        log::add('jellyfin', 'debug', 'NextTrack envoyé');
    }

    private static function findTriggerByMediaId($sections, $mediaId) {
        foreach (self::SECTION_ORDER as $sectionKey) {
            foreach ($sections[$sectionKey]['triggers'] ?? [] as $idx => $trigger) {
                if ($trigger['type'] == 'media' && $trigger['media_id'] == $mediaId) {
                    return ['section' => $sectionKey, 'index' => $idx];
                }
            }
        }
        return null;
    }

    private static function advanceToNextSection($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey) {
        $sectionOrder = self::SECTION_ORDER;
        $currentIdx = array_search($engineState['current_section'], $sectionOrder);

        for ($i = $currentIdx + 1; $i < count($sectionOrder); $i++) {
            $nextKey = $sectionOrder[$i];
            $nextSection = $sessionData['sections'][$nextKey] ?? [];
            if (isset($nextSection['enabled']) && $nextSection['enabled'] === false) continue; // Section désactivée
            $nextTriggers = $nextSection['triggers'] ?? [];
            if (!empty($nextTriggers)) {
                $engineState['current_section'] = $nextKey;
                $engineState['current_trigger_index'] = 0;
                $engineState['queued'] = false;

                self::triggerLighting($sessionEq->getSessionLighting($nextKey));
                $engineState['current_lighting'] = $nextKey;

                self::executeNonMediaTriggers($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);

                $sessionEq->checkAndUpdateCmd('current_section', self::SECTION_LABELS[$nextKey] ?? $nextKey);
                cache::set($cacheKey, json_encode($engineState));
                return;
            }
        }

        $sessionEq->stopSession();
    }

    private static function executeNonMediaTriggers($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey) {
        $sessionType = $sessionEq->getConfiguration('session_type');
        $section = $engineState['current_section'];
        $config = self::getBaseConfig();

        // Résoudre les triggers selon le type de séance
        if ($sessionType == 'commercial') {
            $triggers = $sessionData['playlist'] ?? [];
        } else {
            $triggers = $sessionData['sections'][$section]['triggers'] ?? [];
        }

        while ($engineState['current_trigger_index'] < count($triggers)) {
            $trigger = $triggers[$engineState['current_trigger_index']];

            // Skip triggers désactivés
            if (isset($trigger['enabled']) && $trigger['enabled'] === false) {
                $engineState['current_trigger_index']++;
                continue;
            }

            if ($trigger['type'] == 'media') {
                $engineState['current_media_id'] = $trigger['media_id'];
                $sectionKey = ($sessionType == 'commercial') ? 'commercial' : $section;
                self::applyVolume($playerEq, $trigger, $sectionKey);

                if ($sessionType == 'commercial') {
                    // Commercial : envoyer toute la playlist restante
                    $allMediaIds = [$trigger['media_id']];
                    $playlist = $sessionData['playlist'] ?? [];
                    for ($i = $engineState['current_trigger_index'] + 1; $i < count($playlist); $i++) {
                        if ($playlist[$i]['type'] == 'media' && (!isset($playlist[$i]['enabled']) || $playlist[$i]['enabled'] !== false)) {
                            $allMediaIds[] = $playlist[$i]['media_id'];
                        }
                    }
                    self::playMediaPlaylist($playerEq, $allMediaIds, $config);
                    $engineState['queued'] = (count($allMediaIds) > 1);
                    log::add('jellyfin', 'debug', 'Commercial PlayNow: ' . count($allMediaIds) . ' médias');
                } else {
                    // Cinéma : envoyer ce média + les suivants cross-sections
                    $allMediaIds = [$trigger['media_id']];
                    $sections = $sessionData['sections'] ?? [];
                    $remaining = self::collectAllRemainingMediaIds($sections, $engineState['current_section'], $engineState['current_trigger_index']);
                    $allMediaIds = array_merge($allMediaIds, $remaining);
                    self::playMediaPlaylist($playerEq, $allMediaIds, $config);
                    $engineState['queued'] = (count($allMediaIds) > 1);
                    log::add('jellyfin', 'debug', 'PlayNow playlist: ' . count($allMediaIds) . ' médias');
                }

                $engineState['media_launch_at'] = time();
                cache::set($cacheKey, json_encode($engineState));
                return;
            }

            if ($trigger['type'] == 'pause') {
                $duration = (int)($trigger['duration'] ?? 0);
                if ($duration == 0) {
                    $sessionEq->checkAndUpdateCmd('state', 'paused');
                    $engineState['waiting_resume'] = true;
                    cache::set($cacheKey, json_encode($engineState));
                    return;
                }
                $engineState['pause_until'] = time() + $duration;
                cache::set($cacheKey, json_encode($engineState));
                return;
            }

            if ($trigger['type'] == 'command') {
                try {
                    $cmd = cmd::byId($trigger['cmd_id']);
                    if (is_object($cmd)) $cmd->execCmd();
                } catch (Exception $e) {
                    log::add('jellyfin', 'warning', 'Erreur trigger command: ' . $e->getMessage());
                }
            }

            if ($trigger['type'] == 'scenario') {
                try {
                    $scenario = scenario::byId($trigger['scenario_id']);
                    if (is_object($scenario)) $scenario->launch();
                } catch (Exception $e) {
                    log::add('jellyfin', 'warning', 'Erreur trigger scenario: ' . $e->getMessage());
                }
            }

            $engineState['current_trigger_index']++;
        }

        self::advanceToNextSection($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);
    }

    private static function handlePlayerLost($sessionEq, &$engineState, $cacheKey) {
        $now = time();
        if (!isset($engineState['player_lost_since'])) {
            $engineState['player_lost_since'] = $now;
        }
        $lostDuration = $now - $engineState['player_lost_since'];
        $timeout = (int)config::byKey('player_lost_timeout', 'jellyfin', 10);
        $maxTimeout = (int)config::byKey('player_lost_max', 'jellyfin', 300);

        if ($lostDuration >= $maxTimeout) {
            log::add('jellyfin', 'error', 'Lecteur absent depuis ' . $lostDuration . 's. Arrêt séance.');
            $sessionEq->stopSession();
        } elseif ($lostDuration >= $timeout) {
            $sessionEq->checkAndUpdateCmd('state', 'paused');
            $engineState['player_lost'] = true;
        }
        cache::set($cacheKey, json_encode($engineState));
    }

    private static function updateSessionProgress($sessionEq, $sessionData, $engineState) {
        $sessionType = $sessionEq->getConfiguration('session_type');
        $totalTriggers = 0;
        $completedTriggers = 0;

        if ($sessionType == 'cinema') {
            $currentSectionIdx = array_search($engineState['current_section'], self::SECTION_ORDER);
            foreach (self::SECTION_ORDER as $idx => $key) {
                $count = count($sessionData['sections'][$key]['triggers'] ?? []);
                $totalTriggers += $count;
                if ($idx < $currentSectionIdx) {
                    $completedTriggers += $count;
                } elseif ($idx == $currentSectionIdx) {
                    $completedTriggers += $engineState['current_trigger_index'];
                }
            }
        } else {
            $totalTriggers = count($sessionData['playlist'] ?? []);
            $completedTriggers = $engineState['current_trigger_index'] ?? 0;
        }

        $progress = ($totalTriggers > 0) ? round(($completedTriggers / $totalTriggers) * 100) : 0;
        $sessionEq->checkAndUpdateCmd('progress', $progress);
    }

    public function createMediaCommand($mediaId, $mediaName, $imgTag = null) {
        $cleanName = str_replace(' ', '_', $mediaName);
        $cleanName = preg_replace('/[^A-Za-z0-9_]/', '', $cleanName);
        $logicalId = 'media_' . $mediaId;

        $cmd = $this->getCmd(null, $logicalId);
        if (is_object($cmd)) return __('Cette commande existe déjà', __FILE__);

        if (empty($imgTag)) {
            $ip = config::byKey('jellyfin_ip', 'jellyfin');
            $port = config::byKey('jellyfin_port', 'jellyfin');
            $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
            $userId = self::getPrimaryUserId();
            
            if (!empty($ip) && !empty($apikey) && $userId) {
                $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
                $url = $baseUrl . '/Users/' . $userId . '/Items/' . $mediaId . '?api_key=' . $apikey;
                $itemData = self::requestApi($url);
                if (isset($itemData['ImageTags']['Primary'])) {
                    $imgTag = $itemData['ImageTags']['Primary'];
                }
            }
        }

        $cmd = new jellyfinCmd();
        $cmd->setName("Play : " . $mediaName);
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId($logicalId);
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setIsVisible(0); 
        $cmd->setConfiguration('media_id', $mediaId);
        $cmd->setConfiguration('is_media_shortcut', 1); 
        if($imgTag) $cmd->setConfiguration('image_tag', $imgTag);
        $cmd->save();
        return __('Commande créée avec succès', __FILE__);
    }
}

class jellyfinCmd extends cmd {
    public function execute($_options = null) {
        $eqLogic = $this->getEqLogic();
        $logicalId = $this->getLogicalId();

        // Commandes de séance
        if ($eqLogic->getConfiguration('session_type') != '') {
            switch ($logicalId) {
                case 'start':  return $eqLogic->startSession();
                case 'stop':   return $eqLogic->stopSession();
                case 'pause':  return $eqLogic->pauseSession();
                case 'resume': return $eqLogic->resumeSession();
            }
            return;
        }

        // Commande profil audio
        if ($logicalId == 'set_audio_profile') {
            $value = isset($_options['select']) ? $_options['select'] : '';
            if (in_array($value, ['night', 'cinema', 'thx'])) {
                $eqLogic->checkAndUpdateCmd('audio_profile', $value);
                log::add('jellyfin', 'info', 'Profil audio changé: ' . $value);
            }
            return;
        }

        // Commandes appareil (existant)
        $eqLogic->remoteControl($logicalId, $_options);
    }
}
?>