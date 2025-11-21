import asyncio
import websockets
import json
import socket
import mysql.connector
from datetime import datetime

# Diccionario para guardar conexiones activas: { user_id: websocket_object }
connected_clients = {}

# CONFIGURACIÓN BASE DE DATOS (Igual que en PHP)
DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'project_aurora_db',
    'raise_on_warnings': True
}

def verify_token_in_db(token):
    """
    Verifica el token contra MySQL.
    Retorna el user_id si es válido, o None si no.
    """
    try:
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor()

        # 1. Buscar token válido y no expirado
        query = "SELECT user_id FROM ws_auth_tokens WHERE token = %s AND expires_at > NOW()"
        cursor.execute(query, (token,))
        row = cursor.fetchone()
        
        user_id = None
        if row:
            user_id = str(row[0])
            # 2. Opcional: Borrar token usado (Single Use) o dejar que expire.
            # Por simplicidad y permitir múltiples pestañas, dejamos que expire.
        
        cursor.close()
        cnx.close()
        return user_id

    except mysql.connector.Error as err:
        print(f"[DB ERROR] {err}")
        return None

async def handle_browser_client(websocket):
    """Maneja la conexión con el navegador (Cliente JS)"""
    # Variable para guardar el ID de esta conexión específica
    conn_id = "unknown" 
    
    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError:
                continue
            
            if data.get('type') == 'auth':
                token = data.get('token')
                # 1. Capturamos el ID que envía el JS (o asignamos uno temporal si no viene)
                conn_id = data.get('request_id', 'no-id')
                
                if not token:
                    # Logueamos con el ID
                    print(f"[WS][{conn_id}] Error: Token requerido")
                    await websocket.send(json.dumps({"type": "error", "msg": "Token requerido"}))
                    return

                user_id = verify_token_in_db(token)

                if user_id:
                    connected_clients[user_id] = websocket
                    # 2. Logueamos el éxito usando el ID
                    print(f"[WS][{conn_id}] Usuario AUTENTICADO: ID {user_id}")
                    
                    await websocket.send(json.dumps({"type": "connected", "msg": "Conectado de forma segura"}))
                    await websocket.wait_closed()
                else:
                    print(f"[WS][{conn_id}] Fallo de auth (Token inválido)")
                    await websocket.send(json.dumps({"type": "error", "msg": "Token inválido"}))
                    await websocket.close()
                    return
            
    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        print(f"[WS][{conn_id}] Error crítico: {e}")
    finally:
        # Limpieza
        for uid, ws in list(connected_clients.items()):
            if ws == websocket:
                del connected_clients[uid]
                print(f"[WS][{conn_id}] Usuario {uid} desconectado.")

async def handle_php_notification(reader, writer):
    """Escucha notificaciones internas desde PHP (Puerto 8081)"""
    try:
        data = await reader.read(1024)
        message = data.decode()
        
        if not message:
            return

        payload = json.loads(message)
        target_id = str(payload.get('target_id'))
        
        if target_id in connected_clients:
            ws = connected_clients[target_id]
            try:
                await ws.send(json.dumps(payload))
                print(f"[PHP->WS] Mensaje enviado al usuario {target_id}")
            except Exception as e:
                print(f"[PHP->WS] Error enviando al usuario {target_id}: {e}")
        else:
            print(f"[PHP->WS] El usuario {target_id} no está conectado actualmente.")
            
    except Exception as e:
        print(f"[PHP->WS] Error procesando mensaje de PHP: {e}")
    finally:
        writer.close()
        await writer.wait_closed()

async def start_servers():
    print("Iniciando servidores seguros...")
    # Servidor WebSocket para Navegadores (Puerto 8080)
    async with websockets.serve(handle_browser_client, "localhost", 8080):
        print("✅ Servidor WebSocket corriendo en ws://localhost:8080")
        
        # Servidor TCP para PHP (Puerto 8081)
        server = await asyncio.start_server(handle_php_notification, "127.0.0.1", 8081)
        print("✅ Escuchando notificaciones de PHP en tcp://127.0.0.1:8081")

        async with server:
            await server.serve_forever()

if __name__ == "__main__":
    try:
        asyncio.run(start_servers())
    except KeyboardInterrupt:
        print("\n🛑 Servidor detenido.")