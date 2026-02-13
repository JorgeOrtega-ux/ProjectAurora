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
import redis

# --- CONFIGURACIÓN ---
logging.basicConfig(level=logging.INFO, format='%(asctime)s - SCHEDULER - %(message)s')
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# [SEGURIDAD] Validación estricta
DB_HOST = os.getenv('DB_HOST')
DB_USER = os.getenv('DB_USER')
DB_PASS = os.getenv('DB_PASS')
DB_NAME = os.getenv('DB_NAME')
SYSTEM_KEY = os.getenv('SYSTEM_API_KEY')
REDIS_HOST = os.getenv('REDIS_HOST')
REDIS_PORT = os.getenv('REDIS_PORT')
REDIS_PASS = os.getenv('REDIS_PASSWORD')

if not all([DB_HOST, DB_USER, DB_PASS is not None, DB_NAME, REDIS_HOST, REDIS_PORT]):
    logging.error("❌ Error fatal: Faltan variables de entorno críticas.")
    exit(1)

REDIS_PORT = int(REDIS_PORT)
API_URL = "http://localhost/ProjectAurora/api/" # Ajusta si usas IP o dominio

# Directorios
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMP_DIR = os.path.join(BASE_DIR, 'storage', 'temp')

# --- REDIS ---
try:
    use_ssl = os.getenv('REDIS_SCHEME', 'tcp').lower() == 'tls'
    r_client = redis.Redis(
        host=REDIS_HOST, port=REDIS_PORT, password=REDIS_PASS,
        decode_responses=True, ssl=use_ssl, ssl_cert_reqs=None, socket_timeout=5
    )
    r_client.ping()
    logging.info("✅ Redis conectado.")
except Exception as e:
    logging.error(f"❌ Error Redis: {e}")
    exit(1)

def get_db_connection():
    return mysql.connector.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)

# --- TAREAS ---

def check_and_run_backup():
    """Ejecuta backups automáticos según configuración en DB"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT config_key, config_value FROM server_config WHERE config_key LIKE 'auto_backup_%'")
        rows = cursor.fetchall()
        config = {row['config_key']: row['config_value'] for row in rows}
        
        enabled = config.get('auto_backup_enabled', '0') == '1'
        freq = int(config.get('auto_backup_frequency', 24))
        
        if not enabled:
            conn.close()
            return

        cursor.execute("SELECT created_at FROM security_logs WHERE action_type = 'backup_create' ORDER BY id DESC LIMIT 1")
        last = cursor.fetchone()
        
        should_run = False
        if not last:
            should_run = True
        elif (datetime.now() - last['created_at']) > timedelta(hours=freq):
            should_run = True
            
        conn.close()
        
        if should_run:
            logging.info(f"🔄 Ejecutando backup automático ({freq}h)...")
            trigger_system_action('system.create_backup')

    except Exception as e:
        logging.error(f"Error en backup scheduler: {e}")

def cleanup_stale_uploads():
    """Elimina archivos .part viejos en storage/temp"""
    try:
        if not os.path.exists(TEMP_DIR): return
        
        files = glob.glob(os.path.join(TEMP_DIR, '*.part'))
        deleted = 0
        
        for f in files:
            uuid = os.path.basename(f).replace('.part', '')
            if not r_client.exists(f"upload_token:{uuid}"):
                try:
                    os.remove(f)
                    deleted += 1
                except: pass
        
        if deleted > 0:
            logging.info(f"🧹 Limpieza: {deleted} archivos temporales eliminados.")
            
    except Exception as e:
        logging.error(f"Error limpieza uploads: {e}")

def sync_counters_to_db():
    """
    [NUEVO] Sincroniza los contadores de Redis (Hot) a MySQL (Cold).
    Esto asegura que los Likes/Views no se pierdan si Redis se reinicia.
    """
    logging.info("💾 Sincronizando contadores Redis -> MySQL...")
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # 1. Sincronizar Videos (Likes, Dislikes, Views)
        # Patrón: video:stats:{uuid}
        video_keys = r_client.keys("video:stats:*")
        for key in video_keys:
            try:
                uuid = key.split(':')[-1]
                stats = r_client.hgetall(key)
                
                views = int(stats.get('views', 0))
                likes = int(stats.get('likes', 0))
                dislikes = int(stats.get('dislikes', 0))
                
                # Actualizamos solo si hay datos. Usamos UPDATE directo.
                # Optimización: Podríamos verificar si cambió, pero el UPDATE es barato aquí.
                sql = """
                    UPDATE videos 
                    SET views_count = %s, likes_count = %s, dislikes_count = %s 
                    WHERE uuid = %s
                """
                cursor.execute(sql, (views, likes, dislikes, uuid))
            except Exception as e:
                logging.error(f"Error sync video {key}: {e}")

        # 2. Sincronizar Usuarios (Suscriptores)
        # Patrón: user:stats:{uuid}
        user_keys = r_client.keys("user:stats:*")
        for key in user_keys:
            try:
                uuid = key.split(':')[-1]
                subs = int(r_client.hget(key, 'subscribers') or 0)
                
                sql = "UPDATE users SET subscribers_count = %s WHERE uuid = %s"
                cursor.execute(sql, (subs, uuid))
            except Exception as e:
                logging.error(f"Error sync user {key}: {e}")

        conn.commit()
        conn.close()
        # logging.info("✅ Sincronización DB completada.") # Comentar para no saturar logs
        
    except Exception as e:
        logging.error(f"❌ Error crítico sincronizando DB: {e}")

def trigger_system_action(route):
    """Helper para llamar a la API interna"""
    timestamp = str(int(time.time()))
    signature = hmac.new(
        SYSTEM_KEY.encode('utf-8'), timestamp.encode('utf-8'), hashlib.sha256
    ).hexdigest()
    
    headers = {'X-System-Timestamp': timestamp, 'X-System-Signature': signature}
    try:
        requests.post(API_URL, data={'route': route}, headers=headers, timeout=5)
    except Exception as e:
        logging.error(f"Fallo trigger API {route}: {e}")

# --- LOOP PRINCIPAL ---
if __name__ == "__main__":
    logging.info("🚀 Scheduler iniciado. Cron activo.")
    
    counter_min = 0
    
    while True:
        try:
            # Tareas por Minuto (Cada 1 min)
            check_and_run_backup()
            
            # Tareas cada 5 minutos (Sincronización DB)
            if counter_min % 5 == 0:
                sync_counters_to_db()

            # Tareas cada 60 minutos (Limpieza profunda)
            if counter_min >= 60:
                cleanup_stale_uploads()
                counter_min = 0
            
            counter_min += 1
            time.sleep(60)
            
        except KeyboardInterrupt:
            logging.info("🛑 Scheduler detenido.")
            break
        except Exception as e:
            logging.error(f"💥 Error en loop principal: {e}")
            time.sleep(10)