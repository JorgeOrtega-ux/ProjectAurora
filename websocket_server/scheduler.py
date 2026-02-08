import time
import os
import requests
import mysql.connector
from dotenv import load_dotenv
import logging
from datetime import datetime, timedelta
import hmac
import hashlib

# Configuración
logging.basicConfig(level=logging.INFO, format='%(asctime)s - WORKER - %(message)s')
load_dotenv()

# [SEGURIDAD] Validación estricta de TODAS las variables
# Si falta alguna, el programa se detiene intencionalmente.

DB_HOST = os.getenv('DB_HOST')
if not DB_HOST:
    raise ValueError("Error crítico: Falta la variable DB_HOST.")

DB_USER = os.getenv('DB_USER')
if not DB_USER:
    raise ValueError("Error crítico: Falta la variable DB_USER.")

DB_PASS = os.getenv('DB_PASS')
if DB_PASS is None:
    raise ValueError("Error crítico: Falta la variable DB_PASS.")

DB_NAME = os.getenv('DB_NAME')
if not DB_NAME:
    raise ValueError("Error crítico: Falta la variable DB_NAME.")

SYSTEM_KEY = os.getenv('SYSTEM_API_KEY')
if not SYSTEM_KEY:
    raise ValueError("Error crítico: Falta la variable SYSTEM_API_KEY.")

# URL de la API (Ajustar según tu entorno real)
API_URL = "http://192.168.8.2/ProjectAurora/api/"

def get_db_connection():
    return mysql.connector.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)

def check_and_run():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        # 1. Leer Configuración
        cursor.execute("SELECT config_key, config_value FROM server_config WHERE config_key LIKE 'auto_backup_%'")
        rows = cursor.fetchall()
        config = {row['config_key']: row['config_value'] for row in rows}

        enabled = config.get('auto_backup_enabled', '0') == '1'
        frequency_hours = int(config.get('auto_backup_frequency', 24))

        if not enabled:
            # logging.info("Backups automáticos desactivados.")
            conn.close()
            return

        # 2. Verificar último backup
        cursor.execute("SELECT created_at FROM security_logs WHERE action_type = 'backup_create' ORDER BY id DESC LIMIT 1")
        last_log = cursor.fetchone()
        
        should_run = False
        
        if not last_log:
            should_run = True # Nunca se ha hecho uno
        else:
            last_time = last_log['created_at'] # Datetime object
            diff = datetime.now() - last_time
            if diff > timedelta(hours=frequency_hours):
                should_run = True

        conn.close()

        # 3. Ejecutar Backup vía API si corresponde
        if should_run:
            logging.info(f"Iniciando backup automático (Frecuencia: {frequency_hours}h)...")
            
            # --- NUEVA LÓGICA DE SEGURIDAD (HMAC) ---
            timestamp = str(int(time.time()))
            
            # Generar firma: HMAC-SHA256(Timestamp, SystemKey)
            signature = hmac.new(
                SYSTEM_KEY.encode('utf-8'), 
                timestamp.encode('utf-8'), 
                hashlib.sha256
            ).hexdigest()

            headers = {
                'X-System-Timestamp': timestamp,
                'X-System-Signature': signature
            }
            
            payload = {'route': 'system.create_backup'}
            
            try:
                res = requests.post(API_URL, data=payload, headers=headers)
                if res.status_code == 200:
                    data = res.json()
                    if data.get('success'):
                        logging.info(f"Backup ÉXITO: {data.get('filename')}")
                    else:
                        logging.error(f"Backup FALLO API: {data.get('message')}")
                else:
                    logging.error(f"Backup FALLO HTTP: {res.status_code}")
            except Exception as e:
                logging.error(f"Error conectando con API: {e}")

    except Exception as e:
        logging.error(f"Error general en scheduler: {e}")

if __name__ == "__main__":
    logging.info("Iniciando Scheduler de Backups...")
    while True:
        check_and_run()
        # Verificar cada minuto
        time.sleep(60)