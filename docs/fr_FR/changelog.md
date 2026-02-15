# Changelog

Ce fichier recense toutes les modifications notables apportées au plugin Jellyfin.

## [1.0.0] - 15-02-2026 (Release Candidate)

🌍 **Internationalisation & Correctifs**

* **Multi-langues** : Le plugin est désormais entièrement traduit en **Anglais** (en_US), **Allemand** (de_DE) et **Espagnol** (es_ES).
* **Correctif** : Réparation du bouton d'ouverture de la bibliothèque sur le widget.
* **Correctif** : Mise à jour de la syntaxe PHP dans la page de configuration pour une compatibilité parfaite avec le système de traduction Jeedom.
* **Documentation** : Mise à jour des liens et de la structure pour le Market.

---

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
* **Correctif Android TV** : Ajout d'une sécurité (pause 300ms) pour garantir le changement de média sur les box Android/Freebox POP.

### 🔧 Améliorations Techniques
* **Filtrage Intelligent** : Les nouveaux clients non-contrôlables ne créent plus d'équipements polluants.
* **Nettoyage de Session** : Forçage du statut "Stopped" si un client disparaît brutalement du réseau.

---

## [Beta] - 12-02-2026

🎉 **Lancement initial du plugin !**

* **Connexion WebSocket** : Écoute des événements en temps réel.
* **Découverte Auto** : Création automatique des équipements.
* **Contrôle Média** : Play, Pause, Stop, Seek, etc.
* **Métadonnées & Images** : Récupération complète.
* **Widget** : Interface graphique dédiée.
