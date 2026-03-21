# Plugin Jellyfin pour Jeedom

## Description

Le plugin Jellyfin permet l'intégration avancée d'un serveur Jellyfin dans Jeedom. Il offre le pilotage complet de vos lecteurs, la remontée d'informations en temps réel (médias, jaquettes, progression), et l'automatisation de séances cinéma et de diffusions commerciales.

### Fonctionnalités principales

- **Contrôle des lecteurs** : play, pause, stop, suivant, précédent, seek
- **Informations en temps réel** : titre, durée, position, couverture, type de média
- **Explorateur de bibliothèque** : parcourir et rechercher dans votre médiathèque Jellyfin
- **Raccourcis favoris** : accès rapide à vos médias préférés depuis le widget
- **Séances cinéma** : enchaînement automatisé de clips par sections (intro, pubs, bandes annonces, film) avec ambiances lumineuses et tops film
- **Diffusions commerciales** : lecture en boucle de playlists de médias
- **Normalisation audio** : calibration LUFS et contrôle automatique du volume de l'ampli
- **Profils audio** : Nuit, Cinéma, THX (cinéma) / Muet, Discret, Normal, Fort (commercial)
- **Multi-langues** : français, anglais, espagnol, allemand

---

## Installation

### Prérequis

- Jeedom version 4.4 ou supérieure
- Un serveur Jellyfin accessible depuis le réseau local
- Une clé API Jellyfin (générée dans le Dashboard Jellyfin > Clés d'API)

### Installation du plugin

1. Depuis le Market Jeedom, recherchez "Jellyfin" et installez le plugin
2. Activez le plugin
3. Lancez l'installation des dépendances (Python 3, requests, ffmpeg)
4. Configurez le plugin (voir ci-dessous)
5. Démarrez le daemon

### Configuration du serveur

Accédez à la page de configuration du plugin :

- **IP du serveur** : adresse IP de votre serveur Jellyfin (sans `http://`)
- **Port du serveur** : port Jellyfin (par défaut 8096)
- **Clé API Jellyfin** : clé générée dans Jellyfin > Dashboard > Clés d'API

---

## Détection des types de média

Le plugin peut identifier le type de chaque média en analysant le chemin du fichier. Configurez des mots-clés séparés par des virgules pour chaque type :

- **Films** : ex. `film, movie`
- **Séries** : ex. `serie, show`
- **Audio / Musique** : ex. `music, audio, album`
- **Publicités** : ex. `pub, advert`
- **Bandes Annonces** : ex. `trailer, bande-annonce`
- **Sound Trailers** : ex. `jingle, dts, dolby`

---

## Les Lecteurs Jellyfin

### Détection automatique

Les lecteurs sont détectés automatiquement lorsqu'ils lancent une lecture sur Jellyfin. Un équipement Jeedom est créé pour chaque lecteur détecté.

### Configuration d'un lecteur

Cliquez sur un lecteur pour accéder à sa configuration :

- **Device ID** : identifiant unique du lecteur (détecté automatiquement)
- **Afficher le liseré** : active un cadre coloré autour du widget
- **Couleur du liseré** : couleur du cadre

#### Configuration audio (optionnel)

- **Commande volume ampli** : sélectionnez la commande Jeedom de type action/slider qui contrôle le volume de votre ampli. Nécessaire pour la normalisation audio.
- **Volume par défaut** : volume appliqué quand aucun volume n'est défini par clip (0-100)
- **Type de sortie audio** :
  - *Ampli (passthrough)* : l'ampli décode l'audio (DTS, AC3). Utilisez ce mode si votre lecteur envoie le flux audio brut à l'ampli via HDMI.
  - *TV / PCM* : le client décode l'audio. Utilisez ce mode si le son sort directement de la TV.
- **Commande info volume ampli** : commande Jeedom de type info qui lit le volume actuel de l'ampli. Optionnel, utilisé pour la calibration audio.

### Commandes disponibles

| Commande | Type | Description |
|----------|------|-------------|
| Prev | action | Piste précédente (rewind si > 30s) |
| Play | action | Reprendre la lecture |
| Pause | action | Mettre en pause |
| Play/Pause | action | Basculer lecture/pause |
| Next | action | Piste suivante |
| Stop | action | Arrêter la lecture |
| Title | info | Titre du média en cours |
| Status | info | Statut (Playing/Paused/Stopped) |
| Duration | info | Durée totale (HH:MM:SS) |
| Position | info | Position actuelle |
| Remaining | info | Temps restant |
| Cover | info | Couverture du média (HTML img) |
| Media Type | info | Type de média détecté |
| Set Position | action | Seek à une position (slider) |
| Profil audio cinéma | info | Profil audio actif |
| Changer profil cinéma | action | Changer le profil (Nuit/Cinéma/THX/Manuel) |
| Profil audio commercial | info | Profil commercial actif |
| Changer profil commercial | action | Changer le profil commercial |

---

## Le Widget

Le widget du lecteur affiche en temps réel les informations de lecture :

- **Couverture** du média avec fond flou
- **Titre**, statut et type de média
- **Barre de progression** interactive (clic pour seek)
- **Contrôles** : précédent, play/pause, stop, suivant
- **Bouton favoris** (coeur) : ouvre le panneau des raccourcis
- **Bouton séances** (film) : ouvre la liste des séances disponibles
- **Bouton bibliothèque** (logo Jellyfin) : ouvre l'explorateur

### Explorateur de bibliothèque

Cliquez sur le logo Jellyfin pour ouvrir l'explorateur :

- Navigation par dossiers avec fil d'ariane
- Recherche dans toute la bibliothèque
- Informations techniques (résolution, codec audio)
- Lecture directe ou ajout aux favoris

### Raccourcis favoris

Le panneau favoris permet un accès rapide à vos médias :

- Ajoutez un favori depuis l'explorateur ou depuis le widget (bouton coeur sur le média en cours)
- Cliquez sur un favori pour le lancer
- Supprimez un favori avec le bouton ✕

---

## Séances Cinéma

### Concept

Une séance cinéma est un enchaînement automatisé de médias organisés en sections, avec gestion des ambiances lumineuses et contrôle du volume audio.

### Créer une séance

1. Cliquez sur **"Nouvelle séance"** dans la page du plugin
2. Choisissez **"Séance cinéma"** et donnez un nom
3. Dans l'onglet **Équipement**, sélectionnez le lecteur cible
4. Passez à l'onglet **Séance** pour configurer le contenu

### Les sections

Une séance cinéma est composée de 7 sections, chacune identifiée par une couleur :

| Section | Couleur | Description |
|---------|---------|-------------|
| Préparation | Orange | Actions avant la séance (fermer volets, allumer ampli...) |
| Intro | Violet | Clips d'introduction (logos, jingles) |
| Publicités | Rouge | Spots publicitaires |
| Bandes annonces | Cyan | Trailers de films |
| Court métrage | Jaune | Courts métrages |
| Trailer audio | Bleu | Sound trailers (DTS, Dolby...) |
| Film | Vert | Le film principal |

### Les déclencheurs (triggers)

Chaque section contient une liste ordonnée de déclencheurs :

- **Média** : un clip vidéo de la bibliothèque Jellyfin
- **Pause** : un temps d'attente (0 = pause illimitée, reprise manuelle)
- **Action** : une commande Jeedom ou un scénario Jeedom

Les déclencheurs peuvent être :
- **Réordonnés** avec les flèches ↑ ↓
- **Supprimés** avec le bouton ✕
- **Activés/Désactivés** individuellement avec le toggle
- **Édités** : cliquez sur le label d'une pause ou action pour la modifier

### Activer/Désactiver une section

Chaque section dispose d'un toggle. Une section désactivée est ignorée lors de la lecture.

### Tops film (calibrage)

Les tops permettent de déclencher des ambiances lumineuses à des moments précis du film :

| Top | Description |
|-----|-------------|
| Pré-générique | Le film commence à ralentir |
| Générique 1 | Début du premier générique |
| Post film 1 | Scène post-générique |
| Générique 2 | Reprise du générique |
| Post film 2 | Deuxième scène post-générique |
| Fin | Fin de la séance |

Pour calibrer : ajoutez un film, cliquez "Calibrer tops", utilisez le lecteur vidéo intégré pour marquer les tops.

### Ambiances lumineuses

Chaque section et chaque top peut déclencher un scénario Jeedom. Configurez les défauts dans la configuration du plugin. Chaque séance peut surcharger ces valeurs.

Si le spectateur met en pause avec sa télécommande, l'ambiance "Pause" se déclenche. À la reprise, l'ambiance de la section en cours est restaurée.

### Lancer une séance

1. **Depuis l'éditeur** : bouton "Lancer"
2. **Depuis le widget** : bouton 🎬
3. **Depuis un scénario** : commande `start`

---

## Diffusions Commerciales

### Concept

Une diffusion commerciale est une playlist de médias jouée en boucle, sans sections ni ambiances lumineuses.

### Modes de boucle

- **Pas de boucle** : lecture unique
- **Boucle infinie** : recommence indéfiniment
- **Nombre de boucles** : boucle N fois puis s'arrête

---

## Normalisation Audio

### Concept

La normalisation analyse le volume de chaque clip (mesure LUFS) et ajuste automatiquement le volume de l'ampli pour un niveau sonore homogène. Standard EBU R128 / Netflix / Spotify.

### Calibration

1. **"Calibration audio"** dans la page du plugin
2. Téléchargez et importez le **bruit rose** de référence dans Jellyfin (une seule fois)
3. Sélectionnez le lecteur et le bruit rose
4. Réglez votre ampli au volume idéal et saisissez la valeur
5. Analysez le LUFS et sauvegardez

### Normaliser une séance

1. Bouton **"Normaliser le son"** dans l'éditeur
2. Choisissez analyse rapide ou complète
3. Les volumes auto sont calculés et appliqués

### Profils audio

| Profil cinéma | Offset | Profil commercial | Offset |
|---------------|--------|-------------------|--------|
| Nuit | -20 dB | Muet | vol=0 |
| Cinéma | 0 dB | Discret | -20 dB |
| THX | +10 dB | Normal | 0 dB |
| Manuel | bypass | Fort | +5 dB |
| | | Manuel | bypass |

Le profil "Manuel" désactive complètement le contrôle du volume par le plugin.

---

## Intégration Scénarios Jeedom

### Commandes disponibles

```
#[Salon][Séance Samedi][start]#     → Lancer la séance
#[Salon][Séance Samedi][stop]#      → Arrêter
#[Salon][Séance Samedi][state]#     → État (stopped/playing/paused)
#[Salon][Séance Samedi][progress]#  → Progression (%)

#[Salon][Shield TV][set_audio_profile]# → Changer profil cinéma
#[Salon][Shield TV][set_commercial_audio_profile]# → Changer profil commercial
```

---

## Dépannage

### Le daemon ne démarre pas
Vérifiez la configuration (IP, port, clé API) et les dépendances.

### Les clips ne s'enchaînent pas
Vérifiez les logs `jellyfin` en mode INFO. Le daemon doit être démarré.

### La normalisation ne fonctionne pas
ffmpeg doit être installé. La calibration doit être effectuée. La commande volume doit être configurée sur le lecteur.

### Le volume est trop fort / trop bas
Ajustez les offsets par section, la compensation bruit rose (+4 dB par défaut), ou utilisez le profil "Manuel" pour reprendre le contrôle.

### Les commandes (play, pause, stop) ne fonctionnent pas
Si votre Jellyfin est derrière un **reverse proxy** (nginx, Apache, Caddy, Traefik...), il faut activer le forwarding des WebSockets. Sans cela, les clients ne peuvent pas établir de connexion WebSocket avec le serveur, et Jellyfin marque toutes les sessions comme non-contrôlables (`SupportsRemoteControl: false`).

- **Nginx Proxy Manager** : activez l'option "WebSocket Support" dans la configuration du proxy host
- **Nginx manuel** : ajoutez les directives `proxy_set_header Upgrade $http_upgrade;` et `proxy_set_header Connection "upgrade";`
- **Apache** : activez les modules `mod_proxy_wstunnel` et `mod_rewrite`

Après la modification, redémarrez votre reverse proxy et rafraîchissez vos clients Jellyfin.

### L'équipement n'est pas créé
L'équipement est créé automatiquement lorsqu'un média est en lecture. Si aucun équipement n'apparaît, vérifiez les logs `jellyfin` en mode Debug pour voir les sessions détectées et leur statut.
