import asyncio
import websockets
import json
import mysql.connector
import datetime
import os
from dotenv import load_dotenv

# Cargar variables de entorno
load_dotenv()

# --- ESTADO GLOBAL ---
connected_clients = {} # {user_id: {session_id: ws}}
admin_sessions = set()
waiting_queue = [] # Lista de websockets esperando turno

# Configuración BD
DB_CONFIG = {
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASS'),
    'host': os.getenv('DB_HOST'),
    'database': os.getenv('DB_NAME'),
    'raise_on_warnings': True
}

def log(message):
    timestamp = datetime.datetime.now().strftime("%H:%M:%S")
    log_line = f"[{timestamp}] {message}"
    print(log_line)
    if admin_sessions:
        broadcast_log(log_line)

def broadcast_log(log_line):
    payload = json.dumps({"type": "server_log_debug", "log": log_line})
    dead_sockets = set()
    for ws in admin_sessions:
        try: asyncio.create_task(ws.send(payload))
        except: dead_sockets.add(ws)
    admin_sessions.difference_update(dead_sockets)

# --- DB HELPERS ---
def verify_token_and_get_session(token):
    try:
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor()
        query = """
            SELECT t.user_id, t.session_id, u.role 
            FROM ws_auth_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = %s AND t.expires_at > NOW()
        """
        cursor.execute(query, (token,))
        row = cursor.fetchone()
        result = None if not row else (str(row[0]), str(row[1]), str(row[2]))
        cursor.close()
        cnx.close()
        return result
    except mysql.connector.Error:
        return None

def get_server_capacity():
    try:
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor()
        
        cursor.execute("SELECT max_concurrent_users FROM server_config WHERE id = 1")
        row = cursor.fetchone()
        max_users = int(row[0]) if row else 500
        
        cursor.execute("SELECT COUNT(*) FROM user_sessions")
        row = cursor.fetchone()
        active_count = int(row[0]) if row else 0
        
        cursor.close()
        cnx.close()
        return active_count, max_users
    except Exception as e:
        log(f"[DB Error] {e}")
        return 9999, 500

# [NUEVO] Obtiene los IDs de sesión que tienen derecho a estar conectados (Top N por antigüedad)
def get_allowed_sessions(limit):
    try:
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor()
        # Obtenemos solo las 'limit' sesiones más antiguas (menor ID)
        # Es importante usar ORDER BY id ASC para respetar la antigüedad
        query = f"SELECT session_id FROM user_sessions ORDER BY id ASC LIMIT {limit}"
        cursor.execute(query)
        rows = cursor.fetchall()
        allowed = {row[0] for row in rows}
        cursor.close()
        cnx.close()
        return allowed
    except Exception as e:
        log(f"[DB Error Allowed] {e}")
        return set()

# [NUEVO] Lógica para expulsar usuarios excedentes en tiempo real
async def enforce_connection_limits():
    log("[LIMITS] Verificando límites de conexión...")
    active_count, max_users = get_server_capacity()
    
    # Solo actuamos si hay más gente conectada que el límite
    if active_count > max_users:
        allowed_sessions = get_allowed_sessions(max_users)
        log(f"[LIMITS] Exceso detectado. Permitidos: {len(allowed_sessions)} sesiones.")
        
        # Recorrer todos los clientes conectados
        for user_id, sessions in list(connected_clients.items()):
            # Si es admin/founder, a veces queremos protegerlos, pero la lógica de "rank" ya suele incluirlos si entraron primero.
            # Si quieres protección extra para admins, puedes chequear user_role aquí.
            
            for session_id, ws in list(sessions.items()):
                # Si su sesión NO está en la lista de permitidos, ¡fuera!
                if session_id not in allowed_sessions:
                    try:
                        log(f"[KICK] Expulsando sesión {session_id[:8]}... (Rango excedido)")
                        await ws.send(json.dumps({
                            "type": "force_redirect",
                            "url": "/ProjectAurora/status-page?status=server_full"
                        }))
                        # Opcional: cerrar el socket para forzar limpieza
                        # await ws.close() 
                    except:
                        pass

# --- BROADCAST STATS ---
async def broadcast_server_stats(active_db, max_users):
    if not admin_sessions: return
    queue_len = len(waiting_queue)
    real_users_in_app = max(0, active_db - queue_len)
    payload = {
        "type": "server_stats_debug",
        "stats": {
            "max_users": max_users,
            "db_total_sessions": active_db,
            "queue_length": queue_len,
            "real_users_in_app": real_users_in_app
        }
    }
    msg = json.dumps(payload)
    for ws in list(admin_sessions):
        try: asyncio.create_task(ws.send(msg))
        except: pass

# --- COLA ---
async def check_queue_status():
    while True:
        try:
            active_db, max_users = get_server_capacity()
            await broadcast_server_stats(active_db, max_users)

            if waiting_queue:
                users_playing = max(0, active_db - len(waiting_queue))
                available_slots = max_users - users_playing
                
                if available_slots > 0:
                    for _ in range(available_slots):
                        if not waiting_queue: break
                        ws = waiting_queue.pop(0)
                        try:
                            await ws.send(json.dumps({"type": "access_granted"}))
                            log(f"[QUEUE] ✅ Acceso concedido.")
                        except: pass
                
                for index, ws in enumerate(waiting_queue):
                    try:
                        await ws.send(json.dumps({
                            "type": "queue_update", 
                            "position": index + 1, 
                            "total_waiting": len(waiting_queue)
                        }))
                    except: pass
        except Exception as e:
            log(f"[QUEUE LOOP ERROR] {e}")
        await asyncio.sleep(3) 

async def handle_browser_client(websocket):
    user_id = None
    session_id = None
    user_role = None
    is_in_queue = False
    try:
        async for message in websocket:
            try: data = json.loads(message)
            except: continue
            msg_type = data.get('type')

            if msg_type == 'auth':
                token = data.get('token')
                auth_data = verify_token_and_get_session(token)
                if auth_data:
                    user_id, session_id, user_role = auth_data
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                    connected_clients[user_id][session_id] = websocket
                    
                    if user_role in ['founder', 'administrator']:
                        admin_sessions.add(websocket)
                    
                    await websocket.send(json.dumps({"type": "connected"}))
                    
                    # [NUEVO] Validar inmediatamente si entra (por si se coló antes del update)
                    await enforce_connection_limits()
                else:
                    await websocket.send(json.dumps({"type": "error", "msg": "Auth failed"}))

            elif msg_type == 'join_queue':
                if websocket not in waiting_queue:
                    waiting_queue.append(websocket)
                    is_in_queue = True
                    pos = len(waiting_queue)
                    await websocket.send(json.dumps({"type": "queue_update", "position": pos, "total_waiting": pos}))

            elif msg_type == 'get_online_users':
                if user_role in ['founder', 'administrator']:
                    await websocket.send(json.dumps({"type": "online_users_list", "payload": list(connected_clients.keys())}))

    except websockets.exceptions.ConnectionClosed: pass
    except Exception: pass
    finally:
        if is_in_queue and websocket in waiting_queue: waiting_queue.remove(websocket)
        if user_id and session_id and user_id in connected_clients:
            if session_id in connected_clients[user_id]: del connected_clients[user_id][session_id]
            if not connected_clients[user_id]: del connected_clients[user_id]
        if websocket in admin_sessions: admin_sessions.remove(websocket)

async def handle_php_notification(reader, writer):
    try:
        data = await reader.read(2048)
        msg = data.decode()
        if not msg: return
        payload = json.loads(msg)
        target = str(payload.get('target_id'))
        
        if target == 'global':
            ev_type = payload.get('type')
            
            # [NUEVO] Evento específico para rebalancear
            if ev_type == 'rebalance_connections':
                log("[ADMIN] Configuración de usuarios cambiada. Rebalanceando...")
                await enforce_connection_limits()
            
            else:
                # Broadcast normal
                for uid, sessions in list(connected_clients.items()):
                    for sid, ws in list(sessions.items()):
                        try: await ws.send(json.dumps(payload))
                        except: pass
                for ws in waiting_queue:
                    try: await ws.send(json.dumps(payload))
                    except: pass

        elif target in connected_clients:
            for ws in connected_clients[target].values():
                try: await ws.send(json.dumps(payload))
                except: pass
    except Exception as e: log(f"[Bridge Error] {e}")
    finally: writer.close(); await writer.wait_closed()

async def start_servers():
    log("=== Servidor Aurora Iniciado (Live Rebalance) ===")
    asyncio.create_task(check_queue_status())
    async with websockets.serve(handle_browser_client, "0.0.0.0", 8080):
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        async with server: await server.serve_forever()

if __name__ == "__main__":
    try: asyncio.run(start_servers())
    except KeyboardInterrupt: pass