# Plugin Jellyfin pour Jeedom

![Jeedom Version](https://img.shields.io/badge/Jeedom-4.4%2B-success) ![Version](https://img.shields.io/badge/Version-Beta-orange) ![License](https://img.shields.io/badge/License-AGPL-blue)

**Intégrez votre serveur multimédia Jellyfin au cœur de votre domotique.**

Ce plugin a été conçu pour offrir une interaction fluide, rapide et fiable entre Jeedom et Jellyfin. Il ne se contente pas d'envoyer des commandes : il écoute votre serveur en temps réel pour une expérience utilisateur sans latence.

![Logo Jellyfin](plugin_info/jellyfin_icon.png)

## 🌟 Fonctionnalités Principales

### 1. Remontée d'informations en temps réel
* **Détection automatique** des clients Jellyfin actifs sur le réseau.
* **État de lecture** : Lecture, Pause, Stop.
* **Informations Média** : Titre, Série, Saison, Episode, Artiste, Album.
* **Temps** : Durée totale, position actuelle et temps restant.
* **Visuel** : Récupération de la **jaquette (Cover)** avec gestion automatique du ratio (Carré pour la musique, Poster pour les films).

### 2. Contrôle du lecteur (Télécommande)
* Play / Pause / Stop.
* Précédent / Suivant.
* Contrôle de la position (Seek) via une barre de progression interactive sur le widget.

### 3. 🆕 Explorateur de Bibliothèque (Médiathèque)
Plus besoin de sortir de Jeedom pour choisir quoi regarder !
* Cliquez sur le logo Jellyfin du widget pour ouvrir l'explorateur.
* **Navigation fluide** dans vos dossiers, films et musiques.
* **Fil d'ariane** (Breadcrumb) interactif pour remonter dans l'arborescence.
* **Détails du média** : Affichage du résumé (synopsis), de l'année, de la note communautaire et de la durée avant le lancement.
* **Lancement direct** : Lancez la lecture d'un film ou d'une musique sur l'équipement cible d'un simple clic.

### 4. 🆕 Gestion des Favoris
Créez des raccourcis vers vos contenus préférés directement sur le widget.
* **Ajout facile** : Depuis l'explorateur, cliquez sur "Ajouter aux favoris".
* **Accès rapide** : Un tiroir latéral sur le widget affiche vos favoris avec leurs affiches.
* **Lancement one-click** : Lancez votre playlist, votre film ou votre chaine TV favorite instantanément.
* **Suppression** : Gestion simple des favoris obsolètes directement depuis le widget.

### 5. Optimisations Techniques
* **Démon Python** : Utilisation d'un démon pour une écoute "WebSocket" des événements Jellyfin (réactif et peu gourmand).
* **Filtrage Intelligent** : Ne crée pas d'équipements pour les clients non contrôlables (pour éviter de polluer Jeedom), mais assure la mise à jour des infos pour les clients existants.
* **Nettoyage Automatique** : Gestion des sessions fantômes (si un lecteur est éteint brutalement).

---

## 🔧 Installation et Configuration

1.  Installez le plugin depuis le Market Jeedom (ou via GitHub).
2.  Activez le plugin.
3.  Installez les **dépendances** (nécessaire pour le démon Python).
4.  Dans la configuration du plugin :
    * Renseignez l'**Adresse IP** de votre serveur Jellyfin.
    * Renseignez le **Port** (par défaut `8096` ou `443` si HTTPS).
    * Renseignez la **Clé API** (À générer dans Jellyfin : *Tableau de bord > Avancé > Clés d'API*).
5.  Lancez le Démon.
6.  Lancez une lecture sur un de vos appareils Jellyfin : l'équipement sera automatiquement créé dans Jeedom.

---

## 📱 Le Widget

Le plugin inclut un widget dédié, conçu pour s'intégrer parfaitement au Dashboard :
* **Design sombre** (Dark mode) reprenant les codes de Jellyfin.
* **Fond dynamique** basé sur la jaquette du média en cours (effet flouté).
* **Tiroir de favoris** rétractable pour gagner de la place.

---

## ⚠️ Remarques
* Les équipements ne sont créés que s'ils sont détectés comme actifs par le serveur Jellyfin.
* Certains clients (navigateurs web, certains TV) peuvent ne pas supporter le contrôle à distance (Play/Pause), mais les informations de lecture remonteront quand même.

---

**Auteur :** NeoRed
**Licence :** AGPL

*   **Un bug ?** Merci d'ouvrir une [Issue](https://github.com/NeoRed-domo/plugin-jellyfin/issues) en décrivant précisément le problème et en fournissant les logs en mode `Debug`.
*   **Une idée ?** N'hésitez pas à proposer des améliorations via des Pull Requests.

---

*Développé par [NeoRed-domo](https://github.com/NeoRed-domo).*
*Ce plugin n'est pas affilié officiellement au projet Jellyfin.*
