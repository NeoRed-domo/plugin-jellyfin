<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class jellyfin extends eqLogic {

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
            if ($pid != '') posix_kill($pid, 9);
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

        // Liste pour suivre les appareils actifs
        $activeDevices = array();

        foreach ($sessions as $sessionData) {
            $deviceId = '';
            if (isset($sessionData['device_id'])) $deviceId = $sessionData['device_id'];
            elseif (isset($sessionData['DeviceId'])) $deviceId = $sessionData['DeviceId'];
            
            $clientName = isset($sessionData['client']) ? $sessionData['client'] : (isset($sessionData['Client']) ? $sessionData['Client'] : 'Jellyfin Device');
            
            if (empty($deviceId)) continue;

            // 1. On l'ajoute à la liste des "VIVANTS" pour que le nettoyeur ne le tue pas.
            $activeDevices[] = (string)$deviceId;

            // Vérification contrôlable
            $isControllable = false;
            if (isset($sessionData['SupportsRemoteControl']) && $sessionData['SupportsRemoteControl'] === true) $isControllable = true;
            if (isset($sessionData['supports_remote_control']) && $sessionData['supports_remote_control'] === true) $isControllable = true;

            // Récupération de l'équipement (s'il existe)
            $logicalId = (strlen($deviceId) > 120) ? md5($deviceId) : $deviceId;
            $eqLogic = self::byLogicalId($logicalId, 'jellyfin');

            // 2. LOGIQUE DE FILTRAGE INTELLIGENTE
            // Si pas contrôlable et n'existe pas -> On ignore (pas de création polluante)
            if (!$isControllable) {
                if (!is_object($eqLogic)) {
                    continue; 
                }
                // Si existe déjà -> On continue pour la mise à jour
            }

            // Création si nécessaire (et si autorisé par le filtre ci-dessus)
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
                        $htmlImg = '<img class="img-responsive" style="border-radius: 10px;" src="data:' . $mimeType . ';base64,' . base64_encode($imgData) . '">';
                        $eqLogic->checkAndUpdateCmd('cover', $htmlImg);
                        $eqLogic->setConfiguration('last_image_id', $imageItemId);
                        $eqLogic->save();
                    }
                }
            }
            // Update Play/Pause Icon
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
            if ($jellyfinEq->getIsEnable() == 1) {
                $confDevId = (string)$jellyfinEq->getConfiguration('device_id');
                
                // Si l'équipement n'est pas dans la liste des actifs
                if (!in_array($confDevId, $activeDevices)) {
                    $currentStatus = $jellyfinEq->getCmd(null, 'status')->execCmd();
                    if ($currentStatus != 'Stopped') {
                        $jellyfinEq->checkAndUpdateCmd('status', 'Stopped');
                        $jellyfinEq->checkAndUpdateCmd('title', '');
                        $jellyfinEq->checkAndUpdateCmd('media_type', '');
                        $jellyfinEq->checkAndUpdateCmd('duration', '00:00:00');
                        $jellyfinEq->checkAndUpdateCmd('position', '00:00:00');
                        $jellyfinEq->checkAndUpdateCmd('remaining', '00:00:00');
                        
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
        // RETOUR EN FRANÇAIS POUR L'AFFICHAGE
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
    public function postSave() { $this->createCommands(); }

    public function createCommands() {
        // NOMS DES COMMANDES EN ANGLAIS
        $commands = [
            'Prev' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-step-backward"></i>', 'order' => 1],
            'Play' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-play"></i>', 'order' => 2],
            'Pause' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-pause"></i>', 'order' => 3],
            'Play_Pause' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-play-circle"></i>', 'order' => 4, 'name' => 'Toggle Play/Pause'],
            'Next' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-step-forward"></i>', 'order' => 5],
            'Stop' => ['type' => 'action', 'subtype' => 'other', 'icon' => '<i class="fas fa-stop"></i>', 'order' => 6],
            'Title' => ['type' => 'info', 'subtype' => 'string', 'order' => 7],
            'Status' => ['type' => 'info', 'subtype' => 'string', 'order' => 8],
            'Duration' => ['type' => 'info', 'subtype' => 'string', 'order' => 9],
            'Position' => ['type' => 'info', 'subtype' => 'string', 'order' => 10],
            'Remaining' => ['type' => 'info', 'subtype' => 'string', 'order' => 11],
            'Cover' => ['type' => 'info', 'subtype' => 'string', 'order' => 12],
            'Duration_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 13, 'name' => 'Duration (Scenario)'],
            'Position_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 14, 'name' => 'Position (Scenario)'],
            'Remaining_Num' => ['type' => 'info', 'subtype' => 'string', 'order' => 15, 'name' => 'Remaining (Scenario)'],
            'Media_Type' => ['type' => 'info', 'subtype' => 'string', 'order' => 16, 'name' => 'Media Type'],
            'Set_Position' => ['type' => 'action', 'subtype' => 'slider', 'order' => 99, 'name' => 'Set Position', 'isVisible' => 0],
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
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        $deviceId = $this->getConfiguration('device_id');
        if (empty($ip) || empty($deviceId)) return;
        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        $sessionData = self::getSessionDataFromDeviceId($baseUrl, $apikey, $deviceId);
        if (!$sessionData || !isset($sessionData['Id'])) return;
        $sessionId = $sessionData['Id'];
        
        if ($commandName == 'set_position') {
            $seconds = isset($_options['slider']) ? $_options['slider'] : (isset($_options['value']) ? $_options['value'] : null);
            if ($seconds !== null) {
                $ticks = $seconds * 10000000;
                $url = $baseUrl . '/Sessions/' . $sessionId . '/Playing/Seek?seekPositionTicks=' . $ticks . '&api_key=' . $apikey;
                self::requestApi($url, 'POST');
            }
            return;
        }

        $cmd = $this->getCmd(null, $commandName);
        if (is_object($cmd)) {
            $mediaId = $cmd->getConfiguration('media_id');
            if (!empty($mediaId)) {
                $this->playMedia($mediaId, 'play_now');
                return;
            }
        }

        $action = '';
        switch ($commandName) {
            case 'play': $action = 'Unpause'; break;
            case 'pause': $action = 'Pause'; break;
            case 'play_pause': $action = 'PlayPause'; break;
            case 'stop': $action = 'Stop'; break;
            case 'next': $action = 'NextTrack'; break;
            case 'prev': $action = 'PreviousTrack'; break;
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
            // Si c'est un raccourci média
            if ($cmd->getConfiguration('is_media_shortcut') == 1) {
                $mediaId = $cmd->getConfiguration('media_id');
                $imgTag = $cmd->getConfiguration('image_tag');
                $name = str_replace('Lancer : ', '', $cmd->getName());
                // Compatibilité Play :
                $name = str_replace('Play : ', '', $name);
                
                $imgUrl = self::getItemImageUrl($mediaId, $imgTag);
                
                // HTML simplifié : Image directe dans .shortcut-item
                $shortcutsHtml .= '<div class="shortcut-item cursor" onclick="jeedom.cmd.execute({id: \'' . $cmd->getId() . '\'});" title="' . $name . '">';
                $shortcutsHtml .= '  <i class="fas fa-times-circle delete-shortcut-btn" data-cmd_id="' . $cmd->getId() . '" title="Supprimer"></i>';
                $shortcutsHtml .= '  <img src="' . $imgUrl . '">'; // Plus de div wrapper inutile
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

    public static function getLibraryItems($parentId = '') {
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        if (empty($ip) || empty($apikey)) return ['error' => 'Configuration incomplète'];
        $userId = self::getPrimaryUserId();
        if (!$userId) return ['error' => 'Aucun utilisateur Jellyfin trouvé'];
        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        
        $url = $baseUrl . '/Users/' . $userId . '/Items?api_key=' . $apikey;
        if (!empty($parentId)) $url .= '&ParentId=' . $parentId;
        
        $url .= '&Fields=Overview,ProductionYear,CommunityRating,PremiereDate,RunTimeTicks';
        $url .= '&SortBy=IsFolder,SortName&SortOrder=Descending,Ascending';
        
        return self::requestApi($url);
    }

    public static function getItemImageUrl($itemId, $tag = null) {
        $url = "plugins/jellyfin/core/php/proxy.php?itemId=" . $itemId . "&maxWidth=400";
        if ($tag) {
            $url .= "&tag=" . $tag;
        }
        return $url; 
    }
    
    // --- GESTION DE LA LECTURE CORRIGÉE (ANDROID TV FIX - 300ms) ---
    public function playMedia($mediaId, $mode = 'play_now') {
        $ip = config::byKey('jellyfin_ip', 'jellyfin');
        $port = config::byKey('jellyfin_port', 'jellyfin');
        $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
        $deviceId = $this->getConfiguration('device_id');
        
        if (empty($ip) || empty($deviceId)) return ['error' => 'Configuration invalide'];

        $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
        $sessionData = self::getSessionDataFromDeviceId($baseUrl, $apikey, $deviceId);

        if (!$sessionData || !isset($sessionData['Id'])) {
            return ['error' => 'Session inactive (lecteur éteint ?)'];
        }
        
        $sessionId = $sessionData['Id'];
        
        log::add('jellyfin', 'debug', 'PlayMedia demandé. Mode: ' . $mode . ' - MediaId: ' . $mediaId);

        // --- ANDROID TV FIX : Si le lecteur joue déjà un média et qu'on veut "Play Now", on STOP d'abord.
        if ($mode == 'play_now' && isset($sessionData['NowPlayingItem'])) {
            log::add('jellyfin', 'debug', 'Lecture en cours détectée. Envoi du STOP forcé (Fix Android TV).');
            $urlStop = $baseUrl . '/Sessions/' . $sessionId . '/Playing/Stop?api_key=' . $apikey;
            self::requestApi($urlStop, 'POST');
            // Pause de 300ms pour Android TV
            usleep(300000); 
        }

        if ($mode == 'queue_next') {
            $url = $baseUrl . '/Sessions/' . $sessionId . '/Queue?ItemIds=' . $mediaId . '&Mode=PlayNext&api_key=' . $apikey;
        } else {
            $playCommand = 'PlayNow';
            $url = $baseUrl . '/Sessions/' . $sessionId . '/Playing?ItemIds=' . $mediaId . '&PlayCommand=' . $playCommand . '&api_key=' . $apikey;
        }
        
        log::add('jellyfin', 'debug', 'Appel URL: ' . $url);
        
        self::requestApi($url, 'POST');
        return ['state' => 'ok'];
    }

    public function createMediaCommand($mediaId, $mediaName, $imgTag = null) {
        $cleanName = str_replace(' ', '_', $mediaName);
        $cleanName = preg_replace('/[^A-Za-z0-9_]/', '', $cleanName);
        $logicalId = 'media_' . $mediaId; 

        $cmd = $this->getCmd(null, $logicalId);
        if (is_object($cmd)) return "Cette commande existe déjà";

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
        return "Commande créée avec succès";
    }
}

class jellyfinCmd extends cmd {
    public function execute($_options = null) {
        $this->getEqLogic()->remoteControl($this->getLogicalId(), $_options);
    }
}
?>