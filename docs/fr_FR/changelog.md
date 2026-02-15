# Changelog Bêta

Ce fichier recense toutes les modifications notables apportées au plugin Jellyfin en version Bêta.

## [Beta] - 14-02-2026

🌟 **Mise à jour majeure : Médiathèque & Favoris**

### 🆕 Nouvelles Fonctionnalités
* **Explorateur de Bibliothèque** : Navigation complète dans les dossiers, films et musiques via une interface dédiée (accessible via le logo Jellyfin).
* **Gestion des Favoris** : Possibilité d'ajouter des médias en favoris, de les visualiser dans un tiroir latéral sur le widget et de les lancer en un clic.
* **Lancement Direct** : Possibilité de lancer la lecture d'un média spécifique sur un équipement depuis l'explorateur Jeedom.
* **Détails Média** : Affichage du résumé (synopsis), de la note, de l'année et de la durée exacte avant le lancement.

### 🎨 Interface & Widget
* **Ratio d'image Adaptatif** : Gestion automatique du format de la jaquette (Carré pour la musique, Rectangle/Poster pour les films).
* **Fil d'Ariane Interactif** : Navigation cliquable dans l'explorateur pour revenir facilement aux dossiers précédents.
* **Ergonomie** : Ajout du nom de l'équipement dans les fenêtres contextuelles et confirmations visuelles.
* **Barre de progression** : Amélioration de la fluidité et de la précision du contrôle (Seek).

### 🔧 Améliorations Techniques
* **Filtrage Intelligent** : Les nouveaux clients non-contrôlables ne créent plus d'équipements polluants, mais ceux existants continuent d'être mis à jour.
* **Nettoyage de Session** : Forçage du statut "Stopped" si un client disparaît brutalement du réseau (ex: fermeture navigateur).
* **Standardisation** : Passage des ID de commandes internes en Anglais (pour la stabilité) et labels d'affichage en Français.

---

## [Beta] - 12-02-2026

🎉 **Lancement initial du plugin sur le Market Jeedom !**

### 🚀 Fonctionnalités
* **Connexion WebSocket** : Écoute des événements du serveur en temps réel (plus réactif qu'un cron).
* **Découverte Auto** : Création automatique des équipements Jeedom dès qu'une lecture est détectée sur le serveur.
* **Contrôle Média** : Commandes Play, Pause, Stop, Précédent, Suivant et Seek (changement de position).
* **Métadonnées** : Récupération complète (Titre, Album, Artiste, Série, Saison, Épisode) et gestion des images.
* **Widget** : Interface graphique dédiée (Dashboard & Mobile) avec barre de progression interactive.
* **Système** : Gestion du démon en Python pour la connexion permanente.
