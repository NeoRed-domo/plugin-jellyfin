# Changelog

Ce fichier recense toutes les modifications notables apportées au plugin Jellyfin.

## [1.2.1] - 20-03-2026

🔧 **Corrections**

* **Widget temps réel** : le titre et le type de média se mettent désormais à jour en temps réel lors des changements de clip (listeners manquants)
* **Jaquette temps réel** : remplacement du stockage base64 (trop volumineux pour les events Jeedom) par une URL proxy légère — la jaquette se met à jour instantanément
* **Halo monitoring** : le halo vert sur le clip actif fonctionne désormais sur les diffusions commerciales et les séances cinéma
* **Race condition double-fire** : les actions du moteur (lancement, volume, ambiance) ne se déclenchent plus en double grâce à l'écriture anticipée du cache avant les appels HTTP lents

---

## [1.2.0] - 20-03-2026

🌟 **Normalisation audio & Améliorations majeures**

### Normalisation audio (LUFS)
* **Calibration** : bruit rose intégré (-24 LUFS), mesure via ffmpeg, formule EBU R128
* **Profils cinéma** : Nuit (-20 dB), Cinéma (0 dB), THX (+10 dB), Manuel (bypass)
* **Profils commercial** : Muet, Discret (-20 dB), Normal (0 dB), Fort (+5 dB), Manuel (bypass)
* **Contrôle ampli** : volume ajusté automatiquement clip par clip avec offsets par section
* **Type de sortie audio** : Ampli (passthrough, correction DRC AC3) ou TV/PCM
* **Changement de profil en temps réel** pendant la lecture

### Améliorations des séances
* **Monitoring live** : halo vert animé sur la section et le clip en cours de lecture
* **Progression** : basée sur la durée réelle de lecture (et non le nombre de clips)
* **Badges techniques** : résolution vidéo et codec audio affichés sur chaque clip
* **Toggles** : activation/désactivation individuelle des sections et déclencheurs
* **Compteur de boucle** visible pendant les diffusions commerciales

### Documentation & Traductions
* **Documentation complète** en 4 langues (FR, EN, ES, DE)
* **305 chaînes traduites** par langue

### Corrections notables
* Enchaînements fiabilisés (playlist PlayNow + auto-avancement Jellyfin)
* Mesure LUFS précise (fichier temporaire au lieu du pipe, correction DRC AC3)
* Double-fire sur auto-avancement (écriture cache immédiate)
* Commande volume ampli sauvegardait le nom au lieu de l'ID

---

## [1.1.0] - 14-03-2026

🎬 **Séances de diffusion**

### Séances cinéma
* **7 sections** : Préparation, Intro, Publicités, Bandes annonces, Court métrage, Trailer audio, Film
* **Ambiances lumineuses** : scénario Jeedom par section et par top film
* **Tops film calibrables** : pré-générique, générique 1, post film 1, générique 2, post film 2, fin
* **Éditeur de séance** : interface accordéon avec couleurs par section, drag & drop des déclencheurs

### Diffusions commerciales
* **Playlist en boucle** : infinie, N fois, ou lecture unique
* **Enchaînement automatique** via playlist Jellyfin

### Widget
* **Bouton séance** (🎬) pour lancer une séance depuis le dashboard
* **Liste des séances** avec poster, durée et statistiques

### Moteur d'exécution
* Daemon polling à 0.25s pour réactivité maximale
* Machine à états (attente lancement, en lecture, média terminé)
* Détection auto-avancement Jellyfin (resync)
* Pré-chauffage du prochain clip (warm-up transcoding)
* Proxy vidéo HTTPS/HTTP pour la calibration

---

## [1.0.0] - 15-02-2026

🌍 **Première version stable**

* **Multi-langues** : plugin traduit en anglais (en_US), allemand (de_DE) et espagnol (es_ES)
* **Correctif** : bouton bibliothèque du widget
* **Correctif** : syntaxe PHP page de configuration
* **Documentation** : liens et structure pour le Market

---

## [Beta] - 14-02-2026

🌟 **Médiathèque & Favoris**

* **Explorateur de bibliothèque** : navigation, recherche, détails média
* **Gestion des favoris** : ajout, lancement rapide, suppression
* **Barre de progression** améliorée
* **Filtrage intelligent** des clients non-contrôlables

---

## [Beta] - 12-02-2026

🎉 **Lancement initial**

* Détection automatique des lecteurs
* Contrôle média : Play, Pause, Stop, Seek
* Métadonnées et images en temps réel
* Widget dashboard avec barre de progression interactive
* Daemon Python pour connexion permanente
