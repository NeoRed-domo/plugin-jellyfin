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
        response = requests.post(callback_url, params=params, json=data, timeout=1) # Timeout réduit pour la réactivité
        if response.status_code != 200:
            logging.error(f"Erreur Jeedom: {response.status_code} - {response.text}")
    except Exception as e:
        logging.error(f"Impossible de contacter Jeedom: {e}")

def get_jellyfin_sessions(jellyfin_url, jellyfin_token):
    """Récupère les sessions actives depuis l'API Jellyfin"""
    headers = {'X-Emby-Token': jellyfin_token, 'Content-Type': 'application/json'}
    url = f"{jellyfin_url.rstrip('/')}/Sessions"
    
    try:
        r = requests.get(url, headers=headers, timeout=2) # Timeout court pour ne pas bloquer la boucle
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
    
    # MODIFICATION : Cycle très court pour une haute réactivité de l'interface
    TARGET_CYCLE = 0.5 

    # Mémoire des appareils actifs lors du cycle précédent {device_id: device_name}
    known_devices = {} 

    # 4. Boucle Principale
    while not shutdown_flag:
        start_time = time.time()

        try:
            # Récupération
            sessions = get_jellyfin_sessions(args.jellyfin_url, args.jellyfin_token)
            payload_data = []
            current_cycle_devices = {}

            # Traitement des sessions actives
            for session in sessions:
                if 'DeviceId' not in session: continue
                
                dev_id = session['DeviceId']
                dev_name = session.get('DeviceName', 'Jellyfin Client')
                
                # On mémorise que cet appareil est actif ce tour-ci
                current_cycle_devices[dev_id] = dev_name
                
                # Extraction des données utiles
                now_playing = session.get('NowPlayingItem', {})
                play_state = session.get('PlayState', {})
                
                session_data = {
                    'device_id': dev_id,
                    'client': dev_name,
                    'NowPlayingItem': now_playing,
                    'PlayState': play_state,
                    'title': now_playing.get('Name', ''),
                    'status': 'Paused' if play_state.get('IsPaused', False) else 'Playing',
                    'item_id': now_playing.get('Id', ''),
                    'image_tag': now_playing.get('PrimaryImageTag', ''),
                    'run_time_ticks': now_playing.get('RunTimeTicks', 0),
                    'position_ticks': play_state.get('PositionTicks', 0),
                    'supports_remote_control': session.get('SupportsRemoteControl', False)
                }
                payload_data.append(session_data)

            # --- Détection des appareils disparus (STOP) ---
            for old_dev_id, old_dev_name in known_devices.items():
                if old_dev_id not in current_cycle_devices:
                    logging.info(f"Appareil arrêté détecté : {old_dev_name} ({old_dev_id})")
                    # On force le statut STOP
                    stop_payload = {
                        'device_id': old_dev_id,
                        'client': old_dev_name,
                        'status': 'Stopped', 
                        'title': '',
                        'item_id': '',
                        'NowPlayingItem': {},
                        'PlayState': {},
                        'run_time_ticks': 0,
                        'position_ticks': 0
                    }
                    payload_data.append(stop_payload)

            # Mise à jour de la mémoire
            known_devices = current_cycle_devices

            # Envoi vers Jeedom
            if payload_data:
                send_to_jeedom(args.callback, args.apikey, payload_data)

            # Gestion du temps (Compensation précise)
            elapsed = time.time() - start_time
            sleep_time = TARGET_CYCLE - elapsed

            if sleep_time > 0:
                time.sleep(sleep_time)
            
            # Flush pour voir les logs immédiatement si besoin
            sys.stdout.flush()

        except Exception as e:
            logging.error(f"Erreur critique dans la boucle: {e}")
            sys.stdout.flush()
            time.sleep(5) 

    logging.info("Arrêt propre du démon terminé.")

if __name__ == "__main__":
    main()