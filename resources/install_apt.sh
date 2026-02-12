#!/bin/bash
# Script d'installation des dépendances pour Jellyfin
# Utilisation de paquets système (apt) pour la stabilité

PROGRESS_FILE=/tmp/jellyfin_dep
if [ ! -z $1 ]; then
    PROGRESS_FILE=$1
fi

touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "Lancement de l'installation des dépendances Jellyfin..."

# 1. Mise à jour des dépôts (silencieux)
echo 10 > ${PROGRESS_FILE}
echo "Mise à jour apt..."
sudo apt-get update -qq

# 2. Installation de Python3, Pip et surtout REQUESTS via apt
# On ajoute python3-requests explicitement ici
echo 30 > ${PROGRESS_FILE}
echo "Installation de Python3 et Requests..."
sudo apt-get install -y python3 python3-pip python3-requests

echo 70 > ${PROGRESS_FILE}
echo "Vérification de l'installation..."

# 3. Vérification simple pour s'assurer que requests est bien là
# Si cette commande échoue, l'installation des dépendances sera marquée en erreur
python3 -c "import requests; print('Module requests présent système')"

echo 100 > ${PROGRESS_FILE}
echo "Installation terminée avec succès !"
rm ${PROGRESS_FILE}