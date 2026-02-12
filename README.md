# Plugin Jellyfin pour Jeedom

![Jeedom Version](https://img.shields.io/badge/Jeedom-4.4%2B-success) ![Version](https://img.shields.io/badge/Version-Beta-orange) ![License](https://img.shields.io/badge/License-AGPL-blue)

**Intégrez votre serveur multimédia Jellyfin au cœur de votre domotique.**

Ce plugin a été conçu pour offrir une interaction fluide, rapide et fiable entre Jeedom et Jellyfin. Il ne se contente pas d'envoyer des commandes : il écoute votre serveur en temps réel pour une expérience utilisateur sans latence.

---

## ⚡ Fonctionnalités Clés

*   **Pilotage Complet** : Lecture, Pause, Stop, Précédent, Suivant, Seek (saut dans la timeline).
*   **Retour d'état Temps Réel** : Grâce à une connexion WebSocket, l'état de vos lecteurs est instantané dans Jeedom.
*   **Découverte Automatique** : Pas de configuration fastidieuse des lecteurs. Lancez un média sur un appareil, le plugin le détecte et le crée automatiquement.
*   **Métadonnées Riches** : Récupération automatique des titres, artistes, albums, saisons, épisodes et jaquettes.
*   **Widget Dédié** : Un widget responsive intégrant une barre de progression interactive et l'affichage adaptatif des jaquettes.
*   **Scénarios** : Déclenchez vos "Modes Cinéma" (lumières, volets) dès que la lecture commence.

## 🛠️ Prérequis

*   **Jeedom** : Version 4.4 ou supérieure.
*   **Serveur Jellyfin** : Accessible depuis votre réseau local.
*   **Clé API** : Une clé API générée depuis votre serveur Jellyfin.

## 🚀 Installation & Configuration

### 1. Installation
Le plugin est disponible sur le **Market Jeedom**.
*   Installez le plugin "Jellyfin".
*   Activez-le.
*   Les dépendances s'installeront automatiquement.

### 2. Configuration du Serveur
Rendez-vous dans **Plugins > Multimédia > Jellyfin**, puis dans la configuration du plugin :
*   **IP / Host** : Renseignez l'adresse de votre serveur (ex: `192.168.1.10` ou `mon-jellyfin.lan`). Ne pas mettre `http://` ici.
*   **Port** : Indiquez le port (par défaut `8096`).
*   **Clé API** : Collez la clé générée (Dashboard Jellyfin > Tableau de bord > Clés API).
*   Sauvegardez.

### 3. Ajout des Lecteurs
Le plugin gère la **découverte automatique**.
1.  Assurez-vous que le démon du plugin est au statut **OK**.
2.  Lancez une lecture sur un de vos appareils (TV, Navigateur, Smartphone).
3.  Allez dans **Plugins > Multimédia > Jellyfin**.
4.  Votre équipement apparaîtra automatiquement (ou après un clic sur le bouton "**Forcer scan**").

## 🐛 Support & Contribution

Ce projet est open-source. Les contributions sont les bienvenues.

*   **Un bug ?** Merci d'ouvrir une [Issue](https://github.com/NeoRed-domo/plugin-jellyfin/issues) en décrivant précisément le problème et en fournissant les logs en mode `Debug`.
*   **Une idée ?** N'hésitez pas à proposer des améliorations via des Pull Requests.

---

*Développé par [NeoRed-domo](https://github.com/NeoRed-domo).*
*Ce plugin n'est pas affilié officiellement au projet Jellyfin.*
