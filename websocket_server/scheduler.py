import time
import os
import requests
import mysql.connector
from dotenv import load_dotenv
import logging
from datetime import datetime, timedelta
import hmac
import hashlib
import glob
import redis  # [NUEVO]

# Configuración
logging.basicConfig(level=logging.INFO, format='%(asctime)s - SCHEDULER - %(message)s')
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# [SEGURIDAD] Validación estricta de TODAS las variables
DB_HOST = os.getenv('DB_HOST')
if not DB_HOST: raise ValueError("Error crítico: Falta la variable DB_HOST.")

DB_USER = os.getenv('DB_USER')
if not DB_USER: raise ValueError("Error crítico: Falta la variable DB_USER.")

DB_PASS = os.getenv('DB_PASS')
if DB_PASS is None: raise ValueError("Error crítico: Falta la variable DB_PASS.")

DB_NAME = os.getenv('DB_NAME')
if not DB_NAME: raise ValueError("Error crítico: Falta la variable DB_NAME.")

SYSTEM_KEY = os.getenv('SYSTEM_API_KEY')
if not SYSTEM_KEY: raise ValueError("Error crítico: Falta la variable SYSTEM_API_KEY.")

REDIS_HOST = os.getenv('REDIS_HOST')
REDIS_PORT = os.getenv('REDIS_PORT')
REDIS_PASS = os.getenv('REDIS_PASSWORD')

if not REDIS_HOST or not REDIS_PORT:
    logging.error("❌ Error fatal: Faltan variables Redis en .env")
    exit(1)

REDIS_PORT = int(REDIS_PORT)

# URL de la API (Ajustar según tu entorno real)
API_URL = "http://192.168.8.2/ProjectAurora/api/"

# Directorios
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMP_DIR = os.path.join(BASE_DIR, 'storage', 'temp')

# --- INICIALIZACIÓN GLOBAL DE REDIS ---
try:
    use_ssl = os.getenv('REDIS_SCHEME', 'tcp').lower() == 'tls'
    
    r_client = redis.Redis(
        host=REDIS_HOST, 
        port=REDIS_PORT, 
        password=REDIS_PASS, 
        decode_responses=True,
        ssl=use_ssl,
        ssl_cert_reqs=None,
        socket_timeout=5
    )
    r_client.ping()
    logging.info("✅ Conexión a Redis establecida para mantenimiento.")

except Exception as e:
    logging.error(f"❌ Error fatal conectando a Redis: {e}")
    exit(1)

def get_db_connection():
    return mysql.connector.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)

def check_and_run_backup():
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
            
            timestamp = str(int(time.time()))
            
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
        logging.error(f"Error general en scheduler backup: {e}")

# [NUEVO] Tarea de limpieza de subidas huérfanas
def cleanup_stale_uploads():
    logging.info("🧹 Ejecutando limpieza de archivos temporales huérfanos...")
    try:
        if not os.path.exists(TEMP_DIR):
            return

        # Buscar todos los archivos .part
        part_files = glob.glob(os.path.join(TEMP_DIR, '*.part'))
        deleted_count = 0

        for file_path in part_files:
            filename = os.path.basename(file_path)
            # El formato esperado es {uuid}.part
            uuid = filename.replace('.part', '')
            
            # Consultar a Redis si el token de subida para este UUID sigue vivo
            # Si NO existe, significa que expiró (2 horas) o se completó
            redis_key = f"upload_token:{uuid}"
            
            if not r_client.exists(redis_key):
                try:
                    os.remove(file_path)
                    deleted_count += 1
                    logging.info(f"🗑️ Eliminado archivo huérfano: {filename}")
                except OSError as e:
                    logging.error(f"❌ Error al borrar {filename}: {e}")

        if deleted_count > 0:
            logging.info(f"✅ Limpieza completada: {deleted_count} archivos eliminados.")
        
    except Exception as e:
        logging.error(f"❌ Error en cleanup_stale_uploads: {e}")

if __name__ == "__main__":
    logging.info("Iniciando Scheduler de Mantenimiento...")
    
    # Contadores para intervalos diferentes
    minutes_counter = 0
    
    while True:
        # Tareas de cada minuto
        check_and_run_backup()
        
        # Tareas de cada hora (60 minutos)
        if minutes_counter >= 60:
            cleanup_stale_uploads()
            minutes_counter = 0
        
        minutes_counter += 1
        time.sleep(60)