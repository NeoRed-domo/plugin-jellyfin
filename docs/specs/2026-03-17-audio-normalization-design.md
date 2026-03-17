# Normalisation Audio — Spécification Technique

## 1. Vue d'ensemble

Système de normalisation audio pour les séances de diffusion. Mesure le LUFS (loudness perçue) de chaque clip, calcule le volume optimal par clip en fonction d'un volume de référence calibré, et applique des offsets par section et par profil audio.

### Principes directeurs
- Basé sur le standard LUFS (EBU R128 / Netflix / Spotify)
- Calibration par lecteur (chaque couple lecteur+ampli a sa propre référence)
- Offsets par section configurables (pubs plus basses, film au niveau de référence)
- Profils audio (Nuit/Cinéma/THX) pilotables par scénario Jeedom
- Override manuel par clip toujours possible
- Analyse via ffmpeg (dépendance optionnelle, ne bloque pas le daemon)
- Cache LUFS par media_id (évite les re-analyses pour les clips récurrents)
- 100% optionnel : sans ampli configuré, tout fonctionne comme avant

---

## 2. Modèle de données

### 2.1 Config lecteur (eqLogic appareil)

Champs existants :
- `amp_volume_cmd_id` : commande action slider volume ampli
- `amp_default_volume` : volume par défaut (0-100)

Nouveaux champs :
- `amp_volume_info_cmd_id` : commande info lecture volume ampli (pour capture)
- `audio_ref_volume` : volume de référence calibré (ex: 52)
- `audio_ref_media_id` : ID Jellyfin du média de référence
- `audio_ref_lufs` : LUFS mesuré du média de référence (ex: -24.3)

### 2.2 Config plugin (globale)

Offsets par section (en dB) :
- `audio_offset_preparation` : -12
- `audio_offset_intro` : -12
- `audio_offset_pubs` : -12
- `audio_offset_trailers` : -8
- `audio_offset_short_film` : -4
- `audio_offset_audio_trailer` : 0
- `audio_offset_film` : 0

Profils audio (en dB) :
- `audio_profile_night` : -20
- `audio_profile_cinema` : 0
- `audio_profile_thx` : +10

### 2.3 Commandes lecteur (nouvelles)

| Logical ID | Type | SubType | Description |
|-----------|------|---------|-------------|
| set_audio_profile | action | select | Changer le profil (night/cinema/thx) |
| audio_profile | info | string | Profil actif (night/cinema/thx) |

Valeur par défaut : "cinema". Pilotable par scénario Jeedom.

### 2.4 Par trigger média (dans session_data)

Champs ajoutés :
- `lufs` : valeur LUFS mesurée (null si pas analysé)
- `volume_auto` : volume calculé par normalisation (null si pas calibré)
- `volume` : override manuel (existant, prioritaire sur volume_auto)

### 2.5 Par séance (dans session_data)

- `audio_calibrated` : true/false — la séance a été normalisée

### 2.6 Formule de calcul

```
volume_raw = ref_volume + (ref_lufs - clip_lufs) + section_offset + profile_offset
volume_final = clamp(volume_raw, 0, 100)
```

Où :
- `ref_volume` : volume de référence calibré du lecteur (échelle ampli 0-100)
- `ref_lufs` : LUFS du média de référence
- `clip_lufs` : LUFS mesuré du clip
- `section_offset` : offset de la section (ex: -12dB pour pubs)
- `profile_offset` : offset du profil actif (ex: -20dB pour Nuit)

**Note sur les unités** : cette formule assume une relation approximativement linéaire entre les dB et l'échelle 0-100 de l'ampli. C'est une approximation valide pour la majorité des amplis grand public (1 step ≈ 1 dB). Le clamping garantit que la valeur reste dans les bornes de l'ampli.

Si `volume` (override manuel) est défini sur le trigger, il remplace `volume_final`.

### 2.7 Hiérarchie des volumes

1. **Volume forcé** (`trigger.volume`) → priorité absolue
2. **Volume auto** (`trigger.volume_auto`) → calculé par normalisation
3. **Volume par défaut** (`amp_default_volume`) → si rien n'est défini
4. **Aucun** → pas de changement de volume

---

## 3. Page de calibration audio

### 3.1 Accès

Nouveau bouton dans la barre de gestion du plugin : "🔊 Calibration audio"

### 3.2 Contenu

```
┌─ Calibration Audio ──────────────────────────────┐
│                                                    │
│  LECTEUR                                          │
│  [Shield TV ▼]                                    │
│  Calibration: ✓ Calibré (vol: 52, LUFS: -24.3)  │
│  ou: ⚠ Non calibré                               │
│                                                    │
│  1. MÉDIA DE RÉFÉRENCE                            │
│  [Sélectionner un média]  (JellyfinBrowser)       │
│  Média actuel: Interstellar    [Changer]          │
│                                                    │
│  2. ÉCOUTE                                        │
│  [▶ Lire en boucle]  [■ Arrêter]                 │
│  Réglez votre ampli au volume idéal.              │
│                                                    │
│  3. CAPTURE DU VOLUME                             │
│  [📡 Capturer le volume actuel]  →  52            │
│  (bouton désactivé si commande info non configurée│
│   sur le lecteur — fallback saisie manuelle)      │
│  Volume: [__52__] (éditable)                      │
│                                                    │
│  4. ANALYSE LUFS                                  │
│  [🔍 Analyser]  →  LUFS: -24.3                   │
│                                                    │
│  [💾 Sauvegarder]                                 │
└──────────────────────────────────────────────────┘
```

### 3.3 Workflow

1. Sélectionner le lecteur (dropdown des appareils avec ampli configuré)
2. Choisir un média de référence via JellyfinBrowser
3. Lancer la lecture en boucle (via Jellyfin `SetRepeatMode=RepeatOne` + PlayNow)
4. Régler l'ampli physiquement (ou via Jeedom)
5. Capturer le volume (lecture commande info ampli si configurée) — toujours éditable manuellement
6. Analyser le LUFS du média de référence (analyse complète)
7. Sauvegarder → stocké dans la config du lecteur

---

## 4. Normalisation d'une séance

### 4.1 Bouton dans l'éditeur

"🔊 Normaliser le son" dans la barre d'actions de la séance.
- Grisé si la calibration du lecteur n'a pas été faite
- Grisé si ffmpeg n'est pas installé (avec message explicatif)
- Badge "✓ Son normalisé" visible quand `audio_calibrated = true`

### 4.2 Workflow au clic

1. Choix du mode :
   - "Analyse rapide (~10-30s/clip)" : 60s d'audio au milieu du clip
   - "Analyse complète (~30-60s/clip)" : fichier entier

2. Barre de progression asynchrone :
   ```
   ▶ Analyse de "Pub Coca" (1/7)...
   ████████░░░░  LUFS: -18.2
   ▶ Analyse de "BA Avengers" (2/7)...
   ████░░░░░░░░
   ...
   ✓ Calcul des volumes...
   ✓ Application du profil "Cinéma"...
   ✓ 7 clips normalisés (1 erreur)
   ```
   Affichage minimum 1s par étape (pour lisibilité même si l'analyse est rapide).

3. Résultat : `volume_auto` calculé et affiché sur chaque trigger.

### 4.3 Analyse LUFS

**Méthode** : streaming vidéo via Jellyfin → extraction audio par ffmpeg sur Jeedom

```bash
# Analyse rapide (60s au milieu du clip)
# Utilise startTimeTicks pour seek au milieu (RunTimeTicks / 2)
curl -s "http://jellyfin:8096/Videos/{id}/stream?static=true&startTimeTicks={midpoint}&api_key=..." \
  | ffmpeg -i pipe:0 -vn -t 60 -af loudnorm=print_format=json -f null - 2>&1

# Analyse complète (fichier entier)
curl -s "http://jellyfin:8096/Videos/{id}/stream?static=true&api_key=..." \
  | ffmpeg -i pipe:0 -vn -af loudnorm=print_format=json -f null - 2>&1
```

Notes :
- `/Videos/{id}/stream?static=true` fonctionne pour tous les types de médias (vidéo et audio)
- `-vn` ignore la piste vidéo (seul l'audio est analysé)
- `startTimeTicks` = `RunTimeTicks / 2` pour l'analyse rapide (seek au milieu)
- Résultat parsé : `input_i` = integrated loudness en LUFS

**Cache LUFS** : les résultats sont cachés par media_id via `cache::set('jellyfin::lufs::' . $mediaId, $lufs)`. Si un clip a déjà été analysé (même média dans une autre séance), le cache est utilisé directement. L'utilisateur peut forcer une ré-analyse.

**Progression** : stockée dans `cache::set('jellyfin::audio_analysis::' . $sessionId)` :
```json
{
  "status": "analyzing",
  "current_clip": "Pub Coca",
  "current_index": 1,
  "total_clips": 7,
  "results": [
    {"media_id": "abc", "lufs": -18.2, "volume_auto": 55}
  ],
  "errors": [
    {"media_id": "def", "name": "Clip corrompu", "error": "ffmpeg: no audio stream found"}
  ]
}
```

Polling JS toutes les 2s.

### 4.4 Gestion des erreurs d'analyse

- **ffmpeg non installé** : bouton "Normaliser" grisé avec tooltip explicatif
- **ffmpeg échoue sur un clip** (fichier corrompu, pas de piste audio, timeout réseau) : le clip est ignoré, `lufs` reste null, `volume_auto` reste null, l'erreur est logguée et affichée dans le résumé
- **Impossible de streamer** (média supprimé de Jellyfin) : même traitement, skip + erreur
- Les clips en erreur utilisent le fallback habituel (volume par défaut ou pas de volume)

### 4.5 Affichage dans l'éditeur

- `🔊 auto:48` en vert = volume calculé par normalisation
- `🔊 52` en orange = override manuel (prioritaire)
- `🔇` en gris = pas de volume (ni auto ni forcé, pas d'ampli)
- `⚠ LUFS: erreur` en rouge = analyse échouée pour ce clip

### 4.6 Sessions commerciales

La normalisation est disponible pour les sessions commerciales. Les clips commerciaux n'ayant pas de section, l'offset section est 0dB. Seuls le LUFS + le profil actif s'appliquent.

---

## 5. Profils audio

### 5.1 Configuration (plugin)

3 champs dans la section audio de la config :
```
Profils audio :
  Nuit   : [-20] dB
  Cinéma : [  0] dB  (référence)
  THX    : [+10] dB
```

### 5.2 Commandes lecteur

Nouvelles commandes sur l'eqLogic appareil :
- `set_audio_profile` : action/select avec options "night|Nuit;cinema|Cinéma;thx|THX"
- `audio_profile` : info/string, valeur par défaut "cinema"

### 5.3 Application

Le moteur lit `audio_profile` du lecteur à chaque changement de clip (dans `applyVolume`). L'offset du profil actif est ajouté à la formule.

**Changement de profil en cours de séance** : le nouveau profil s'applique au prochain clip. Le clip en cours n'est pas affecté (pas de changement de volume en plein milieu d'une lecture).

### 5.4 Signature applyVolume mise à jour

`applyVolume($playerEq, $trigger, $sectionKey)` — le paramètre `$sectionKey` est ajouté pour résoudre l'offset de section. Les 4 call sites existants sont mis à jour. Pour les sessions commerciales, `$sectionKey = 'commercial'` (offset 0dB).

---

## 6. Dépendance ffmpeg

### 6.1 Installation

Ajout dans `resources/install_apt.sh` :
```bash
sudo apt-get install -y ffmpeg
```

### 6.2 Vérification (soft dependency)

ffmpeg est une **dépendance optionnelle** — ne bloque PAS le daemon.
- `dependancy_info()` continue de vérifier uniquement python3+requests
- La disponibilité de ffmpeg est vérifiée au moment de l'analyse (`which ffmpeg`)
- Si ffmpeg est absent : le bouton "Normaliser" est grisé, la page calibration affiche un message d'installation

---

## 7. Impact sur l'existant

### 7.1 Fichiers modifiés

- `core/class/jellyfin.class.php` :
  - `createCommands()` : ajout set_audio_profile / audio_profile
  - `applyVolume($playerEq, $trigger, $sectionKey)` : signature étendue, formule LUFS
  - Nouvelle méthode : `analyzeLufs($mediaId, $mode)` — appel ffmpeg
  - Mise à jour des 4 call sites de applyVolume
- `core/ajax/jellyfin.ajax.php` : nouvelles actions (save_calibration, analyze_session_audio, get_analysis_progress, capture_amp_volume)
- `desktop/php/jellyfin.php` : champ commande info volume ampli, bouton calibration audio
- `desktop/js/jellyfin.js` : page calibration (modale), bouton normalisation, progression asynchrone, affichage volume auto/forcé
- `plugin_info/configuration.php` : offsets par section, profils audio
- `resources/install_apt.sh` : ajout ffmpeg

### 7.2 Ce qui ne change pas

- Le moteur d'exécution (tickCinema/tickCommercial) — utilise `applyVolume` (signature étendue)
- La structure des séances (session_data) — on ajoute des champs, on ne modifie pas l'existant
- Le daemon Python
- `dependancy_info()` — pas de modification (ffmpeg = soft dependency)
