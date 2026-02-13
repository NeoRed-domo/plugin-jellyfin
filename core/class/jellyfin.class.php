<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class jellyfin extends eqLogic {

    /*     * ************************* Gestion des Dépendances ****************************** */

    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'jellyfin_dep';
        $return['progress_file'] = jeedom::getTmpFolder('jellyfin') . '/dependancy';
        $return['state'] = 'nok';

        // On vérifie simplement que python3 et requests sont dispos
        $cmd = 'python3 -c "import requests; print(1)"';
        exec($cmd, $output, $returnVar);

        if ($returnVar == 0) {
            $return['state'] = 'ok';
        }

        return $return;
    }

    public static function dependancy_install() {
        log::remove('jellyfin_dep');
        
        // AJOUT : Initialisation du log par PHP pour garantir les permissions www-data
        log::add('jellyfin_dep', 'info', 'Lancement de l\'installation des dépendances...');
        
        $script_path = realpath(__DIR__ . '/../../resources/install_apt.sh');
        $log_path = log::getPathToLog('jellyfin_dep');
        $cmd = 'sudo /bin/bash ' . $script_path . ' >> ' . $log_path . ' 2>&1 &';
        exec($cmd);
        return true;
    }

    /*     * ************************* Gestion du Démon ****************************** */

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
            if (@posix_kill(trim(file_get_contents($pid_file)), 0)) {
                $return['state'] = 'ok';
            } else {
                unlink($pid_file);
            }
        }
        return $return;
    }

    public static function deamon_start() {
        self::deamon_stop();

        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration ou les dépendances', __FILE__));
        }

        $ip     = config::byKey('jellyfin_ip', 'jellyfin');
        $port   = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');

        if (empty($ip) || empty($port) || empty($apikey)) {
            log::add('jellyfin', 'error', 'Configuration incomplète (IP, Port ou API Key manquants).');
            return;
        }

        if (strpos($ip, 'http') === false) {
            $jellyfin_full_url = 'http://' . $ip . ':' . $port;
        } else {
            $jellyfin_full_url = $ip . ':' . $port;
        }

        $path = realpath(__DIR__ . '/../../resources/daemon');
        $script = $path . '/jellyfind.py';

        if (!file_exists($script)) {
            throw new Exception(__('Script Python introuvable : ' . $script, __FILE__));
        }

        // Gestion des logs
        $jeedomLogLevel = log::getLogLevel('jellyfin_daemon');
        $pythonLogLevel = 'ERROR'; 
        if ($jeedomLogLevel <= 100 || $jeedomLogLevel === 'debug') {
            $pythonLogLevel = 'DEBUG';
        } elseif ($jeedomLogLevel <= 200 || $jeedomLogLevel === 'info') {
            $pythonLogLevel = 'INFO';
        } elseif ($jeedomLogLevel <= 300 || $jeedomLogLevel === 'warning') {
            $pythonLogLevel = 'WARNING';
        }

        $callback = network::getNetworkAccess('internal') . '/plugins/jellyfin/core/php/jeeJellyfin.php';

        $cmd = 'python3 ' . escapeshellarg($script);
        $cmd .= ' --loglevel ' . escapeshellarg($pythonLogLevel);
        $cmd .= ' --callback ' . escapeshellarg($callback);
        $cmd .= ' --apikey ' . escapeshellarg(jeedom::getApiKey('jellyfin'));
        $cmd .= ' --pid ' . escapeshellarg(jeedom::getTmpFolder('jellyfin') . '/jellyfin.pid');
        $cmd .= ' --jellyfin_url ' . escapeshellarg($jellyfin_full_url);
        $cmd .= ' --jellyfin_token ' . escapeshellarg($apikey);
        $cmd .= ' --socket ' . escapeshellarg(jeedom::getTmpFolder('jellyfin') . '/jellyfin.sock');

        log::add('jellyfin', 'info', 'Lancement du démon : ' . $cmd);

        exec($cmd . ' >> ' . log::getPathToLog('jellyfin_daemon') . ' 2>&1 &');
    }

    public static function deamon_stop() {
        $pid_file = jeedom::getTmpFolder('jellyfin') . '/jellyfin.pid';
        if (file_exists($pid_file)) {
            $pid = trim(file_get_contents($pid_file));
            if ($pid != '') {
                posix_kill($pid, 9);
            }
            unlink($pid_file);
        }
    }

    /*     * ************************* Traitement des Données ****************************** */

    public static function processSessions($sessions) {
        if (!is_array($sessions)) return;

        $ip     = config::byKey('jellyfin_ip', 'jellyfin');
        $port   = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        
        $baseUrl = '';
        if (!empty($ip) && !empty($port)) {
            $baseUrl = (strpos($ip, 'http') === false) ? 'http://' . $ip . ':' . $port : $ip . ':' . $port;
        }

        foreach ($sessions as $sessionData) {
            $deviceId   = isset($sessionData['device_id']) ? $sessionData['device_id'] : '';
            $clientName = isset($sessionData['client']) ? $sessionData['client'] : 'Jellyfin Device';

            if (empty($deviceId)) continue;

            $isControllable = false;
            if (isset($sessionData['SupportsRemoteControl']) && $sessionData['SupportsRemoteControl'] === true) $isControllable = true;
            if (isset($sessionData['supports_remote_control']) && $sessionData['supports_remote_control'] === true) $isControllable = true;

            if (!$isControllable) continue;

            $logicalId = $deviceId;
            if (strlen($deviceId) > 120) $logicalId = md5($deviceId);

            $eqLogic = self::byLogicalId($logicalId, 'jellyfin');
            if (!is_object($eqLogic)) {
                $eqLogic = new jellyfin();
                $eqLogic->setName($clientName.' - Jellyfin');
                $eqLogic->setLogicalId($logicalId);
                $eqLogic->setEqType_name('jellyfin');
                $eqLogic->setConfiguration('device_id', $deviceId);
                
                // --- INITIALISATION PAR DEFAUT : LISERÉ DÉSACTIVÉ ---
                $eqLogic->setConfiguration('widget_border_enable', 0); 
                $eqLogic->setConfiguration('widget_border_color', '#e5e5e5');
                
                $eqLogic->setIsEnable(1);
                $eqLogic->setIsVisible(1);
                $eqLogic->save();
            }

            // Données de lecture
            $hasMedia = false;
            $npItem = isset($sessionData['NowPlayingItem']) ? $sessionData['NowPlayingItem'] : array();

            if (!empty($npItem)) {
                $hasMedia = true;
            } elseif (isset($sessionData['item_id']) && !empty($sessionData['item_id'])) {
                $hasMedia = true;
            }

            $statusStr = isset($sessionData['status']) ? $sessionData['status'] : 'Stopped';

            if (!$hasMedia) {
                $statusStr = 'Stopped';
                $sessionData['title'] = ''; 
                $eqLogic->checkAndUpdateCmd('media_type', '');
            } else {
                $mediaPath = isset($npItem['Path']) ? $npItem['Path'] : '';
                if (empty($mediaPath) && isset($sessionData['full_now_playing_item']['Path'])) {
                    $mediaPath = $sessionData['full_now_playing_item']['Path'];
                }

                $detectedType = self::determineMediaType($mediaPath);
                $eqLogic->checkAndUpdateCmd('media_type', $detectedType);
            }

            $eqLogic->checkAndUpdateCmd('title', isset($sessionData['title']) ? $sessionData['title'] : '');
            $eqLogic->checkAndUpdateCmd('status', $statusStr);

            $runTimeTicks = isset($sessionData['run_time_ticks']) ? (int)$sessionData['run_time_ticks'] : 0;
            $positionTicks = isset($sessionData['position_ticks']) ? (int)$sessionData['position_ticks'] : 0;

            if ($statusStr == 'Stopped') {
                $eqLogic->checkAndUpdateCmd('duration', '00:00:00');
                $eqLogic->checkAndUpdateCmd('position', '00:00:00');
                $eqLogic->checkAndUpdateCmd('remaining', '00:00:00');
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

                $itemType = isset($npItem['Type']) ? $npItem['Type'] : 'Unknown';
                $imageItemId = '';
                
                if (isset($npItem['SeriesId'])) {
                    $imageItemId = $npItem['SeriesId'];
                } elseif (isset($npItem['AlbumId'])) {
                    $imageItemId = $npItem['AlbumId'];
                } elseif (isset($npItem['PrimaryImageItemId'])) {
                    $imageItemId = $npItem['PrimaryImageItemId'];
                } elseif (isset($npItem['Id'])) {
                    $imageItemId = $npItem['Id'];
                } elseif (isset($sessionData['item_id'])) {
                    $imageItemId = $sessionData['item_id'];
                }

                if ($itemType == 'Audio' && !isset($npItem['AlbumId']) && !isset($npItem['PrimaryImageItemId'])) {
                    $imageItemId = ''; 
                }

                $storedImageId = $eqLogic->getConfiguration('last_image_id', '');

                if (!empty($baseUrl) && !empty($imageItemId) && $imageItemId !== $storedImageId) {
                    $imgUrl = $baseUrl . '/Items/' . $imageItemId . '/Images/Primary?fillWidth=400&quality=90&api_key=' . $apikey;
                    $imgData = self::requestApi($imgUrl, 'GET', null, true);
                    
                    if ($imgData && strlen($imgData) > 500) {
                        $headerHex = bin2hex(substr($imgData, 0, 2));
                        $mimeType = ($headerHex == '8950') ? 'image/png' : 'image/jpeg';
                        $htmlImg = '<img class="img-responsive" style="border-radius: 10px;" src="data:' . $mimeType . ';base64,' . base64_encode($imgData) . '">';
                        
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
    }

    private static function determineMediaType($path) {
        if (empty($path)) return 'Autre';
        $path = strtolower($path);
        $checkOrder = [
            'filter_ad'            => 'Publicité',
            'filter_sound_trailer' => 'Sound Trailer',
            'filter_trailer'       => 'Bande Annonce',
            'filter_movie'         => 'Film',
            'filter_series'        => 'Série',
            'filter_audio'         => 'Audio'
        ];
        foreach ($checkOrder as $configKey => $typeLabel) {
            $keywords = config::byKey($configKey, 'jellyfin');
            if (empty($keywords)) continue;
            $keywordArray = explode(',', $keywords);
            foreach ($keywordArray as $word) {
                $word = trim(strtolower($word));
                if (!empty($word) && strpos($path, $word) !== false) {
                    return $typeLabel;
                }
            }
        }
        return 'Autre';
    }

    /*     * ************************* Commandes ****************************** */

    public function postSave() {
        $this->createCommands();
    }

    public function createCommands() {
        $commands = [
            'Prev'         => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-step-backward"></i>', 'order' => 1],
            'Play'         => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-play"></i>', 'order' => 2],
            'Pause'        => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-pause"></i>', 'order' => 3],
            'Play_Pause'   => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-play-circle"></i>', 'order' => 4, 'name' => 'Toggle Play/Pause'],
            'Next'         => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-step-forward"></i>', 'order' => 5],
            'Stop'         => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-stop"></i>', 'order' => 6],
            'Title'        => ['type' => 'info', 'subtype' => 'string', 'order' => 7],
            'Status'       => ['type' => 'info', 'subtype' => 'string', 'order' => 8],
            'Duration'     => ['type' => 'info', 'subtype' => 'string', 'order' => 9],
            'Position'     => ['type' => 'info', 'subtype' => 'string', 'order' => 10],
            'Remaining'    => ['type' => 'info', 'subtype' => 'string', 'order' => 11],
            'Cover'        => ['type' => 'info', 'subtype' => 'string', 'order' => 12],
            'Duration_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 13, 'name' => 'Durée (Scenario)'],
            'Position_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 14, 'name' => 'Position (Scenario)'],
            'Remaining_Num'=> ['type' => 'info', 'subtype' => 'string', 'order' => 15, 'name' => 'Restant (Scenario)'],
            'Media_Type'   => ['type' => 'info', 'subtype' => 'string', 'order' => 16, 'name' => 'Type de média'],
            'Set_Position' => ['type' => 'action', 'subtype' => 'slider', 'order' => 99, 'name' => 'Définir Position', 'isVisible' => 0],
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
    }

    public function remoteControl($commandName, $_options = null) {
        $ip       = config::byKey('jellyfin_ip', 'jellyfin');
        $port     = config::byKey('jellyfin_port', 'jellyfin');
        $apikey   = config::byKey('jellyfin_apikey', 'jellyfin');
        $deviceId = $this->getConfiguration('device_id');
        
        if (empty($ip) || empty($deviceId)) return;

        $baseUrl = (strpos($ip, 'http') === false) ? 'http://' . $ip . ':' . $port : $ip . ':' . $port;
        $sessionData = self::getSessionDataFromDeviceId($baseUrl, $apikey, $deviceId);

        if (!$sessionData || !isset($sessionData['Id'])) return;

        $sessionId = $sessionData['Id'];
        $isPaused = isset($sessionData['PlayState']['IsPaused']) ? $sessionData['PlayState']['IsPaused'] : false;

        if ($commandName == 'set_position') {
            $seconds = isset($_options['slider']) ? $_options['slider'] : (isset($_options['value']) ? $_options['value'] : null);
            if ($seconds !== null) {
                $ticks = $seconds * 10000000;
                $url = $baseUrl . '/Sessions/' . $sessionId . '/Playing/Seek?seekPositionTicks=' . $ticks . '&api_key=' . $apikey;
                self::requestApi($url, 'POST');
            }
            return;
        }

        $action = '';
        switch ($commandName) {
            case 'play':       $action = 'Unpause'; break;
            case 'pause':      $action = 'Pause'; break; 
            case 'play_pause': $action = 'PlayPause'; break;
            case 'stop':       $action = 'Stop'; break;
            case 'next':       $action = 'NextTrack'; break;
            case 'prev':       $action = 'PreviousTrack'; break;
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

    public static function requestApi($url, $method = 'GET', $data = null, $binary = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        if (!$binary) curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            } else {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Length: 0']);
            }
        }
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 400) return null;
        if ($binary) return $result;
        return json_decode($result, true);
    }

    /*     * ************************* Affichage ****************************** */

    public function toHtml($_version = 'dashboard') {
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) return $replace;
        $version = jeedom::versionAlias($_version);
        if ($this->getDisplay('hideOn' . $version) == 1) return '';

        // --- GESTION DU LISERÉ ---
        if ($this->getConfiguration('widget_border_enable', 0) == 1) {
            $replace['#widget_border_color#'] = $this->getConfiguration('widget_border_color', '#e5e5e5');
        } else {
            $replace['#widget_border_color#'] = 'transparent';
        }
        
        foreach ($this->getCmd('info') as $cmd) {
            $replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
            $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
        }
        foreach ($this->getCmd('action') as $cmd) {
            $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
        }
        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'jellyfin', 'jellyfin')));
    }
}

class jellyfinCmd extends cmd {
    public function execute($_options = null) {
        $this->getEqLogic()->remoteControl($this->getLogicalId(), $_options);
    }
}