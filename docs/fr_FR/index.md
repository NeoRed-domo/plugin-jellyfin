# Plugin Jellyfin pour Jeedom

Ce plugin permet de connecter votre serveur **Jellyfin** à Jeedom pour récupérer l'état de lecture, contrôler les médias et afficher un widget interactif (style Spotify).

## 1. Fonctionnalités

*   **Récupération d'état** : Savoir si une lecture est en cours (Play, Pause, Stop).
*   **Informations Média** : Titre, Artiste, Série, Saison, Épisode, Durée, Position.
*   **Contrôle** : Lecture, Pause, Stop, Précédent, Suivant.
*   **Widget Dashboard** : Une interface visuelle riche avec la jaquette, la barre de progression interactive et les temps (écoulé/restant/total).
*   **Commandes** : Toutes les données sont disponibles sous forme de commandes Jeedom pour vos scénarios.

## 2. Configuration du Plugin

Après installation du plugin, vous devez l'activer. Il n'y a pas de configuration générale (daemon) pour le moment, tout se passe au niveau de chaque équipement.

### Gestion des dépendances
Le plugin utilise des outils standards. Cliquez sur **Relancer** dans la partie Dépendances si le statut est NOK (bien que le plugin soit autonome en PHP pour sa version actuelle).

## 3. Ajout d'un équipement (Serveur/Client)

1.  Rendez-vous dans le menu **Plugins > Multimédia > Jellyfin**.
2.  Cliquez sur **Ajouter**.
3.  Donnez un nom à votre équipement (ex: "Jellyfin Salon").

### Paramètres de connexion

Dans l'onglet **Equipement**, vous devez renseigner :

*   **Adresse IP / Host** : L'adresse de votre serveur Jellyfin (ex: `192.168.1.50`).
*   **Port** : Le port de votre serveur (par défaut `8096`).
*   **API Key** : Votre clé API Jellyfin.
*   **Device ID (Session)** : L'identifiant de la session (Client) que vous souhaitez surveiller.

> **Astuce pour trouver le Device ID :**
> Lancez une lecture sur votre appareil Jellyfin cible (TV, Navigateur...), puis regardez les logs du plugin ou utilisez l'outil de découverte si disponible (prévu en v1.1).

## 4. Le Widget

Le widget est conçu pour s'intégrer parfaitement au Dashboard.
*   **Barre de progression** : Vous pouvez cliquer n'importe où sur la barre pour avancer/reculer dans le média.
*   **Temps** :
    *   À gauche : Temps écoulé.
    *   En haut à droite : Temps restant.
    *   En bas à droite : Durée totale.
*   **Jaquette** : S'adapte automatiquement (carrée pour la musique, format paysage pour les films).

## 5. FAQ

**La jaquette ne s'affiche pas ?**
Vérifiez que votre serveur Jellyfin est bien accessible depuis Jeedom et que l'API Key a les droits suffisants.

**Le temps restant ne bouge pas ?**
Le widget calcule le temps localement pour fluidifier l'affichage, mais il se synchronise avec Jeedom à chaque rafraîchissement (polling).

***

**Changelog**
*   **v1.0** : Version initiale. Support complet lecture/pause/stop et widget interactif.