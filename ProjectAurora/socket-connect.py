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
connected_clients = {} # { user_id: { session_id: websocket } }
admin_sessions = set()
db_pool = None 

# [FIX RATE LIMITING] Control de fuerza bruta
ip_attempts = {} 
MAX_AUTH_ATTEMPTS = 5
ATTEMPT_WINDOW = 60 

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

    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()

    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                # Obtenemos info extra para el chat (username, pfp)
                query = """
                    SELECT t.user_id, t.session_id, u.role, u.username, u.profile_picture 
                    FROM ws_auth_tokens t
                    JOIN users u ON t.user_id = u.id
                    WHERE t.token = %s AND t.expires_at > NOW()
                """
                await cur.execute(query, (token_hash,))
                row = await cur.fetchone()
                if row:
                    return (str(row[0]), str(row[1]), str(row[2]), str(row[3]), str(row[4]))
                return None
    except Exception as e:
        logger.error(f"Error verificando token: {e}")
        return None

async def broadcast_user_status(user_id, status):
    # Avisar a admins (dashboards)
    timestamp = datetime.now().isoformat()
    if admin_sessions:
        message = json.dumps({
            "type": "user_status_change",
            "payload": {"user_id": user_id, "status": status, "timestamp": timestamp}
        })
        dead_sockets = set()
        for ws in admin_sessions:
            try: await ws.send(message)
            except: dead_sockets.add(ws)
        admin_sessions.difference_update(dead_sockets)
    
    # [NUEVO] Avisar a amigos conectados (para actualizar el punto verde en la lista de chats)
    # TODO: Implementar consulta de amigos conectados para broadcasting eficiente. 
    # Por ahora, los clientes pueden hacer polling o recibir el evento global si es necesario, 
    # pero para escalabilidad se recomienda implementar en el futuro 'friend_status_change'.

async def handle_chat_message(user_id, user_info, payload):
    """
    Maneja el envío de mensajes: 
    - Valida contexto (privado o comunidad).
    - [SEGURIDAD] Valida privacidad y amistad antes de enviar.
    - Guarda en la tabla correcta.
    - Difunde a los destinatarios.
    """
    target_uuid = payload.get('target_uuid') # Puede ser community_uuid o user_uuid
    message_text = payload.get('message')
    context = payload.get('context', 'community') # 'community' o 'private'
    reply_to_id = payload.get('reply_to_id')

    if not target_uuid or not message_text:
        return

    if not db_pool:
        logger.error("DB Pool no disponible para chat.")
        return

    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                
                # --- LÓGICA CHAT PRIVADO ---
                if context == 'private':
                    # 1. Obtener ID del receptor
                    await cur.execute("SELECT id FROM users WHERE uuid = %s", (target_uuid,))
                    receiver_row = await cur.fetchone()
                    if not receiver_row:
                        logger.warning(f"Usuario {user_id} intentó enviar DM a UUID inexistente: {target_uuid}")
                        return
                    
                    receiver_id = str(receiver_row[0])
                    
                    if receiver_id == user_id:
                        return # No auto-mensajes

                    # [SEGURIDAD CRÍTICA] VALIDACIÓN DE PRIVACIDAD
                    # Verificar si el usuario permite mensajes y el estado de amistad
                    privacy_query = """
                        SELECT 
                            COALESCE(up.message_privacy, 'friends') as privacy,
                            (SELECT status FROM friendships WHERE (sender_id = %s AND receiver_id = %s) OR (sender_id = %s AND receiver_id = %s)) as friend_status
                        FROM users u
                        LEFT JOIN user_preferences up ON u.id = up.user_id
                        WHERE u.id = %s
                    """
                    # Parámetros: (sender, receiver, receiver, sender) para amistad, (receiver_id) para usuario
                    await cur.execute(privacy_query, (user_id, receiver_id, receiver_id, user_id, receiver_id))
                    privacy_row = await cur.fetchone()

                    if privacy_row:
                        privacy = privacy_row[0]
                        status = privacy_row[1]
                        
                        is_blocked = False
                        
                        if privacy == 'nobody':
                            is_blocked = True
                        elif privacy == 'friends' and status != 'accepted':
                            is_blocked = True
                        
                        if is_blocked:
                            logger.warning(f"Bloqueo de seguridad: Usuario {user_id} intentó enviar mensaje a {receiver_id} sin permiso (Privacidad: {privacy}, Estado: {status}).")
                            return # Detener ejecución silenciosamente (o enviar error al cliente si se desea)

                    # -----------------------------------------------------

                    # 2. Obtener datos del reply si existe
                    reply_data = {}
                    if reply_to_id:
                        parent_query = """
                            SELECT m.message, u.username
                            FROM private_messages m
                            JOIN users u ON m.sender_id = u.id
                            WHERE m.id = %s
                        """
                        await cur.execute(parent_query, (reply_to_id,))
                        parent_row = await cur.fetchone()
                        if parent_row:
                            reply_data = {'message': parent_row[0], 'sender_username': parent_row[1]}
                        else:
                            reply_to_id = None

                    # 3. Insertar en private_messages
                    insert_query = """
                        INSERT INTO private_messages (sender_id, receiver_id, message, reply_to_id, type)
                        VALUES (%s, %s, %s, %s, 'text')
                    """
                    await cur.execute(insert_query, (user_id, receiver_id, message_text, reply_to_id))
                    message_id = cur.lastrowid
                    created_at = datetime.now().isoformat()

                    # 4. Construir payload
                    response_payload = json.dumps({
                        "type": "private_message",
                        "payload": {
                            "id": message_id,
                            "target_uuid": target_uuid, # UUID del que recibe para el remitente / UUID del que envia para el receptor
                            "context": "private",
                            "message": message_text,
                            "sender_id": user_id,
                            "sender_username": user_info['username'],
                            "sender_profile_picture": user_info['profile_picture'],
                            "sender_role": user_info['role'],
                            "created_at": created_at,
                            "type": "text",
                            "status": "active",
                            "reply_to_id": reply_to_id,
                            "reply_message": reply_data.get('message'),
                            "reply_sender_username": reply_data.get('sender_username')
                        }
                    })

                    # 5. Enviar al RECEPTOR (si está conectado)
                    if receiver_id in connected_clients:
                        for ws in connected_clients[receiver_id].values():
                            try: await ws.send(response_payload)
                            except: pass
                    
                    # 6. Enviar al REMITENTE (sync entre pestañas/dispositivos)
                    if user_id in connected_clients:
                        for ws in connected_clients[user_id].values():
                            try: await ws.send(response_payload)
                            except: pass

                # --- LÓGICA COMUNIDAD ---
                else:
                    # 1. Validar membresía y obtener ID
                    check_query = """
                        SELECT c.id 
                        FROM communities c
                        JOIN community_members cm ON c.id = cm.community_id
                        WHERE c.uuid = %s AND cm.user_id = %s
                    """
                    await cur.execute(check_query, (target_uuid, user_id))
                    comm_row = await cur.fetchone()

                    if not comm_row:
                        return

                    community_id = comm_row[0]

                    # 2. Reply data
                    reply_data = {}
                    if reply_to_id:
                        parent_query = """
                            SELECT m.message, u.username
                            FROM community_messages m
                            JOIN users u ON m.user_id = u.id
                            WHERE m.id = %s AND m.community_id = %s
                        """
                        await cur.execute(parent_query, (reply_to_id, community_id))
                        parent_row = await cur.fetchone()
                        if parent_row:
                            reply_data = {'message': parent_row[0], 'sender_username': parent_row[1]}
                        else:
                            reply_to_id = None

                    # 3. Insertar
                    insert_query = """
                        INSERT INTO community_messages (community_id, user_id, message, reply_to_id, type)
                        VALUES (%s, %s, %s, %s, 'text')
                    """
                    await cur.execute(insert_query, (community_id, user_id, message_text, reply_to_id))
                    
                    message_id = cur.lastrowid
                    created_at = datetime.now().isoformat()

                    # 4. Obtener miembros
                    members_query = "SELECT user_id FROM community_members WHERE community_id = %s"
                    await cur.execute(members_query, (community_id,))
                    members = await cur.fetchall()
                    member_ids = set([str(m[0]) for m in members])

                    # 5. Broadcast
                    response_payload = json.dumps({
                        "type": "new_chat_message",
                        "payload": {
                            "id": message_id,
                            "community_uuid": target_uuid,
                            "context": "community",
                            "message": message_text,
                            "sender_id": user_id,
                            "sender_username": user_info['username'],
                            "sender_profile_picture": user_info['profile_picture'],
                            "sender_role": user_info['role'],
                            "created_at": created_at,
                            "type": "text",
                            "status": "active",
                            "reply_to_id": reply_to_id,
                            "reply_message": reply_data.get('message'),
                            "reply_sender_username": reply_data.get('sender_username')
                        }
                    })

                    for connected_uid, sessions in connected_clients.items():
                        if connected_uid in member_ids:
                            for ws in sessions.values():
                                try: await ws.send(response_payload)
                                except: pass 

    except Exception as e:
        logger.error(f"Error procesando mensaje de chat: {e}")

async def handle_browser_client(websocket):
    user_id = None
    session_id = None
    user_role = None
    user_data_cache = {} 
    remote_ip = websocket.remote_address[0]
    
    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError:
                continue
                
            msg_type = data.get('type')

            if msg_type == 'auth':
                if not check_rate_limit(remote_ip):
                    logger.warning(f"IP {remote_ip} bloqueada temporalmente.")
                    await websocket.send(json.dumps({"type": "auth_error_permanent", "msg": "Too many attempts."}))
                    return

                token = data.get('token')
                if not token: continue

                auth_data = await verify_token_and_get_session(token)
                
                if auth_data:
                    user_id, session_id, user_role, username, pfp = auth_data
                    
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                    connected_clients[user_id][session_id] = websocket
                    
                    user_data_cache = {
                        'role': user_role,
                        'username': username,
                        'profile_picture': pfp
                    }

                    if user_role in ['founder', 'administrator']:
                        admin_sessions.add(websocket)
                    
                    logger.info(f"Usuario conectado: ID {user_id} | Rol: {user_role}")
                    
                    await websocket.send(json.dumps({"type": "connected"}))
                    await broadcast_user_status(user_id, 'online')
                else:
                    record_failed_attempt(remote_ip)
                    await websocket.send(json.dumps({"type": "auth_error_permanent", "msg": "Token invalid"}))
                    return 

            elif msg_type == 'get_online_users':
                if user_role in ['founder', 'administrator']:
                    response = json.dumps({
                        "type": "online_users_list", 
                        "payload": list(connected_clients.keys())
                    })
                    await websocket.send(response)
            
            # --- CHAT MESSAGE ---
            elif msg_type == 'chat_message':
                if user_id: 
                    # El payload ahora incluye 'context' y 'target_uuid'
                    await handle_chat_message(user_id, user_data_cache, data.get('payload', {}))

    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        logger.error(f"Error socket cliente: {e}")
    finally:
        if user_id and session_id:
            if user_id in connected_clients and session_id in connected_clients[user_id]:
                del connected_clients[user_id][session_id]
                if not connected_clients[user_id]:
                    del connected_clients[user_id]
                    await broadcast_user_status(user_id, 'offline')
        
        if websocket in admin_sessions:
            admin_sessions.discard(websocket)

async def get_community_members(community_id):
    if not db_pool: return []
    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("SELECT user_id FROM community_members WHERE community_id = %s", (community_id,))
                rows = await cur.fetchall()
                return [str(r[0]) for r in rows]
    except Exception as e:
        logger.error(f"Error fetching community members: {e}")
        return []

async def handle_php_notification(reader, writer):
    try:
        data = await reader.read(8192) 
        msg = data.decode()
        if not msg: return
        
        try:
            full_payload = json.loads(msg)
        except json.JSONDecodeError:
            return

        target = str(full_payload.get('target_id'))
        msg_type = full_payload.get('type')
        payload_data = full_payload.get('payload', {})
        
        # --- CASO 1: MENSAJE PRIVADO (Directo) ---
        if msg_type == 'private_message':
            # Notificar al receptor (Target)
            client_message = json.dumps({
                "type": "private_message",
                "payload": payload_data.get('message_data')
            })
            
            # Enviar al receptor
            if target in connected_clients:
                for ws in connected_clients[target].values():
                    try: await ws.send(client_message)
                    except: pass
            
            # Enviar al remitente (Sender) para sincronización
            sender_id = str(payload_data.get('sender_id'))
            if sender_id and sender_id in connected_clients:
                for ws in connected_clients[sender_id].values():
                    try: await ws.send(client_message)
                    except: pass

        # --- CASO 2: BROADCAST DE COMUNIDAD ---
        elif target == 'community_broadcast':
            community_id = payload_data.get('community_id')
            message_data = payload_data.get('message_data') 
            
            client_message = json.dumps({
                "type": msg_type, # 'new_chat_message' o 'message_update'
                "payload": message_data
            })
            
            member_ids = await get_community_members(community_id)
            for uid in member_ids:
                if uid in connected_clients:
                    for ws in connected_clients[uid].values():
                        try: await ws.send(client_message)
                        except: pass

        # --- CASO 3: MENSAJE GLOBAL ---
        elif target == 'global':
            client_message = json.dumps(full_payload)
            for uid, sessions in connected_clients.items():
                for ws in sessions.values():
                    try: await ws.send(client_message)
                    except: pass

        # --- CASO 4: MENSAJE DIRECTO DE SISTEMA ---
        elif target in connected_clients:
            client_message = json.dumps(full_payload)
            for ws in connected_clients[target].values():
                try: await ws.send(client_message)
                except: pass

    except Exception as e:
        logger.error(f"Error puente PHP: {e}")
    finally:
        writer.close()
        await writer.wait_closed()

async def main():
    await init_db_pool()
    logger.info("=== Servidor Aurora Iniciado (Híbrido: Chat + DMs) ===")
    
    ws_server = await websockets.serve(handle_browser_client, "0.0.0.0", 8080)
    php_bridge = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
    
    await asyncio.Future() 

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("🛑 Servidor detenido.")
    except Exception as e:
        logger.critical(f"🔥 Error fatal: {e}")