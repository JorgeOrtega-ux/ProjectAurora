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
import shutil
from concurrent.futures import ThreadPoolExecutor
import re 
import math

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
VIDEOS_DIR = os.path.join(BASE_DIR, 'public', 'storage', 'videos')

# [FIX BLOQUEO] Executor para tareas pesadas (Backups/IO/Video)
EXECUTOR = ThreadPoolExecutor(max_workers=3)

# --- INICIALIZACIÓN GLOBAL DE REDIS ---
try:
    # [SEGURIDAD DINÁMICA]
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

def update_video_status(uuid, status, error_msg=None):
    """Actualiza el estado del video en la DB"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        if error_msg:
            sql = "UPDATE videos SET status = %s, error_message = %s WHERE uuid = %s"
            cursor.execute(sql, (status, error_msg, uuid))
        else:
            sql = "UPDATE videos SET status = %s WHERE uuid = %s"
            cursor.execute(sql, (status, uuid))
        conn.commit()
        conn.close()
    except Exception as e:
        logging.error(f"Error actualizando video DB: {e}")

# [NUEVO] Helper específico para actualizar solo el porcentaje sin tocar el estado
def update_video_progress_db(uuid, percentage):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        sql = "UPDATE videos SET processing_percentage = %s WHERE uuid = %s"
        cursor.execute(sql, (percentage, uuid))
        conn.commit()
        conn.close()
    except Exception as e:
        logging.error(f"Error actualizando porcentaje DB: {e}")

def get_video_metadata(file_path):
    """Obtiene metadatos (duración y dimensiones) usando ffprobe"""
    try:
        # [MODIFICADO] Solicitamos streams para ver ancho/alto y formato JSON para parseo seguro
        cmd = [
            'ffprobe', 
            '-v', 'error', 
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height:format=duration', 
            '-of', 'json', 
            file_path
        ]
        result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        data = json.loads(result.stdout)
        
        format_info = data.get('format', {})
        stream_info = data.get('streams', [{}])[0]
        
        return {
            'duration': float(format_info.get('duration', 0)),
            'width': int(stream_info.get('width', 0)),
            'height': int(stream_info.get('height', 0))
        }
    except Exception as e:
        logging.error(f"Error ffprobe: {e}")
        return {'duration': 0, 'width': 0, 'height': 0}

# [NUEVO] Convierte timecode HH:MM:SS.ms a segundos totales
def timecode_to_seconds(timecode):
    try:
        h, m, s = timecode.split(':')
        return int(h) * 3600 + int(m) * 60 + float(s)
    except Exception:
        return 0.0

def secure_resolve_mysqldump():
    config_path = get_server_config('sys_mysqldump_path', '')
    valid_names = ['mysqldump', 'mysqldump.exe']
    
    if config_path and os.path.isfile(config_path):
        if os.path.basename(config_path).lower() in valid_names:
            return config_path

    common_paths = [
        r"C:\xampp\mysql\bin\mysqldump.exe",
        r"E:\xampp\mysql\bin\mysqldump.exe",
        r"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe",
        r"C:\wamp64\bin\mysql\mysql5.7.31\bin\mysqldump.exe"
    ]

    for path in common_paths:
        if os.path.isfile(path):
            return path

    return "mysqldump"

def enforce_retention_policy():
    try:
        limit = int(get_server_config('auto_backup_retention', 10))
        if limit <= 0: return

        files = glob.glob(os.path.join(BACKUP_DIR, '*.sql'))
        if len(files) <= limit: return

        files.sort(key=os.path.getmtime, reverse=True)
        to_delete = files[limit:]
        
        for f in to_delete:
            try: os.remove(f)
            except: pass
        
        logging.info(f"Limpieza: Se eliminaron {len(to_delete)} backups antiguos.")
    except Exception as e:
        logging.error(f"Error en política de retención: {e}")

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
            temp_conf.write(f"[client]\nuser=\"{DB_USER}\"\npassword=\"{DB_PASS}\"\nhost=\"{DB_HOST}\"\n")
            conf_path = temp_conf.name
        
        dump_cmd = [
            mysqldump_bin,
            f'--defaults-extra-file={conf_path}',
            DB_NAME, 
            '--single-transaction', 
            '--quick'
        ]

        with open(filepath, 'w') as outfile:
            process = subprocess.Popen(dump_cmd, stdout=outfile, stderr=subprocess.PIPE, text=True)
            _, stderr = process.communicate()

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
        
        if not files: raise ValueError("Lista de archivos vacía")

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

        with zipfile.ZipFile(zip_filepath, 'w', zipfile.ZIP_DEFLATED) as zf:
            for abs_path, arc_name in valid_files:
                zf.write(abs_path, arc_name)

        token = secrets.token_hex(32)
        redis_data = {
            'filepath': zip_filepath, 'filename': zip_filename, 'is_temp': True,
            'user_id': user_id, 'created_at': time.time()
        }
        
        r_client.setex(f"download:token:{token}", 300, json.dumps(redis_data))
        download_url = f"download.php?token={token}"
        
        notify_frontend('download_ready', {
            'url': download_url, 'filename': zip_filename, 'type': source_type
        })
        
        log_security_event('zip_create', f"Admin:{user_id} | Created: {zip_filename}")
        logging.info(f"✅ ZIP creado y token generado: {token[:8]}...")

    except Exception as e:
        logging.error(f"❌ Error creando ZIP: {e}")
        notify_frontend('notification', {'type': 'error', 'text': 'Error al generar el archivo comprimido.'})

def task_process_video(payload):
    logging.info("🎬 [Thread] Iniciando procesamiento de video Multi-Bitrate (HLS)...")
    
    video_uuid = payload.get('video_uuid')
    raw_path = payload.get('raw_path')
    
    if not video_uuid or not raw_path or not os.path.exists(raw_path):
        logging.error(f"❌ Datos de video inválidos: {video_uuid}")
        notify_frontend('notification', {'type': 'error', 'text': 'Error al procesar video.'})
        update_video_status(video_uuid, 'error', 'Raw file not found')
        return

    update_video_status(video_uuid, 'processing')

    # Crear directorio público para el video
    output_dir = os.path.join(VIDEOS_DIR, video_uuid)
    os.makedirs(output_dir, exist_ok=True)

    try:
        # 1. Obtener Metadatos Originales
        meta = get_video_metadata(raw_path)
        duration = meta.get('duration', 0)
        width = meta.get('width', 0)
        height = meta.get('height', 0)
        
        # Determinar orientación para metadatos
        orientation = 'portrait' if height > width else 'landscape'
        
        # [TIERED LIMITS] Validación de duración máxima
        max_duration = payload.get('max_duration', 0) 
        
        if max_duration > 0 and duration > max_duration:
            error_msg = f"Límite de duración excedido ({int(duration)}s > {max_duration}s)"
            logging.warning(f"⚠️ Video {video_uuid} rechazado: {error_msg}")
            if os.path.exists(raw_path): os.remove(raw_path)
            update_video_status(video_uuid, 'error', error_msg)
            notify_frontend('notification', {'type': 'error', 'text': 'Error: El video excede la duración permitida.'})
            return

        # --- DEFINICIÓN DE ESCALERA DE CALIDADES (LADDER) ---
        # Ahora incluye 4K (2160p) y 2K (1440p)
        available_qualities = [
            # 4K UHD: Bitrate alto para mantener detalle en pantallas grandes
            {'name': '4k',    'w': 3840, 'h': 2160, 'bv': '14000k', 'ba': '320k'},
            # 2K QHD: El punto dulce entre HD y 4K
            {'name': '2k',    'w': 2560, 'h': 1440, 'bv': '9000k',  'ba': '256k'},
            # Full HD Estándar
            {'name': '1080p', 'w': 1920, 'h': 1080, 'bv': '4500k',  'ba': '192k'},
            {'name': '720p',  'w': 1280, 'h': 720,  'bv': '2500k',  'ba': '128k'},
            {'name': '480p',  'w': 854,  'h': 480,  'bv': '1000k',  'ba': '128k'},
            {'name': '360p',  'w': 640,  'h': 360,  'bv': '600k',   'ba': '96k'},
            {'name': '240p',  'w': 426,  'h': 240,  'bv': '350k',   'ba': '64k'},
            {'name': '144p',  'w': 256,  'h': 144,  'bv': '100k',   'ba': '48k'}
        ]

        # Filtramos: Solo generamos calidades IGUALES o MENORES al original
        # Si el video original es 1920x1080, NO generará 4K ni 2K.
        # Si el video original es 3840x2160, generará TODO (4K, 2K, 1080p...).
        target_qualities = [q for q in available_qualities if q['h'] <= height]

        # Fallback: Si el video es muy pequeño o extraño y no entra en ninguna categoría,
        # generamos al menos una variante con la resolución original.
        if not target_qualities:
             target_qualities = [{'name': 'original', 'w': width, 'h': height, 'bv': '1000k', 'ba': '128k'}]

        logging.info(f"Video {video_uuid}: Generando calidades -> {[q['name'] for q in target_qualities]}")

        # --- CONSTRUCCIÓN DEL COMANDO FFMPEG ---
        cmd = ['ffmpeg', '-y', '-i', raw_path]
        
        # Listas para construir los argumentos dinámicos
        var_stream_map = []
        
        # Iteramos sobre las calidades objetivo para crear los mapas
        for i, q in enumerate(target_qualities):
            # Mapear video y audio del input 0
            # Escalar video usando 'scale'
            # force_original_aspect_ratio=decrease evita distorsión si el ratio no es exacto
            cmd += [
                f'-map', '0:v:0',
                f'-map', '0:a:0',
                f'-c:v:{i}', 'libx264', 
                f'-b:v:{i}', q['bv'], 
                f'-s:v:{i}', f"{q['w']}x{q['h']}",
                f'-c:a:{i}', 'aac', 
                f'-b:a:{i}', q['ba']
            ]
            
            # Agrupar video+audio en el mapa de streams para HLS (Muxed)
            # Formato: "v:0,a:0 v:1,a:1 ..."
            var_stream_map.append(f"v:{i},a:{i}")

        # Nombre del archivo maestro
        master_pl_name = 'index.m3u8' 
        
        cmd += [
            '-f', 'hls',
            '-var_stream_map', " ".join(var_stream_map),
            '-master_pl_name', master_pl_name,
            '-hls_time', '6',
            '-hls_playlist_type', 'vod',
            '-hls_segment_filename', os.path.join(output_dir, 'v%v_segment_%03d.ts'), # %v = ID variante
            # Cada variante tendrá su propio playlist (v0.m3u8, v1.m3u8...)
            os.path.join(output_dir, 'v%v.m3u8')
        ]
        
        logging.info(f"⏳ Ejecutando Transcodificación Multi-Bitrate (incluye 4K/2K si aplica)...")
        
        # Ejecutar con Popen para leer progreso en tiempo real
        process = subprocess.Popen(
            cmd, 
            stdout=subprocess.PIPE, 
            stderr=subprocess.PIPE, 
            text=True, 
            universal_newlines=True,
            bufsize=1 # Line buffered
        )

        last_percent = -1
        # Regex para capturar 'time=HH:MM:SS.mm' del log de FFmpeg
        time_regex = re.compile(r"time=(\d{2}:\d{2}:\d{2}\.\d{2})")

        # Loop de lectura de progreso
        while True:
            line = process.stderr.readline()
            
            if not line and process.poll() is not None:
                break
            
            if line:
                # Buscar patrón de tiempo
                match = time_regex.search(line)
                if match and duration > 0:
                    time_str = match.group(1)
                    current_seconds = timecode_to_seconds(time_str)
                    
                    # Calcular porcentaje
                    percent = int((current_seconds / duration) * 100)
                    if percent > 100: percent = 100
                    
                    # Actualizar cada 2%
                    if percent >= last_percent + 2:
                        last_percent = percent
                        
                        update_video_progress_db(video_uuid, percent)
                        
                        notify_frontend('processing_progress', {
                            'uuid': video_uuid,
                            'percent': percent,
                            'status': 'processing'
                        })

        # Verificar resultado final
        return_code = process.wait()
        
        if return_code != 0:
            remaining_err = process.stderr.read()
            raise Exception(f"FFmpeg Error (Code {return_code}): {remaining_err}")

        # 3. Actualizar BD con rutas y estado final
        # [IMPORTANTE] hls_path apunta al MASTER playlist (index.m3u8)
        relative_hls = f"public/storage/videos/{video_uuid}/{master_pl_name}"
        
        conn = get_db_connection()
        cursor = conn.cursor()
        
        sql = """
            UPDATE videos SET 
                status = 'waiting_for_metadata', 
                hls_path = %s, 
                duration = %s,
                orientation = %s,
                processing_percentage = 100 
            WHERE uuid = %s
        """
        cursor.execute(sql, (relative_hls, duration, orientation, video_uuid))
        conn.commit()
        conn.close()
        
        # 4. Notificar al Frontend
        notify_frontend('processing_complete', {
            'uuid': video_uuid,
            'status': 'waiting_for_metadata',
            'title': 'Video Procesado',
            'percent': 100
        })
        
        logging.info(f"✅ Video procesado exitosamente (Multi-Bitrate): {video_uuid}")

        # 5. Encadenar tarea: Generación de Sprites
        logging.info("➡️ Encadenando tarea: Generación de Sprites (Scrubbing)...")
        task_generate_sprites({
            'video_uuid': video_uuid,
            'raw_path': raw_path,
            'duration': duration,
            'orientation': orientation
        })

    except Exception as e:
        logging.error(f"❌ Error procesando video {video_uuid}: {e}")
        update_video_status(video_uuid, 'error', str(e))
        notify_frontend('notification', {'type': 'error', 'text': 'Fallo en la transcodificación del video.'})

def task_generate_thumbnails(payload):
    logging.info("🖼️ [Thread] Generando miniaturas inteligentes (Alta Calidad)...")
    
    video_uuid = payload.get('video_uuid')
    raw_path = payload.get('raw_path')
    duration = float(payload.get('duration', 0)) # Duración en segundos

    if not video_uuid or not os.path.exists(raw_path):
        logging.error(f"❌ Archivo no encontrado para thumbs: {raw_path}")
        notify_frontend('notification', {'type': 'error', 'text': 'Error: Video fuente no encontrado.'})
        return

    # Directorio de salida
    thumbs_dir = os.path.join(BASE_DIR, 'public', 'storage', 'thumbnails', 'generated', video_uuid)
    os.makedirs(thumbs_dir, exist_ok=True)

    # Lógica de intervalos
    num_thumbs = 1
    if duration > 60:
        minutes = duration / 60
        num_thumbs = int(minutes) 
        
    if num_thumbs > 12: 
        num_thumbs = 12
    if num_thumbs < 3:
        num_thumbs = 3 

    interval = duration / (num_thumbs + 1) 

    generated_files = []

    try:
        for i in range(1, num_thumbs + 1):
            timestamp = i * interval
            filename = f"thumb_{int(timestamp)}.jpg"
            output_path = os.path.join(thumbs_dir, filename)
            rel_path = f"public/storage/thumbnails/generated/{video_uuid}/{filename}"

            # Filtro 16:9 con relleno negro (Pillarbox)
            cmd = [
                'ffmpeg', '-y', 
                '-ss', str(timestamp),      
                '-i', raw_path,             
                '-vframes', '1',            
                '-q:v', '2',                
                '-vf', "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2:black", 
                output_path
            ]
            
            subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

            if os.path.exists(output_path):
                generated_files.append(rel_path)

        # Guardar en DB 
        if generated_files:
            conn = get_db_connection()
            cursor = conn.cursor()
            json_paths = json.dumps(generated_files)
            cursor.execute("UPDATE videos SET generated_thumbnails = %s WHERE uuid = %s", (json_paths, video_uuid))
            conn.commit()
            conn.close()

            logging.info(f"✅ Se generaron {len(generated_files)} miniaturas HD para {video_uuid}")
            
            notify_frontend('thumbnails_generated', {
                'uuid': video_uuid,
                'thumbnails': generated_files
            })
        else:
            logging.warning("⚠️ No se generaron miniaturas (FFmpeg falló o el video es ilegible)")

    except Exception as e:
        logging.error(f"❌ Error generando miniaturas: {e}")

def task_generate_sprites(payload):
    logging.info("🎞️ [Thread] Iniciando Generación de Sprites (Scrubbing)...")
    
    video_uuid = payload.get('video_uuid')
    raw_path = payload.get('raw_path')
    duration = float(payload.get('duration', 0))
    orientation = payload.get('orientation', 'landscape')
    
    if not video_uuid or not os.path.exists(raw_path):
        logging.error("❌ Datos insuficientes para sprites")
        return

    # Directorio para los sprites (reutilizamos la carpeta pública del video)
    output_dir = os.path.join(VIDEOS_DIR, video_uuid)
    sprite_filename = "sprite_sheet.jpg"
    vtt_filename = "sprites.vtt"
    sprite_path_abs = os.path.join(output_dir, sprite_filename)
    vtt_path_abs = os.path.join(output_dir, vtt_filename)
    
    # Rutas relativas para DB
    sprite_path_rel = f"public/storage/videos/{video_uuid}/{sprite_filename}"
    vtt_path_rel = f"public/storage/videos/{video_uuid}/{vtt_filename}"

    # CONFIGURACIÓN DE LA CUADRÍCULA
    ROWS = 5
    COLS = 5
    TOTAL_IMAGES = ROWS * COLS
    
    interval = duration / TOTAL_IMAGES
    if interval <= 0: interval = 1

    # Definir dimensiones de celda según orientación
    if orientation == 'portrait':
        scale_filter = "scale=-1:160"
        cell_height = 160
        # Estimamos el ancho si es 9:16 -> aprox 90px
        cell_width = 90 
    else:
        scale_filter = "scale=160:-1"
        cell_width = 160
        # Estimamos el alto si es 16:9 -> aprox 90px
        cell_height = 90

    try:
        # COMANDO FFMPEG
        vf_string = f"fps=1/{interval},{scale_filter},tile={COLS}x{ROWS}"
        
        cmd = [
            'ffmpeg', '-y',
            '-i', raw_path,
            '-vf', vf_string,
            '-frames:v', '1', # Solo queremos 1 imagen de salida (la hoja completa)
            '-q:v', '3',      # Calidad JPG decente
            sprite_path_abs
        ]
        
        logging.info(f"🎞️ Ejecutando FFmpeg Sprite: {vf_string}")
        subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

        if os.path.exists(sprite_path_abs):
            # Obtener dimensiones reales de la hoja generada para VTT preciso
            meta = get_video_metadata(sprite_path_abs) # Reusamos funcion aunque sea imagen
            sheet_width = meta['width']
            sheet_height = meta['height']
            
            # Recalcular dimensiones exactas de celda
            real_cell_width = sheet_width / COLS
            real_cell_height = sheet_height / ROWS

            # GENERAR ARCHIVO VTT
            with open(vtt_path_abs, 'w') as vtt:
                vtt.write("WEBVTT\n\n")
                
                for i in range(TOTAL_IMAGES):
                    # Tiempo inicio y fin de este frame
                    start_time = i * interval
                    end_time = (i + 1) * interval
                    
                    # Formatear tiempo HH:MM:SS.ms
                    def fmt_time(t):
                        h = int(t // 3600)
                        m = int((t % 3600) // 60)
                        s = t % 60
                        return f"{h:02}:{m:02}:{s:06.3f}"

                    # Calcular coordenadas XY
                    col_idx = i % COLS
                    row_idx = i // COLS
                    x = int(col_idx * real_cell_width)
                    y = int(row_idx * real_cell_height)
                    w = int(real_cell_width)
                    h = int(real_cell_height)

                    vtt.write(f"{fmt_time(start_time)} --> {fmt_time(end_time)}\n")
                    vtt.write(f"{sprite_filename}#xywh={x},{y},{w},{h}\n\n")

            logging.info(f"✅ Sprite sheet y VTT generados: {sprite_path_rel}")

            # ACTUALIZAR BASE DE DATOS
            conn = get_db_connection()
            cursor = conn.cursor()
            sql = "UPDATE videos SET sprite_path = %s, vtt_path = %s WHERE uuid = %s"
            cursor.execute(sql, (sprite_path_rel, vtt_path_rel, video_uuid))
            conn.commit()
            conn.close()

        else:
            logging.error("❌ Falló la generación del Sprite Sheet (archivo no creado)")

    except Exception as e:
        logging.error(f"❌ Excepción en task_generate_sprites: {e}")

def task_register_view_persistence(payload):
    """
    Registra la visita en el historial (video_views).
    [CORREGIDO] Ya NO actualiza el contador global 'videos.views_count' aquí.
    De eso se encarga el Scheduler masivamente para evitar double-counting.
    """
    video_uuid = payload.get('video_uuid')
    ip = payload.get('ip')
    user_id = payload.get('user_id') # Puede ser None si no está logueado
    user_agent = payload.get('user_agent', 'Unknown')
    timestamp = payload.get('timestamp') # PHP envía time()
    
    if not video_uuid:
        logging.warning("⚠️ task_register_view_persistence recibida sin video_uuid")
        return

    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        # 1. Obtener el ID numérico interno del video
        cursor.execute("SELECT id FROM videos WHERE uuid = %s", (video_uuid,))
        video_result = cursor.fetchone()
        
        if video_result:
            video_id = video_result[0]
            
            # 2. Insertar en el log histórico (video_views)
            # Usamos FROM_UNIXTIME para convertir el timestamp de PHP a DATETIME de MySQL
            sql_log = """
                INSERT INTO video_views (video_id, user_id, ip_address, user_agent, viewed_at)
                VALUES (%s, %s, %s, %s, FROM_UNIXTIME(%s))
            """
            cursor.execute(sql_log, (video_id, user_id, ip, user_agent, timestamp))
            
            # 3. [DESACTIVADO] El Scheduler ya actualiza el contador global.
            # Evitamos contar la visita dos veces.
            # sql_update = "UPDATE videos SET views_count = views_count + 1 WHERE id = %s"
            # cursor.execute(sql_update, (video_id,))
            
            conn.commit()
            # logging.info(f"👁️ Visita registrada OK para video {video_uuid}")
        else:
            logging.warning(f"⚠️ Intento de registrar visita en video inexistente: {video_uuid}")
        
        cursor.close()
        conn.close()

    except Exception as e:
        logging.error(f"❌ Error persistiendo visita en BD: {e}")
TASKS = {
    'create_backup': task_create_backup,
    'create_zip': task_create_zip,
    'process_video': task_process_video,
    'generate_thumbnails': task_generate_thumbnails,
    'generate_sprites': task_generate_sprites,
    'register_view_persistence': task_register_view_persistence  # <--- ESTA LÍNEA ES LA CLAVE
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
    
    # Asegurar directorios
    os.makedirs(VIDEOS_DIR, exist_ok=True)
    
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