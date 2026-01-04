import asyncio
import websockets

# Manejador de conexiones individuales
async def handler(websocket):
    # Log simple al conectar
    print(f"Nuevo usuario conectado desde: {websocket.remote_address}")
    
    try:
        # Bucle infinito para mantener la conexión abierta
        # y escuchar mensajes (aunque por ahora no hagamos nada con ellos)
        async for message in websocket:
            pass 
    except websockets.exceptions.ConnectionClosed:
        print("Usuario desconectado")
    except Exception as e:
        print(f"Error en conexión: {e}")

# Función principal de arranque
async def main():
    print("Iniciando servidor WebSocket en ws://localhost:8765...")
    # Iniciamos el servidor en localhost puerto 8765
    async with websockets.serve(handler, "localhost", 8765):
        await asyncio.Future()  # Ejecutar para siempre

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\nServidor detenido manualmente.")