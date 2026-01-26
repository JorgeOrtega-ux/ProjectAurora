import asyncio
import websockets
import mysql.connector
import os
import json
import logging
from urllib.parse import parse_qs, urlparse
from dotenv import load_dotenv

# Configuración de Logs
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# Estructura de Usuarios Logueados: { user_id: { session_id: set(sockets) } }
connected_users = {}

# Estructura de Invitados: set(sockets)
connected_guests = set()

WS_PORT = 8765
INTERNAL_CMD_PORT = 8766

def get_db_connection():
    try:
        return mysql.connector.connect(
            host=os.getenv('DB_HOST', 'localhost'),
            user=os.getenv('DB_USER', 'root'),
            password=os.getenv('DB_PASS', ''),
            database=os.getenv('DB_NAME', 'project_aurora_db')
        )
    except mysql.connector.Error as err:
        logging.error(f"Error conectando a BD: {err}")
        return None

def validate_and_consume_token(path):
    try:
        if not path: return None, None
        parsed_url = urlparse(path)
        params = parse_qs(parsed_url.query)
        
        # 1. Verificar si es invitado
        type_param = params.get('type')
        if type_param and type_param[0] == 'guest':
            return 'GUEST', 'GUEST'

        # 2. Verificar usuario normal (Token)
        token_list = params.get('token')
        if not token_list: return None, None
        token = token_list[0]
    except Exception as e:
        logging.error(f"Error parseando URL: {e}")
        return None, None

    conn = get_db_connection()
    if not conn: return None, None

    try:
        cursor = conn.cursor(dictionary=True)
        # Obtenemos user_id y session_id
        query = "SELECT user_id, session_id FROM ws_auth_tokens WHERE token = %s AND expires_at > NOW()"
        cursor.execute(query, (token,))
        record = cursor.fetchone()
        
        if not record:
            return None, None
            
        user_id = record['user_id']
        session_id = record['session_id'] or 0
        
        delete_query = "DELETE FROM ws_auth_tokens WHERE token = %s"
        cursor.execute(delete_query, (token,))
        conn.commit()
        
        return user_id, session_id

    except Exception as e:
        logging.error(f"Error BD: {e}")
        return None, None
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()

async def ws_handler(websocket):
    path = ""
    try:
        if hasattr(websocket, 'request') and websocket.request: path = websocket.request.path
        elif hasattr(websocket, 'path'): path = websocket.path
    except: pass

    user_id, session_id = validate_and_consume_token(path)

    if not user_id:
        await websocket.close(code=1008, reason="Authentication Failed")
        return

    # === CASO INVITADO ===
    if user_id == 'GUEST':
        connected_guests.add(websocket)
        logging.info(f"Invitado conectado. Total invitados: {len(connected_guests)}")
        
        try:
            await websocket.send(json.dumps({"type": "connection_established", "mode": "guest"}))
            # Loop de lectura (keep-alive). Los invitados NO pueden enviar comandos.
            async for message in websocket: pass 
        except: pass
        finally:
            if websocket in connected_guests:
                connected_guests.remove(websocket)
            logging.info("Invitado desconectado.")
        return

    # === CASO USUARIO REGISTRADO ===
    if user_id not in connected_users: connected_users[user_id] = {}
    if session_id not in connected_users[user_id]: connected_users[user_id][session_id] = set()
    
    connected_users[user_id][session_id].add(websocket)
    logging.info(f"User {user_id} (Session {session_id}) conectado.")

    try:
        await websocket.send(json.dumps({"type": "connection_established", "mode": "user"}))
        async for message in websocket: pass 
    except: pass
    finally:
        if user_id in connected_users and session_id in connected_users[user_id]:
            if websocket in connected_users[user_id][session_id]:
                connected_users[user_id][session_id].remove(websocket)
            if not connected_users[user_id][session_id]:
                del connected_users[user_id][session_id]
            if not connected_users[user_id]:
                del connected_users[user_id]
        logging.info(f"User {user_id} desconectado.")

# === SERVIDOR INTERNO PARA COMANDOS PHP ===
async def internal_command_handler(reader, writer):
    try:
        data = await reader.read(100)
        message = data.decode().strip()
        parts = message.split(':')
        command = parts[0]

        if command == 'KICK_SESSION' and len(parts) == 3:
            await kick_session(int(parts[1]), int(parts[2]))
        
        elif command == 'KICK_ALL' and len(parts) == 2:
            await kick_all_sessions(int(parts[1]))
            
        elif command == 'BROADCAST_ALL' and len(parts) == 2:
            msg_type = parts[1] # Ej: MAINTENANCE_START
            await broadcast_to_everyone(msg_type)

        writer.write(b"OK")
        await writer.drain()
    except Exception as e:
        logging.error(f"Error CMD interno: {e}")
    finally:
        writer.close()

async def kick_session(user_id, session_id):
    if user_id in connected_users and session_id in connected_users[user_id]:
        payload = json.dumps({"type": "force_logout", "reason": "Sesión revocada"})
        for ws in list(connected_users[user_id][session_id]):
            try:
                await ws.send(payload)
                await ws.close()
            except: pass
        logging.info(f"KICK: User {user_id}, Session {session_id}")

async def kick_all_sessions(user_id):
    if user_id in connected_users:
        payload = json.dumps({"type": "force_logout", "reason": "Cuenta cerrada"})
        for sid, sockets in connected_users[user_id].items():
            for ws in list(sockets):
                try:
                    await ws.send(payload)
                    await ws.close()
                except: pass
        logging.info(f"KICK ALL: User {user_id}")

async def broadcast_to_everyone(msg_type):
    # Definir el payload basado en el tipo
    payload = {}
    if msg_type == 'MAINTENANCE_START':
        payload = json.dumps({"type": "maintenance_start", "message": "El sistema ha entrado en mantenimiento."})
    else:
        payload = json.dumps({"type": "info", "message": "Anuncio del sistema"})

    # 1. Enviar a Usuarios Registrados
    user_count = 0
    for uid, sessions in connected_users.items():
        for sid, sockets in sessions.items():
            for ws in list(sockets):
                try:
                    await ws.send(payload)
                    user_count += 1
                except: pass
    
    # 2. Enviar a Invitados
    guest_count = 0
    for ws in list(connected_guests):
        try:
            await ws.send(payload)
            guest_count += 1
        except: pass

    logging.info(f"BROADCAST ({msg_type}): Enviado a {user_count} usuarios y {guest_count} invitados.")

async def main():
    logging.info(f"🚀 WS: 0.0.0.0:{WS_PORT} | 🔧 CMD: 127.0.0.1:{INTERNAL_CMD_PORT}")
    
    # 1. Iniciar servidor WebSocket
    async with websockets.serve(ws_handler, "0.0.0.0", WS_PORT):
        
        # 2. Iniciar servidor de Comandos Internos
        server = await asyncio.start_server(internal_command_handler, '127.0.0.1', INTERNAL_CMD_PORT)
        
        # 3. Mantener ambos corriendo
        async with server:
            await server.serve_forever()

if __name__ == "__main__":
    try: asyncio.run(main())
    except KeyboardInterrupt: pass