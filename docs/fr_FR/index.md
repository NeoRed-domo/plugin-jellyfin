# Plugin Jellyfin pour Jeedom

Ce plugin permet de connecter votre serveur **Jellyfin** à Jeedom pour récupérer l'état de lecture de vos différents lecteurs (Clients), les contrôler et naviguer dans votre bibliothèque multimédia.

**Langues supportées :** 🇫🇷 Français | 🇺🇸 English | 🇩🇪 Deutsch | 🇪🇸 Español

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
* *Note : Optimisé pour Android TV (Freebox POP, Shield...) avec gestion des délais de latence.*

### 3. Explorateur de Bibliothèque (Médiathèque)
Plus besoin de sortir de Jeedom pour choisir quoi regarder !
* Cliquez sur le logo Jellyfin du widget pour ouvrir l'explorateur.
* **Navigation fluide** dans vos dossiers, films et musiques.
* **Fil d'ariane** (Breadcrumb) interactif pour remonter dans l'arborescence.
* **Détails du média** : Affichage du résumé (synopsis), de l'année, de la note communautaire et de la durée.
* **Lancement direct** : Lancez la lecture sur l'équipement cible d'un simple clic.

### 4. Gestion des Favoris
Créez des raccourcis vers vos contenus préférés directement sur le widget.
* **Ajout facile** : Depuis l'explorateur, cliquez sur "Ajouter aux favoris".
* **Accès rapide** : Un tiroir latéral sur le widget affiche vos favoris avec leurs affiches.
* **Lancement one-click** : Lancez votre playlist ou votre film favori instantanément.

### 5. Optimisations Techniques
* **Démon Python** : Connexion WebSocket réactive et peu gourmande.
* **Filtrage Intelligent** : Gestion propre des équipements pour éviter la pollution de Jeedom.
* **Internationalisation** : Interface entièrement traduite (FR, EN, DE, ES).

---

## 🔧 Installation et Configuration

1.  Installez le plugin depuis le Market Jeedom.
2.  Activez le plugin.
3.  Installez les **dépendances** (nécessaire pour le démon Python).
4.  Dans la configuration du plugin :
    * Renseignez l'**Adresse IP** de votre serveur Jellyfin.
    * Renseignez le **Port** (par défaut `8096` ou `443` si HTTPS).
    * Renseignez la **Clé API** (À générer dans Jellyfin : *Tableau de bord > Avancé > Clés d'API*).
5.  Lancez le Démon (Vérifiez qu'il est au statut OK).
6.  Lancez une lecture sur un de vos appareils Jellyfin : l'équipement sera automatiquement créé dans Jeedom.

---

## 📱 Le Widget

Le plugin inclut un widget dédié, conçu pour s'intégrer parfaitement au Dashboard :
* **Design sombre** (Dark mode) reprenant les codes de Jellyfin.
* **Fond dynamique** basé sur la jaquette du média en cours (effet flouté).
* **Tiroir de favoris** rétractable pour gagner de la place (cliquez sur le cœur).
* **Bouton Bibliothèque** (Logo Jellyfin) pour parcourir vos médias.

---

## ⚠️ FAQ & Remarques
* **Pourquoi mon équipement n'apparaît pas ?** : Lancez une lecture sur l'appareil. Le plugin ne crée les équipements que lorsqu'ils sont actifs pour la première fois.
* **Contrôle impossible ?** : Certains clients (navigateurs web, certaines TV DLNA) ne supportent pas le contrôle à distance. Le plugin remontera les infos mais les boutons Play/Pause seront inactifs.
* **Bibliothèque vide ?** : Vérifiez que votre serveur Jellyfin est bien allumé et accessible depuis Jeedom.

---

**Auteur :** NeoRed
**Licence :** AGPL
