import asyncio
import websockets
import json
import mysql.connector
import datetime

# Diccionario para guardar conexiones activas:
# Estructura: { user_id: { session_id: websocket_object } }
connected_clients = {}

# Conjunto para rastrear sesiones de administradores (para enviarles notificaciones de estado)
admin_sessions = set()

DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'project_aurora_db',
    'raise_on_warnings': True
}

def verify_token_and_get_session(token):
    """
    Verifica el token y retorna (user_id, session_id, role).
    """
    try:
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
            # Convertimos a string para consistencia
            result = (str(row[0]), str(row[1]), str(row[2]))
        
        cursor.close()
        cnx.close()
        return result

    except mysql.connector.Error as err:
        print(f"[DB ERROR] {err}")
        return None

async def broadcast_user_status(user_id, status, timestamp=None):
    """
    Envía una señal a todos los administradores conectados informando
    que el usuario 'user_id' ha cambiado su estado (online/offline).
    """
    if not admin_sessions:
        return

    message = json.dumps({
        "type": "user_status_change",
        "payload": {
            "user_id": user_id,
            "status": status, # 'online' o 'offline'
            "timestamp": timestamp # Solo relevante para offline (hora de desconexión ISO)
        }
    })

    # Recopilar sockets de admins desconectados para limpiar
    dead_sockets = set()

    for ws in admin_sessions:
        try:
            await ws.send(message)
        except websockets.exceptions.ConnectionClosed:
            dead_sockets.add(ws)
        except Exception:
            dead_sockets.add(ws)
    
    # Limpiar sesiones muertas del set de admins
    admin_sessions.difference_update(dead_sockets)

async def handle_browser_client(websocket):
    """Maneja la conexión con el navegador"""
    user_id = None
    session_id = None
    user_role = None
    
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
                
                if not token:
                    await websocket.send(json.dumps({"type": "error", "msg": "Token requerido"}))
                    return

                auth_data = verify_token_and_get_session(token)

                if auth_data:
                    user_id, session_id, user_role = auth_data
                    
                    # Inicializar diccionario del usuario si no existe
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                        # SI ES LA PRIMERA SESIÓN DE ESTE USUARIO, AVISAR QUE ESTÁ ONLINE
                        await broadcast_user_status(user_id, "online")
                    
                    # Guardar conexión mapeada por session_id
                    connected_clients[user_id][session_id] = websocket
                    
                    # Si es admin, añadirlo a la lista de listeners privilegiados
                    if user_role in ['founder', 'administrator']:
                        admin_sessions.add(websocket)

                    print(f"[WS] Auth OK: User {user_id} ({user_role}) Session {session_id}")
                    await websocket.send(json.dumps({"type": "connected", "msg": "Conectado"}))
                else:
                    print(f"[WS] Auth Failed")
                    await websocket.send(json.dumps({"type": "error", "msg": "Token inválido"}))
                    await websocket.close()
                    return

            # --- COMANDOS DE ADMIN ---
            elif msg_type == 'get_online_users':
                # Solo responder si ya está autenticado y es admin
                if user_id and user_role in ['founder', 'administrator']:
                    # Enviamos la lista de IDs que tienen al menos una sesión activa
                    online_ids = list(connected_clients.keys())
                    await websocket.send(json.dumps({
                        "type": "online_users_list",
                        "payload": online_ids
                    }))

    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        print(f"[WS] Error: {e}")
    finally:
        # Limpieza al desconectar
        if user_id and session_id and user_id in connected_clients:
            if session_id in connected_clients[user_id]:
                del connected_clients[user_id][session_id]
            
            # Si el usuario ya no tiene NINGUNA sesión activa, está OFFLINE
            if not connected_clients[user_id]:
                del connected_clients[user_id]
                # AVISAR A ADMINS QUE ESTÁ OFFLINE
                now_iso = datetime.datetime.now().isoformat()
                await broadcast_user_status(user_id, "offline", now_iso)

            print(f"[WS] Desconectado: User {user_id} Session {session_id}")

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
        
        if target_id in connected_clients:
            user_sessions = connected_clients[target_id]
            
            # CASO 1: Logout forzado (Global o Específico)
            if msg_type == 'force_logout':
                target_session = payload.get('payload', {}).get('target_session_id')
                
                # A) Si hay un target_session_id, cerramos SOLO esa sesión (Revocar dispositivo)
                if target_session:
                    if target_session in user_sessions:
                        ws = user_sessions[target_session]
                        try:
                            await ws.send(json.dumps({'type': 'force_logout', 'message': 'Sesión cerrada remotamente.'}))
                        except Exception as e:
                            print(f"[PHP->WS] Error enviando logout: {e}")
                
                # B) Si NO hay target_session_id, es un logout GLOBAL (Admin Ban/Suspend)
                else:
                    print(f"[PHP->WS] Ejecutando expulsión global para usuario {target_id}")
                    # Recorremos TODAS las sesiones activas de ese usuario
                    for sess_id, ws in list(user_sessions.items()):
                        try:
                            await ws.send(json.dumps({
                                'type': 'force_logout', 
                                'message': 'Tu cuenta ha sido suspendida o modificada.'
                            }))
                        except Exception as e:
                            print(f"[PHP->WS] Error en broadcast logout: {e}")

            # CASO 2: Logout forzado de TODAS las sesiones EXCEPTO una
            elif msg_type == 'force_logout_others':
                exclude_session = payload.get('payload', {}).get('exclude_session_id')
                
                for sess_id, ws in list(user_sessions.items()):
                    if sess_id != exclude_session:
                        try:
                            await ws.send(json.dumps({'type': 'force_logout', 'message': 'Sesión cerrada desde otro dispositivo.'}))
                        except:
                            pass

            # CASO 3: Notificación normal (enviar a todas las sesiones del usuario)
            else:
                for sess_id, ws in user_sessions.items():
                    try:
                        await ws.send(json.dumps(payload))
                    except:
                        pass
                print(f"[PHP->WS] Notificación {msg_type} enviada a User {target_id}")
        else:
            print(f"[PHP->WS] Usuario {target_id} no conectado.")
            
    except Exception as e:
        print(f"[PHP->WS] Error procesando mensaje: {e}")
    finally:
        writer.close()
        await writer.wait_closed()

async def start_servers():
    print("Iniciando servidores Project Aurora...")
    # 0.0.0.0 permite conexiones desde otras IPs (red local)
    async with websockets.serve(handle_browser_client, "0.0.0.0", 8080):
        print("✅ WS Server escuchando en 0.0.0.0:8080")
        
        # Listener interno para PHP
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        print("✅ PHP Listener en tcp://127.0.0.1:8081")
        async with server:
            await server.serve_forever()

if __name__ == "__main__":
    try:
        asyncio.run(start_servers())
    except KeyboardInterrupt:
        print("\nServidor detenido.")