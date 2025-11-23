import asyncio
import websockets
import json
import mysql.connector
import datetime
import os
from dotenv import load_dotenv

# Cargar variables de entorno
load_dotenv()

# Diccionario para guardar conexiones activas:
# Estructura: { user_id: { session_id: websocket_object } }
connected_clients = {}

# Conjunto para rastrear sesiones de administradores
admin_sessions = set()

# Configuración de BD usando variables de entorno
DB_CONFIG = {
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASS'),
    'host': os.getenv('DB_HOST'),
    'database': os.getenv('DB_NAME'),
    'raise_on_warnings': True
}

# --- HELPER DE LOGS ---
def log(message):
    """Imprime mensajes con timestamp para mejor depuración"""
    timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {message}")

def verify_token_and_get_session(token):
    """
    Verifica el token y retorna (user_id, session_id, role).
    """
    try:
        # [NUEVO LOG] Aquí es donde realmente intenta tocar la base de datos
        log("[DB] ⏳ Intentando conectar a la Base de Datos para validar token...") 
        
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor()

        # Obtenemos user_id, session_id y el ROL del usuario haciendo JOIN
        query = """
            SELECT t.user_id, t.session_id, u.role 
            FROM ws_auth_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = %s AND t.expires_at > NOW()
        """
        cursor.execute(query, (token,))
        row = cursor.fetchone()
        
        result = None
        if row:
            result = (str(row[0]), str(row[1]), str(row[2]))
        else:
            log(f"[DB] Token rechazado (No encontrado o expirado): {token[:10]}...")

        cursor.close()
        cnx.close()
        return result

    except mysql.connector.Error as err:
        log(f"[DB ERROR] 💥 Falló la conexión: {err}")
        return None

async def broadcast_user_status(user_id, status, timestamp=None):
    if not admin_sessions:
        return

    log(f"[BROADCAST] Usuario {user_id} ahora está {status}")

    message = json.dumps({
        "type": "user_status_change",
        "payload": {
            "user_id": user_id,
            "status": status,
            "timestamp": timestamp
        }
    })

    dead_sockets = set()
    for ws in admin_sessions:
        try:
            await ws.send(message)
        except (websockets.exceptions.ConnectionClosed, Exception):
            dead_sockets.add(ws)
    
    admin_sessions.difference_update(dead_sockets)

async def handle_browser_client(websocket):
    """Maneja la conexión con el navegador"""
    user_id = None
    session_id = None
    user_role = None
    
    # client_ip = websocket.remote_address[0] # Descomentar si quieres ver IPs

    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError:
                continue
            
            msg_type = data.get('type')

            # --- AUTENTICACIÓN ---
            if msg_type == 'auth':
                token = data.get('token')
                req_id = data.get('request_id', 'unknown')
                
                if not token:
                    log(f"[WS] Error Auth: Token no recibido (ReqID: {req_id})")
                    await websocket.send(json.dumps({"type": "error", "msg": "Token requerido"}))
                    return

                auth_data = verify_token_and_get_session(token)

                if auth_data:
                    user_id, session_id, user_role = auth_data
                    
                    # Inicializar diccionario del usuario si no existe
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                        # SI ES LA PRIMERA SESIÓN, AVISAR QUE ESTÁ ONLINE
                        await broadcast_user_status(user_id, "online")
                    
                    connected_clients[user_id][session_id] = websocket
                    
                    if user_role in ['founder', 'administrator']:
                        admin_sessions.add(websocket)

                    log(f"[WS] AUTH OK ✅ | User: {user_id} ({user_role}) | Session: {session_id} | ReqID: {req_id}")
                    await websocket.send(json.dumps({"type": "connected", "msg": "Conectado"}))
                else:
                    log(f"[WS] AUTH FAIL ❌ | Token inválido | ReqID: {req_id}")
                    await websocket.send(json.dumps({"type": "error", "msg": "Token inválido"}))
                    await websocket.close()
                    return

            # --- COMANDOS DE ADMIN ---
            elif msg_type == 'get_online_users':
                if user_id and user_role in ['founder', 'administrator']:
                    online_ids = list(connected_clients.keys())
                    log(f"[WS] Admin {user_id} solicitó lista online. Total: {len(online_ids)}")
                    await websocket.send(json.dumps({
                        "type": "online_users_list",
                        "payload": online_ids
                    }))

    except websockets.exceptions.ConnectionClosed:
        pass 
    except Exception as e:
        log(f"[WS ERROR] {e}")
    finally:
        # Limpieza al desconectar
        if user_id and session_id and user_id in connected_clients:
            if session_id in connected_clients[user_id]:
                del connected_clients[user_id][session_id]
            
            if not connected_clients[user_id]:
                del connected_clients[user_id]
                now_iso = datetime.datetime.now().isoformat()
                await broadcast_user_status(user_id, "offline", now_iso)
                log(f"[WS] Usuario {user_id} desconectado totalmente (Offline)")
            else:
                # Aún tiene otras pestañas abiertas
                # log(f"[WS] Cierre sesión {session_id} de usuario {user_id} (Mantiene otras activas)")
                pass

        if websocket in admin_sessions:
            admin_sessions.remove(websocket)

async def handle_php_notification(reader, writer):
    """Escucha comandos desde PHP (Localhost Only)"""
    try:
        data = await reader.read(2048)
        message = data.decode()
        
        if not message:
            return

        payload = json.loads(message)
        target_id = str(payload.get('target_id'))
        msg_type = payload.get('type')
        
        log(f"[PHP-Bridge] 📩 Recibido comando '{msg_type}' para User {target_id}")
        
        if target_id in connected_clients:
            user_sessions = connected_clients[target_id]
            
            # CASO 1: Logout forzado
            if msg_type == 'force_logout':
                target_session = payload.get('payload', {}).get('target_session_id')
                
                if target_session:
                    if target_session in user_sessions:
                        ws = user_sessions[target_session]
                        try:
                            log(f"[PHP-Bridge] 🚪 Cerrando sesión específica {target_session} de User {target_id}")
                            await ws.send(json.dumps({'type': 'force_logout', 'message': 'Sesión cerrada remotamente.'}))
                        except Exception as e:
                            log(f"[PHP-Bridge] Error enviando logout: {e}")
                else:
                    reason = payload.get('payload', {}).get('reason', 'unknown')
                    log(f"[PHP-Bridge] 🚫 Expulsión GLOBAL User {target_id}. Razón: {reason}")
                    for sess_id, ws in list(user_sessions.items()):
                        try:
                            await ws.send(json.dumps({
                                'type': 'force_logout', 
                                'message': 'Tu cuenta ha sido suspendida o modificada.',
                                'reason': reason 
                            }))
                        except Exception as e:
                            log(f"[PHP-Bridge] Error en broadcast logout: {e}")

            # CASO 2: Logout otros
            elif msg_type == 'force_logout_others':
                exclude_session = payload.get('payload', {}).get('exclude_session_id')
                log(f"[PHP-Bridge] Cerrando otras sesiones de {target_id} excepto {exclude_session}")
                for sess_id, ws in list(user_sessions.items()):
                    if sess_id != exclude_session:
                        try:
                            await ws.send(json.dumps({'type': 'force_logout', 'message': 'Sesión cerrada desde otro dispositivo.'}))
                        except:
                            pass

            # CASO 3: Notificación normal
            else:
                count = 0
                for sess_id, ws in user_sessions.items():
                    try:
                        await ws.send(json.dumps(payload))
                        count += 1
                    except:
                        pass
                log(f"[PHP-Bridge] ✅ Notificación enviada a {count} sesiones activas de User {target_id}")
        else:
            log(f"[PHP-Bridge] User {target_id} no conectado. Ignorando mensaje.")
            
    except Exception as e:
        log(f"[PHP-Bridge ERROR] {e}")
    finally:
        writer.close()
        await writer.wait_closed()

async def start_servers():
    log("=== Iniciando servidores Project Aurora ===")
    async with websockets.serve(handle_browser_client, "0.0.0.0", 8080):
        log("✅ WS Server escuchando en 0.0.0.0:8080")
        
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        log("✅ PHP Listener en tcp://127.0.0.1:8081")
        async with server:
            await server.serve_forever()

if __name__ == "__main__":
    try:
        asyncio.run(start_servers())
    except KeyboardInterrupt:
        log("Servidor detenido por el usuario.")