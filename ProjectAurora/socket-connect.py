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
waiting_queue = [] # Lista de websockets esperando turno [ws1, ws2, ...]

# Configuración BD
DB_CONFIG = {
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASS'),
    'host': os.getenv('DB_HOST'),
    'database': os.getenv('DB_NAME'),
    'raise_on_warnings': True
}

def log(message):
    timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {message}")

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
    """Retorna (active_sessions, max_users)"""
    try:
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor()
        
        # 1. Obtener Max Users
        cursor.execute("SELECT max_concurrent_users FROM server_config WHERE id = 1")
        row_conf = cursor.fetchone()
        max_users = int(row_conf[0]) if row_conf else 500
        
        # 2. Obtener Sesiones Activas
        cursor.execute("SELECT COUNT(*) FROM user_sessions")
        row_count = cursor.fetchone()
        active_count = int(row_count[0]) if row_count else 0
        
        cursor.close()
        cnx.close()
        return active_count, max_users
    except Exception as e:
        log(f"[DB Error Capacity] {e}")
        return 9999, 500 # Fail-safe para no bloquear

# --- GESTIÓN DE COLA ---
async def check_queue_status():
    """Bucle infinito que procesa la cola cada 5 segundos"""
    while True:
        try:
            if waiting_queue:
                active, max_users = get_server_capacity()
                # Espacios disponibles reales (considerando que la BD puede tener sesiones viejas)
                available_slots = max_users - active
                
                if available_slots > 0:
                    log(f"[QUEUE] Espacios libres: {available_slots}. Procesando cola...")
                    
                    # Liberar tantos usuarios como espacios haya
                    for _ in range(available_slots):
                        if not waiting_queue: break
                        
                        ws = waiting_queue.pop(0)
                        try:
                            await ws.send(json.dumps({"type": "access_granted"}))
                            log("[QUEUE] ✅ Acceso concedido a un usuario.")
                        except:
                            # Si el socket estaba muerto, simplemente pasamos al siguiente
                            pass
                
                # Notificar posición a los que siguen esperando
                for index, ws in enumerate(waiting_queue):
                    try:
                        position = index + 1
                        await ws.send(json.dumps({
                            "type": "queue_update", 
                            "position": position,
                            "total_waiting": len(waiting_queue)
                        }))
                    except:
                        # Se limpiará en el evento de desconexión
                        pass
                        
        except Exception as e:
            log(f"[QUEUE LOOP ERROR] {e}")
            
        await asyncio.sleep(3) # Revisar cada 3 segundos

# --- WEBSOCKET HANDLERS ---
async def broadcast_user_status(user_id, status, timestamp=None):
    if not admin_sessions: return
    message = json.dumps({"type": "user_status_change", "payload": {"user_id": user_id, "status": status, "timestamp": timestamp}})
    dead_sockets = set()
    for ws in admin_sessions:
        try: await ws.send(message)
        except: dead_sockets.add(ws)
    admin_sessions.difference_update(dead_sockets)

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

            # --- AUTENTICACIÓN ---
            if msg_type == 'auth':
                token = data.get('token')
                auth_data = verify_token_and_get_session(token)
                if auth_data:
                    user_id, session_id, user_role = auth_data
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                        await broadcast_user_status(user_id, "online")
                    connected_clients[user_id][session_id] = websocket
                    if user_role in ['founder', 'administrator']:
                        admin_sessions.add(websocket)
                    await websocket.send(json.dumps({"type": "connected"}))
                else:
                    await websocket.send(json.dumps({"type": "error", "msg": "Token inválido"}))

            # --- UNIRSE A LA COLA (Público o Logueado) ---
            elif msg_type == 'join_queue':
                if websocket not in waiting_queue:
                    waiting_queue.append(websocket)
                    is_in_queue = True
                    pos = len(waiting_queue)
                    log(f"[QUEUE] Nuevo usuario en cola. Posición: {pos}")
                    await websocket.send(json.dumps({
                        "type": "queue_update", 
                        "position": pos,
                        "total_waiting": pos
                    }))

            # --- ADMIN ---
            elif msg_type == 'get_online_users':
                if user_role in ['founder', 'administrator']:
                    await websocket.send(json.dumps({"type": "online_users_list", "payload": list(connected_clients.keys())}))

    except websockets.exceptions.ConnectionClosed: pass
    except Exception as e: log(f"[WS ERROR] {e}")
    finally:
        # Limpieza General
        if is_in_queue and websocket in waiting_queue:
            waiting_queue.remove(websocket)
            
        if user_id and session_id and user_id in connected_clients:
            if session_id in connected_clients[user_id]:
                del connected_clients[user_id][session_id]
            if not connected_clients[user_id]:
                del connected_clients[user_id]
                await broadcast_user_status(user_id, "offline", datetime.datetime.now().isoformat())
        
        if websocket in admin_sessions: admin_sessions.remove(websocket)

async def handle_php_notification(reader, writer):
    try:
        data = await reader.read(2048)
        message = data.decode()
        if not message: return
        payload = json.loads(message)
        target_id = str(payload.get('target_id'))
        
        # BROADCAST GLOBAL
        if target_id == 'global':
            log(f"[PHP-Bridge] 🌍 Broadcast GLOBAL: {payload.get('type')}")
            # Notificar a clientes normales
            for uid, sessions in list(connected_clients.items()):
                for sid, ws in list(sessions.items()):
                    try: await ws.send(json.dumps(payload))
                    except: pass
            # Notificar a usuarios en cola también (importante si se quita mantenimiento)
            for ws in waiting_queue:
                try: await ws.send(json.dumps(payload))
                except: pass

        elif target_id in connected_clients:
            # (Lógica de notificaciones individuales igual que antes)
            user_sessions = connected_clients[target_id]
            for ws in user_sessions.values():
                try: await ws.send(json.dumps(payload))
                except: pass
            
    except Exception as e: log(f"[PHP-Bridge ERROR] {e}")
    finally: writer.close(); await writer.wait_closed()

async def start_servers():
    log("=== Iniciando servidores Project Aurora (con Cola) ===")
    # Iniciar tarea de cola en segundo plano
    asyncio.create_task(check_queue_status())
    
    async with websockets.serve(handle_browser_client, "0.0.0.0", 8080):
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        async with server: await server.serve_forever()

if __name__ == "__main__":
    try: asyncio.run(start_servers())
    except KeyboardInterrupt: log("Servidor detenido.")