# Jellyfin (jellyfin)

Bonjour à tous,

Je vous présente mon plugin **Jellyfin** pour Jeedom.

## Nom et id

> Jellyfin (jellyfin)

## Ce que fait le plugin

Intégration complète d'un serveur **Jellyfin** (alternative open source à Plex/Emby) dans Jeedom :

- **Contrôle des lecteurs** : play, pause, stop, suivant, précédent, seek — sur tous les clients Jellyfin (Android TV, navigateur, etc.)
- **Informations temps réel** : titre, jaquette, progression, durée, type de média — remontés toutes les 0.25s via un daemon dédié
- **Widget personnalisé** : affichage type lecteur multimédia avec barre de progression interactive, fond flou dynamique, panneau de favoris
- **Explorateur de bibliothèque** : navigation, recherche et lecture directe depuis l'interface Jeedom
- **Séances cinéma automatisées** : enchaînement de clips organisés en 7 sections (préparation, intro, publicités, bandes annonces, court métrage, sound trailer, film), avec ambiances lumineuses par section, tops film calibrables, et gestion de la pause télécommande
- **Diffusions commerciales** : playlists en boucle (infinie, N fois, ou unique) pour de l'affichage dynamique
- **Normalisation audio LUFS** : calibration par bruit rose, mesure EBU R128 via ffmpeg, volume ampli ajusté automatiquement clip par clip
- **Profils audio** : Nuit / Cinéma / THX / Manuel (cinéma) et Muet / Discret / Normal / Fort / Manuel (commercial)
- **Multi-langues** : FR, EN, ES, DE

## Langages utilisés

- **PHP** : backend, logique métier, moteur de séances
- **Python 3** : daemon de polling
- **JavaScript** : interface desktop, éditeur de séances, widget
- **HTML/CSS** : widget dashboard custom

## Daemon, dépendances, cron

- **Daemon** : oui, daemon Python qui interroge l'API Jellyfin toutes les 0.25s et envoie les données au callback PHP
- **Dépendances** : oui — python3, python3-requests, ffmpeg (installées via apt)
- **Cron** : non

## Panel dédié

Non.

## Gratuit ou payant

**Gratuit** et open source (licence AGPL).

## Lien GitHub

https://github.com/NeoRed-domo/plugin-jellyfin

---

**Tags** : `jellyfin`, `demon`, `dependance_install`, `python`, `stable`, `gratuit`
