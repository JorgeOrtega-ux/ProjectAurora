import asyncio
import websockets
import os
import sys
import logging
from logging.handlers import RotatingFileHandler
from dotenv import load_dotenv

# Importar módulos locales
import db_sync
import event_router
import connection_manager
from handlers import handle_bridge_message

# --- FIX PARA WINDOWS ---
if sys.platform == 'win32':
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8')
    if hasattr(sys.stderr, 'reconfigure'):
        sys.stderr.reconfigure(encoding='utf-8')

# Cargar variables de entorno
load_dotenv()

# --- CONFIGURACIÓN LOGGING ---
if not os.path.exists('logs'):
    os.makedirs('logs')

file_handler = RotatingFileHandler('logs/websocket_server.log', maxBytes=5*1024*1024, backupCount=3, encoding='utf-8')
file_handler.setLevel(logging.INFO)
file_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
file_handler.setFormatter(file_formatter)

console_handler = logging.StreamHandler(sys.stdout)
console_handler.setLevel(logging.INFO)
console_handler.setFormatter(file_formatter)

# Handler personalizado para enviar logs a admins conectados
class AdminBroadcastHandler(logging.Handler):
    def emit(self, record):
        if not connection_manager.has_admin_sessions():
            return
        log_entry = self.format(record)
        # Delegamos el broadcast al manager para evitar lógica circular compleja
        try:
            loop = asyncio.get_running_loop()
            if loop.is_running():
                loop.create_task(connection_manager.broadcast_log_to_admins(log_entry))
        except RuntimeError:
            pass 

admin_handler = AdminBroadcastHandler()
admin_handler.setLevel(logging.INFO)
admin_handler.setFormatter(logging.Formatter('[%(asctime)s] %(message)s', datefmt='%H:%M:%S'))

logger = logging.getLogger()
logger.setLevel(logging.INFO)
if logger.hasHandlers():
    logger.handlers.clear()
logger.addHandler(file_handler)
logger.addHandler(console_handler)
logger.addHandler(admin_handler)

# --- SERVER PHP BRIDGE ---
async def start_php_bridge():
    server = await asyncio.start_server(handle_bridge_connection, "127.0.0.1", 8081)
    logger.info("🌉 Bridge PHP escuchando en 127.0.0.1:8081")
    async with server:
        await server.serve_forever()

async def handle_bridge_connection(reader, writer):
    try:
        data = await reader.read(8192)
        msg = data.decode()
        if msg:
            await handle_bridge_message(msg)
    except Exception as e:
        logger.error(f"Error en puente PHP: {e}")
    finally:
        writer.close()
        await writer.wait_closed()

# --- MAIN ---
async def main():
    logger.info("=== Servidor Aurora Iniciado (Modular) ===")
    
    # 1. Inicializar Bases de Datos
    await db_sync.init_db_pool()
    await db_sync.init_redis_pool()
    
    # 2. Iniciar Servidor Websocket (Cliente Browser)
    # Usamos event_router.handle_client_connection como el handler principal
    ws_server = await websockets.serve(event_router.handle_client_connection, "0.0.0.0", 8080)
    logger.info("🚀 Websocket Server escuchando en 0.0.0.0:8080")

    # 3. Iniciar Bridge PHP en segundo plano
    asyncio.create_task(start_php_bridge())
    
    # Mantener vivo
    await asyncio.Future()

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("🛑 Servidor detenido.")
    except Exception as e:
        logger.critical(f"🔥 Error fatal: {e}")