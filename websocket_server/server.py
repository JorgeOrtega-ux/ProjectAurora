import asyncio
import websockets
import mysql.connector
import os
import json
from urllib.parse import urlparse, parse_qs
from datetime import datetime

# Configuración de Base de Datos
DB_CONFIG = {
    'user': 'root',           # Ajusta según tu .env local
    'password': '',           # Ajusta según tu .env local
    'host': 'localhost',
    'database': 'project_aurora_db'
}

# Diccionario global para gestionar salas: { 'uuid_tablero': set(websocket_conn, ...) }
rooms = {}

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

async def validate_access(token, whiteboard_uuid):
    """
    Verifica:
    1. Que el token sea válido y pertenezca a un usuario.
    2. Que ese usuario tenga permiso (dueño o público) sobre el whiteboard.
    Retorna user_id si es válido, None si no.
    """
    conn = None
    cursor = None
    user_id = None
    
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        # 1. Obtener user_id del token
        query_token = "SELECT user_id FROM ws_tokens WHERE token = %s AND expires_at > NOW()"
        cursor.execute(query_token, (token,))
        result_token = cursor.fetchone()
        
        if not result_token:
            return None
            
        user_id = result_token['user_id']
        
        # 2. Borrar token (One-Time Use)
        delete_query = "DELETE FROM ws_tokens WHERE token = %s"
        cursor.execute(delete_query, (token,))
        conn.commit()
        
        # 3. Verificar acceso al whiteboard
        # Permitir si: visibility='public' O user_id es el dueño
        query_access = "SELECT id FROM whiteboards WHERE uuid = %s AND (visibility = 'public' OR user_id = %s)"
        cursor.execute(query_access, (whiteboard_uuid, user_id))
        result_access = cursor.fetchone()
        
        if not result_access:
            print(f"Acceso denegado: Usuario {user_id} no tiene permiso en {whiteboard_uuid}")
            return None
            
    except mysql.connector.Error as err:
        print(f"Error de BD: {err}")
        return None
    finally:
        if cursor: cursor.close()
        if conn: conn.close()
        
    return user_id

async def handler(websocket):
    # 1. Obtener URL y parámetros (uuid y token)
    try:
        path = websocket.request.path
    except AttributeError:
        path = getattr(websocket, 'path', '/')

    parsed_url = urlparse(path)
    params = parse_qs(parsed_url.query)
    
    token_list = params.get('token')
    uuid_list = params.get('uuid')
    
    remote_ip = "Desconocida"
    try:
        remote_ip = websocket.remote_address[0]
    except:
        pass

    if not token_list or not uuid_list:
        print(f"Conexión rechazada: Datos incompletos. IP: {remote_ip}")
        await websocket.close(code=4001, reason="Missing params")
        return

    token = token_list[0]
    whiteboard_uuid = uuid_list[0]
    
    # 2. Validar Acceso (Auth + Permisos)
    user_id = await validate_access(token, whiteboard_uuid)
    
    if not user_id:
        print(f"Conexión rechazada: Auth fallida o sin permisos. IP: {remote_ip}")
        await websocket.close(code=4003, reason="Unauthorized")
        return

    # 3. Unir a la Sala
    if whiteboard_uuid not in rooms:
        rooms[whiteboard_uuid] = set()
    rooms[whiteboard_uuid].add(websocket)
    
    print(f"✅ Usuario {user_id} unido a sala {whiteboard_uuid}. Total: {len(rooms[whiteboard_uuid])}")
    
    try:
        # Enviar mensaje de bienvenida
        await websocket.send(json.dumps({
            "type": "system",
            "message": "Conectado a la sala en tiempo real."
        }))

        # 4. Bucle principal de mensajes (Broadcast)
        async for message in websocket:
            # Reenviar a todos los demás en la sala
            if whiteboard_uuid in rooms:
                other_clients = []
                # [CORRECCIÓN CRÍTICA] Iteración segura para evitar errores de atributo 'open'
                for ws in rooms[whiteboard_uuid]:
                    if ws == websocket:
                        continue
                    
                    is_active = False
                    try:
                        # Intenta compatibilidad con versiones nuevas (v10+) y antiguas (v8/9)
                        # Nota: En v10+ 'open' es una propiedad boolean. En versiones anteriores 'closed' existe.
                        if hasattr(ws, 'open') and isinstance(ws.open, bool):
                            is_active = ws.open
                        elif hasattr(ws, 'closed'):
                            is_active = not ws.closed
                        else:
                            # Fallback para estructuras más recientes de websockets si ha cambiado
                            is_active = True 
                    except:
                        is_active = False

                    if is_active:
                        other_clients.append(ws)
                
                if other_clients:
                    # [DEBUG] Log para confirmar reenvío
                    # print(f"-> Reenviando mensaje a {len(other_clients)} clientes.")
                    
                    # Broadcast eficiente con manejo de excepciones (evita caída masiva)
                    await asyncio.gather(*[client.send(message) for client in other_clients], return_exceptions=True)
            
    except websockets.exceptions.ConnectionClosed:
        print(f"Usuario {user_id} desconectado de {whiteboard_uuid}.")
    except Exception as e:
        print(f"Error en conexión User {user_id}: {e}")
    finally:
        # 5. Limpieza al desconectar
        if whiteboard_uuid in rooms:
            rooms[whiteboard_uuid].discard(websocket)
            if len(rooms[whiteboard_uuid]) == 0:
                del rooms[whiteboard_uuid]
                print(f"Sala {whiteboard_uuid} vacía y eliminada.")

async def main():
    print(f"Iniciando servidor WebSocket Seguro en ws://localhost:8765...")
    # Asegúrate de que el puerto coincida con tu .env o configuración
    async with websockets.serve(handler, "localhost", 8765):
        await asyncio.Future()  # Ejecutar para siempre

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\nServidor detenido manualmente.")