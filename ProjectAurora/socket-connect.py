import asyncio
import websockets
import json
import socket

# Diccionario para guardar conexiones activas: { user_id: websocket_object }
connected_clients = {}

# [CORRECCIÓN] Eliminamos el argumento 'path' que ya no se usa en versiones nuevas
async def handle_browser_client(websocket):
    """Maneja la conexión con el navegador (Cliente JS)"""
    try:
        # 1. Esperar mensaje de autenticación
        async for message in websocket:
            data = json.loads(message)
            
            if data.get('type') == 'auth':
                user_id = str(data.get('user_id'))
                connected_clients[user_id] = websocket
                print(f"[WS] Usuario conectado: ID {user_id}")
                # Confirmar conexión
                await websocket.send(json.dumps({"type": "connected", "msg": "Conectado al servidor en vivo"}))
            
    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        print(f"[WS] Error en conexión: {e}")
    finally:
        # Limpiar desconexión
        for uid, ws in list(connected_clients.items()):
            if ws == websocket:
                del connected_clients[uid]
                print(f"[WS] Usuario desconectado: ID {uid}")

async def handle_php_notification(reader, writer):
    """Escucha notificaciones internas desde PHP (Puerto 8081)"""
    try:
        data = await reader.read(1024)
        message = data.decode()
        
        if not message:
            return

        # PHP envía JSON: {"target_id": 123, "type": "notification", "data": {...}}
        payload = json.loads(message)
        target_id = str(payload.get('target_id'))
        
        if target_id in connected_clients:
            ws = connected_clients[target_id]
            try:
                # Reenviar al navegador del usuario destino
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
    print("Iniciando servidores...")
    # Servidor WebSocket para Navegadores (Puerto 8080)
    # [CORRECCIÓN] Eliminamos el host explícito en serve si da problemas, o usamos 'localhost'
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