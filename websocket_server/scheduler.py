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
import json

# --- CONFIGURACIÓN ---
logging.basicConfig(
    level=logging.INFO, 
    format='%(asctime)s - SCHEDULER - %(levelname)s - %(message)s'
)
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
# Ajusta esta URL si tu API está en otro puerto o dominio
API_URL = "http://localhost/ProjectAurora/api/" 

# Directorios
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMP_DIR = os.path.join(BASE_DIR, 'storage', 'temp')

# --- CONEXIÓN REDIS GLOBAL ---
try:
    use_ssl = os.getenv('REDIS_SCHEME', 'tcp').lower() == 'tls'
    r_client = redis.Redis(
        host=REDIS_HOST, port=REDIS_PORT, password=REDIS_PASS,
        decode_responses=True, ssl=use_ssl, ssl_cert_reqs=None, socket_timeout=5
    )
    r_client.ping()
    logging.info("✅ Redis conectado correctamente.")
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
            # Si no existe token en Redis, es basura
            if not r_client.exists(f"upload_token:{uuid}"):
                try:
                    os.remove(f)
                    deleted += 1
                except: pass
        
        if deleted > 0:
            logging.info(f"🧹 Limpieza: {deleted} archivos temporales eliminados.")
            
    except Exception as e:
        logging.error(f"Error limpieza uploads: {e}")

def sync_views_buffer():
    """
    [OPTIMIZADO] Vacía el buffer de CONTADORES de visitas (INCR) a MySQL.
    Actualiza el número total de vistas en la tabla 'videos'.
    """
    try:
        # Buscamos keys tipo video:buffer:views:{uuid}
        pattern = "video:buffer:views:*"
        keys = list(r_client.scan_iter(match=pattern))
        
        if not keys: return

        # logging.info(f"⚡ Procesando buffer de contadores ({len(keys)} videos)...")
        conn = get_db_connection()
        cursor = conn.cursor()
        
        count = 0
        for key in keys:
            try:
                # Extraer UUID
                parts = key.split(':')
                video_uuid = parts[-1]
                
                # Operación Atómica: Leer y Borrar (Reset a 0)
                views_accumulated = r_client.getset(key, 0)
                
                if views_accumulated and int(views_accumulated) > 0:
                    val = int(views_accumulated)
                    sql = "UPDATE videos SET views_count = views_count + %s WHERE uuid = %s"
                    cursor.execute(sql, (val, video_uuid))
                    count += 1
            except Exception as e:
                logging.error(f"Error buffer key {key}: {e}")

        conn.commit()
        conn.close()
        if count > 0:
            logging.info(f"✅ Contadores actualizados en {count} videos.")

    except Exception as e:
        logging.error(f"❌ Error en sync_views_buffer: {e}")

def process_video_logs_buffer():
    """
    [NUEVO] Procesa el HISTORIAL de visitas (Logs) en lote.
    Lee de la lista 'video:logs:buffer', resuelve IDs y hace Bulk Insert en 'video_views'.
    """
    LIST_KEY = 'video:logs:buffer'
    BATCH_SIZE = 200 # Procesamos de 200 en 200
    
    try:
        # 1. Verificar si hay logs pendientes (Check rápido)
        if r_client.llen(LIST_KEY) == 0:
            return

        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Procesamos hasta 5 lotes (1000 logs) por ciclo para no bloquear el scheduler
        for _ in range(5):
            # Usamos pipeline para hacer un "LPOP multiple" seguro y compatible
            p = r_client.pipeline()
            for _ in range(BATCH_SIZE):
                p.lpop(LIST_KEY)
            results = p.execute()
            
            # Filtramos los None (cuando la lista se vacía)
            raw_logs = [item for item in results if item is not None]
            
            if not raw_logs:
                break
                
            # 2. Parsear JSONs y recolectar UUIDs
            entries = []
            uuids_to_resolve = set()
            
            for log_str in raw_logs:
                try:
                    data = json.loads(log_str)
                    # Validar integridad básica
                    if 'video_uuid' in data:
                        entries.append(data)
                        uuids_to_resolve.add(data['video_uuid'])
                except:
                    continue # Ignorar logs corruptos
            
            if not entries:
                continue

            # 3. Resolver UUIDs a IDs numéricos (MySQL FK)
            if not uuids_to_resolve: continue
            
            format_strings = ','.join(['%s'] * len(uuids_to_resolve))
            sql_ids = f"SELECT uuid, id FROM videos WHERE uuid IN ({format_strings})"
            cursor.execute(sql_ids, tuple(uuids_to_resolve))
            
            uuid_map = {row[0]: row[1] for row in cursor.fetchall()}
            
            # 4. Construir filas para Bulk Insert
            insert_values = []
            for entry in entries:
                vid_uuid = entry.get('video_uuid')
                if vid_uuid in uuid_map:
                    video_id = uuid_map[vid_uuid]
                    user_id = entry.get('user_id') # Puede ser None
                    ip = entry.get('ip', '0.0.0.0')
                    ua = entry.get('user_agent', 'Unknown')[:255] # Truncar por seguridad
                    ts = float(entry.get('timestamp', time.time()))
                    viewed_at = datetime.fromtimestamp(ts)

                    insert_values.append((video_id, user_id, ip, ua, viewed_at))
            
            if insert_values:
                sql_insert = """
                    INSERT INTO video_views (video_id, user_id, ip_address, user_agent, viewed_at)
                    VALUES (%s, %s, %s, %s, %s)
                """
                cursor.executemany(sql_insert, insert_values)
                conn.commit()
                logging.info(f"📜 Historial: Se guardaron {len(insert_values)} visitas en BD.")

        cursor.close()
        conn.close()

    except Exception as e:
        logging.error(f"❌ Error procesando logs de historial: {e}")

# [DESACTIVADO POR SEGURIDAD]
# Esta función ha sido desactivada para evitar que Redis sobrescriba la DB.
# PHP ya escribe Likes y Subs directamente en MySQL (Source of Truth).
# Si Redis se reinicia, esta función borraba los datos reales. ¡NO DESCOMENTAR!
"""
def sync_counters_to_db():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        # ... (Lógica original comentada)
        conn.commit()
        conn.close()
    except Exception as e:
        logging.error(f"Error sync general: {e}")
"""

def trigger_system_action(route):
    """Helper para llamar a la API interna (para lanzar backups, etc)"""
    if not SYSTEM_KEY: return
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
    
    counter_sec = 0
    
    while True:
        try:
            # TAREAS RÁPIDAS (Cada 10 segundos)
            if counter_sec % 10 == 0:
                sync_views_buffer() # Actualiza los contadores (números)

            # TAREAS MEDIAS (Cada 30 segundos)
            if counter_sec % 30 == 0:
                process_video_logs_buffer() # [NUEVO] Actualiza el historial (logs)

            # TAREAS LENTAS (Cada 60 segundos)
            if counter_sec % 60 == 0:
                check_and_run_backup()
                # sync_counters_to_db() # <--- DESACTIVADO

            # TAREAS DE LIMPIEZA (Cada 1 hora = 3600 seg)
            if counter_sec >= 3600:
                cleanup_stale_uploads()
                counter_sec = 0
            
            counter_sec += 5
            time.sleep(5) # Ciclo de 5 segundos
            
        except KeyboardInterrupt:
            logging.info("🛑 Scheduler detenido.")
            break
        except Exception as e:
            logging.error(f"💥 Error en loop principal: {e}")
            time.sleep(10)