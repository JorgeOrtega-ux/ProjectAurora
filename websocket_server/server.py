import asyncio
import websockets
import redis.asyncio as redis
import os
import json
import logging
from urllib.parse import parse_qs, urlparse
from dotenv import load_dotenv

# --- CONFIGURACIÓN DE LOGGING DETALLADO ---
# Formato: [2026-02-10 14:00:00] [INFO] Mensaje...
logging.basicConfig(
    level=logging.INFO, 
    format='[%(asctime)s] [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)

load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# Configuración Redis
REDIS_HOST = os.getenv('REDIS_HOST')
REDIS_PORT = os.getenv('REDIS_PORT')
REDIS_PASS = os.getenv('REDIS_PASSWORD')

# [SEGURIDAD] Límite de tamaño para mensajes entrantes (8KB)
MAX_PAYLOAD_SIZE = 8192 

if not REDIS_HOST or not REDIS_PORT:
    logging.critical("❌ FATAL: Configuración Redis incompleta en variables de entorno.")
    exit(1)

REDIS_PORT = int(REDIS_PORT)
WS_PORT = 8765

# Almacenamiento en memoria de conexiones
connected_users = {}
connected_guests = set()
background_tasks = set()

async def get_redis_client():
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

async def validate_and_consume_token(path, r_client, request_meta):
    """Valida el token y retorna (user_id, session_id). Registra detalles en el log."""
    try:
        if not path: 
            logging.warning(f"⚠️ [{request_meta}] Conexión rechazada: Path vacío.")
            return None, None
            
        parsed = urlparse(path)
        params = parse_qs(parsed.query)
        
        # Modo Invitado
        if params.get('type') == ['guest']:
            logging.info(f"👤 [{request_meta}] Autenticación: MODO INVITADO aceptado.")
            return 'GUEST', 'GUEST'

        # Modo Usuario (Token único uso)
        token = params.get('token', [None])[0]
        if not token:
            logging.warning(f"⛔ [{request_meta}] Autenticación fallida: Falta el token.")
            return None, None
        
        key = f"ws_token:{token}"
        data = await r_client.get(key)
        
        if data:
            await r_client.delete(key) # Consumir token (One-Time-Use)
            parts = data.split(':')
            user_id = parts[0]
            session_id = parts[1] if len(parts) > 1 else '0'
            logging.info(f"✅ [{request_meta}] Autenticación ÉXITO. UserID: {user_id} | SessionID: {session_id}")
            return user_id, session_id
        else:
            logging.warning(f"⛔ [{request_meta}] Autenticación fallida: Token inválido o expirado.")
            return None, None

    except Exception as e:
        logging.error(f"❌ [{request_meta}] Excepción validando token: {e}")
        return None, None

# --- GESTIÓN DE MENSAJES ---

async def broadcast_message(msg_type, content=None):
    payload = json.dumps({"type": msg_type, "message": content})
    
    total_sent = 0
    all_sockets = set(connected_guests)
    for sessions in connected_users.values():
        for sockets in sessions.values():
            all_sockets.update(sockets)
            
    for ws in all_sockets:
        try: 
            await ws.send(payload)
            total_sent += 1
        except: pass
    
    if total_sent > 0:
        logging.info(f"📢 BROADCAST enviado: Tipo='{msg_type}' | Receptores={total_sent}")

async def disconnect_user_session(user_id, session_id, reason="Desconectado"):
    if user_id in connected_users and session_id in connected_users[user_id]:
        payload = json.dumps({"type": "force_logout", "reason": reason})
        sockets = list(connected_users[user_id][session_id])
        
        for ws in sockets:
            try:
                await ws.send(payload)
                await ws.close()
            except: pass
        logging.info(f"🚪 Sesión cerrada forzosamente: User {user_id} | Session {session_id} | Razón: {reason}")

async def disconnect_user_all(user_id, reason="Cuenta cerrada"):
    if user_id in connected_users:
        sessions = list(connected_users[user_id].keys())
        count = 0
        for sid in sessions:
            await disconnect_user_session(user_id, sid, reason)
            count += 1
        logging.info(f"🚫 Usuario desconectado totalmente: User {user_id} | Sesiones cerradas: {count}")

async def disconnect_guests():
    if not connected_guests:
        return

    count = len(connected_guests)
    logging.warning(f"🚨 PÁNICO: Expulsando a {count} invitados.")
    
    payload = json.dumps({
        "type": "force_logout", 
        "reason": "El sistema ha entrado en Modo de Seguridad."
    })
    
    guests_to_kick = list(connected_guests)
    for ws in guests_to_kick:
        try:
            await ws.send(payload)
            await ws.close(code=1008, reason="Panic Mode")
        except: pass
            
    connected_guests.clear()
    await update_stats_broadcast()

async def update_stats_broadcast():
    try:
        r_client = await get_redis_client()
        stats = {
            'online_users': len(connected_users),
            'online_guests': len(connected_guests),
            'online_total': len(connected_users) + len(connected_guests)
        }
        await r_client.hset('aurora:stats:realtime', mapping=stats)
        await r_client.aclose()
        
        # logging.debug(f"📊 Estadísticas actualizadas: {stats}") # Descomentar si quieres mucho detalle
        await broadcast_message("stats_update", stats)
    except Exception as e:
        logging.error(f"❌ Error actualizando estadísticas: {e}")

# --- REDIS LISTENER ---

async def redis_listener():
    logging.info("🎧 Listener de Redis Pub/Sub iniciado y esperando mensajes...")
    while True:
        try:
            r_sub = await get_redis_client()
            pubsub = r_sub.pubsub()
            await pubsub.subscribe('aurora_ws_control')
            
            async for message in pubsub.listen():
                if message['type'] == 'message':
                    try:
                        data = json.loads(message['data'])
                        cmd = data.get('cmd')
                        
                        logging.info(f"📨 Comando recibido de Redis: {cmd}")

                        if cmd == 'BROADCAST':
                            await broadcast_message(data.get('msg_type'), data.get('message'))
                        elif cmd == 'maintenance_start':
                            await broadcast_message('maintenance_start', data.get('message'))
                        elif cmd in ['KICK_SESSION', 'LOGOUT_SESSION']:
                            await disconnect_user_session(str(data.get('user_id')), str(data.get('session_id')))
                        elif cmd == 'KICK_ALL':
                            await disconnect_user_all(str(data.get('user_id')), data.get('reason', "Cuenta cerrada"))
                        elif cmd == 'DROP_GUESTS':
                            await disconnect_guests()
                            
                    except Exception as e:
                        logging.error(f"❌ Error procesando mensaje PubSub: {e}")
                        
        except Exception as e:
            logging.error(f"❌ Error conexión Redis Listener: {e}. Reintentando en 5s...")
            await asyncio.sleep(5)

# --- HANDLER WEBSOCKET PRINCIPAL ---

async def ws_handler(websocket):
    # 1. Obtener Metadatos de la Conexión
    remote_ip = websocket.remote_address[0] if websocket.remote_address else "Unknown"
    path = getattr(getattr(websocket, 'request', None), 'path', '') or getattr(websocket, 'path', '')
    
    # Extraer Request ID enviado por el cliente JS para correlación
    parsed = urlparse(path)
    params = parse_qs(parsed.query)
    client_req_id = params.get('req_id', ['unknown'])[0]
    
    request_meta = f"IP:{remote_ip} | ReqID:{client_req_id}"
    
    logging.info(f"🔗 [{request_meta}] Nueva conexión entrante iniciada.")

    r_client = await get_redis_client()
    user_id, session_id = await validate_and_consume_token(path, r_client, request_meta)
    
    # Comprobar alertas activas al conectar
    try:
        alert = await r_client.get('system:active_alert')
        if alert: 
            logging.info(f"ℹ️ [{request_meta}] Enviando alerta activa al usuario.")
            await websocket.send(json.dumps({"type": "system_alert", "message": json.loads(alert)}))
    except: pass
    
    await r_client.aclose()

    if not user_id:
        logging.warning(f"🔒 [{request_meta}] Cerrando conexión por fallo de autenticación.")
        await websocket.close(code=1008, reason="Auth Failed")
        return

    # REGISTRO
    user_id = str(user_id)
    is_guest = (user_id == 'GUEST')
    
    if is_guest:
        connected_guests.add(websocket)
    else:
        session_id = str(session_id)
        if user_id not in connected_users: connected_users[user_id] = {}
        if session_id not in connected_users[user_id]: connected_users[user_id][session_id] = set()
        connected_users[user_id][session_id].add(websocket)

    logging.info(f"🔌 [{request_meta}] Conexión establecida. Estado: {'Invitado' if is_guest else 'Usuario Registrado'}.")
    await update_stats_broadcast()
    
    try:
        mode = "guest" if is_guest else "user"
        await websocket.send(json.dumps({"type": "connection_established", "mode": mode}))
        
        async for message in websocket:
            # Límite de tamaño
            if len(message) > MAX_PAYLOAD_SIZE:
                logging.warning(f"⚠️ [{request_meta}] Payload excedido ({len(message)} bytes). Cerrando.")
                await websocket.close(code=1009, reason="Payload Too Large")
                break

            try:
                data = json.loads(message)
                if not isinstance(data, dict): raise ValueError("JSON inválido")
                
                # Aquí puedes loguear los mensajes que envía el cliente
                # logging.info(f"📩 [{request_meta}] Mensaje recibido: {data.get('type')}")

            except json.JSONDecodeError:
                logging.warning(f"⚠️ [{request_meta}] JSON Malformado recibido.")
                continue
            except Exception as e:
                logging.error(f"❌ [{request_meta}] Error procesando mensaje: {e}")

    except websockets.exceptions.ConnectionClosedOK:
        logging.info(f"👋 [{request_meta}] Cliente desconectado normalmente.")
    except websockets.exceptions.ConnectionClosedError as e:
        logging.warning(f"💔 [{request_meta}] Conexión cerrada con error: {e.code} - {e.reason}")
    except Exception as e:
        logging.error(f"❌ [{request_meta}] Error inesperado en el socket: {e}")
    finally:
        # LIMPIEZA
        if is_guest:
            if websocket in connected_guests: connected_guests.remove(websocket)
        else:
            if user_id in connected_users and session_id in connected_users[user_id]:
                s_set = connected_users[user_id][session_id]
                if websocket in s_set: s_set.remove(websocket)
                if not s_set: del connected_users[user_id][session_id]
                if not connected_users[user_id]: del connected_users[user_id]
        
        logging.info(f"🧹 [{request_meta}] Recursos de conexión limpiados.")
        await update_stats_broadcast()

async def main():
    logging.info("========================================")
    logging.info(f"🚀 SERVIDOR WS INICIANDO EN PUERTO {WS_PORT}")
    logging.info("========================================")
    
    task = asyncio.create_task(redis_listener())
    background_tasks.add(task)
    task.add_done_callback(background_tasks.discard)
    
    async with websockets.serve(ws_handler, "0.0.0.0", WS_PORT):
        await asyncio.Future()

if __name__ == "__main__":
    try: 
        asyncio.run(main())
    except KeyboardInterrupt:
        logging.info("🛑 Servidor detenido manualmente.")