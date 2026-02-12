#!/usr/bin/env python3
import requests
import time
import sys
import json
import logging
import argparse
import os
import signal

# ----------------------------------------------------------------------------
# Globals
# ----------------------------------------------------------------------------
shutdown_flag = False

# ----------------------------------------------------------------------------
# Gestion des signaux (Arrêt propre par Jeedom)
# ----------------------------------------------------------------------------
def signal_handler(sig, frame):
    global shutdown_flag
    logging.info("Signal d'arrêt reçu (SIGTERM). Arrêt en cours...")
    shutdown_flag = True

signal.signal(signal.SIGTERM, signal_handler)

# ----------------------------------------------------------------------------
# Fonctions Core
# ----------------------------------------------------------------------------

def send_to_jeedom(callback_url, plugin_apikey, data):
    """Envoie les données au fichier PHP d'écoute via POST"""
    params = {'apikey': plugin_apikey}
    try:
        response = requests.post(callback_url, params=params, json=data, timeout=2)
        if response.status_code != 200:
            logging.error(f"Erreur Jeedom: {response.status_code} - {response.text}")
    except Exception as e:
        logging.error(f"Impossible de contacter Jeedom: {e}")

def get_jellyfin_sessions(jellyfin_url, jellyfin_token):
    """Récupère les sessions actives depuis l'API Jellyfin"""
    headers = {'X-Emby-Token': jellyfin_token, 'Content-Type': 'application/json'}
    url = f"{jellyfin_url.rstrip('/')}/Sessions"
    
    try:
        r = requests.get(url, headers=headers, timeout=5)
        if r.status_code == 200:
            return r.json()
        else:
            logging.warning(f"API Jellyfin répond: {r.status_code}")
    except Exception as e:
        logging.error(f"Erreur connexion Jellyfin: {e}")
    return []

def write_pid(pid_file):
    """Ecrit le PID pour la surveillance Jeedom"""
    try:
        with open(pid_file, 'w') as f:
            f.write(str(os.getpid()))
    except Exception as e:
        logging.error(f"Impossible d'écrire le PID: {e}")
        sys.exit(1)

# ----------------------------------------------------------------------------
# Main Logic
# ----------------------------------------------------------------------------

def main():
    # 1. Parsing des arguments
    parser = argparse.ArgumentParser(description='Daemon Jellyfin pour Jeedom')
    parser.add_argument("--jellyfin_url", required=True, help="URL Jellyfin")
    parser.add_argument("--jellyfin_token", required=True, help="Token API Jellyfin")
    parser.add_argument("--callback", required=True, help="URL Callback Jeedom")
    parser.add_argument("--apikey", required=True, help="API Key Plugin")
    parser.add_argument("--pid", required=True, help="Chemin PID")
    parser.add_argument("--loglevel", default="info", help="Niveau de log (debug/info/error)")
    parser.add_argument("--socket", help="Socket interne Jeedom (inutilisé)")
    
    args = parser.parse_args()

    # 2. Configuration du Logging
    # On configure pour écrire sur la sortie standard (console)
    numeric_level = getattr(logging, args.loglevel.upper(), logging.INFO)
    logging.basicConfig(
        level=numeric_level,
        format='[%(asctime)s][PYTHON] : %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S',
        stream=sys.stdout
    )

    logging.info(f"Démarrage du Daemon Jellyfin (Log level: {args.loglevel.upper()})")
    
    # 3. Initialisation
    write_pid(args.pid)
    TARGET_CYCLE = 1.0

    # 4. Boucle Principale
    while not shutdown_flag:
        start_time = time.time()

        try:
            # Récupération
            sessions = get_jellyfin_sessions(args.jellyfin_url, args.jellyfin_token)
            payload_data = []

            # Traitement
            for session in sessions:
                if 'DeviceId' not in session: continue
                
                # Extraction des données utiles
                now_playing = session.get('NowPlayingItem', {})
                play_state = session.get('PlayState', {})
                
                # On construit le paquet pour Jeedom
                # MODIFICATION IMPORTANTE : On envoie les objets complets 'NowPlayingItem' et 'PlayState'
                # Cela permet au PHP de récupérer l'AlbumId, ParentId, etc.
                session_data = {
                    'device_id': session['DeviceId'],
                    'client': session.get('DeviceName', 'Jellyfin Client'),
                    
                    # -- Objets Complets (Nouveau) --
                    'NowPlayingItem': now_playing,
                    'PlayState': play_state,
                    
                    # -- Champs à plat (Rétro-compatibilité) --
                    'title': now_playing.get('Name', ''),
                    'status': 'Paused' if play_state.get('IsPaused', False) else 'Playing',
                    'item_id': now_playing.get('Id', ''),
                    'image_tag': now_playing.get('PrimaryImageTag', ''),
                    'run_time_ticks': now_playing.get('RunTimeTicks', 0),
                    'position_ticks': play_state.get('PositionTicks', 0),
                    'supports_remote_control': session.get('SupportsRemoteControl', False)
                }
                payload_data.append(session_data)

            # Envoi vers Jeedom
            if payload_data:
                send_to_jeedom(args.callback, args.apikey, payload_data)

            # Gestion du temps (Compensation de dérive)
            elapsed = time.time() - start_time
            sleep_time = TARGET_CYCLE - elapsed

            if sleep_time > 0:
                # logging.debug(f"Cycle: {elapsed:.3f}s | Sleep: {sleep_time:.3f}s")
                time.sleep(sleep_time)
            else:
                logging.debug(f"Cycle lent: {elapsed:.3f}s (Pas de pause)")
            
            # FORCE L'AFFICHAGE DES LOGS (Evite le buffering)
            sys.stdout.flush()

        except Exception as e:
            logging.error(f"Erreur critique dans la boucle: {e}")
            sys.stdout.flush()
            time.sleep(5) 

    logging.info("Arrêt propre du démon terminé.")

if __name__ == "__main__":
    main()