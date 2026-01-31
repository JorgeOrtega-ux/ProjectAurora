import asyncio
import websockets
import redis.asyncio as redis
import os
import json
import logging
from urllib.parse import parse_qs, urlparse
from dotenv import load_dotenv

# Configuración de Logs
logging.basicConfig(level=logging.INFO, format='%(asctime)s - SERVER - %(levelname)s - %(message)s')

load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../.env'))

# Configuración Redis
REDIS_HOST = os.getenv('REDIS_HOST', '127.0.0.1')
REDIS_PORT = int(os.getenv('REDIS_PORT', 6379))
REDIS_PASS = os.getenv('REDIS_PASSWORD', None)

# Estructura de Usuarios Logueados: { user_id: { session_id: set(sockets) } }
connected_users = {}
# Estructura de Invitados: set(sockets)
connected_guests = set()

WS_PORT = 8765

async def get_redis_client():
    return redis.Redis(
        host=REDIS_HOST, 
        port=REDIS_PORT, 
        password=REDIS_PASS, 
        decode_responses=True
    )

async def validate_and_consume_token(path, r_client):
    try:
        if not path: return None, None
        parsed_url = urlparse(path)
        params = parse_qs(parsed_url.query)
        
        type_param = params.get('type')
        if type_param and type_param[0] == 'guest':
            return 'GUEST', 'GUEST'

        token_list = params.get('token')
        if not token_list: return None, None
        token = token_list[0]
        
        redis_key = f"ws_token:{token}"
        data = await r_client.get(redis_key)
        
        if not data:
            return None, None
            
        await r_client.delete(redis_key)
        
        parts = data.split(':')
        user_id = parts[0]
        session_id = parts[1] if len(parts) > 1 else 0
        
        return user_id, session_id

    except Exception as e:
        logging.error(f"Error validando token: {e}")
        return None, None

# --- Función Auxiliar para Desconectar Sesiones ---
async def _disconnect_session(user_id, session_id, reason_msg, log_prefix):
    """
    Lógica centralizada para cerrar sockets de una sesión específica.
    """
    if user_id in connected_users and session_id in connected_users[user_id]:
        payload = json.dumps({"type": "force_logout", "reason": reason_msg})
        
        # Crear copia de la lista para iterar sin problemas de concurrencia al remover
        sockets_to_close = list(connected_users[user_id][session_id])
        
        for ws in sockets_to_close:
            try:
                await ws.send(payload)
                await ws.close()
            except: pass
            
        logging.info(f"{log_prefix}: User {user_id}, Session {session_id}")

# --- Funciones Específicas llamadas por Redis Listener ---

async def kick_session_local(user_id, session_id):
    """Acción administrativa o forzosa (KICK)"""
    await _disconnect_session(user_id, session_id, "Has sido expulsado", "KICK EJECUTADO")

async def logout_session_local(user_id, session_id):
    """Acción voluntaria del usuario (LOGOUT)"""
    await _disconnect_session(user_id, session_id, "Sesión cerrada correctamente", "LOGOUT MANUAL")

async def kick_all_sessions_local(user_id):
    if user_id in connected_users:
        payload = json.dumps({"type": "force_logout", "reason": "Cuenta cerrada"})
        sessions = list(connected_users[user_id].items())
        for sid, sockets in sessions:
            for ws in list(sockets):
                try:
                    await ws.send(payload)
                    await ws.close()
                except: pass
        logging.info(f"KICK ALL EJECUTADO: User {user_id}")

async def broadcast_to_everyone_local(msg_type, content=None):
    payload = json.dumps({"type": msg_type, "message": content})
    
    count = 0
    # Usuarios
    for uid, sessions in connected_users.items():
        for sid, sockets in sessions.items():
            for ws in list(sockets):
                try: 
                    await ws.send(payload)
                    count += 1
                except: pass
    
    # Invitados
    for ws in list(connected_guests):
        try: 
            await ws.send(payload)
            count += 1
        except: pass
    
    # logging.info(f"📢 Mensaje enviado a {count} clientes conectados.")

async def redis_listener_loop():
    """
    Escucha mensajes Pub/Sub de Redis enviados por el Worker o la API.
    """
    r_sub = await get_redis_client()
    pubsub = r_sub.pubsub()
    await pubsub.subscribe('aurora_ws_control')
    
    logging.info("🎧 Escuchando canal Redis: aurora_ws_control")

    async for message in pubsub.listen():
        if message['type'] == 'message':
            try:
                data = json.loads(message['data'])
                cmd = data.get('cmd')
                
                if cmd == 'KICK_SESSION':
                    u_id = str(data.get('user_id'))
                    s_id = str(data.get('session_id'))
                    await kick_session_local(u_id, s_id)

                elif cmd == 'LOGOUT_SESSION': 
                    u_id = str(data.get('user_id'))
                    s_id = str(data.get('session_id'))
                    await logout_session_local(u_id, s_id)
                    
                elif cmd == 'KICK_ALL':
                    u_id = str(data.get('user_id'))
                    await kick_all_sessions_local(u_id)
                    
                elif cmd == 'BROADCAST':
                    msg_type = data.get('msg_type')
                    msg_content = data.get('message')
                    logging.info(f"📨 Retransmitiendo BROADCAST: {msg_type}")
                    await broadcast_to_everyone_local(msg_type, msg_content)
                    
            except Exception as e:
                logging.error(f"Error procesando mensaje Pub/Sub: {e}")

# --- ACTUALIZACIÓN DE ESTADÍSTICAS (Con Push en vivo) ---
async def update_stats_in_redis():
    """Actualiza los contadores en Redis Y envía los datos nuevos al Frontend (Push)"""
    try:
        r_client = await get_redis_client()
        
        # Contar usuarios únicos (no sockets, sino IDs únicos)
        total_users = len(connected_users)
        total_guests = len(connected_guests)
        
        # 1. Guardar en Redis (Persistencia)
        await r_client.hset('aurora:stats:realtime', mapping={
            'online_users': total_users,
            'online_guests': total_guests
        })
        
        await r_client.aclose() 
        
        # logging.info(f"📊 Stats actualizados: Usuarios {total_users} | Invitados {total_guests}")

        # 2. ENVIAR A TODOS LOS CLIENTES (Push en Vivo)
        # Esto arregla el problema de que al refrescar veas "0"
        stats_payload = {
            'online_users': total_users,
            'online_guests': total_guests,
            'online_total': total_users + total_guests
        }
        await broadcast_to_everyone_local("stats_update", stats_payload)

    except Exception as e:
        logging.error(f"Error actualizando stats: {e}")

async def ws_handler(websocket):
    path = ""
    try:
        if hasattr(websocket, 'request') and websocket.request: path = websocket.request.path
        elif hasattr(websocket, 'path'): path = websocket.path
    except: pass

    r_client = await get_redis_client()
    user_id, session_id = await validate_and_consume_token(path, r_client)
    
    await r_client.aclose()

    if not user_id:
        await websocket.close(code=1008, reason="Authentication Failed")
        return

    # === NUEVO: CHECK DE ALERTA ACTIVA ===
    # Obtenemos un cliente nuevo o reusamos si prefieres reestructurar, 
    # pero para simplicidad abrimos uno rápido para checar persistencia.
    try:
        r_temp = await get_redis_client()
        active_alert_raw = await r_temp.get('system:active_alert')
        await r_temp.aclose()
        
        if active_alert_raw:
            # Enviamos la alerta inmediatamente al conectarse
            alert_data = json.loads(active_alert_raw)
            await websocket.send(json.dumps({
                "type": "system_alert", 
                "message": alert_data
            }))
    except Exception as e:
        logging.error(f"Error enviando alerta persistente: {e}")
    # ======================================

    # --- LÓGICA INVITADO ---
    if user_id == 'GUEST':
        connected_guests.add(websocket)
        await update_stats_in_redis() # Actualizar al conectar
        
        logging.info(f"Invitado conectado. Total invitados: {len(connected_guests)}")
        try:
            await websocket.send(json.dumps({"type": "connection_established", "mode": "guest"}))
            async for message in websocket: pass 
        except: pass
        finally:
            if websocket in connected_guests:
                connected_guests.remove(websocket)
                await update_stats_in_redis() # Actualizar al desconectar
        return

    # --- LÓGICA USUARIO REGISTRADO ---
    user_id = str(user_id)
    session_id = str(session_id)
    
    if user_id not in connected_users: connected_users[user_id] = {}
    if session_id not in connected_users[user_id]: connected_users[user_id][session_id] = set()
    
    connected_users[user_id][session_id].add(websocket)
    await update_stats_in_redis() # Actualizar al conectar
    
    logging.info(f"User {user_id} (Session {session_id}) conectado.")

    try:
        await websocket.send(json.dumps({"type": "connection_established", "mode": "user"}))
        async for message in websocket: pass 
    except: pass
    finally:
        if user_id in connected_users and session_id in connected_users[user_id]:
            if websocket in connected_users[user_id][session_id]:
                connected_users[user_id][session_id].remove(websocket)
            
            # Limpieza de estructuras vacías
            if not connected_users[user_id][session_id]:
                del connected_users[user_id][session_id]
            if not connected_users[user_id]:
                del connected_users[user_id]
                
        # Actualizar stats solo si el usuario ya no tiene conexiones activas
        # o simplemente actualizar siempre para asegurar consistencia
        await update_stats_in_redis() 
        
        logging.info(f"User {user_id} desconectado.")

async def main():
    logging.info(f"🚀 WS Servidor iniciando en puerto {WS_PORT}...")
    
    # 1. Iniciar el listener de Pub/Sub en background
    asyncio.create_task(redis_listener_loop())
    
    # 2. Iniciar servidor WebSocket con Heartbeat (Mata-Fantasmas)
    # ping_interval=20: Envía ping cada 20s
    # ping_timeout=20: Si no responde en 20s, cierra la conexión y limpia memoria
    async with websockets.serve(ws_handler, "0.0.0.0", WS_PORT, ping_interval=20, ping_timeout=20):
        await asyncio.Future()

if __name__ == "__main__":
    try: asyncio.run(main())
    except KeyboardInterrupt: pass