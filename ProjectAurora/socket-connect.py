import asyncio
import websockets
import json
import os
import sys
import logging
import hashlib
import time
from logging.handlers import RotatingFileHandler
from datetime import datetime
from dotenv import load_dotenv
import aiomysql

# --- FIX PARA WINDOWS (CODIFICACIÓN) ---
if sys.platform == 'win32':
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8')
    if hasattr(sys.stderr, 'reconfigure'):
        sys.stderr.reconfigure(encoding='utf-8')

# Cargar variables de entorno
load_dotenv()

# --- CONFIGURACIÓN LOGGING ---
if not os.path.exists('logs'):
    os.makedirs('logs')

file_handler = RotatingFileHandler('logs/websocket_server.log', maxBytes=5*1024*1024, backupCount=3, encoding='utf-8')
file_handler.setLevel(logging.INFO)
file_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
file_handler.setFormatter(file_formatter)

console_handler = logging.StreamHandler(sys.stdout)
console_handler.setLevel(logging.INFO)
console_handler.setFormatter(file_formatter)

class AdminBroadcastHandler(logging.Handler):
    def emit(self, record):
        if not admin_sessions:
            return
        log_entry = self.format(record)
        payload = json.dumps({"type": "server_log_debug", "log": log_entry})
        try:
            loop = asyncio.get_running_loop()
            if loop.is_running():
                loop.create_task(self.broadcast(payload))
        except RuntimeError:
            pass 

    async def broadcast(self, payload):
        dead_sockets = set()
        for ws in admin_sessions:
            try:
                await ws.send(payload)
            except:
                dead_sockets.add(ws)
        admin_sessions.difference_update(dead_sockets)

admin_handler = AdminBroadcastHandler()
admin_handler.setLevel(logging.INFO)
admin_handler.setFormatter(logging.Formatter('[%(asctime)s] %(message)s', datefmt='%H:%M:%S'))

logger = logging.getLogger()
logger.setLevel(logging.INFO)
if logger.hasHandlers():
    logger.handlers.clear()
logger.addHandler(file_handler)
logger.addHandler(console_handler)
logger.addHandler(admin_handler)

# --- ESTADO GLOBAL ---
connected_clients = {} 
admin_sessions = set()
db_pool = None 

# [FIX RATE LIMITING] Control de fuerza bruta
ip_attempts = {} # {ip: {count: int, reset_at: float}}
MAX_AUTH_ATTEMPTS = 5
ATTEMPT_WINDOW = 60 # segundos

DB_CONFIG = {
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASS'),
    'host': os.getenv('DB_HOST'),
    'db': os.getenv('DB_NAME'),
    'autocommit': True
}

async def init_db_pool():
    global db_pool
    try:
        # [FIX ESCALABILIDAD] Aumentamos pool size
        db_pool = await aiomysql.create_pool(**DB_CONFIG, minsize=1, maxsize=20)
        logger.info("✅ Pool de conexiones a base de datos inicializado (Max 20).")
    except Exception as e:
        logger.critical(f"❌ Error fatal conectando a BD: {e}")
        exit(1)

def check_rate_limit(ip):
    now = time.time()
    if ip not in ip_attempts:
        ip_attempts[ip] = {'count': 0, 'reset_at': now + ATTEMPT_WINDOW}
    
    data = ip_attempts[ip]
    if now > data['reset_at']:
        data['count'] = 0
        data['reset_at'] = now + ATTEMPT_WINDOW
    
    if data['count'] >= MAX_AUTH_ATTEMPTS:
        return False
    return True

def record_failed_attempt(ip):
    if ip in ip_attempts:
        ip_attempts[ip]['count'] += 1

async def verify_token_and_get_session(raw_token):
    if not db_pool:
        return None

    # [FIX SEGURIDAD] Hashear el token recibido antes de comparar
    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()

    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                # Comparamos contra el hash almacenado en BD
                query = """
                    SELECT t.user_id, t.session_id, u.role 
                    FROM ws_auth_tokens t
                    JOIN users u ON t.user_id = u.id
                    WHERE t.token = %s AND t.expires_at > NOW()
                """
                await cur.execute(query, (token_hash,))
                row = await cur.fetchone()
                if row:
                    return (str(row[0]), str(row[1]), str(row[2]))
                return None
    except Exception as e:
        logger.error(f"Error verificando token: {e}")
        return None

async def broadcast_user_status(user_id, status):
    if not admin_sessions:
        return
    timestamp = datetime.now().isoformat()
    message = json.dumps({
        "type": "user_status_change",
        "payload": {"user_id": user_id, "status": status, "timestamp": timestamp}
    })
    dead_sockets = set()
    for ws in admin_sessions:
        try: await ws.send(message)
        except: dead_sockets.add(ws)
    admin_sessions.difference_update(dead_sockets)

async def handle_browser_client(websocket):
    user_id = None
    session_id = None
    user_role = None
    remote_ip = websocket.remote_address[0]
    
    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError:
                continue
                
            msg_type = data.get('type')

            if msg_type == 'auth':
                # [FIX RATE LIMIT] Verificar IP antes de procesar
                if not check_rate_limit(remote_ip):
                    logger.warning(f"IP {remote_ip} bloqueada temporalmente por fuerza bruta.")
                    await websocket.send(json.dumps({
                        "type": "auth_error_permanent", 
                        "msg": "Too many attempts. Wait."
                    }))
                    return

                token = data.get('token')
                if not token:
                    continue

                auth_data = await verify_token_and_get_session(token)
                
                if auth_data:
                    user_id, session_id, user_role = auth_data
                    
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                    connected_clients[user_id][session_id] = websocket
                    
                    if user_role in ['founder', 'administrator']:
                        admin_sessions.add(websocket)
                    
                    logger.info(f"Usuario conectado: ID {user_id} | Rol: {user_role} | Sesión: {session_id[:8]}...")
                    
                    await websocket.send(json.dumps({"type": "connected"}))
                    await broadcast_user_status(user_id, 'online')
                else:
                    record_failed_attempt(remote_ip)
                    logger.warning(f"Intento de conexión fallido desde {remote_ip}. Token inválido.")
                    # [FIX BUCLE INFINITO] Enviar error fatal al cliente
                    await websocket.send(json.dumps({
                        "type": "auth_error_permanent", 
                        "msg": "Token expired or invalid"
                    }))
                    return 

            elif msg_type == 'get_online_users':
                if user_role in ['founder', 'administrator']:
                    response = json.dumps({
                        "type": "online_users_list", 
                        "payload": list(connected_clients.keys())
                    })
                    await websocket.send(response)

    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        logger.error(f"Error inesperado en socket cliente: {e}")
    finally:
        if user_id and session_id:
            if user_id in connected_clients:
                if session_id in connected_clients[user_id]:
                    del connected_clients[user_id][session_id]
                
                if not connected_clients[user_id]:
                    del connected_clients[user_id]
                    logger.info(f"Usuario {user_id} desconectado completamente.")
                    await broadcast_user_status(user_id, 'offline')
        
        if websocket in admin_sessions:
            admin_sessions.discard(websocket)

async def handle_php_notification(reader, writer):
    try:
        data = await reader.read(4096)
        msg = data.decode()
        if not msg: return
        
        try:
            full_payload = json.loads(msg)
        except json.JSONDecodeError:
            logger.error(f"Payload JSON inválido recibido de PHP.")
            return

        target = str(full_payload.get('target_id'))
        msg_type = full_payload.get('type')
        inner_payload = full_payload.get('payload', {})
        
        client_message = json.dumps(full_payload)
        
        if target == 'global':
            logger.info(f"📢 Notificación GLOBAL: {msg_type}")
            all_users = list(connected_clients.items())
            for uid, sessions in all_users:
                for sid, ws in sessions.items():
                    try: await ws.send(client_message)
                    except: pass

        elif target in connected_clients:
            target_sess = inner_payload.get('target_session_id')
            exclude_sess = inner_payload.get('exclude_session_id')
            
            log_suffix = f"(Target: {target_sess})" if target_sess else ""
            logger.info(f"📨 Notificación a Usuario {target}: {msg_type} {log_suffix}")

            user_sessions_copy = list(connected_clients[target].items())
            for sess_id, ws in user_sessions_copy:
                should_send = True
                if target_sess and str(target_sess) != str(sess_id): should_send = False
                if exclude_sess and str(exclude_sess) == str(sess_id): should_send = False
                
                if should_send:
                    final_msg = client_message
                    if msg_type == 'force_logout_others':
                        new_payload = full_payload.copy()
                        new_payload['type'] = 'force_logout'
                        if 'payload' not in new_payload: new_payload['payload'] = {}
                        new_payload['payload']['reason'] = 'security_change'
                        final_msg = json.dumps(new_payload)

                    try: await ws.send(final_msg)
                    except: pass

    except Exception as e:
        logger.error(f"Error en puente PHP-Socket: {e}")
    finally:
        writer.close()
        await writer.wait_closed()

async def main():
    await init_db_pool()
    logger.info("=== Servidor Aurora Iniciado (Async + Logging Profesional) ===")
    
    ws_server = await websockets.serve(handle_browser_client, "0.0.0.0", 8080)
    php_bridge = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
    
    await asyncio.Future() 

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("🛑 Servidor detenido por el usuario.")
    except Exception as e:
        logger.critical(f"🔥 Error fatal en el servidor: {e}")