import asyncio
import websockets
import mysql.connector
import os
import json
from urllib.parse import urlparse, parse_qs
from datetime import datetime

# Configuración de Base de Datos
# NOTA: En un entorno real, usa python-dotenv para cargar esto desde el archivo .env
DB_CONFIG = {
    'user': 'root',           # Ajusta según tu .env local
    'password': '',           # Ajusta según tu .env local
    'host': 'localhost',
    'database': 'project_aurora_db'
}

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

async def authenticate_user(token):
    """
    Verifica el token en la BD. Si es válido, retorna el user_id y borra el token.
    Si no, retorna None.
    """
    conn = None
    cursor = None
    user_id = None
    
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        # 1. Buscar token válido y no expirado
        query = "SELECT user_id FROM ws_tokens WHERE token = %s AND expires_at > NOW()"
        cursor.execute(query, (token,))
        result = cursor.fetchone()
        
        if result:
            user_id = result['user_id']
            
            # 2. Borrar token (One-Time Use)
            delete_query = "DELETE FROM ws_tokens WHERE token = %s"
            cursor.execute(delete_query, (token,))
            conn.commit()
            
    except mysql.connector.Error as err:
        print(f"Error de BD: {err}")
    finally:
        if cursor: cursor.close()
        if conn: conn.close()
        
    return user_id

async def handler(websocket):
    # 1. Obtener URL y parámetros
    # CORRECCIÓN: En websockets nuevos, path está dentro de request
    try:
        path = websocket.request.path
    except AttributeError:
        # Fallback por si acaso se usa una versión muy antigua
        path = getattr(websocket, 'path', '/')

    parsed_url = urlparse(path)
    params = parse_qs(parsed_url.query)
    
    token_list = params.get('token')
    
    # Obtener IP (Manejo compatible con versiones nuevas)
    remote_ip = "Desconocida"
    try:
        remote_ip = websocket.remote_address[0]
    except:
        pass

    if not token_list:
        print(f"Conexión rechazada: Falta token. IP: {remote_ip}")
        await websocket.close(code=4001, reason="Token missing")
        return

    token = token_list[0]
    
    # 2. Validar Token contra MySQL
    user_id = await authenticate_user(token)
    
    if not user_id:
        print(f"Conexión rechazada: Token inválido o expirado. IP: {remote_ip}")
        await websocket.close(code=4003, reason="Invalid or expired token")
        return

    print(f"✅ Usuario autenticado (ID: {user_id}). Conexión aceptada desde {remote_ip}.")
    
    try:
        # Enviar mensaje de bienvenida
        await websocket.send(json.dumps({
            "type": "system",
            "message": "Conectado y autenticado correctamente."
        }))

        # Bucle principal de mensajes
        async for message in websocket:
            print(f"Mensaje de User {user_id}: {message}")
            # Aquí iría la lógica de retransmisión (broadcast) o procesamiento
            
    except websockets.exceptions.ConnectionClosed:
        print(f"Usuario {user_id} desconectado.")
    except Exception as e:
        print(f"Error en conexión User {user_id}: {e}")

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