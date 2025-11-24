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
    # Este mensaje es interno para debug, puede mantener su estructura o simplificarse
    # Lo dejamos simple para evitar confusiones en el cliente si decidiera leerlo
    payload = json.dumps({"type": "server_log_debug", "log": log_line})
    dead_sockets = set()
    for ws in admin_sessions:
        try: asyncio.create_task(ws.send(payload))
        except: dead_sockets.add(ws)
    admin_sessions.difference_update(dead_sockets)

# --- FUNCIÓN PARA AVISAR CAMBIO DE ESTADO ---
async def broadcast_user_status(user_id, status):
    """
    Envía un mensaje DIRECTO (sin envoltura extra) a los admins.
    El JS espera: { type: 'user_status_change', payload: {...} }
    """
    if not admin_sessions:
        return

    timestamp = datetime.datetime.now().isoformat()
    
    # CORRECCIÓN: Estructura plana, sin 'socket-message' ni 'detail'
    message = json.dumps({
        "type": "user_status_change",
        "payload": {
            "user_id": user_id,
            "status": status, # 'online' o 'offline'
            "timestamp": timestamp
        }
    })

    dead_sockets = set()
    for ws in admin_sessions:
        try:
            await ws.send(message)
        except:
            dead_sockets.add(ws)
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
    except mysql.connector.Error as e:
        log(f"[DB Error] {e}")
        return None

async def handle_browser_client(websocket):
    user_id = None
    session_id = None
    user_role = None
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
                    
                    log(f"✅ Usuario {user_id} ({user_role}) se ha unido. Sesión: {session_id}")
                    
                    await websocket.send(json.dumps({"type": "connected"}))

                    # Avisar a los admins que este usuario entró (Estado: Online)
                    await broadcast_user_status(user_id, 'online')

                else:
                    log(f"❌ Intento de conexión fallido: Token inválido o expirado.")
                    await websocket.send(json.dumps({"type": "error", "msg": "Auth failed"}))

            elif msg_type == 'get_online_users':
                if user_role in ['founder', 'administrator']:
                    # CORRECCIÓN: Enviar estructura plana que espera el JS
                    response = json.dumps({
                        "type": "online_users_list", 
                        "payload": list(connected_clients.keys())
                    })
                    await websocket.send(response)

    except websockets.exceptions.ConnectionClosed: 
        pass
    except Exception as e: 
        log(f"⚠️ Error en cliente socket: {e}")
    finally:
        if user_id and session_id:
            log(f"👋 Usuario {user_id} se ha desconectado. Sesión: {session_id}")
            
            if user_id in connected_clients:
                if session_id in connected_clients[user_id]: 
                    del connected_clients[user_id][session_id]
                
                # Si el usuario ya no tiene ninguna sesión abierta
                if not connected_clients[user_id]: 
                    del connected_clients[user_id]
                    # Avisar a los admins que salió totalmente (Estado: Offline)
                    await broadcast_user_status(user_id, 'offline')
        
        if websocket in admin_sessions: admin_sessions.remove(websocket)

async def handle_php_notification(reader, writer):
    try:
        data = await reader.read(2048)
        msg = data.decode()
        if not msg: return
        payload = json.loads(msg)
        target = str(payload.get('target_id'))
        
        # CORRECCIÓN: No envolvemos en 'socket-message'. 
        # PHP ya envía la estructura {type: '...', payload: ...} correcta.
        client_message = json.dumps(payload)
        
        if target == 'global':
            log(f"📢 Enviando notificación GLOBAL: {payload.get('type')}")
            for uid, sessions in list(connected_clients.items()):
                for sid, ws in list(sessions.items()):
                    try: await ws.send(client_message)
                    except: pass

        elif target in connected_clients:
            log(f"📨 Enviando notificación a Usuario {target}: {payload.get('type')}")
            for ws in connected_clients[target].values():
                try: await ws.send(client_message)
                except: pass
    except Exception as e: log(f"[Bridge Error] {e}")
    finally: writer.close(); await writer.wait_closed()

async def start_servers():
    log("=== Servidor Aurora Iniciado (Estructura JSON Corregida) ===")
    async with websockets.serve(handle_browser_client, "0.0.0.0", 8080):
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        async with server: await server.serve_forever()

if __name__ == "__main__":
    try: asyncio.run(start_servers())
    except KeyboardInterrupt: pass