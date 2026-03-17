# Normalisation Audio — Plan d'implémentation

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Normalisation LUFS des séances de diffusion avec calibration audio, profils (Nuit/Cinéma/THX), et analyse ffmpeg asynchrone.

**Architecture:** Calibration par lecteur (volume + LUFS de référence), analyse LUFS via ffmpeg en streaming depuis Jellyfin, calcul du volume par clip avec offsets par section et profil, stocké dans session_data. Progression asynchrone via cache Jeedom + polling JS.

**Tech Stack:** PHP 7+, JavaScript (jQuery), ffmpeg (soft dependency), API Jellyfin streaming, cache Jeedom.

**Spec:** `docs/specs/2026-03-17-audio-normalization-design.md`

---

## File Map

| Fichier | Responsabilité | Tâches |
|---------|---------------|--------|
| `resources/install_apt.sh` | Installation ffmpeg | T1 |
| `plugin_info/configuration.php` | Config offsets sections + profils audio | T2 |
| `core/class/jellyfin.class.php` | Commandes profil, applyVolume LUFS, analyzeLufs | T3, T4 |
| `core/ajax/jellyfin.ajax.php` | AJAX calibration + normalisation + capture | T5 |
| `desktop/php/jellyfin.php` | UI commande info ampli, bouton calibration | T6 |
| `desktop/js/jellyfin.js` | Page calibration, normalisation, affichage volume | T6, T7 |

**Ordre d'exécution :** T1 → T2 → T3 → T4 → T5 → T6 → T7

---

## Task 1 : Dépendance ffmpeg

**Fichiers :**
- Modifier : `resources/install_apt.sh`

- [ ] **Step 1.1 : Ajouter ffmpeg dans le script d'installation**

Après la ligne `sudo apt-get install -y python3 python3-pip python3-requests`, ajouter :

```bash
echo 50 > ${PROGRESS_FILE}
echo "Installation de ffmpeg..."
sudo apt-get install -y ffmpeg
```

- [ ] **Step 1.2 : Ajouter une méthode helper pour vérifier ffmpeg**

Dans `core/class/jellyfin.class.php`, ajouter dans la section Helpers :

```php
public static function isFfmpegAvailable() {
    exec('which ffmpeg 2>/dev/null', $output, $returnVar);
    return ($returnVar == 0);
}
```

- [ ] **Step 1.3 : Commit**

```bash
git add resources/install_apt.sh core/class/jellyfin.class.php
git commit -m "feat(audio): add ffmpeg soft dependency + availability check"
```

---

## Task 2 : Configuration plugin — Offsets sections + Profils audio

**Fichiers :**
- Modifier : `plugin_info/configuration.php`

- [ ] **Step 2.1 : Ajouter la section Offsets audio par section**

Après la section "Timings d'enchaînement", ajouter :

```php
<fieldset>
    <legend><i class="fas fa-volume-up"></i> {{Normalisation audio — Offsets par section (dB)}}</legend>
    <div class="alert alert-info">
        {{Offset en dB appliqué à chaque section par rapport au volume de référence. 0 = même volume que la référence. Négatif = plus bas.}}
    </div>
    <?php
    $audioOffsets = [
        'audio_offset_preparation'   => ['Préparation', -12],
        'audio_offset_intro'         => ['Intro', -12],
        'audio_offset_pubs'          => ['Publicités', -12],
        'audio_offset_trailers'      => ['Bandes annonces', -8],
        'audio_offset_short_film'    => ['Court métrage', -4],
        'audio_offset_audio_trailer' => ['Trailer audio', 0],
        'audio_offset_film'          => ['Film', 0]
    ];
    foreach ($audioOffsets as $key => $info) {
        echo '<div class="form-group">';
        echo '  <label class="col-sm-3 control-label">{{' . $info[0] . '}}</label>';
        echo '  <div class="col-sm-2">';
        echo '    <div class="input-group">';
        echo '      <input class="configKey form-control" data-l1key="' . $key . '" placeholder="' . $info[1] . '" type="number" />';
        echo '      <span class="input-group-addon">dB</span>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }
    ?>
</fieldset>
```

- [ ] **Step 2.2 : Ajouter la section Profils audio**

```php
<fieldset>
    <legend><i class="fas fa-headphones"></i> {{Profils audio (dB)}}</legend>
    <div class="alert alert-info">
        {{Offset global appliqué sur tous les volumes. Cinéma = référence (0dB). Pilotable par scénario Jeedom via la commande du lecteur.}}
    </div>
    <div class="form-group">
        <label class="col-sm-3 control-label">{{Nuit}}</label>
        <div class="col-sm-2">
            <div class="input-group">
                <input class="configKey form-control" data-l1key="audio_profile_night" placeholder="-20" type="number" />
                <span class="input-group-addon">dB</span>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-3 control-label">{{Cinéma (référence)}}</label>
        <div class="col-sm-2">
            <div class="input-group">
                <input class="configKey form-control" data-l1key="audio_profile_cinema" placeholder="0" type="number" />
                <span class="input-group-addon">dB</span>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-3 control-label">{{THX}}</label>
        <div class="col-sm-2">
            <div class="input-group">
                <input class="configKey form-control" data-l1key="audio_profile_thx" placeholder="10" type="number" />
                <span class="input-group-addon">dB</span>
            </div>
        </div>
    </div>
</fieldset>
```

- [ ] **Step 2.3 : Commit**

```bash
git add plugin_info/configuration.php
git commit -m "feat(audio): plugin config - section offsets and audio profiles"
```

---

## Task 3 : Commandes profil audio + createCommands

**Fichiers :**
- Modifier : `core/class/jellyfin.class.php`

- [ ] **Step 3.1 : Ajouter les commandes audio_profile dans createCommands()**

Après la commande `Set_Position` dans le tableau `$commands`, ajouter :

```php
'Audio_Profile' => ['type' => 'info', 'subtype' => 'string', 'order' => 17, 'name' => __('Profil audio', __FILE__)],
'Set_Audio_Profile' => ['type' => 'action', 'subtype' => 'select', 'order' => 18, 'name' => __('Changer profil audio', __FILE__), 'isVisible' => 0],
```

Après la boucle `foreach ($commands ...)`, ajouter la configuration du select et l'init de la valeur :

```php
$setProfile = $this->getCmd('action', 'set_audio_profile');
if (is_object($setProfile)) {
    $profileInfo = $this->getCmd('info', 'audio_profile');
    if (is_object($profileInfo)) {
        $setProfile->setValue($profileInfo->getId());
    }
    $setProfile->setConfiguration('listValue', 'night|Nuit;cinema|Cinéma;thx|THX');
    $setProfile->save();
    // Init valeur par défaut
    if (is_object($profileInfo) && $profileInfo->execCmd() == '') {
        $this->checkAndUpdateCmd('audio_profile', 'cinema');
    }
}
```

- [ ] **Step 3.2 : Gérer set_audio_profile dans jellyfinCmd::execute()**

Dans la méthode `execute()` de `jellyfinCmd`, avant le `remoteControl`, ajouter :

```php
if ($logicalId == 'set_audio_profile') {
    $value = isset($_options['select']) ? $_options['select'] : '';
    if (in_array($value, ['night', 'cinema', 'thx'])) {
        $eqLogic->checkAndUpdateCmd('audio_profile', $value);
        log::add('jellyfin', 'info', 'Profil audio changé: ' . $value);
    }
    return;
}
```

- [ ] **Step 3.3 : Commit**

```bash
git add core/class/jellyfin.class.php
git commit -m "feat(audio): audio profile commands (set_audio_profile / audio_profile)"
```

---

## Task 4 : applyVolume avec LUFS + analyzeLufs

**Fichiers :**
- Modifier : `core/class/jellyfin.class.php`

- [ ] **Step 4.1 : Mettre à jour applyVolume avec la formule LUFS**

Remplacer la méthode `applyVolume` :

```php
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
        // volume_auto a été calculé avec profil cinema (0dB) — ajouter uniquement l'offset du profil actif
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
```

- [ ] **Step 4.2 : Mettre à jour les 3 call sites de applyVolume**

Chaque appel doit passer le `$sectionKey` :

1. Resync dans tickCinema (~ligne 1120) : `self::applyVolume($playerEq, $foundTriggers[$found['index']], $found['section']);`
2. skipToNextTrigger (~ligne 1256) — normaliser 'playlist' en 'commercial' :
   ```php
   $sectionKey = ($next['section'] == 'playlist') ? 'commercial' : $next['section'];
   self::applyVolume($playerEq, $next['trigger'], $sectionKey);
   ```
3. executeNonMediaTriggers (~ligne 1623) — un seul call site, conditionnel :
   ```php
   $sectionKey = ($sessionType == 'commercial') ? 'commercial' : $engineState['current_section'];
   self::applyVolume($playerEq, $trigger, $sectionKey);
   ```

- [ ] **Step 4.3 : Ajouter la méthode analyzeLufs()**

```php
public static function analyzeLufs($mediaId, $mode = 'quick') {
    if (!self::isFfmpegAvailable()) {
        return ['error' => 'ffmpeg non installé'];
    }

    $config = self::getBaseConfig();
    if (!$config) return ['error' => 'Configuration Jellyfin incomplète'];

    // Vérifier le cache
    $cacheKey = 'jellyfin::lufs::' . $mediaId;
    $cached = cache::byKey($cacheKey)->getValue(null);
    if ($cached !== null && $mode != 'force') {
        return ['lufs' => (float)$cached, 'cached' => true];
    }

    // Construire l'URL de streaming
    $streamUrl = $config['baseUrl'] . '/Videos/' . $mediaId . '/stream?static=true&api_key=' . $config['apikey'];

    // Pour le mode rapide, seek au milieu
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
    }

    // Commande ffmpeg
    $timeLimit = ($mode == 'quick') ? '-t 60' : '';
    $cmd = 'curl -s "' . $streamUrl . '" | ffmpeg -i pipe:0 -vn ' . $timeLimit . ' -af loudnorm=print_format=json -f null - 2>&1';

    $output = [];
    exec($cmd, $output, $returnVar);
    $fullOutput = implode("\n", $output);

    // Parser le LUFS
    if (preg_match('/"input_i"\s*:\s*"([^"]+)"/', $fullOutput, $matches)) {
        $lufs = (float)$matches[1];
        cache::set($cacheKey, $lufs);
        return ['lufs' => $lufs, 'cached' => false];
    }

    return ['error' => 'Impossible de mesurer le LUFS', 'output' => substr($fullOutput, -500)];
}
```

- [ ] **Step 4.4 : Ajouter la méthode calculateAutoVolume()**

```php
public static function calculateAutoVolume($playerEq, $clipLufs, $sectionKey) {
    $refVolume = (float)$playerEq->getConfiguration('audio_ref_volume', 0);
    $refLufs = (float)$playerEq->getConfiguration('audio_ref_lufs', -23);
    $sectionOffset = (float)config::byKey('audio_offset_' . $sectionKey, 'jellyfin', 0);
    // Profil cinema (0dB) pour le calcul de base — le profil actif est appliqué dynamiquement dans applyVolume
    $volume = $refVolume + ($refLufs - $clipLufs) + $sectionOffset;
    return (int)max(0, min(100, $volume));
}
```

- [ ] **Step 4.5 : Commit**

```bash
git add core/class/jellyfin.class.php
git commit -m "feat(audio): applyVolume with LUFS formula + analyzeLufs + calculateAutoVolume"
```

---

## Task 5 : API AJAX — Calibration + Normalisation

**Fichiers :**
- Modifier : `core/ajax/jellyfin.ajax.php`

- [ ] **Step 5.1 : Action save_calibration**

```php
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
```

- [ ] **Step 5.2 : Action capture_amp_volume**

```php
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
```

- [ ] **Step 5.3 : Action analyze_lufs (single media)**

```php
if (init('action') == 'analyze_lufs') {
    $mediaId = init('mediaId');
    $mode = init('mode', 'quick');
    $force = init('force', 0);
    if (empty($mediaId)) throw new Exception(__('ID média requis', __FILE__));
    $result = jellyfin::analyzeLufs($mediaId, $force ? 'force' : $mode);
    if (isset($result['error'])) throw new Exception($result['error']);
    ajax::success($result);
}
```

- [ ] **Step 5.4 : Action analyze_session_audio (async, tous les clips)**

```php
if (init('action') == 'analyze_session_audio') {
    $eqLogic = jellyfin::byId(init('id'));
    if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') == '') {
        throw new Exception(__('Séance introuvable', __FILE__));
    }
    $mode = init('mode', 'quick');
    set_time_limit(0); // Analyse longue — pas de timeout PHP
    $sessionData = $eqLogic->getConfiguration('session_data');
    $sessionType = $eqLogic->getConfiguration('session_type');
    $playerId = $sessionData['player_id'] ?? null;
    $playerEq = jellyfin::byId($playerId);
    if (!is_object($playerEq)) throw new Exception(__('Lecteur introuvable', __FILE__));

    // Collecter tous les triggers média
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

    // Initialiser la progression
    $progressKey = 'jellyfin::audio_analysis::' . $eqLogic->getId();
    cache::set($progressKey, json_encode([
        'status' => 'analyzing',
        'current_clip' => '',
        'current_index' => 0,
        'total_clips' => count($mediaList),
        'results' => [],
        'errors' => []
    ]));

    // Lancer l'analyse séquentielle
    $results = [];
    $errors = [];
    foreach ($mediaList as $i => $item) {
        // Update progression
        cache::set($progressKey, json_encode([
            'status' => 'analyzing',
            'current_clip' => $item['name'],
            'current_index' => $i + 1,
            'total_clips' => count($mediaList),
            'results' => $results,
            'errors' => $errors
        ]));

        // Pause min 1s pour affichage
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

            // Mettre à jour le trigger dans session_data
            if ($sessionType == 'cinema') {
                $sessionData['sections'][$item['section']]['triggers'][$item['index']]['lufs'] = $lufs;
                $sessionData['sections'][$item['section']]['triggers'][$item['index']]['volume_auto'] = $volumeAuto;
            } else {
                $sessionData['playlist'][$item['index']]['lufs'] = $lufs;
                $sessionData['playlist'][$item['index']]['volume_auto'] = $volumeAuto;
            }
        }
    }

    // Marquer comme calibré seulement si au moins un clip a été analysé
    $sessionData['audio_calibrated'] = (count($results) > 0);
    $eqLogic->setConfiguration('session_data', $sessionData);
    $eqLogic->save();

    // Progression finale
    cache::set($progressKey, json_encode([
        'status' => 'done',
        'current_clip' => '',
        'current_index' => count($mediaList),
        'total_clips' => count($mediaList),
        'results' => $results,
        'errors' => $errors
    ]));

    ajax::success(['analyzed' => count($results), 'errors' => count($errors)]);
}
```

- [ ] **Step 5.5 : Action get_analysis_progress**

```php
if (init('action') == 'get_analysis_progress') {
    $sessionId = init('id');
    $progressKey = 'jellyfin::audio_analysis::' . $sessionId;
    $progress = cache::byKey($progressKey)->getValue(null);
    if ($progress) {
        ajax::success(json_decode($progress, true));
    } else {
        ajax::success(['status' => 'idle']);
    }
}
```

- [ ] **Step 5.6 : Action check_ffmpeg**

```php
if (init('action') == 'check_ffmpeg') {
    ajax::success(['available' => jellyfin::isFfmpegAvailable()]);
}
```

- [ ] **Step 5.7 : Commit**

```bash
git add core/ajax/jellyfin.ajax.php
git commit -m "feat(audio): AJAX API - calibration, LUFS analysis, progress, ffmpeg check"
```

---

## Task 6 : UI — Config lecteur + Page calibration

**Fichiers :**
- Modifier : `desktop/php/jellyfin.php`
- Modifier : `desktop/js/jellyfin.js`

- [ ] **Step 6.1 : Ajouter le champ commande info volume ampli dans la config lecteur**

Dans `desktop/php/jellyfin.php`, après le bloc `amp_default_volume`, ajouter :

```php
<div class="form-group device-only">
    <label class="col-sm-3 control-label"><?php echo __('Commande info volume ampli', __FILE__); ?></label>
    <div class="col-sm-4">
        <input type="hidden" class="eqLogicAttr" data-l1key="configuration" data-l2key="amp_volume_info_cmd_id" id="amp_volume_info_cmd_id" />
        <div class="input-group">
            <input type="text" class="form-control" id="amp_volume_info_cmd_display" readonly placeholder="<?php echo __('Aucun (optionnel)', __FILE__); ?>" />
            <span class="input-group-btn">
                <button class="btn btn-default" type="button" id="bt_pick_amp_volume_info"><i class="fas fa-list-alt"></i></button>
                <button class="btn btn-default" type="button" id="bt_clear_amp_volume_info"><i class="fas fa-times"></i></button>
            </span>
        </div>
        <span class="help-block"><?php echo __('Commande info pour lire le volume actuel (optionnel, pour la calibration).', __FILE__); ?></span>
    </div>
</div>
```

- [ ] **Step 6.2 : Ajouter le bouton "Calibration audio" dans la barre de gestion**

Dans la section eqLogicThumbnailContainer de gestion, ajouter :

```php
<div class="cursor eqLogicAction logoSecondary" data-action="audio_calibration">
    <i class="fas fa-volume-up"></i>
    <br>
    <span><?php echo __('Calibration audio', __FILE__); ?></span>
</div>
```

- [ ] **Step 6.3 : Ajouter le JS pour le picker info volume + affichage dans printEqLogic**

Dans le script de `jellyfin.php`, ajouter les handlers pour le picker info volume (même pattern que le picker action volume existant) et l'affichage du nom dans `printEqLogic`.

- [ ] **Step 6.4 : Ajouter l'objet AudioCalibration dans jellyfin.js**

Objet JS complet pour la page de calibration :
- `open()` : ouvre une modale bootbox fullscreen
- Dropdown lecteurs (appareils avec ampli configuré)
- Sélection média de référence via JellyfinBrowser
- Boutons lecture en boucle / arrêt
- Capture volume ampli (AJAX `capture_amp_volume`) + champ éditable
- Analyse LUFS (AJAX `analyze_lufs`) avec spinner
- Sauvegarde (AJAX `save_calibration`)
- Affichage statut calibration par lecteur

- [ ] **Step 6.5 : Commit**

```bash
git add desktop/php/jellyfin.php desktop/js/jellyfin.js
git commit -m "feat(audio): calibration page UI + amp info volume config"
```

---

## Task 7 : UI — Normalisation séance + Affichage volume auto/forcé

**Fichiers :**
- Modifier : `desktop/js/jellyfin.js`

- [ ] **Step 7.1 : Ajouter le bouton "Normaliser le son" dans l'éditeur**

Dans `renderCinema()` et `renderCommercial()`, ajouter le bouton dans la barre d'actions (à côté de "Calibrer tops") :

```javascript
html += '    <button class="btn btn-sm btn-default" onclick="SessionEditor.normalizeAudio()" id="normalize-audio-btn"><i class="fas fa-volume-up"></i> ' + _t('Normaliser le son') + '</button>';
```

Le bouton est grisé si : pas de calibration lecteur OU ffmpeg non installé.
Badge "✓ Son normalisé" affiché si `session_data.audio_calibrated == true`.

- [ ] **Step 7.2 : Ajouter la méthode normalizeAudio dans SessionEditor**

```javascript
normalizeAudio: function() {
    // Vérifier ffmpeg
    $.ajax({
        type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
        data: { action: 'check_ffmpeg' }, dataType: 'json',
        success: function(data) {
            if (data.state != 'ok' || !data.result.available) {
                bootbox.alert(_t('ffmpeg n\'est pas installé. Lancez l\'installation des dépendances.'));
                return;
            }
            // Choix du mode
            bootbox.dialog({
                title: '<i class="fas fa-volume-up"></i> ' + _t('Normalisation audio'),
                message: _t('Choisissez le mode d\'analyse :'),
                buttons: {
                    quick: { label: _t('Analyse rapide (~10-30s/clip)'), className: 'btn-primary',
                        callback: function() { SessionEditor._runAnalysis('quick'); } },
                    complete: { label: _t('Analyse complète (~30-60s/clip)'), className: 'btn-default',
                        callback: function() { SessionEditor._runAnalysis('complete'); } },
                    cancel: { label: _t('Annuler'), className: 'btn-default' }
                }
            });
        }
    });
},

_runAnalysis: function(mode) {
    // Lancer l'analyse + afficher la modale de progression
    var progressHtml = '<div id="audio-analysis-progress" style="padding:20px;">' +
        '<div style="text-align:center; margin-bottom:15px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>' +
        '<div id="analysis-status" style="color:#aaa; text-align:center;">Démarrage...</div>' +
        '<div style="margin-top:10px; height:6px; background:#333; border-radius:3px;">' +
        '  <div id="analysis-bar" style="height:100%; background:#1DB954; border-radius:3px; width:0%; transition:width 0.5s;"></div>' +
        '</div>' +
        '<div id="analysis-log" style="margin-top:15px; max-height:200px; overflow-y:auto; font-size:11px; color:#888;"></div>' +
        '</div>';

    var modal = bootbox.dialog({
        title: '<i class="fas fa-volume-up"></i> ' + _t('Analyse audio en cours'),
        message: progressHtml,
        closeButton: false,
        buttons: {}
    });

    // Lancer l'analyse AJAX (non-bloquant côté serveur = bloquant mais le polling suit)
    $.ajax({
        type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
        data: { action: 'analyze_session_audio', id: SessionEditor.eqLogicId, mode: mode },
        dataType: 'json', global: false, timeout: 600000, // 10min max
        success: function(data) {
            // Fin de l'analyse
        }
    });

    // Polling progression
    SessionEditor._analysisPoll = setInterval(function() {
        $.ajax({
            type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'get_analysis_progress', id: SessionEditor.eqLogicId },
            dataType: 'json', global: false,
            success: function(data) {
                if (data.state != 'ok') return;
                var p = data.result;
                if (p.status == 'analyzing') {
                    var pct = p.total_clips > 0 ? Math.round((p.current_index / p.total_clips) * 100) : 0;
                    $('#analysis-bar').css('width', pct + '%');
                    $('#analysis-status').html('<i class="fas fa-music"></i> ' + p.current_clip + ' (' + p.current_index + '/' + p.total_clips + ')');
                    // Log des résultats
                    var logHtml = '';
                    (p.results || []).forEach(function(r) { logHtml += '<div style="color:#1DB954;">✓ LUFS: ' + r.lufs.toFixed(1) + ' → vol: ' + r.volume_auto + '</div>'; });
                    (p.errors || []).forEach(function(e) { logHtml += '<div style="color:#e74c3c;">✗ ' + e.name + ': ' + e.error + '</div>'; });
                    $('#analysis-log').html(logHtml);
                } else if (p.status == 'done') {
                    clearInterval(SessionEditor._analysisPoll);
                    var total = (p.results || []).length;
                    var errs = (p.errors || []).length;
                    $('#analysis-bar').css('width', '100%');
                    $('#analysis-status').html('<i class="fas fa-check" style="color:#1DB954;"></i> ' + total + ' clip(s) normalisé(s)' + (errs > 0 ? ' (' + errs + ' erreur(s))' : ''));
                    setTimeout(function() { bootbox.hideAll(); SessionEditor.reload(); }, 2000);
                }
            }
        });
    }, 2000);
},
```

- [ ] **Step 7.3 : Mettre à jour renderTriggerList pour afficher volume auto/forcé**

Remplacer le bloc d'affichage volume existant par :

```javascript
if (t.type == 'media') {
    if (t.volume !== undefined && t.volume !== null && t.volume !== '') {
        // Override manuel (orange)
        html += '  <span class="cursor" style="color:#f39c12; font-size:10px;" onclick="..." title="Volume forcé"><i class="fas fa-volume-up"></i> ' + t.volume + '</span>';
    } else if (t.volume_auto !== undefined && t.volume_auto !== null && t.volume_auto !== '') {
        // Volume auto LUFS (vert)
        html += '  <span class="cursor" style="color:#1DB954; font-size:10px;" onclick="..." title="Volume auto (LUFS)"><i class="fas fa-volume-up"></i> auto:' + t.volume_auto + '</span>';
    } else {
        // Pas de volume (gris)
        html += '  <span class="cursor" style="color:#555; font-size:10px;" onclick="..." title="Définir le volume"><i class="fas fa-volume-off"></i></span>';
    }
}
```

- [ ] **Step 7.4 : Commit**

```bash
git add desktop/js/jellyfin.js
git commit -m "feat(audio): normalize button, async progress, volume auto/forced display"
```

---

## Résumé des commits

| # | Message |
|---|---------|
| 1 | `feat(audio): add ffmpeg soft dependency + availability check` |
| 2 | `feat(audio): plugin config - section offsets and audio profiles` |
| 3 | `feat(audio): audio profile commands (set_audio_profile / audio_profile)` |
| 4 | `feat(audio): applyVolume with LUFS formula + analyzeLufs + calculateAutoVolume` |
| 5 | `feat(audio): AJAX API - calibration, LUFS analysis, progress, ffmpeg check` |
| 6 | `feat(audio): calibration page UI + amp info volume config` |
| 7 | `feat(audio): normalize button, async progress, volume auto/forced display` |
