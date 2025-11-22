import asyncio
import websockets
import json
import mysql.connector

# Diccionario para guardar conexiones activas:
# Estructura: { user_id: { session_id: websocket_object, session_id_2: ws_obj } }
connected_clients = {}

DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'project_aurora_db',
    'raise_on_warnings': True
}

def verify_token_and_get_session(token):
    """
    Verifica el token y retorna (user_id, session_id).
    """
    try:
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor()

        # Obtenemos user_id Y session_id vinculados al token
        query = "SELECT user_id, session_id FROM ws_auth_tokens WHERE token = %s AND expires_at > NOW()"
        cursor.execute(query, (token,))
        row = cursor.fetchone()
        
        result = None
        if row:
            # Convertimos a string para consistencia
            result = (str(row[0]), str(row[1]))
        
        cursor.close()
        cnx.close()
        return result

    except mysql.connector.Error as err:
        print(f"[DB ERROR] {err}")
        return None

async def handle_browser_client(websocket):
    """Maneja la conexión con el navegador"""
    user_id = None
    session_id = None
    
    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError:
                continue
            
            if data.get('type') == 'auth':
                token = data.get('token')
                
                if not token:
                    await websocket.send(json.dumps({"type": "error", "msg": "Token requerido"}))
                    return

                auth_data = verify_token_and_get_session(token)

                if auth_data:
                    user_id, session_id = auth_data
                    
                    # Inicializar diccionario del usuario si no existe
                    if user_id not in connected_clients:
                        connected_clients[user_id] = {}
                    
                    # Guardar conexión mapeada por session_id
                    connected_clients[user_id][session_id] = websocket
                    
                    print(f"[WS] Auth OK: User {user_id} (Session: {session_id})")
                    await websocket.send(json.dumps({"type": "connected", "msg": "Conectado"}))
                else:
                    print(f"[WS] Auth Failed")
                    await websocket.send(json.dumps({"type": "error", "msg": "Token inválido"}))
                    await websocket.close()
                    return
            
    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        print(f"[WS] Error: {e}")
    finally:
        # Limpieza al desconectar
        if user_id and session_id and user_id in connected_clients:
            if session_id in connected_clients[user_id]:
                del connected_clients[user_id][session_id]
            # Si el usuario ya no tiene sesiones, borrar la entrada del usuario
            if not connected_clients[user_id]:
                del connected_clients[user_id]
            print(f"[WS] Desconectado: User {user_id} (Session: {session_id})")

async def handle_php_notification(reader, writer):
    """Escucha comandos desde PHP"""
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
            
            # CASO 1: Logout forzado de UNA sesión específica
            if msg_type == 'force_logout':
                target_session = payload.get('payload', {}).get('target_session_id')
                
                if target_session and target_session in user_sessions:
                    ws = user_sessions[target_session]
                    try:
                        await ws.send(json.dumps({'type': 'force_logout', 'message': 'Sesión cerrada remotamente.'}))
                        # Opcional: cerrar el socket del lado servidor tras enviar
                        # await ws.close() 
                        print(f"[PHP->WS] Logout enviado a User {target_id} Session {target_session}")
                    except Exception as e:
                        print(f"[PHP->WS] Error enviando logout: {e}")

            # CASO 2: Logout forzado de TODAS las sesiones EXCEPTO una (la actual)
            elif msg_type == 'force_logout_others':
                exclude_session = payload.get('payload', {}).get('exclude_session_id')
                
                for sess_id, ws in list(user_sessions.items()):
                    if sess_id != exclude_session:
                        try:
                            await ws.send(json.dumps({'type': 'force_logout', 'message': 'Sesión cerrada desde otro dispositivo.'}))
                            print(f"[PHP->WS] Logout enviado a User {target_id} Session {sess_id}")
                        except:
                            pass

            # CASO 3: Notificación normal (enviar a todos los dispositivos del usuario)
            else:
                for sess_id, ws in user_sessions.items():
                    try:
                        await ws.send(json.dumps(payload))
                    except:
                        pass
                print(f"[PHP->WS] Notificación {msg_type} enviada a User {target_id} ({len(user_sessions)} dispositivos)")
        else:
            print(f"[PHP->WS] Usuario {target_id} no conectado.")
            
    except Exception as e:
        print(f"[PHP->WS] Error procesando mensaje: {e}")
    finally:
        writer.close()
        await writer.wait_closed()

async def start_servers():
    print("Iniciando servidores Project Aurora...")
    # [CORRECCIÓN] Usamos "0.0.0.0" para permitir conexiones externas (móviles, otras PC)
    async with websockets.serve(handle_browser_client, "0.0.0.0", 8080):
        print("✅ WS Server escuchando en 0.0.0.0:8080 (Accesible desde red local)")
        
        # PHP sigue conectando localmente, así que 127.0.0.1 está bien para el listener interno
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        print("✅ PHP Listener en tcp://127.0.0.1:8081")
        async with server:
            await server.serve_forever()

if __name__ == "__main__":
    try:
        asyncio.run(start_servers())
    except KeyboardInterrupt:
        print("\nServidor detenido.")