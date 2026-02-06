import asyncio
import websockets
import redis.asyncio as redis
import os
import json
import logging
from urllib.parse import parse_qs, urlparse
from dotenv import load_dotenv

# Configuración de Logs
logging.basicConfig(level=logging.INFO, format='%(asctime)s - WS_SERVER - %(levelname)s - %(message)s')

load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# Configuración Redis
REDIS_HOST = os.getenv('REDIS_HOST')
REDIS_PORT = os.getenv('REDIS_PORT')
REDIS_PASS = os.getenv('REDIS_PASSWORD')

if not REDIS_HOST or not REDIS_PORT:
    logging.error("❌ Error fatal: Configuración Redis incompleta.")
    exit(1)

REDIS_PORT = int(REDIS_PORT)
WS_PORT = 8765

# Almacenamiento en memoria de conexiones
connected_users = {}
connected_guests = set()
background_tasks = set()

async def get_redis_client():
    # [SEGURIDAD DINÁMICA]
    # Determina si usa SSL basado en la variable de entorno
    use_ssl = os.getenv('REDIS_SCHEME', 'tcp').lower() == 'tls'
    
    return redis.Redis(
        host=REDIS_HOST, 
        port=REDIS_PORT, 
        password=REDIS_PASS, 
        decode_responses=True,
        ssl=use_ssl,
        ssl_cert_reqs=None,
        socket_timeout=5
    )

async def validate_and_consume_token(path, r_client):
    """Valida el token de un solo uso para la conexión WS"""
    try:
        if not path: return None, None
        parsed = urlparse(path)
        params = parse_qs(parsed.query)
        
        # Modo Invitado
        if params.get('type') == ['guest']:
            return 'GUEST', 'GUEST'

        # Modo Usuario (Token único uso)
        token = params.get('token', [None])[0]
        if not token: return None, None
        
        key = f"ws_token:{token}"
        data = await r_client.get(key)
        
        if data:
            await r_client.delete(key)
            parts = data.split(':')
            return parts[0], (parts[1] if len(parts) > 1 else '0')
            
        return None, None
    except Exception as e:
        logging.error(f"Error validando token: {e}")
        return None, None

# --- FUNCIONES DE GESTIÓN DE CONEXIONES ---

async def broadcast_message(msg_type, content=None):
    """Envía mensaje a TODOS (Usuarios e Invitados)"""
    payload = json.dumps({"type": msg_type, "message": content})
    
    # Recolectar todos los sockets activos
    all_sockets = set(connected_guests)
    for sessions in connected_users.values():
        for sockets in sessions.values():
            all_sockets.update(sockets)
            
    # Enviar masivamente
    for ws in all_sockets:
        try: await ws.send(payload)
        except: pass

async def disconnect_user_session(user_id, session_id, reason="Desconectado"):
    """Cierra sockets de una sesión específica"""
    if user_id in connected_users and session_id in connected_users[user_id]:
        payload = json.dumps({"type": "force_logout", "reason": reason})
        sockets = list(connected_users[user_id][session_id]) # Copia segura para iterar
        
        for ws in sockets:
            try:
                await ws.send(payload)
                await ws.close()
            except: pass
        logging.info(f"Sesión cerrada: User {user_id} / Session {session_id}")

async def disconnect_user_all(user_id, reason="Cuenta cerrada"):
    """Cierra TODAS las sesiones de un usuario (Ej: Cambio de contraseña)"""
    if user_id in connected_users:
        sessions = list(connected_users[user_id].keys())
        for sid in sessions:
            await disconnect_user_session(user_id, sid, reason)

async def update_stats_broadcast():
    """Actualiza contadores en Redis y notifica al frontend en tiempo real"""
    try:
        r_client = await get_redis_client()
        stats = {
            'online_users': len(connected_users),
            'online_guests': len(connected_guests),
            'online_total': len(connected_users) + len(connected_guests)
        }
        
        # Persistir en Redis para API (lectura estática)
        await r_client.hset('aurora:stats:realtime', mapping=stats)
        await r_client.aclose()
        
        # Push a clientes (actualización en vivo)
        await broadcast_message("stats_update", stats)
    except Exception as e:
        logging.error(f"Error actualizando stats: {e}")

# --- LISTENER DE REDIS (PUBSUB) ---

async def redis_listener():
    """Escucha mensajes del Worker o la API PHP"""
    logging.info("🎧 Iniciando Listener Redis Pub/Sub...")
    while True:
        try:
            r_sub = await get_redis_client()
            pubsub = r_sub.pubsub()
            await pubsub.subscribe('aurora_ws_control')
            
            logging.info("✅ Suscrito al canal: aurora_ws_control")
            
            async for message in pubsub.listen():
                if message['type'] == 'message':
                    try:
                        data = json.loads(message['data'])
                        cmd = data.get('cmd')
                        
                        # 1. Difusión General (Alertas, etc.)
                        if cmd == 'BROADCAST':
                            await broadcast_message(data.get('msg_type'), data.get('message'))
                        
                        # 2. [FIX] Modo Mantenimiento
                        elif cmd == 'maintenance_start':
                            # Reenviar la señal 'maintenance_start' a todos los clientes conectados
                            await broadcast_message('maintenance_start', data.get('message'))

                        # 3. Expulsión de una sesión específica
                        elif cmd == 'KICK_SESSION' or cmd == 'LOGOUT_SESSION':
                            await disconnect_user_session(str(data.get('user_id')), str(data.get('session_id')))
                        
                        # 4. [FIX] Expulsión total (Suspensión/Eliminación)
                        elif cmd == 'KICK_ALL':
                            # Capturamos la razón enviada desde PHP (si existe)
                            reason = data.get('reason', "Cuenta cerrada")
                            await disconnect_user_all(str(data.get('user_id')), reason)
                            
                    except Exception as e:
                        logging.error(f"Error procesando mensaje PubSub: {e}")
                        
        except Exception as e:
            logging.error(f"❌ Error conexión Redis Listener: {e}. Reintentando en 5s...")
            await asyncio.sleep(5)

# --- HANDLER WEBSOCKET ---

async def ws_handler(websocket):
    path = getattr(getattr(websocket, 'request', None), 'path', '') or getattr(websocket, 'path', '')
    
    r_client = await get_redis_client()
    user_id, session_id = await validate_and_consume_token(path, r_client)
    
    try:
        alert = await r_client.get('system:active_alert')
        if alert: await websocket.send(json.dumps({"type": "system_alert", "message": json.loads(alert)}))
    except: pass
    
    await r_client.aclose()

    if not user_id:
        await websocket.close(code=1008, reason="Auth Failed")
        return

    # REGISTRO DE CONEXIÓN
    user_id = str(user_id)
    is_guest = (user_id == 'GUEST')
    
    if is_guest:
        connected_guests.add(websocket)
    else:
        session_id = str(session_id)
        if user_id not in connected_users: connected_users[user_id] = {}
        if session_id not in connected_users[user_id]: connected_users[user_id][session_id] = set()
        connected_users[user_id][session_id].add(websocket)

    await update_stats_broadcast()
    
    try:
        mode = "guest" if is_guest else "user"
        await websocket.send(json.dumps({"type": "connection_established", "mode": mode}))
        await websocket.wait_closed()
    finally:
        # LIMPIEZA AL DESCONECTAR
        if is_guest:
            if websocket in connected_guests: connected_guests.remove(websocket)
        else:
            if user_id in connected_users and session_id in connected_users[user_id]:
                s_set = connected_users[user_id][session_id]
                if websocket in s_set: s_set.remove(websocket)
                if not s_set: del connected_users[user_id][session_id]
                if not connected_users[user_id]: del connected_users[user_id]
        
        await update_stats_broadcast()

async def main():
    logging.info(f"🚀 WS Servidor iniciando en puerto {WS_PORT}...")
    
    task = asyncio.create_task(redis_listener())
    background_tasks.add(task)
    task.add_done_callback(background_tasks.discard)
    
    async with websockets.serve(ws_handler, "0.0.0.0", WS_PORT):
        await asyncio.Future()

if __name__ == "__main__":
    try: asyncio.run(main())
    except KeyboardInterrupt: pass