import os
import redis
import json
import logging
import subprocess
import mysql.connector
from datetime import datetime
from dotenv import load_dotenv
import time
import glob
import zipfile
import secrets
import tempfile 
from concurrent.futures import ThreadPoolExecutor

# --- CONFIGURACIÓN ---
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - WORKER - %(levelname)s - %(message)s'
)

# Cargar variables de entorno
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# [SEGURIDAD] Validación estricta de Base de Datos
DB_HOST = os.getenv('DB_HOST')
DB_USER = os.getenv('DB_USER')
DB_PASS = os.getenv('DB_PASS')
DB_NAME = os.getenv('DB_NAME')
REDIS_HOST = os.getenv('REDIS_HOST')
REDIS_PORT = os.getenv('REDIS_PORT')
REDIS_PASS = os.getenv('REDIS_PASSWORD')

# Validación de arranque
if not all([DB_HOST, DB_USER, DB_PASS is not None, DB_NAME, REDIS_HOST, REDIS_PORT]):
    logging.error("❌ Error fatal: Faltan variables de entorno críticas en .env")
    exit(1)

REDIS_PORT = int(REDIS_PORT)
QUEUE_NAME = 'aurora_task_queue'
PUBSUB_CHANNEL = 'aurora_ws_control'

# Directorios (Ruta absoluta)
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BACKUP_DIR = os.path.join(BASE_DIR, 'storage', 'backups')
LOGS_DIR = os.path.join(BASE_DIR, 'logs')
TEMP_DIR = os.path.join(BASE_DIR, 'storage', 'temp')

# [FIX BLOQUEO] Executor para tareas pesadas (Backups/IO)
EXECUTOR = ThreadPoolExecutor(max_workers=2)

# --- INICIALIZACIÓN GLOBAL DE REDIS ---
try:
    # [SEGURIDAD DINÁMICA]
    # Si REDIS_SCHEME es 'tls' usamos SSL, si es 'tcp' no.
    # Esto permite que funcione en XAMPP (local) y en Producción sin cambios de código.
    use_ssl = os.getenv('REDIS_SCHEME', 'tcp').lower() == 'tls'
    
    r_client = redis.Redis(
        host=REDIS_HOST, 
        port=REDIS_PORT, 
        password=REDIS_PASS, 
        decode_responses=True,
        ssl=use_ssl,           # True solo si es producción/TLS
        ssl_cert_reqs=None,    # Ignorar errores de certificado (si usas SSL)
        socket_timeout=5       # Timeout de seguridad para no colgar el script
    )
    r_client.ping() # Verificar conexión
    
    mode_msg = "🔒 CON SSL" if use_ssl else "🔓 SIN SSL (Modo Local)"
    logging.info(f"✅ Conexión a Redis establecida correctamente [{mode_msg}].")

except Exception as e:
    logging.error(f"❌ Error fatal conectando a Redis: {e}")
    exit(1)

# --- FUNCIONES AUXILIARES ---

def get_db_connection():
    return mysql.connector.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME
    )

def log_security_event(action, details, ip='127.0.0.1'):
    """Registra el evento en MySQL"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        query = "INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (%s, %s, %s)"
        cursor.execute(query, (details, action, ip))
        conn.commit()
        conn.close()
    except Exception as e:
        logging.error(f"Error escribiendo log SQL: {e}")

def get_server_config(key, default):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT config_value FROM server_config WHERE config_key = %s", (key,))
        result = cursor.fetchone()
        conn.close()
        return result[0] if result else default
    except Exception:
        return default

def notify_frontend(msg_type, message):
    """Envía un mensaje al servidor WebSocket vía Redis PubSub"""
    try:
        payload = json.dumps({
            'cmd': 'BROADCAST',
            'msg_type': msg_type,
            'message': message
        })
        r_client.publish(PUBSUB_CHANNEL, payload)
    except Exception as e:
        logging.error(f"❌ Error publicando en Redis: {e}")

def enforce_retention_policy():
    try:
        limit = int(get_server_config('auto_backup_retention', 10))
        if limit <= 0: return

        files = glob.glob(os.path.join(BACKUP_DIR, '*.sql'))
        if len(files) <= limit: return

        # Ordenar por fecha de modificación (descendente) y eliminar excedentes
        files.sort(key=os.path.getmtime, reverse=True)
        to_delete = files[limit:]
        
        for f in to_delete:
            try: os.remove(f)
            except: pass
        
        logging.info(f"Limpieza: Se eliminaron {len(to_delete)} backups antiguos.")
    except Exception as e:
        logging.error(f"Error en política de retención: {e}")

def secure_resolve_mysqldump():
    """
    Resuelve la ruta de mysqldump buscando en config, rutas comunes de Windows y PATH.
    """
    config_path = get_server_config('sys_mysqldump_path', '')
    valid_names = ['mysqldump', 'mysqldump.exe']
    
    if config_path and os.path.isfile(config_path):
        if os.path.basename(config_path).lower() in valid_names:
            return config_path
        else:
            logging.warning(f"⚠️ Ruta config inválida: {config_path}")

    common_paths = [
        r"C:\xampp\mysql\bin\mysqldump.exe",
        r"E:\xampp\mysql\bin\mysqldump.exe",
        r"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe",
        r"C:\wamp64\bin\mysql\mysql5.7.31\bin\mysqldump.exe"
    ]

    for path in common_paths:
        if os.path.isfile(path):
            logging.info(f"✅ Mysqldump encontrado en: {path}")
            return path

    return "mysqldump"

# --- TAREAS (EJECUTADAS EN HILOS) ---

def task_create_backup(payload):
    logging.info("⚙️ [Thread] Iniciando tarea: Crear Backup...")
    
    timestamp = datetime.now().strftime('%Y-%m-%d_%H-%M-%S')
    hash_suffix = str(int(time.time()))[-6:] 
    filename = f"backup_{timestamp}_{hash_suffix}.sql"
    filepath = os.path.join(BACKUP_DIR, filename)

    os.makedirs(BACKUP_DIR, exist_ok=True)
    mysqldump_bin = secure_resolve_mysqldump()
    
    try:
        with tempfile.NamedTemporaryFile(mode='w', delete=False) as temp_conf:
            # Escribir configuración estilo my.cnf
            temp_conf.write(f"[client]\nuser=\"{DB_USER}\"\npassword=\"{DB_PASS}\"\nhost=\"{DB_HOST}\"\n")
            conf_path = temp_conf.name
        
        # Ejecutar mysqldump usando el archivo de configuración
        dump_cmd = [
            mysqldump_bin,
            f'--defaults-extra-file={conf_path}',
            DB_NAME, 
            '--single-transaction', 
            '--quick'
        ]

        with open(filepath, 'w') as outfile:
            process = subprocess.Popen(
                dump_cmd,
                stdout=outfile,
                stderr=subprocess.PIPE,
                text=True
            )
            _, stderr = process.communicate()

        # [SEGURIDAD] Borrar archivo de credenciales INMEDIATAMENTE
        if os.path.exists(conf_path):
            os.remove(conf_path)

        if process.returncode == 0 and os.path.exists(filepath) and os.path.getsize(filepath) > 0:
            logging.info(f"✅ Backup creado exitosamente: {filename}")
            
            is_system = payload.get('is_system', False)
            user_id = payload.get('requested_by', 0)
            identifier = f"System | Created: {filename}" if is_system else f"Admin:{user_id} | Created: {filename}"
            
            log_security_event('backup_create', identifier)
            
            if is_system:
                enforce_retention_policy()

            notify_frontend('notification', {'type': 'success', 'text': f'Respaldo finalizado: {filename}'})
            notify_frontend('action', {'action': 'refresh_backups'})

        else:
            if os.path.exists(filepath): os.remove(filepath)
            logging.error(f"❌ Error en mysqldump (Code {process.returncode}): {stderr}")
            notify_frontend('notification', {'type': 'error', 'text': 'Fallo al crear el respaldo.'})

    except Exception as e:
        if 'conf_path' in locals() and os.path.exists(conf_path):
            os.remove(conf_path)
            
        logging.error(f"❌ Excepción al ejecutar backup: {e}")
        notify_frontend('notification', {'type': 'error', 'text': 'Error crítico en el Worker.'})

def task_create_zip(payload):
    logging.info("⚙️ [Thread] Iniciando tarea: Compresión ZIP...")
    
    try:
        files = payload.get('files', [])
        source_type = payload.get('type', '')
        user_id = payload.get('requested_by', 0)
        
        if not files:
            raise ValueError("Lista de archivos vacía")

        base_dir = LOGS_DIR if source_type == 'log' else BACKUP_DIR
        
        valid_files = []
        for f_rel in files:
            f_rel = f_rel.replace('..', '').strip('/')
            full_path = os.path.join(base_dir, f_rel)
            
            if os.path.isfile(full_path) and full_path.startswith(base_dir):
                valid_files.append((full_path, f_rel))
        
        if not valid_files:
            notify_frontend('notification', {'type': 'warning', 'text': 'Ningún archivo válido para comprimir.'})
            return

        os.makedirs(TEMP_DIR, exist_ok=True)
        zip_filename = f"aurora_batch_{int(time.time())}_{secrets.token_hex(4)}.zip"
        zip_filepath = os.path.join(TEMP_DIR, zip_filename)

        logging.info(f"📦 Comprimiendo {len(valid_files)} archivos en {zip_filename}...")

        with zipfile.ZipFile(zip_filepath, 'w', zipfile.ZIP_DEFLATED) as zf:
            for abs_path, arc_name in valid_files:
                zf.write(abs_path, arc_name)

        token = secrets.token_hex(32)
        redis_data = {
            'filepath': zip_filepath,
            'filename': zip_filename,
            'is_temp': True,
            'user_id': user_id,
            'created_at': time.time()
        }
        
        r_client.setex(f"download:token:{token}", 300, json.dumps(redis_data))
        
        download_url = f"download.php?token={token}"
        
        notify_frontend('download_ready', {
            'url': download_url,
            'filename': zip_filename,
            'type': source_type
        })
        
        log_security_event('zip_create', f"Admin:{user_id} | Created: {zip_filename}")
        logging.info(f"✅ ZIP creado y token generado: {token[:8]}...")

    except Exception as e:
        logging.error(f"❌ Error creando ZIP: {e}")
        notify_frontend('notification', {'type': 'error', 'text': 'Error al generar el archivo comprimido.'})

TASKS = {
    'create_backup': task_create_backup,
    'create_zip': task_create_zip
}

def run_job(raw_data):
    try:
        job = json.loads(raw_data)
        task_name = job.get('task')
        payload = job.get('payload', {})

        if task_name in TASKS:
            TASKS[task_name](payload)
        else:
            logging.warning(f"Tarea desconocida recibida: {task_name}")
    except Exception as e:
        logging.error(f"Error procesando job: {e}")

if __name__ == "__main__":
    logging.info(f"🚀 Aurora Worker iniciado. Esperando trabajos en '{QUEUE_NAME}'...")
    
    try:
        while True:
            try:
                item = r_client.blpop(QUEUE_NAME, timeout=2)
                
                if item:
                    _, raw_data = item
                    EXECUTOR.submit(run_job, raw_data)

            except redis.exceptions.ConnectionError:
                logging.error("Conexión con Redis perdida. Reintentando en 5s...")
                time.sleep(5)
            except Exception as e:
                logging.error(f"Error en loop principal: {e}")
                time.sleep(1)
                
    except KeyboardInterrupt:
        logging.info("Deteniendo Worker...")
        EXECUTOR.shutdown(wait=True)