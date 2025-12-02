import json
import logging
import websockets
import connection_manager
import handlers
import time

logger = logging.getLogger(__name__)

# Control de fuerza bruta (Auth) simple
ip_attempts = {} 
MAX_AUTH_ATTEMPTS = 5
ATTEMPT_WINDOW = 60 

def check_rate_limit(ip):
    now = time.time()
    if ip not in ip_attempts:
        ip_attempts[ip] = {'count': 0, 'reset_at': now + ATTEMPT_WINDOW}
    
    data = ip_attempts[ip]
    if now > data['reset_at']:
        data['count'] = 0
        data['reset_at'] = now + ATTEMPT_WINDOW
    
    if data['count'] >= MAX_AUTH_ATTEMPTS:
        return False
    return True

def record_failed_attempt(ip):
    if ip in ip_attempts:
        ip_attempts[ip]['count'] += 1

async def handle_client_connection(websocket):
    user_id = None
    session_id = None
    user_data = {} 
    remote_ip = websocket.remote_address[0]
    
    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError:
                continue
                
            msg_type = data.get('type')

            # --- AUTH ---
            if msg_type == 'auth':
                if not check_rate_limit(remote_ip):
                    await websocket.send(json.dumps({"type": "auth_error_permanent", "msg": "Too many attempts."}))
                    return

                token = data.get('token')
                if not token: continue

                # Llamamos al handler de autenticación
                result = await handlers.handle_auth(token)
                
                if result:
                    # Desempaquetar datos
                    user_id, session_id, role, username, pfp, uuid_val = result
                    user_data = {
                        'role': role,
                        'username': username,
                        'profile_picture': pfp,
                        'uuid': uuid_val
                    }
                    
                    # Registrar en el manager
                    await connection_manager.add_client(user_id, session_id, websocket, role)
                    
                    logger.info(f"Usuario conectado: ID {user_id} | Rol: {role}")
                    await websocket.send(json.dumps({"type": "connected"}))
                else:
                    record_failed_attempt(remote_ip)
                    await websocket.send(json.dumps({"type": "auth_error_permanent", "msg": "Token invalid"}))
                    return 

            # --- ROUTING DE MENSAJES AUTENTICADOS ---
            elif user_id: # Solo si ya está autenticado
                
                if msg_type == 'get_online_users':
                    # Solo admins
                    if user_data.get('role') in ['founder', 'administrator']:
                        users = connection_manager.get_online_users_ids()
                        await websocket.send(json.dumps({"type": "online_users_list", "payload": users}))
                
                elif msg_type == 'chat_message':
                    await handlers.handle_chat_message(user_id, user_data, data.get('payload', {}))

                elif msg_type == 'typing':
                    await handlers.handle_typing_event(user_id, data.get('payload', {}))

    except websockets.exceptions.ConnectionClosed:
        pass
    except Exception as e:
        logger.error(f"Error socket cliente: {e}")
    finally:
        # Limpieza
        if user_id:
            await handlers.handle_voice_disconnect(user_id)
            if session_id:
                await connection_manager.remove_client(user_id, session_id, websocket)