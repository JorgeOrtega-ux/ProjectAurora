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
    payload = json.dumps({"type": "server_log_debug", "log": log_line})
    dead_sockets = set()
    for ws in admin_sessions:
        try: asyncio.create_task(ws.send(payload))
        except: dead_sockets.add(ws)
    admin_sessions.difference_update(dead_sockets)

# --- FUNCIÓN PARA AVISAR CAMBIO DE ESTADO ---
async def broadcast_user_status(user_id, status):
    if not admin_sessions:
        return

    timestamp = datetime.datetime.now().isoformat()
    
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
                    await broadcast_user_status(user_id, 'online')

                else:
                    log(f"❌ Intento de conexión fallido: Token inválido o expirado.")
                    await websocket.send(json.dumps({"type": "error", "msg": "Auth failed"}))

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
        log(f"⚠️ Error en cliente socket: {e}")
    finally:
        if user_id and session_id:
            log(f"👋 Usuario {user_id} se ha desconectado. Sesión: {session_id}")
            
            if user_id in connected_clients:
                if session_id in connected_clients[user_id]: 
                    del connected_clients[user_id][session_id]
                
                if not connected_clients[user_id]: 
                    del connected_clients[user_id]
                    await broadcast_user_status(user_id, 'offline')
        
        if websocket in admin_sessions: admin_sessions.remove(websocket)

async def handle_php_notification(reader, writer):
    try:
        data = await reader.read(2048)
        msg = data.decode()
        if not msg: return
        
        full_payload = json.loads(msg)
        target = str(full_payload.get('target_id'))
        msg_type = full_payload.get('type')
        inner_payload = full_payload.get('payload', {})
        
        client_message = json.dumps(full_payload)
        
        if target == 'global':
            log(f"📢 Enviando notificación GLOBAL: {msg_type}")
            for uid, sessions in list(connected_clients.items()):
                for sid, ws in list(sessions.items()):
                    try: await ws.send(client_message)
                    except: pass

        elif target in connected_clients:
            log(f"📨 Enviando notificación a Usuario {target}: {msg_type}")
            
            # [LÓGICA DE FILTRADO DE SESIONES]
            target_sess = inner_payload.get('target_session_id')
            exclude_sess = inner_payload.get('exclude_session_id')

            # Iteramos sobre una copia para evitar problemas si hay desconexiones
            for sess_id, ws in list(connected_clients[target].items()):
                should_send = True
                
                # 1. Si es para una sesión específica (ej: revoke_session)
                if target_sess and str(target_sess) != str(sess_id):
                    should_send = False
                
                # 2. Si es para todos MENOS esta sesión (ej: change password)
                if exclude_sess and str(exclude_sess) == str(sess_id):
                    should_send = False
                
                if should_send:
                    # Si es 'force_logout_others', transformamos el mensaje a 'force_logout'
                    # para que el JS del cliente lo entienda sin lógica extra.
                    if msg_type == 'force_logout_others':
                        new_payload = full_payload.copy()
                        new_payload['type'] = 'force_logout'
                        if 'payload' not in new_payload: new_payload['payload'] = {}
                        new_payload['payload']['reason'] = 'security_change'
                        final_msg = json.dumps(new_payload)
                    else:
                        final_msg = client_message

                    try: await ws.send(final_msg)
                    except: pass

    except Exception as e: log(f"[Bridge Error] {e}")
    finally: writer.close(); await writer.wait_closed()

async def start_servers():
    log("=== Servidor Aurora Iniciado (Filtrado de Sesiones Activo) ===")
    async with websockets.serve(handle_browser_client, "0.0.0.0", 8080):
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        async with server: await server.serve_forever()

if __name__ == "__main__":
    try: asyncio.run(start_servers())
    except KeyboardInterrupt: pass