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

# --- CONFIGURACIÓN ---
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - WORKER - %(levelname)s - %(message)s'
)

# Cargar variables de entorno (asumiendo que .env está en la raíz del proyecto)
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# Configuración Base de Datos
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
DB_NAME = os.getenv('DB_NAME', 'project_aurora_db')

# Configuración Redis
REDIS_HOST = os.getenv('REDIS_HOST', '127.0.0.1')
REDIS_PORT = int(os.getenv('REDIS_PORT', 6379))
REDIS_PASS = os.getenv('REDIS_PASSWORD', None)

QUEUE_NAME = 'aurora_task_queue'
PUBSUB_CHANNEL = 'aurora_ws_control'

# Directorio de Backups (Ruta absoluta)
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BACKUP_DIR = os.path.join(BASE_DIR, 'storage', 'backups')

# --- FUNCIONES AUXILIARES ---

def get_db_connection():
    return mysql.connector.connect(
        host=DB_HOST, 
        user=DB_USER, 
        password=DB_PASS, 
        database=DB_NAME
    )

def log_security_event(action, details, ip='127.0.0.1'):
    """Registra el evento en MySQL para que aparezca en el panel de admin"""
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
        r_client.publish(PUBSUB_CHANNEL, json.dumps({
            'cmd': 'BROADCAST',
            'msg_type': msg_type,
            'message': message
        }))
    except Exception as e:
        logging.error(f"Error publicando en Redis: {e}")

def enforce_retention_policy():
    try:
        limit = int(get_server_config('auto_backup_retention', 10))
        if limit <= 0: return

        files = glob.glob(os.path.join(BACKUP_DIR, '*.sql'))
        if len(files) <= limit: return

        # Ordenar por fecha de modificación (descendente)
        files.sort(key=os.path.getmtime, reverse=True)

        # Eliminar los excedentes
        to_delete = files[limit:]
        for f in to_delete:
            os.remove(f)
        
        logging.info(f"Limpieza: Se eliminaron {len(to_delete)} backups antiguos.")
        
    except Exception as e:
        logging.error(f"Error en política de retención: {e}")

# --- TAREAS ---

# ... (código anterior sin cambios) ...

# --- Helper nuevo para encontrar mysqldump ---
def resolve_mysqldump_path():
    # 1. Intentar leer desde la configuración guardada en BD (Panel Admin)
    # Si guardaste la ruta en el panel admin (Configuración del Servidor), la usará.
    db_config_path = get_server_config('sys_mysqldump_path', '')
    if db_config_path and os.path.isfile(db_config_path):
        return db_config_path

    # 2. Buscar en rutas comunes de XAMPP (E: y C:)
    common_paths = [
        r"E:\xampp\mysql\bin\mysqldump.exe",  # Tu ruta probable
        r"C:\xampp\mysql\bin\mysqldump.exe",
        r"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe"
    ]

    for path in common_paths:
        if os.path.isfile(path):
            return path

    # 3. Último recurso: Asumir que está en el PATH de Windows
    return "mysqldump"

# --- TAREAS ---

def task_create_backup(payload):
    logging.info("Iniciando tarea: Crear Backup...")
    
    timestamp = datetime.now().strftime('%Y-%m-%d_%H-%M-%S')
    hash_suffix = str(int(time.time()))[-6:] 
    filename = f"backup_{timestamp}_{hash_suffix}.sql"
    filepath = os.path.join(BACKUP_DIR, filename)

    # Asegurar directorio
    os.makedirs(BACKUP_DIR, exist_ok=True)

    # Obtener la ruta correcta del ejecutable
    mysqldump_bin = resolve_mysqldump_path()
    
    # Construir comando
    dump_cmd = [
        mysqldump_bin,
        f'--host={DB_HOST}',
        f'--user={DB_USER}',
        # Nota: Si el password está vacío en XAMPP, mysqldump a veces prefiere no recibir el flag --password vacío.
        # Si DB_PASS tiene valor, lo agregamos:
    ]
    
    if DB_PASS:
        dump_cmd.append(f'--password={DB_PASS}')
    
    dump_cmd.extend([
        DB_NAME,
        f'--result-file={filepath}'
    ])

    try:
        # Ejecutar proceso
        process = subprocess.run(dump_cmd, capture_output=True, text=True)

        if process.returncode == 0 and os.path.exists(filepath) and os.path.getsize(filepath) > 0:
            logging.info(f"Backup creado exitosamente: {filename}")
            
            is_system = payload.get('is_system', False)
            user_id = payload.get('requested_by', 0)
            
            identifier = f"System | Created: {filename}" if is_system else f"Admin:{user_id} | Created: {filename}"
            
            # 1. Log en DB
            log_security_event('backup_create', identifier)
            
            # 2. Limpieza si es automático
            if is_system:
                enforce_retention_policy()

            # 3. Notificar al Frontend
            notify_frontend('notification', {
                'type': 'success',
                'text': f'Copia de seguridad completada: {filename}'
            })
            
            # Recargar lista
            notify_frontend('action', {'action': 'refresh_backups'})

        else:
            logging.error(f"Error en mysqldump (Code {process.returncode}): {process.stderr}")
            # Mensaje de error más descriptivo
            error_msg = 'Fallo al crear copia.'
            if process.returncode != 0:
                error_msg += f' Código: {process.returncode}.'
            notify_frontend('notification', {'type': 'error', 'text': error_msg})

    except FileNotFoundError:
        logging.error(f"No se encontró el ejecutable: {mysqldump_bin}")
        notify_frontend('notification', {'type': 'error', 'text': 'Error: Ejecutable mysqldump no encontrado en el servidor.'})
        
    except Exception as e:
        logging.error(f"Excepción al ejecutar backup: {e}")
        notify_frontend('notification', {'type': 'error', 'text': 'Error crítico en proceso de backup.'})

# ... (resto del archivo igual) ...

# --- DISPATCHER ---

TASKS = {
    'create_backup': task_create_backup
}

# --- MAIN LOOP ---

if __name__ == "__main__":
    logging.info("🚀 Aurora Worker iniciado. Esperando trabajos en Redis...")
    
    r_client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, password=REDIS_PASS, decode_responses=True)

    while True:
        try:
            # BLPOP bloquea hasta que haya un elemento en la lista
            # Retorna una tupla (nombre_cola, valor)
            item = r_client.blpop(QUEUE_NAME, timeout=0) 
            
            if item:
                _, raw_data = item
                try:
                    job = json.loads(raw_data)
                    task_name = job.get('task')
                    payload = job.get('payload', {})

                    if task_name in TASKS:
                        TASKS[task_name](payload)
                    else:
                        logging.warning(f"Tarea desconocida recibida: {task_name}")

                except json.JSONDecodeError:
                    logging.error("Error decodificando JSON del trabajo.")
                except Exception as e:
                    logging.error(f"Error procesando trabajo: {e}")

        except redis.exceptions.ConnectionError:
            logging.error("Conexión con Redis perdida. Reintentando en 5s...")
            time.sleep(5)
        except KeyboardInterrupt:
            logging.info("Worker detenido por usuario.")
            break
        except Exception as e:
            logging.error(f"Error fatal en loop principal: {e}")
            time.sleep(1)