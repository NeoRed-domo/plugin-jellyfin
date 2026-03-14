# Séances de Diffusion — Plan d'implémentation

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter les séances de diffusion (cinéma + commercial) au plugin Jellyfin pour Jeedom.

**Architecture:** Les séances sont des eqLogic Jeedom avec `session_type` dans leur configuration. L'éditeur utilise un accordéon par section. Le moteur d'exécution tourne dans le callback du daemon (toutes les 0.5s) via `tickSessionEngine()`. Les ambiances lumineuses sont des scénarios Jeedom configurables au niveau plugin (défauts) et séance (overrides).

**Tech Stack:** PHP 7+, JavaScript (jQuery), HTML/CSS, Python 3 (daemon inchangé), API REST Jellyfin.

**Spec:** `docs/specs/2026-03-14-seances-diffusion-design.md`

**Note:** Ce projet n'a pas de framework de test automatisé. Chaque tâche inclut des étapes de vérification manuelle. Les tests se font sur une instance Jeedom avec le plugin installé.

---

## File Map

### Fichiers modifiés

| Fichier | Responsabilité | Tâches |
|---------|---------------|--------|
| `core/class/jellyfin.class.php` | Classe principale — constantes, commandes session, moteur d'exécution | 1, 2, 3, 5, 6 |
| `core/ajax/jellyfin.ajax.php` | Endpoints AJAX — CRUD session, calibrage, contrôle | 3 |

**Ordre d'exécution important :** Task 6 (fix requestApi) doit être exécuté AVANT Task 3 et Task 5 car le moteur et l'AJAX dépendent de requestApi(). L'ordre recommandé est : T1 → T6 → T2 → T3 → T4 → T5 → T7 → T8.
| `core/php/jeeJellyfin.php` | Callback daemon — appel tickSessionEngine | 5 |
| `plugin_info/configuration.php` | Config plugin — ambiances + timings | 2 |
| `desktop/php/jellyfin.php` | Page desktop — séparation appareils/séances, éditeur | 4 |
| `desktop/js/jellyfin.js` | JS — éditeur accordéon, calibrage, modale séances widget | 4, 7 |
| `core/template/dashboard/jellyfin.html` | Widget — bouton séances | 7 |

### Fichiers inchangés

| Fichier | Raison |
|---------|--------|
| `resources/daemon/jellyfind.py` | Daemon inchangé (tolérance timing acceptée) |
| `core/php/proxy.php` | Proxy images inchangé |

---

## Chunk 1 : Fondations backend

### Task 1 : Constantes, helpers, commandes session, filtres processSessions

**Fichiers :**
- Modifier : `core/class/jellyfin.class.php`

Ce task pose les bases : constantes de sections, helper baseUrl, branchement postSave, commandes session, filtre processSessions, preRemove.

- [ ] **Step 1.1 : Ajouter les constantes de sections et marks en haut de la classe**

Dans `core/class/jellyfin.class.php`, après `class jellyfin extends eqLogic {`, ajouter :

```php
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
```

- [ ] **Step 1.2 : Ajouter le helper getBaseConfig()**

Après les constantes, ajouter :

```php
/* ************************* Helpers ******************* */
public static function getBaseConfig() {
    $ip = config::byKey('jellyfin_ip', 'jellyfin');
    $port = config::byKey('jellyfin_port', 'jellyfin');
    $apikey = config::byKey('jellyfin_apikey', 'jellyfin');
    if (empty($ip) || empty($port) || empty($apikey)) return null;
    $baseUrl = (strpos($ip, 'http') === false) ? 'http://'.$ip.':'.$port : $ip.':'.$port;
    return ['baseUrl' => $baseUrl, 'apikey' => $apikey, 'ip' => $ip, 'port' => $port];
}
```

- [ ] **Step 1.3 : Modifier postSave() pour brancher sur le type**

Remplacer la ligne 297 :
```php
public function postSave() { $this->createCommands(); }
```
Par :
```php
public function postSave() {
    if ($this->getConfiguration('session_type') != '') {
        $this->createSessionCommands();
    } else {
        $this->createCommands();
    }
}
```

- [ ] **Step 1.4 : Ajouter createSessionCommands()**

Après `createCommands()`, ajouter :

```php
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
    // Initialiser state à "stopped" si pas encore défini
    $stateCmd = $this->getCmd('info', 'state');
    if (is_object($stateCmd) && $stateCmd->execCmd() == '') {
        $this->checkAndUpdateCmd('state', 'stopped');
    }
}
```

- [ ] **Step 1.5 : Modifier jellyfinCmd::execute() pour gérer les commandes session**

Remplacer la classe `jellyfinCmd` :

```php
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

        // Commandes appareil (existant)
        $eqLogic->remoteControl($logicalId, $_options);
    }
}
```

- [ ] **Step 1.6 : Ajouter les stubs startSession/stopSession/pauseSession/resumeSession**

Avant la classe `jellyfinCmd`, ajouter dans la classe `jellyfin` :

```php
/* ************************* Gestion Séances ******************* */
public function startSession() {
    log::add('jellyfin', 'info', 'startSession() appelé pour: ' . $this->getName());
    // Implémenté dans Task 5 (moteur d'exécution)
    return ['state' => 'ok'];
}

public function stopSession() {
    log::add('jellyfin', 'info', 'stopSession() appelé pour: ' . $this->getName());
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
    log::add('jellyfin', 'info', 'pauseSession() appelé pour: ' . $this->getName());
    $this->checkAndUpdateCmd('state', 'paused');
    return ['state' => 'ok'];
}

public function resumeSession() {
    log::add('jellyfin', 'info', 'resumeSession() appelé pour: ' . $this->getName());
    $this->checkAndUpdateCmd('state', 'playing');
    // Effacer les flags de pause dans l'état du moteur
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
```

- [ ] **Step 1.7 : Filtrer les séances dans processSessions() — boucle de nettoyage**

Dans `processSessions()`, à la ligne du nettoyage (ligne ~249), ajouter un filtre après `if ($jellyfinEq->getIsEnable() == 1) {` :

```php
// --- NETTOYAGE DES SESSIONS ABSENTES ---
$allJellyfins = self::byType('jellyfin');
foreach ($allJellyfins as $jellyfinEq) {
    // Skip les séances (pas des appareils)
    if ($jellyfinEq->getConfiguration('session_type') != '') continue;

    if ($jellyfinEq->getIsEnable() == 1) {
```

Aussi ajouter un check `is_object` avant `execCmd()` (fix bug existant) :

```php
$statusCmd = $jellyfinEq->getCmd(null, 'status');
if (!is_object($statusCmd)) continue;
$currentStatus = $statusCmd->execCmd();
```

- [ ] **Step 1.8 : Ajouter preRemove() pour nettoyage séance**

```php
public function preRemove() {
    if ($this->getConfiguration('session_type') != '') {
        // Arrêter la séance si active
        $stateCmd = $this->getCmd('info', 'state');
        if (is_object($stateCmd) && $stateCmd->execCmd() != 'stopped') {
            $this->stopSession();
        }
        // Supprimer les crons programmés
        $crons = cron::searchClassAndFunction('jellyfin', 'executeSession', '"session_id":' . $this->getId());
        if (is_array($crons)) {
            foreach ($crons as $cron) {
                $cron->remove();
            }
        }
    }
}
```

- [ ] **Step 1.9 : Ajouter la méthode statique getSessionsForPlayer()**

```php
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
```

- [ ] **Step 1.10 : Ajouter getSessionLighting()**

```php
public function getSessionLighting($slot) {
    $sessionData = $this->getConfiguration('session_data');
    // Override séance
    if (is_array($sessionData) && isset($sessionData['lighting'][$slot]) && !empty($sessionData['lighting'][$slot])) {
        return $sessionData['lighting'][$slot];
    }
    // Défaut plugin
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
```

- [ ] **Step 1.11 : Commit**

```bash
git add core/class/jellyfin.class.php
git commit -m "feat(sessions): backend foundation - constants, session commands, processSessions filter, helpers"
```

---

### Task 2 : Configuration plugin — Ambiances + Timings

**Fichiers :**
- Modifier : `plugin_info/configuration.php`

- [ ] **Step 2.1 : Lire le fichier actuel**

Lire `plugin_info/configuration.php` pour connaître la structure existante.

- [ ] **Step 2.2 : Ajouter la section Ambiances lumineuses**

Après la section filtres médias existante, ajouter un bloc HTML avec 14 sélecteurs de scénarios Jeedom. Utiliser le pattern standard Jeedom :

```php
<legend><i class="fas fa-lightbulb"></i> {{Ambiances lumineuses (défaut)}}</legend>
<div class="form-group">
    <label class="col-lg-4 control-label">{{Préparation}}</label>
    <div class="col-lg-4">
        <select class="configKey form-control" data-l1key="lighting_preparation">
            <option value="">{{Aucun}}</option>
            <?php
            foreach (scenario::all() as $scenario) {
                echo '<option value="' . $scenario->getId() . '">' . $scenario->getHumanName() . '</option>';
            }
            ?>
        </select>
    </div>
</div>
```

Répéter pour chacun des 14 slots : `preparation`, `intro`, `pubs`, `trailers`, `short_film`, `audio_trailer`, `film`, `pre_generique`, `generique_1`, `post_film_1`, `generique_2`, `post_film_2`, `fin`, `pause`.

Organiser en 3 sous-groupes visuels :
- "Par section" (7 sélecteurs)
- "Tops film" (6 sélecteurs)
- "Spécial" (1 sélecteur : pause)

- [ ] **Step 2.3 : Ajouter la section Timings d'enchaînement**

```php
<legend><i class="fas fa-clock"></i> {{Timings d'enchaînement}}</legend>
<div class="form-group">
    <label class="col-lg-4 control-label">{{Pré-chargement média suivant (secondes)}}</label>
    <div class="col-lg-2">
        <input class="configKey form-control" data-l1key="queue_anticipation" placeholder="2" />
    </div>
</div>
<div class="form-group">
    <label class="col-lg-4 control-label">{{NextTrack anticipé (secondes)}}</label>
    <div class="col-lg-2">
        <input class="configKey form-control" data-l1key="next_anticipation" placeholder="0.5" />
    </div>
</div>
<div class="form-group">
    <label class="col-lg-4 control-label">{{Timeout fallback PlayNow (secondes)}}</label>
    <div class="col-lg-2">
        <input class="configKey form-control" data-l1key="fallback_timeout" placeholder="5" />
    </div>
</div>
<div class="form-group">
    <label class="col-lg-4 control-label">{{Pause si lecteur disparu (secondes)}}</label>
    <div class="col-lg-2">
        <input class="configKey form-control" data-l1key="player_lost_timeout" placeholder="10" />
    </div>
</div>
<div class="form-group">
    <label class="col-lg-4 control-label">{{Arrêt si lecteur absent (secondes)}}</label>
    <div class="col-lg-2">
        <input class="configKey form-control" data-l1key="player_lost_max" placeholder="300" />
    </div>
</div>
```

- [ ] **Step 2.4 : Commit**

```bash
git add plugin_info/configuration.php
git commit -m "feat(sessions): plugin config - lighting defaults and timing parameters"
```

---

### Task 3 : API AJAX — Toutes les nouvelles actions

**Fichiers :**
- Modifier : `core/ajax/jellyfin.ajax.php`

- [ ] **Step 3.1 : Lire le fichier actuel**

Lire `core/ajax/jellyfin.ajax.php` pour identifier le point d'insertion (avant le `throw new Exception` final).

- [ ] **Step 3.2 : Ajouter l'action create_session**

```php
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

    // Initialiser session_data selon le type
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
```

- [ ] **Step 3.3 : Ajouter save_session_data et get_session_data**

```php
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
```

- [ ] **Step 3.4 : Ajouter start/stop/pause/resume_session**

```php
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
```

- [ ] **Step 3.5 : Ajouter schedule_session**

```php
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
    // Convertir datetime en cron schedule
    $dt = new DateTime($datetime);
    $cron->setSchedule($dt->format('i') . ' ' . $dt->format('H') . ' ' . $dt->format('d') . ' ' . $dt->format('m') . ' * ' . $dt->format('Y'));
    $cron->save();
    ajax::success(['scheduled' => $datetime]);
}
```

- [ ] **Step 3.6 : Ajouter calibrate_start et calibrate_set_mark**

```php
if (init('action') == 'calibrate_start') {
    $eqLogic = jellyfin::byId(init('id'));
    if (!is_object($eqLogic) || $eqLogic->getConfiguration('session_type') != 'cinema') {
        throw new Exception(__('Séance cinéma introuvable', __FILE__));
    }
    $mediaId = init('mediaId');
    if (empty($mediaId)) throw new Exception(__('ID média requis', __FILE__));

    $sessionData = $eqLogic->getConfiguration('session_data');
    $playerId = $sessionData['player_id'] ?? null;
    if (empty($playerId)) throw new Exception(__('Aucun lecteur sélectionné', __FILE__));

    $player = jellyfin::byId($playerId);
    if (!is_object($player)) throw new Exception(__('Lecteur introuvable', __FILE__));

    $result = $player->playMedia($mediaId, 'play_now');
    ajax::success($result);
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
```

- [ ] **Step 3.7 : Ajouter get_sessions_for_player**

```php
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

        // Calculer stats
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

            // Affiche du film
            $filmTriggers = $sd['sections']['film']['triggers'] ?? [];
            $info['poster'] = null;
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
```

- [ ] **Step 3.8 : Ajouter la méthode statique executeSession() pour le cron**

Dans `core/class/jellyfin.class.php`, ajouter :

```php
public static function executeSession($_options) {
    $sessionId = $_options['session_id'] ?? null;
    if (empty($sessionId)) return;
    $eqLogic = self::byId($sessionId);
    if (is_object($eqLogic) && $eqLogic->getConfiguration('session_type') != '') {
        $eqLogic->startSession();
    }
}
```

- [ ] **Step 3.9 : Commit**

```bash
git add core/ajax/jellyfin.ajax.php core/class/jellyfin.class.php
git commit -m "feat(sessions): AJAX API - CRUD, control, calibration, scheduling"
```

---

## Chunk 2 : Interface utilisateur

### Task 4 : Page desktop — Séparation appareils/séances + éditeur accordéon

**Fichiers :**
- Modifier : `desktop/php/jellyfin.php`
- Modifier : `desktop/js/jellyfin.js`

- [ ] **Step 4.1 : Lire les fichiers desktop actuels**

Lire `desktop/php/jellyfin.php` et `desktop/js/jellyfin.js` en intégralité.

- [ ] **Step 4.2 : Réorganiser la page PHP — Ajouter le bouton "Nouvelle séance"**

Dans la section `.eqLogicThumbnailContainer` de gestion, après le bouton "Configuration", ajouter :

```php
<div class="cursor eqLogicAction logoPrimary" data-action="add_session">
    <i class="fas fa-film"></i>
    <br>
    <span>{{Nouvelle séance}}</span>
</div>
```

- [ ] **Step 4.3 : Séparer les listes appareils et séances dans la grille**

Après la section "Gestion", remplacer la boucle d'affichage unique par deux boucles filtrées :

```php
<legend><i class="fas fa-tv"></i> {{Mes Appareils}}</legend>
<div class="eqLogicThumbnailContainer">
<?php
foreach ($eqLogics as $eqLogic) {
    if ($eqLogic->getConfiguration('session_type') != '') continue;
    $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
    echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
    echo '<img src="' . $eqLogic->getImage() . '"/>';
    echo '<br>';
    echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
    echo '</div>';
}
?>
</div>

<legend><i class="fas fa-film"></i> {{Mes Séances}}</legend>
<div class="eqLogicThumbnailContainer">
<?php
foreach ($eqLogics as $eqLogic) {
    $sessionType = $eqLogic->getConfiguration('session_type');
    if ($sessionType == '') continue;
    $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
    $icon = ($sessionType == 'cinema') ? 'fa-film' : 'fa-redo';
    echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
    echo '<i class="fas '.$icon.'" style="font-size:60px;color:#1DB954;"></i>';
    echo '<br>';
    echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
    echo '<span class="hiddenAsCard displayTableRight hidden">';
    echo '<span class="label label-info">' . ($sessionType == 'cinema' ? '{{Cinéma}}' : '{{Commercial}}') . '</span>';
    echo '</span>';
    echo '</div>';
}
?>
</div>
```

- [ ] **Step 4.4 : Ajouter l'onglet "Séance" dans la vue éditeur**

Dans la section `<ul class="nav nav-tabs">`, ajouter un 3ème onglet :

```php
<li role="presentation"><a href="#sessiontab" aria-controls="session" role="tab" data-toggle="tab"><i class="fas fa-film"></i> {{Séance}}</a></li>
```

Ajouter le contenu de l'onglet dans le `<div class="tab-content">` :

```php
<div role="tabpanel" class="tab-pane" id="sessiontab">
    <div id="session-editor-container">
        <!-- Rempli dynamiquement par JS -->
        <div class="text-center" style="padding: 40px; color: #888;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
        </div>
    </div>
</div>
```

- [ ] **Step 4.5 : Ajouter les champs type de séance et lecteur dans l'onglet Équipement**

Dans l'onglet equipement, ajouter après les champs standards :

```php
<div class="form-group session-only" style="display:none;">
    <label class="col-sm-4 control-label">{{Type de séance}}</label>
    <div class="col-sm-6">
        <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="session_type" disabled>
            <option value="cinema">{{Cinéma}}</option>
            <option value="commercial">{{Commercial}}</option>
        </select>
    </div>
</div>
<div class="form-group session-only" style="display:none;">
    <label class="col-sm-4 control-label">{{Lecteur}}</label>
    <div class="col-sm-6">
        <select id="sel_session_player" class="form-control">
            <option value="">{{Sélectionner un lecteur}}</option>
            <?php
            foreach ($eqLogics as $eq) {
                if ($eq->getConfiguration('session_type') != '' || !$eq->getIsEnable()) continue;
                echo '<option value="' . $eq->getId() . '">' . $eq->getName() . '</option>';
            }
            ?>
        </select>
    </div>
</div>
```

- [ ] **Step 4.6 : Ajouter le JS pour "Nouvelle séance" (bootbox)**

Dans `desktop/js/jellyfin.js`, ajouter le handler pour le bouton "Nouvelle séance" :

```javascript
$('body').off('click', '.eqLogicAction[data-action=add_session]').on('click', '.eqLogicAction[data-action=add_session]', function() {
    bootbox.dialog({
        title: _t('Nouvelle séance'),
        message: '<div class="form-group">' +
            '<label>' + _t('Nom de la séance') + '</label>' +
            '<input type="text" id="input_session_name" class="form-control" placeholder="' + _t('Ex: Soirée Interstellar') + '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label>' + _t('Type') + '</label>' +
            '<select id="input_session_type" class="form-control">' +
            '<option value="cinema">' + _t('Séance cinéma') + '</option>' +
            '<option value="commercial">' + _t('Diffusion commerciale') + '</option>' +
            '</select>' +
            '</div>',
        buttons: {
            cancel: { label: _t('Annuler'), className: 'btn-default' },
            confirm: {
                label: _t('Créer'), className: 'btn-success',
                callback: function() {
                    var name = $('#input_session_name').val();
                    var type = $('#input_session_type').val();
                    if (!name) return false;
                    $.ajax({
                        type: 'POST',
                        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                        data: { action: 'create_session', name: name, session_type: type },
                        dataType: 'json',
                        success: function(data) {
                            if (data.state == 'ok') {
                                window.location.href = 'index.php?v=d&m=jellyfin&p=jellyfin&id=' + data.result.id;
                            }
                        }
                    });
                }
            }
        }
    });
});
```

- [ ] **Step 4.7 : Ajouter le JS pour l'éditeur accordéon (objet SessionEditor)**

Ajouter dans `desktop/js/jellyfin.js` un objet `SessionEditor` qui gère l'accordéon. C'est la plus grosse pièce de JS. Structure :

```javascript
var SessionEditor = {
    eqLogicId: null,
    sessionType: null,
    sessionData: null,
    sectionsMeta: null,
    marksMeta: null,

    load: function(eqLogicId) {
        // Charge les données via AJAX get_session_data
        // Appelle renderCinema() ou renderCommercial()
    },

    renderCinema: function() {
        // Génère l'HTML accordéon dans #session-editor-container
        // Pour chaque section dans SECTION_ORDER :
        //   - Header cliquable (couleur, label, compteur, durée)
        //   - Body (liste triggers + boutons ajout)
        // + Boutons bas de page : Lancer, Calibrer tops, Programmer
        // + Durée totale estimée
    },

    renderCommercial: function() {
        // Génère une seule section "Playlist" avec médias uniquement
        // + Option boucle
    },

    renderTriggerRow: function(trigger, index, sectionKey) {
        // Rend une ligne trigger : icône type + label + durée + [↑][↓][✕]
    },

    addMedia: function(sectionKey) {
        // Ouvre JellyfinBrowser, au retour ajoute le trigger média
    },

    addPause: function(sectionKey) {
        // Bootbox prompt pour durée (0 = illimitée)
    },

    addAction: function(sectionKey) {
        // Bootbox dialog : choix commande OU scénario
        // Si commande : sélecteur cmd Jeedom
        // Si scénario : sélecteur scénario Jeedom
    },

    moveTrigger: function(sectionKey, index, direction) {
        // Échange trigger[index] avec trigger[index + direction]
    },

    removeTrigger: function(sectionKey, index) {
        // Supprime trigger[index] avec confirmation
    },

    save: function() {
        // POST save_session_data
    },

    calculateDuration: function(triggers) {
        // Somme RunTimeTicks des médias + durées pauses
        // Retourne string formatée
    },

    calculateTotalDuration: function() {
        // Somme toutes les sections
    },

    toggleSection: function(sectionKey) {
        // Toggle open/close de l'accordéon
    }
};
```

L'implémentation complète de chaque méthode doit être écrite. Le code complet sera ~200-250 lignes JS.

- [ ] **Step 4.8 : Connecter l'éditeur à l'événement d'affichage eqLogic**

Quand un eqLogic séance est affiché, charger l'éditeur :

```javascript
// Dans le handler existant de chargement eqLogic
// Détecter si c'est une séance et charger l'éditeur
$('.eqLogic').on('show', function() {
    var sessionType = $('.eqLogicAttr[data-l2key="session_type"]').val();
    if (sessionType && sessionType !== '') {
        $('.session-only').show();
        SessionEditor.load($('.eqLogicAttr[data-l1key="id"]').val());
    } else {
        $('.session-only').hide();
    }
});
```

- [ ] **Step 4.9 : Ajouter le JS pour la modale de calibrage**

```javascript
var CalibrationModal = {
    sessionId: null,
    playerId: null,
    mediaId: null,
    marks: {},
    positionInterval: null,

    open: function(sessionId, mediaId, mediaName, playerId, existingMarks) {
        // Construit et ouvre la modale bootbox
        // Lance la lecture via calibrate_start
        // Démarre le polling position
    },

    updatePosition: function() {
        // Lit la position depuis les commandes du lecteur
    },

    seek: function(delta) {
        // Seek relatif via set_position
    },

    seekTo: function(seconds) {
        // Seek absolu (clic sur barre)
    },

    setMark: function(markName) {
        // Capture position courante pour ce mark
    },

    save: function() {
        // Sauvegarde chaque mark via calibrate_set_mark
        // Ferme la modale
    },

    close: function() {
        // Arrête le polling, ferme la modale
    }
};
```

- [ ] **Step 4.10 : Intégrer la sélection lecteur dans l'onglet Équipement**

Le sélecteur lecteur (#sel_session_player) doit mettre à jour `session_data.player_id` et sauvegarder :

```javascript
$('#sel_session_player').on('change', function() {
    if (SessionEditor.sessionData) {
        SessionEditor.sessionData.player_id = parseInt($(this).val()) || null;
        SessionEditor.save();
    }
});
```

Et au chargement, pré-sélectionner le lecteur actuel.

- [ ] **Step 4.11 : Commit**

```bash
git add desktop/php/jellyfin.php desktop/js/jellyfin.js
git commit -m "feat(sessions): desktop page - session listing, accordion editor, calibration modal"
```

---

## Chunk 3 : Moteur d'exécution

### Task 5 : Moteur d'exécution — tickSessionEngine

**Fichiers :**
- Modifier : `core/class/jellyfin.class.php`
- Modifier : `core/php/jeeJellyfin.php`

C'est le cœur technique. Le moteur tourne à chaque callback daemon (0.5s).

- [ ] **Step 5.1 : Ajouter l'appel tickSessionEngine dans jeeJellyfin.php**

Après l'appel `jellyfin::processSessions($payload);`, ajouter :

```php
// Moteur d'exécution des séances
if (class_exists('jellyfin') && method_exists('jellyfin', 'tickSessionEngine')) {
    jellyfin::tickSessionEngine($payload);
}
```

- [ ] **Step 5.2 : Implémenter tickSessionEngine() — structure principale**

```php
public static function tickSessionEngine($sessions) {
    if (!is_array($sessions)) return;

    // Construire un index device_id => sessionData pour lookup rapide
    $deviceIndex = [];
    foreach ($sessions as $s) {
        $devId = $s['device_id'] ?? ($s['DeviceId'] ?? '');
        if (!empty($devId)) $deviceIndex[$devId] = $s;
    }

    // Récupérer tous les lecteurs actifs (appareils avec device_id)
    $allEq = self::byType('jellyfin');
    foreach ($allEq as $eq) {
        if ($eq->getConfiguration('session_type') != '') continue; // Skip séances
        $playerId = $eq->getId();
        $cacheKey = 'jellyfin::active_session::' . $playerId;
        $engineState = cache::byKey($cacheKey)->getValue(null);
        if ($engineState === null) continue; // Pas de séance active sur ce lecteur

        $engineState = json_decode($engineState, true);
        if (!is_array($engineState)) continue;

        $deviceId = $eq->getConfiguration('device_id');
        $playerData = $deviceIndex[$deviceId] ?? null;

        self::tickPlayer($eq, $playerData, $engineState, $cacheKey);
    }
}
```

- [ ] **Step 5.3 : Implémenter tickPlayer() — logique par lecteur**

```php
private static function tickPlayer($playerEq, $playerData, $engineState, $cacheKey) {
    $sessionEq = self::byId($engineState['session_eqlogic_id']);
    if (!is_object($sessionEq)) {
        cache::set($cacheKey, null);
        return;
    }

    $sessionType = $sessionEq->getConfiguration('session_type');
    $sessionData = $sessionEq->getConfiguration('session_data');

    // Déconnexion lecteur ?
    if ($playerData === null) {
        self::handlePlayerLost($sessionEq, $engineState, $cacheKey);
        return;
    }

    // Lecteur reconnecté après perte
    if (isset($engineState['player_lost_since'])) {
        unset($engineState['player_lost_since']);
        unset($engineState['player_lost']);
    }

    // Pause de séance (trigger pause) — ne rien faire tant qu'on attend
    if (isset($engineState['waiting_resume']) && $engineState['waiting_resume']) {
        cache::set($cacheKey, json_encode($engineState));
        return; // Attend commande resume
    }
    if (isset($engineState['pause_until'])) {
        if (time() < $engineState['pause_until']) {
            cache::set($cacheKey, json_encode($engineState));
            return; // Pause temporisée en cours
        }
        // Pause terminée → avancer au trigger suivant
        unset($engineState['pause_until']);
        $engineState['current_trigger_index'] = ($engineState['current_trigger_index'] ?? 0) + 1;
        self::executeNonMediaTriggers($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);
        return;
    }

    $status = $playerData['status'] ?? 'Stopped';
    $itemId = $playerData['item_id'] ?? '';
    $positionTicks = (int)($playerData['position_ticks'] ?? 0);
    $runTimeTicks = (int)($playerData['run_time_ticks'] ?? 0);

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

    // Résoudre le sessionId une seule fois (évite les appels API répétés)
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
```

- [ ] **Step 5.4 : Implémenter tickCinema() — séance cinéma**

```php
private static function tickCinema($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey, $status, $itemId, $positionTicks, $runTimeTicks, $config, $jellyfinSessionId) {
    if (!$config) return;

    $sections = $sessionData['sections'] ?? [];
    $currentSection = $engineState['current_section'] ?? '';
    $triggerIndex = $engineState['current_trigger_index'] ?? 0;
    $currentMediaId = $engineState['current_media_id'] ?? '';

    $triggers = $sections[$currentSection]['triggers'] ?? [];
    $currentTrigger = $triggers[$triggerIndex] ?? null;

    if (!$currentTrigger) {
        self::advanceToNextSection($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);
        return;
    }

    if ($currentTrigger['type'] == 'media') {
        // Vérification item_id — resync si désynchronisé (spec 5.2 point 2, 5.9)
        if ($status == 'Playing' && !empty($itemId) && !empty($currentMediaId) && $itemId != $currentMediaId) {
            // Le lecteur joue un média inattendu — tenter resync
            $found = self::findTriggerByMediaId($sections, $itemId);
            if ($found) {
                $engineState['current_section'] = $found['section'];
                $engineState['current_trigger_index'] = $found['index'];
                $engineState['current_media_id'] = $itemId;
                $engineState['queued'] = false;
                log::add('jellyfin', 'info', 'Resync moteur: média ' . $itemId . ' trouvé dans section ' . $found['section']);
            } else {
                log::add('jellyfin', 'error', 'Média inconnu en lecture: ' . $itemId . '. Arrêt séance.');
                $sessionEq->stopSession();
                return;
            }
        }

        // Fallback timeout : si le média est Stopped et qu'on a envoyé un next, attendre le fallback
        $fallbackTimeout = (float)config::byKey('fallback_timeout', 'jellyfin', 5);
        if ($status == 'Stopped' && !empty($currentMediaId)) {
            if (!isset($engineState['stopped_since'])) {
                $engineState['stopped_since'] = time();
            } elseif (time() - $engineState['stopped_since'] >= $fallbackTimeout) {
                // Fallback : forcer PlayNow du trigger suivant
                unset($engineState['stopped_since']);
                $nextTrigger = self::findNextMediaTrigger($sections, $currentSection, $triggerIndex);
                if ($nextTrigger) {
                    self::playMediaDirect($playerEq, $nextTrigger['media_id'], $config);
                    $engineState['current_trigger_index'] = $triggerIndex + 1;
                    $engineState['current_media_id'] = $nextTrigger['media_id'];
                    $engineState['queued'] = false;
                    log::add('jellyfin', 'warning', 'Fallback PlayNow déclenché pour: ' . $nextTrigger['media_id']);
                } else {
                    self::advanceToNextSection($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);
                    return;
                }
            }
        } else {
            unset($engineState['stopped_since']);
        }

        // Pré-chargement (queue) + NextTrack anticipé
        $queueAnticipation = (float)config::byKey('queue_anticipation', 'jellyfin', 2);
        $nextAnticipation = (float)config::byKey('next_anticipation', 'jellyfin', 0.5);

        if ($runTimeTicks > 0 && $status == 'Playing') {
            $remainingSeconds = ($runTimeTicks - $positionTicks) / 10000000;
            $nextTrigger = self::findNextMediaTrigger($sections, $currentSection, $triggerIndex);

            if ($nextTrigger && !($engineState['queued'] ?? false) && $remainingSeconds <= $queueAnticipation && $remainingSeconds > $nextAnticipation) {
                self::queueMediaDirect($jellyfinSessionId, $nextTrigger['media_id'], $config);
                $engineState['expected_next_media_id'] = $nextTrigger['media_id'];
                $engineState['queued'] = true;
            }

            if ($nextTrigger && $remainingSeconds <= $nextAnticipation) {
                self::sendNextTrackDirect($jellyfinSessionId, $config);
                $engineState['current_trigger_index'] = $triggerIndex + 1;
                $engineState['current_media_id'] = $nextTrigger['media_id'];
                $engineState['queued'] = false;
            }
        }

        // Gestion section FILM — tops
        if ($currentSection == 'film' && isset($sections['film']['marks'])) {
            $positionSeconds = $positionTicks / 10000000;
            foreach (self::MARK_ORDER as $mark) {
                $markTime = $sections['film']['marks'][$mark] ?? null;
                if ($markTime === null) continue;
                $lastMark = $engineState['last_mark_triggered'] ?? '';
                if ($positionSeconds >= $markTime && $lastMark != $mark) {
                    // Vérifier qu'on n'a pas déjà passé ce mark
                    $markIdx = array_search($mark, self::MARK_ORDER);
                    $lastIdx = ($lastMark != '') ? array_search($lastMark, self::MARK_ORDER) : -1;
                    if ($markIdx > $lastIdx) {
                        self::triggerLighting($sessionEq->getSessionLighting($mark));
                        $engineState['last_mark_triggered'] = $mark;
                        $engineState['current_lighting'] = $mark;
                    }
                }
                // Fin du film
                if ($mark == 'fin' && $positionSeconds >= $markTime) {
                    $sessionEq->stopSession();
                    return;
                }
            }
        }
    }

    // Triggers non-média sont traités au lancement (fire-and-forget), pas au tick
    // Ils sont avancés immédiatement dans startSession/advanceToNextSection

    // Mettre à jour progression
    self::updateSessionProgress($sessionEq, $sessionData, $engineState);

    // Sauvegarder état
    cache::set($cacheKey, json_encode($engineState));
}
```

- [ ] **Step 5.5 : Implémenter les helpers du moteur**

```php
private static function findNextMediaTrigger($sections, $currentSection, $currentIndex) {
    $sectionOrder = self::SECTION_ORDER;
    $sectionIdx = array_search($currentSection, $sectionOrder);

    // D'abord chercher dans la section courante
    $triggers = $sections[$currentSection]['triggers'] ?? [];
    for ($i = $currentIndex + 1; $i < count($triggers); $i++) {
        if ($triggers[$i]['type'] == 'media') return $triggers[$i];
    }

    // Puis dans les sections suivantes
    for ($s = $sectionIdx + 1; $s < count($sectionOrder); $s++) {
        $secKey = $sectionOrder[$s];
        foreach ($sections[$secKey]['triggers'] ?? [] as $trigger) {
            if ($trigger['type'] == 'media') return $trigger;
        }
    }

    return null;
}

// Versions "Direct" : utilisent le jellyfinSessionId déjà résolu (pas d'appel API supplémentaire)
// Timeout réduit à 2s pour ne pas bloquer le cycle daemon de 0.5s

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

private static function playMediaDirect($playerEq, $mediaId, $config) {
    // PlayNow SANS le stop forcé (pas de délai Android TV) — timeout 2s
    $deviceId = $playerEq->getConfiguration('device_id');
    $sessionData = self::getSessionDataFromDeviceId($config['baseUrl'], $config['apikey'], $deviceId);
    if (!$sessionData || !isset($sessionData['Id'])) return false;
    $url = $config['baseUrl'] . '/Sessions/' . $sessionData['Id'] . '/Playing?ItemIds=' . $mediaId . '&PlayCommand=PlayNow&StartPositionTicks=0&api_key=' . $config['apikey'];
    self::requestApi($url, 'POST', null, false, 2);
    log::add('jellyfin', 'debug', 'PlayNow direct: ' . $mediaId);
    return true;
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
        $nextTriggers = $sessionData['sections'][$nextKey]['triggers'] ?? [];
        if (!empty($nextTriggers)) {
            $engineState['current_section'] = $nextKey;
            $engineState['current_trigger_index'] = 0;
            $engineState['queued'] = false;

            // Ambiance lumineuse
            self::triggerLighting($sessionEq->getSessionLighting($nextKey));
            $engineState['current_lighting'] = $nextKey;

            // Exécuter les triggers non-média du début
            self::executeNonMediaTriggers($sessionEq, $playerEq, $sessionData, $engineState, $cacheKey);

            $sessionEq->checkAndUpdateCmd('current_section', self::SECTION_LABELS[$nextKey] ?? $nextKey);
            cache::set($cacheKey, json_encode($engineState));
            return;
        }
    }

    // Plus de sections → séance terminée
    $sessionEq->stopSession();
}

private static function executeNonMediaTriggers($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey) {
    $section = $engineState['current_section'];
    $triggers = $sessionData['sections'][$section]['triggers'] ?? [];
    $config = self::getBaseConfig();

    while ($engineState['current_trigger_index'] < count($triggers)) {
        $trigger = $triggers[$engineState['current_trigger_index']];

        if ($trigger['type'] == 'media') {
            // Lancer ce média et s'arrêter
            $engineState['current_media_id'] = $trigger['media_id'];
            if ($config) self::playMediaDirect($playerEq, $trigger['media_id'], $config);
            cache::set($cacheKey, json_encode($engineState));
            return;
        }

        if ($trigger['type'] == 'pause') {
            $duration = (int)($trigger['duration'] ?? 0);
            if ($duration == 0) {
                // Pause illimitée → mettre en pause et attendre resume
                $sessionEq->checkAndUpdateCmd('state', 'paused');
                $engineState['waiting_resume'] = true;
                cache::set($cacheKey, json_encode($engineState));
                return;
            }
            // Pause temporisée → on stocke le timestamp de fin
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

    // Tous les triggers de la section traités → section suivante
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
```

- [ ] **Step 5.6 : Implémenter startSession() complet (remplacer le stub)**

Remplacer le stub `startSession()` par :

```php
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

    // Arrêter séance existante sur ce lecteur
    $existingState = cache::byKey('jellyfin::active_session::' . $playerId)->getValue(null);
    if ($existingState !== null) {
        $existingData = json_decode($existingState, true);
        if (isset($existingData['session_eqlogic_id'])) {
            $existingSession = self::byId($existingData['session_eqlogic_id']);
            if (is_object($existingSession)) $existingSession->stopSession();
        }
    }

    // Initialiser état moteur
    $firstSection = '';
    $firstTriggerIndex = 0;
    if ($sessionType == 'cinema') {
        foreach (self::SECTION_ORDER as $key) {
            if (!empty($sessionData['sections'][$key]['triggers'])) {
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

    // NE PAS écrire dans le cache ici — executeNonMediaTriggers() gère l'écriture finale
    $this->checkAndUpdateCmd('state', 'playing');
    if ($sessionType == 'cinema') {
        $this->checkAndUpdateCmd('current_section', self::SECTION_LABELS[$firstSection] ?? $firstSection);
        self::triggerLighting($this->getSessionLighting($firstSection));
    }
    $this->checkAndUpdateCmd('progress', 0);

    // Lancer le premier trigger — cette méthode écrit le cache à jour
    $cacheKey = 'jellyfin::active_session::' . $playerId;
    cache::set($cacheKey, json_encode($engineState)); // Écriture initiale
    self::executeNonMediaTriggers($this, $playerEq, $sessionData, $engineState, $cacheKey);
    // executeNonMediaTriggers() a mis à jour et écrit le cache avec l'état final

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
```

- [ ] **Step 5.7 : Implémenter tickCommercial()**

```php
private static function tickCommercial($sessionEq, $playerEq, $sessionData, &$engineState, $cacheKey, $status, $itemId, $positionTicks, $runTimeTicks, $config, $jellyfinSessionId) {
    if (!$config) return;

    $playlist = $sessionData['playlist'] ?? [];
    if (empty($playlist)) return;
    $loop = $sessionData['loop'] ?? true;

    $triggerIndex = $engineState['current_trigger_index'] ?? 0;
    $currentMedia = $playlist[$triggerIndex] ?? null;
    if (!$currentMedia) return;

    $queueAnticipation = (float)config::byKey('queue_anticipation', 'jellyfin', 2);
    $nextAnticipation = (float)config::byKey('next_anticipation', 'jellyfin', 0.5);

    if ($runTimeTicks > 0 && $status == 'Playing') {
        $remainingSeconds = ($runTimeTicks - $positionTicks) / 10000000;

        // Prochain média
        $isLast = ($triggerIndex + 1 >= count($playlist));
        if ($isLast && !$loop) {
            // Dernier média, pas de boucle → laisser finir
        } else {
            $nextIndex = $isLast ? 0 : $triggerIndex + 1;
            $nextMedia = $playlist[$nextIndex];

            if (!($engineState['queued'] ?? false) && $remainingSeconds <= $queueAnticipation && $remainingSeconds > $nextAnticipation) {
                self::queueMediaDirect($jellyfinSessionId, $nextMedia['media_id'], $config);
                $engineState['queued'] = true;
            }

            if ($remainingSeconds <= $nextAnticipation) {
                self::sendNextTrackDirect($jellyfinSessionId, $config);
                $engineState['current_trigger_index'] = $nextIndex;
                $engineState['current_media_id'] = $nextMedia['media_id'];
                $engineState['queued'] = false;
            }
        }
    }

    // Si le dernier média est terminé et pas de boucle
    if ($status == 'Stopped' && ($triggerIndex + 1 >= count($playlist)) && !$loop) {
        $sessionEq->stopSession();
        return;
    }

    self::updateSessionProgress($sessionEq, $sessionData, $engineState);
    cache::set($cacheKey, json_encode($engineState));
}
```

- [ ] **Step 5.8 : Commit**

```bash
git add core/class/jellyfin.class.php core/php/jeeJellyfin.php
git commit -m "feat(sessions): execution engine - tickSessionEngine, queue/next, lighting, fallbacks"
```

---

## Chunk 4 : Widget et finalisation

### Task 6 : Corrections existantes (requestApi headers + is_object check)

**Fichiers :**
- Modifier : `core/class/jellyfin.class.php`

- [ ] **Step 6.1 : Corriger requestApi() — headers POST écrasés**

Remplacer la méthode `requestApi()` pour gérer correctement les headers :

```php
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
```

Note : ajout du paramètre `$timeout` (défaut 5s, le moteur utilisera 2s).

- [ ] **Step 6.2 : Commit**

```bash
git add core/class/jellyfin.class.php
git commit -m "fix: requestApi headers + configurable timeout"
```

---

### Task 7 : Widget — Bouton séances + modale

**Fichiers :**
- Modifier : `core/template/dashboard/jellyfin.html`
- Modifier : `desktop/js/jellyfin.js`

- [ ] **Step 7.1 : Ajouter le bouton 🎬 dans le widget**

Dans `jellyfin.html`, dans la barre de contrôles (entre `.heart-btn` et `.logo-jellyfin-img`), ajouter :

```html
<i class="fas fa-film session-btn" title="Séances"></i>
```

- [ ] **Step 7.2 : Ajouter le style glow pour le bouton**

Dans la section `<style>` du widget :

```css
.session-btn { color: #666; cursor: pointer; transition: all 0.2s; }
.session-btn:hover { color: #1DB954; transform: scale(1.2); filter: drop-shadow(0 0 8px rgba(29, 185, 84, 0.8)); }
```

- [ ] **Step 7.3 : Ajouter le handler JS pour ouvrir la modale séances**

Dans la section `<script>` du widget :

```javascript
$('.eqLogic[data-eqLogic_id=#id#] .session-btn').on('click', function() {
    $.ajax({
        type: 'POST',
        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
        data: { action: 'get_sessions_for_player', player_id: '#id#' },
        dataType: 'json',
        success: function(data) {
            if (data.state != 'ok' || !data.result || data.result.length == 0) {
                bootbox.alert(_wt('Aucune séance configurée pour ce lecteur.'));
                return;
            }
            var html = '<div style="max-height: 50vh; overflow-y: auto;">';
            data.result.forEach(function(s) {
                var icon = s.type == 'cinema' ? 'fa-film' : 'fa-redo';
                var typeLabel = s.type == 'cinema' ? 'Cinéma' : 'Boucle';
                var dur = s.total_duration ? Math.floor(s.total_duration / 3600) + 'h' + String(Math.floor((s.total_duration % 3600) / 60)).padStart(2,'0') : '--';
                var poster = s.poster ? '<img src="' + s.poster + '" style="width:50px;height:75px;object-fit:cover;border-radius:4px;">' : '<i class="fas fa-' + icon + '" style="font-size:30px;color:#555;"></i>';
                var stats = dur;
                if (s.counts) {
                    if (s.counts.pubs) stats += ' · ' + s.counts.pubs + ' pub' + (s.counts.pubs > 1 ? 's' : '');
                    if (s.counts.trailers) stats += ' · ' + s.counts.trailers + ' BA';
                    if (s.counts.short_film) stats += ' · ' + s.counts.short_film + ' court';
                }
                html += '<div style="display:flex;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #333;">';
                html += '<div style="width:50px;flex-shrink:0;text-align:center;">' + poster + '</div>';
                html += '<div style="flex-grow:1;">';
                html += '<div style="font-weight:bold;color:#fff;">' + s.name + ' <span style="color:#888;font-size:11px;"><i class="fas ' + icon + '"></i> ' + typeLabel + '</span></div>';
                html += '<div style="font-size:11px;color:#aaa;">' + stats + '</div>';
                if (s.film_name) html += '<div style="font-size:11px;color:#888;">Film: ' + s.film_name + '</div>';
                html += '</div>';
                html += '<div><a class="btn btn-sm btn-success launch-session-btn" data-session-id="' + s.id + '"><i class="fas fa-play"></i></a></div>';
                html += '</div>';
            });
            html += '</div>';

            var modal = bootbox.dialog({
                title: '<i class="fas fa-film"></i> ' + _wt('Séances'),
                message: html,
                buttons: { close: { label: _wt('Fermer'), className: 'btn-default' } },
                className: 'jellyfin-modal-fullscreen'
            });

            modal.find('.launch-session-btn').on('click', function() {
                var sid = $(this).data('session-id');
                var btn = $(this);
                btn.html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    type: 'POST',
                    url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                    data: { action: 'start_session', id: sid },
                    dataType: 'json',
                    success: function(data) {
                        if (data.state == 'ok') { bootbox.hideAll(); }
                        else { btn.html('<i class="fas fa-play"></i>'); bootbox.alert(data.result); }
                    }
                });
            });
        }
    });
});
```

- [ ] **Step 7.4 : Commit**

```bash
git add core/template/dashboard/jellyfin.html desktop/js/jellyfin.js
git commit -m "feat(sessions): widget session button with glow + session list modal"
```

---

### Task 8 : Traductions + Vérification finale

**Fichiers :**
- Modifier : `core/i18n/en_US.json`, `core/i18n/es_ES.json`, `core/i18n/de_DE.json`

- [ ] **Step 8.1 : Ajouter les clés de traduction manquantes**

Pour chaque fichier i18n, ajouter les traductions des nouveaux textes : noms de sections, labels de l'éditeur, messages d'erreur, textes des modales.

- [ ] **Step 8.2 : Vérification manuelle complète**

Sur une instance Jeedom de test :
1. Vérifier que les appareils existants fonctionnent toujours (pas de régression)
2. Créer une séance cinéma → vérifier les commandes créées
3. Créer une séance commerciale → vérifier les commandes créées
4. Vérifier la page desktop (séparation appareils/séances)
5. Vérifier l'éditeur accordéon (ajout/suppression/réordonnancement triggers)
6. Vérifier le calibrage des tops
7. Vérifier le lancement d'une séance depuis le widget
8. Vérifier la programmation d'une séance
9. Vérifier les ambiances lumineuses (section + tops + pause)
10. Vérifier l'enchaînement des médias (next anticipé)
11. Vérifier la déconnexion lecteur (timeout pause/stop)

- [ ] **Step 8.3 : Commit final + mise à jour version**

Mettre à jour `plugin_info/info.json` : version `1.1.0` (ajout feature mineur).

```bash
git add -A
git commit -m "feat(sessions): translations + version bump to 1.1.0

Complete broadcast sessions feature:
- Cinema sessions with 7 configurable sections
- Commercial broadcasts with loop mode
- Lighting ambiance system (14 slots, defaults + overrides)
- Film calibration modal with mark system
- Anticipated media chaining (queue + next)
- Widget integration with session launcher
- Scheduling via cron"
```

---

## Résumé des commits

| # | Message | Fichiers |
|---|---------|----------|
| 1 | `feat(sessions): backend foundation` | jellyfin.class.php |
| 2 | `feat(sessions): plugin config` | configuration.php |
| 3 | `feat(sessions): AJAX API` | jellyfin.ajax.php, jellyfin.class.php |
| 4 | `feat(sessions): desktop page + editor` | jellyfin.php, jellyfin.js |
| 5 | `feat(sessions): execution engine` | jellyfin.class.php, jeeJellyfin.php |
| 6 | `fix: requestApi headers` | jellyfin.class.php |
| 7 | `feat(sessions): widget integration` | jellyfin.html, jellyfin.js |
| 8 | `feat(sessions): translations + v1.1.0` | i18n/*, info.json |
