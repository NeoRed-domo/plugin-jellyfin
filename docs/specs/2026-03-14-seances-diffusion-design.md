# Séances de Diffusion — Spécification Technique

## 1. Vue d'ensemble

Ajout de deux types de séances au plugin Jellyfin :
- **Séance cinéma** : enchaînement structuré en sections avec ambiances lumineuses et calibrage des tops film
- **Diffusion commerciale** : enchaînement simple de médias en boucle

### Principes directeurs
- Coller aux standards Jeedom (eqLogic, commandes, scénarios)
- Modularité : sections et types de triggers extensibles sans réécriture
- Ne pas casser le fonctionnement existant (appareils, widget, daemon)
- Fiabilité d'enchaînement des médias (pré-chargement, fallback, vérification)

---

## 2. Modèle de données

### 2.1 Séance = eqLogic

Chaque séance est un équipement Jeedom (`eqLogic`) avec :
- `eqType_name` = `jellyfin`
- `configuration[session_type]` = `cinema` ou `commercial`
- `configuration[session_data]` = JSON structuré (voir ci-dessous)

Cela permet l'intégration native avec les scénarios Jeedom (`#[Salon][Séance Samedi][start]#`).

**Distinction avec les appareils :** les eqLogics de type séance se distinguent par la présence de `configuration[session_type]`. Les eqLogics appareils n'ont pas cette clé. Tout code itérant sur `self::byType('jellyfin')` (notamment `processSessions()` et son nettoyage) doit filtrer les séances via : `if ($eq->getConfiguration('session_type') != '') continue;`

### 2.2 Ordre des sections

L'ordre d'exécution des sections est défini par un tableau ordonné constant (pas par les clés JSON) :

```php
const SECTION_ORDER = ['preparation', 'intro', 'pubs', 'trailers', 'short_film', 'audio_trailer', 'film'];
```

Ce tableau est le point unique de définition. Ajouter une section = ajouter une entrée ici, un slot lighting correspondant est automatiquement créé.

### 2.3 Structure JSON — Séance cinéma

```json
{
  "player_id": 42,
  "sections": {
    "preparation":   { "triggers": [] },
    "intro":         { "triggers": [] },
    "pubs":          { "triggers": [] },
    "trailers":      { "triggers": [] },
    "short_film":    { "triggers": [] },
    "audio_trailer": { "triggers": [] },
    "film":          {
      "triggers": [],
      "marks": {
        "pre_generique": null,
        "generique_1": null,
        "post_film_1": null,
        "generique_2": null,
        "post_film_2": null,
        "fin": null
      }
    }
  },
  "lighting": {
    "preparation": null,
    "intro": null,
    "pubs": null,
    "trailers": null,
    "short_film": null,
    "audio_trailer": null,
    "film": null,
    "pre_generique": null,
    "generique_1": null,
    "post_film_1": null,
    "generique_2": null,
    "post_film_2": null,
    "fin": null,
    "pause": null
  }
}
```

- `lighting` : ID scénario Jeedom ou `null` (= utiliser le défaut plugin)
- `marks` : temps en secondes ou `null` (tous optionnels)

### 2.4 Structure JSON — Diffusion commerciale

```json
{
  "player_id": 42,
  "loop": true,
  "playlist": []
}
```

`playlist` contient uniquement des triggers de type `media`.

### 2.5 Types de triggers

```json
{ "type": "media",    "media_id": "abc", "name": "Logo DTS", "img_tag": "...", "duration_ticks": 0 }
{ "type": "pause",    "duration": 0 }
{ "type": "command",  "cmd_id": 123, "label": "Allumer ampli" }
{ "type": "scenario", "scenario_id": 45, "label": "Ambiance ciné" }
```

- `media.duration_ticks` : `RunTimeTicks` Jellyfin, sert au calcul des durées estimées
- `pause.duration` : secondes, `0` = illimitée (reprise manuelle uniquement via la commande `resume` de la séance — le bouton play de la télécommande ne reprend PAS une pause de séance, il ne contrôle que la lecture du média en cours)

### 2.6 Extensibilité

Les sections sont définies dans le tableau `SECTION_ORDER`. Ajouter une section (ex: "Entracte") = ajouter une entrée dans ce tableau + un slot lighting est automatiquement créé. Idem pour les types de triggers : définis dans un tableau extensible.

### 2.7 Commandes de l'eqLogic séance

| Logical ID | Type | SubType | Description |
|-----------|------|---------|-------------|
| start | action | other | Lancer la séance |
| stop | action | other | Arrêter la séance |
| pause | action | other | Suspendre la séance |
| resume | action | other | Reprendre la séance |
| state | info | string | stopped / playing / paused |
| current_section | info | string | Section en cours (ex: "Publicités") |
| progress | info | numeric | Pourcentage global de progression (0-100) |

**Branchement postSave/createCommands :** La méthode `postSave()` vérifie `configuration[session_type]`. Si présent → appelle `createSessionCommands()` (commandes ci-dessus). Sinon → appelle `createCommands()` existant (commandes appareil). Les deux ensembles de commandes sont mutuellement exclusifs.

### 2.8 Contraintes

- **Une seule séance active par lecteur** à la fois. Lancer une séance sur un lecteur qui a déjà une séance active arrête la séance précédente.
- **Suppression de séance** : `preRemove()` arrête la séance si elle est en cours et supprime les cron jobs programmés associés.

---

## 3. Interface utilisateur

### 3.1 Page desktop — Réorganisation

La page du plugin sépare visuellement appareils et séances :

```
┌─ Gestion ─────────────────────────────────────┐
│ [+ Ajouter équipement]  [+ Nouvelle séance]   │
│ [Configuration]                                │
├─ Mes Appareils ───────────────────────────────┤
│ [Shield TV]  [Freebox]  [PC Bureau]           │
├─ Mes Séances ─────────────────────────────────┤
│ [🎬 Soirée Interstellar] [🔁 Pubs Magasin]    │
└───────────────────────────────────────────────┘
```

Filtrage par `configuration[session_type]` pour séparer les deux listes. Icônes distinctes par type.

### 3.2 Éditeur de séance — Onglets

```
[← Retour] [Équipement] [Séance] [Commandes]
```

- **Onglet Équipement** : Nom, objet parent, activer/visible, type (cinéma/commercial), lecteur cible
- **Onglet Séance** : Éditeur accordéon (cinéma) ou playlist simple (commercial)
- **Onglet Commandes** : Table standard Jeedom

### 3.3 Éditeur accordéon — Séance cinéma

Chaque section est un panneau repliable :
- **Fermé** : pastille couleur, nom, compteur de triggers, durée estimée
- **Ouvert** : liste ordonnée des triggers avec boutons [↑][↓][✕] + zone d'ajout

Zone d'ajout : 3 boutons `[🎬 Média]` `[⏸ Pause]` `[⚡ Action]`
- Média → ouvre le JellyfinBrowser existant
- Pause → ajoute un bloc avec champ durée
- Action → propose : commande équipement OU scénario Jeedom

**Durées estimées** : calculées depuis `RunTimeTicks` des médias + durées des pauses. Affichées par section et en total en bas de l'éditeur. Les actions et pauses illimitées comptent pour 0.

### 3.4 Éditeur — Diffusion commerciale

Même composant accordéon mais :
- Une seule section "Playlist"
- Uniquement le bouton `[🎬 Média]`
- Option "Boucle infinie" cochée par défaut

### 3.5 Modale de calibrage des tops

Accessible depuis le bouton "Calibrer tops" dans l'onglet Séance (section Film uniquement).

**Pré-requis** : à l'ouverture, vérifie que le lecteur est en ligne et contrôlable. Si le lecteur est déjà en lecture, avertit l'utilisateur que la lecture en cours sera interrompue.

```
┌─ Calibrage des tops — {Nom du film} ───────────┐
│                                                  │
│  01:47:23 ━━━━━━━━━━━━━━●━━━━ 02:49:00          │
│           (barre seek interactive)               │
│                                                  │
│  [◀◀ -10s] [◀ -1s] [⏸ Pause] [▶ +1s] [▶▶ +10s] │
│                                                  │
│  ┌─ Marqueurs (tous optionnels) ───────────────┐ │
│  │ Pré-générique    [Set] ── 01:45:12 ✓       │ │
│  │ Générique 1      [Set] ── --:--             │ │
│  │ Post film 1      [Set] ── --:--             │ │
│  │ Générique 2      [Set] ── --:--             │ │
│  │ Post film 2      [Set] ── --:--             │ │
│  │ Fin              [Set] ── --:--             │ │
│  └─────────────────────────────────────────────┘ │
│                                                  │
│  [Annuler]                          [Valider]    │
└──────────────────────────────────────────────────┘
```

Le film est lu en réel sur le lecteur sélectionné. La position est trackée via le daemon (0.5s). Clic sur la barre = seek. Set = capture du temps courant.

### 3.6 Widget — Bouton séances

Ajout d'un bouton 🎬 dans la barre de contrôles du widget, entre le cœur (favoris) et le logo Jellyfin. Effet glow au hover (halo, même style que favoris et bibliothèque).

Ouvre une modale listant les séances configurées pour ce lecteur :

```
┌─ Séances — {Nom lecteur} ────────────────────┐
│                                                │
│ ┌────┐  Soirée Interstellar        🎬 Cinéma  │
│ │ 🖼 │  ⏱ 3h11 · 2 pubs · 3 BA · 1 court     │
│ │    │  Film: Interstellar (2014)              │
│ └────┘                        [▶ Lancer]       │
│ ──────────────────────────────────────────────│
│ ┌────┐  Pubs Magasin               🔁 Boucle  │
│ │ 🖼 │  ⏱ 12:30 · 5 médias                    │
│ └────┘                        [▶ Lancer]       │
└────────────────────────────────────────────────┘
```

**Affiche** : image du premier média de type `media` dans la section `film`. Si aucun média dans la section film, placeholder icône. Pour les diffusions commerciales, image du premier média de la playlist.

---

## 4. Ambiances lumineuses

### 4.1 Slots d'ambiance

14 slots au total, chacun = un ID scénario Jeedom :

**Par section (7)** : preparation, intro, pubs, trailers, short_film, audio_trailer, film

**Par top film (6)** : pre_generique, generique_1, post_film_1, generique_2, post_film_2, fin

**Spécial (1)** : pause

### 4.2 Hiérarchie de configuration

1. **Config plugin** : ambiances par défaut (s'appliquent à toutes les séances)
2. **Config séance** : overrides optionnels (champ vide = utiliser le défaut plugin)

### 4.3 Gestion pause télécommande

Pendant une séance active, le daemon détecte le changement de statut :
- `Playing → Paused` : mémorise la section/top en cours, déclenche ambiance "Pause"
- `Paused → Playing` : remet l'ambiance correspondant à la section/top actuel

---

## 5. Moteur d'exécution

### 5.1 Enchaînement anticipé des médias

Pour éviter les trous entre les clips :

```
Média A en lecture (durée connue via RunTimeTicks)
  │
  │  Position = durée - 2s  →  Queue le média B
  │                             via /Sessions/{id}/Queue?ItemIds={id}&Mode=PlayNext
  │
  │  Position = durée - 0.5s → Envoie NextTrack
  │                             (B est déjà prêt en mémoire)
  │
  Média B démarre quasi-instantanément
```

Les seuils (-2s pour queue, -0.5s pour next) sont configurables dans la config plugin.

**Compatibilité clients :** Tous les clients Jellyfin ne supportent pas l'endpoint Queue. Sur les clients qui rejettent la requête Queue, le fallback PlayNow sera utilisé, ce qui peut causer un bref écran noir entre les clips.

### 5.2 Garde-fous de fiabilité

1. **Timeout fallback** : si après NextTrack, le nouveau média n'est pas détecté dans les 5s → force un `PlayNow` direct (appel API sans passer par la méthode `playMedia()` existante pour éviter le STOP + 300ms de l'Android TV fix)
2. **Vérification item_id** : à chaque cycle daemon, vérifie que l'`item_id` en lecture correspond au média attendu par le moteur
3. **Heartbeat séance** : à chaque cycle (0.5s), vérifie la cohérence état réel vs état attendu. Si incohérence → action corrective
4. **Seuils configurables** : les timings (queue, next, timeout) sont dans la config plugin pour s'adapter aux différents lecteurs
5. **Validation pré-lancement** : avant de démarrer une séance, le moteur valide tous les media_id via l'API Jellyfin. Si un média est introuvable, le lancement est refusé avec la liste des médias manquants
6. **Échec trigger runtime** : si un PlayNow échoue en cours de séance, le moteur skip au trigger suivant et log un warning. Si un command/scenario échoue, l'erreur est loguée mais l'exécution continue

### 5.3 Flux d'exécution — Cinéma

```
START séance
  ├─ Validation pré-lancement (vérif médias)
  ├─ state = "playing"
  ├─ current_section = première section non-vide
  ├─ Déclenche ambiance lumineuse de la section
  ├─ Exécute triggers séquentiellement :
  │    media    → PlayNow (+ pré-chargement suivant)
  │    pause    → durée fixe ou attente commande resume de la séance
  │    command  → fire-and-forget (exécute et passe au suivant immédiatement)
  │    scenario → fire-and-forget (lance et passe au suivant immédiatement)
  │
  ├─ Section terminée → section suivante
  │    Déclenche nouvelle ambiance lumineuse
  │
  ├─ Section FILM :
  │    Lecture du film
  │    Daemon track position en temps réel (0.5s)
  │    Position atteint un top marqué → déclenche ambiance du top
  │    Position atteint "FIN" → séance terminée
  │
  └─ state = "stopped"
```

**Sémantique fire-and-forget :** Les triggers `command` et `scenario` sont exécutés de manière asynchrone. Le moteur n'attend pas leur complétion. Si une action doit impérativement se terminer avant la lecture suivante (ex: allumer l'ampli), utiliser un scénario Jeedom avec des waits internes.

### 5.4 Flux d'exécution — Commercial

```
START diffusion
  ├─ Validation pré-lancement (vérif médias)
  ├─ state = "playing"
  ├─ PlayNow premier média + Queue deuxième
  ├─ À chaque fin de média → Next anticipé (même logique que cinéma)
  ├─ Après le dernier média → repart au premier
  └─ Boucle jusqu'à commande stop
```

### 5.5 État de séance en cours

Stocké dans le cache Jeedom (`cache::set('jellyfin::active_session::' . $playerEqLogicId)`) :

```json
{
  "session_eqlogic_id": 42,
  "player_eqlogic_id": 15,
  "current_section": "pubs",
  "current_trigger_index": 1,
  "current_media_id": "abc123",
  "expected_next_media_id": "def456",
  "queued": false,
  "current_lighting": "pubs",
  "started_at": 1710000000,
  "last_status": "Playing"
}
```

Volatile (RAM). Ne survit pas à un reboot (la séance serait perdue). La clé de cache est indexée par `player_eqlogic_id` pour garantir la contrainte "une seule séance par lecteur".

### 5.6 Point d'intégration du moteur

Le moteur s'exécute dans le callback du daemon, après le traitement standard :

```
jeeJellyfin.php reçoit les données du daemon
  │
  ├─ jellyfin::processSessions($payload)    ← existant (filtre les séances)
  │
  └─ jellyfin::tickSessionEngine($payload)  ← NOUVEAU
       │
       ├─ Pour chaque lecteur ayant une séance active (cache::get) :
       │    Lit l'état actuel du lecteur dans $payload
       │    Compare avec l'état attendu (cache)
       │    Prend les décisions : queue, next, changement section, ambiance
       │    Met à jour le cache
       │    Met à jour les commandes info de la séance (state, current_section, progress)
       │
       └─ Retourne
```

Ce tick s'exécute toutes les 0.5s. Les appels API Jellyfin depuis le moteur utilisent un timeout réduit de 2s (au lieu de 5s par défaut) pour ne pas bloquer le cycle.

### 5.7 Tolérance de timing

Le cycle daemon est de 0.5s. Le seuil NextTrack par défaut est à -0.5s. Il y a donc une fenêtre d'une seule itération pour déclencher le NextTrack. Si cette fenêtre est ratée (latence réseau, charge PHP), le fallback PlayNow (section 5.2 point 1) prend le relais avec un bref délai visible. C'est un compromis accepté : le daemon Python n'est pas modifié (pas de logique session côté daemon). Si les tests montrent que ce timing est insuffisant, on pourra élargir le seuil (ex: -1s) ou envisager une modification du daemon dans une version ultérieure.

### 5.8 Déconnexion du lecteur

Si le lecteur disparaît pendant une séance active :
- Le moteur détecte l'absence du lecteur dans les données du daemon
- Après 10s consécutives sans données du lecteur (configurable), la séance passe en `state = "paused"` avec un flag `player_lost = true`
- Si le lecteur réapparaît, la séance peut être reprise via la commande `resume` — le moteur tente de reprendre à la position la plus proche
- Si le lecteur ne réapparaît pas dans les 5 minutes, la séance passe en `state = "stopped"` avec une erreur loguée

### 5.9 Resynchronisation après redémarrage daemon

Si le daemon redémarre pendant une séance active :
- Le cache PHP est préservé (indépendant du daemon)
- Au premier tick après redémarrage, le moteur compare l'état réel du lecteur avec l'état caché
- Si le média en cours correspond à un trigger de la séance, le moteur se resynchronise automatiquement (met à jour l'index)
- Si le média ne correspond à rien de connu, la séance est marquée en erreur et stoppée

---

## 6. Configuration plugin

### 6.1 Nouveaux paramètres — Ambiances par défaut

14 sélecteurs de scénarios Jeedom :
- Section : preparation, intro, pubs, trailers, short_film, audio_trailer, film
- Tops film : pre_generique, generique_1, post_film_1, generique_2, post_film_2, fin
- Spécial : pause

### 6.2 Nouveaux paramètres — Timings d'enchaînement

| Paramètre | Défaut | Description |
|-----------|--------|-------------|
| queue_anticipation | 2 | Secondes avant la fin pour pré-charger le média suivant |
| next_anticipation | 0.5 | Secondes avant la fin pour envoyer NextTrack |
| fallback_timeout | 5 | Secondes d'attente avant fallback PlayNow |
| player_lost_timeout | 10 | Secondes avant pause séance si lecteur disparu |
| player_lost_max | 300 | Secondes avant arrêt séance si lecteur toujours absent |

---

## 7. Programmation et déclenchement

### 7.1 Depuis l'interface plugin

Bouton "Programmer" dans l'éditeur de séance → date picker + heure → crée un cron Jeedom one-shot (`cron::byClassAndFunction`).

### 7.2 Depuis un scénario Jeedom

Les commandes `start`, `stop`, `pause`, `resume` sont des commandes Jeedom standard, utilisables dans n'importe quel scénario.

### 7.3 Depuis le widget

Bouton 🎬 → modale séances → bouton "Lancer" → exécute la commande `start`.

---

## 8. API AJAX

### 8.1 Nouvelles actions

| Action | Paramètres | Retour | Description |
|--------|-----------|--------|-------------|
| `create_session` | `name`, `session_type` | eqLogic object | Crée une séance (cinéma ou commercial) |
| `save_session_data` | `id`, `session_data` (JSON) | ok | Sauvegarde la configuration complète de la séance |
| `get_session_data` | `id` | session_data JSON | Récupère la configuration de la séance |
| `start_session` | `id` | ok/error | Lance la séance (avec validation pré-lancement) |
| `stop_session` | `id` | ok | Arrête la séance |
| `pause_session` | `id` | ok | Suspend la séance |
| `resume_session` | `id` | ok | Reprend la séance |
| `schedule_session` | `id`, `datetime` | ok | Programme un lancement différé via cron |
| `calibrate_start` | `id`, `media_id` | ok/error | Lance le film sur le lecteur pour calibrage |
| `calibrate_set_mark` | `id`, `mark_name`, `position` | ok | Enregistre un top de calibrage |
| `get_sessions_for_player` | `player_id` | array | Liste les séances configurées pour un lecteur (pour la modale widget) |

### 8.2 Actions existantes inchangées

`add`, `remove`, `all`, `getLibrary`, `play_media`, `create_command`, `remove_command` — aucune modification.

---

## 9. Impact sur l'existant

### 9.1 Fichiers modifiés

- `core/class/jellyfin.class.php` :
  - `processSessions()` : ajout filtre `session_type` dans la boucle de nettoyage
  - `postSave()` : branchement `createCommands()` vs `createSessionCommands()`
  - `preRemove()` : arrêt séance active + nettoyage crons
  - Nouvelles méthodes : `createSessionCommands()`, `tickSessionEngine()`, `startSession()`, `stopSession()`, `pauseSession()`, `resumeSession()`, `validateSessionMedia()`, `getSessionLighting()`
- `core/ajax/jellyfin.ajax.php` : nouvelles actions (section 8.1)
- `desktop/php/jellyfin.php` : réorganisation page (séparation appareils/séances)
- `desktop/js/jellyfin.js` : éditeur accordéon, modale calibrage, modale séances widget
- `core/template/dashboard/jellyfin.html` : bouton 🎬 dans la barre de contrôles
- `plugin_info/configuration.php` : ambiances par défaut + timings
- `core/php/jeeJellyfin.php` : appel `tickSessionEngine()` après `processSessions()`

### 9.2 Ce qui ne change pas

- Le fonctionnement des appareils (détection, contrôle, widget lecteur)
- Le daemon Python (aucune modification — tolérance de timing acceptée, voir section 5.7)
- Le JellyfinBrowser (réutilisé tel quel pour la sélection de médias)
- Le proxy d'images
- Les commandes existantes des appareils
