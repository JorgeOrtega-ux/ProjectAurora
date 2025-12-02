import json
import logging
import asyncio
from datetime import datetime

logger = logging.getLogger(__name__)

# Estructuras de datos en memoria
# { user_id: { session_id: websocket } }
connected_clients = {} 
admin_sessions = set()

def has_admin_sessions():
    return len(admin_sessions) > 0

async def add_client(user_id, session_id, websocket, user_role):
    if user_id not in connected_clients:
        connected_clients[user_id] = {}
    
    connected_clients[user_id][session_id] = websocket
    
    if user_role in ['founder', 'administrator']:
        admin_sessions.add(websocket)
        
    await broadcast_user_status(user_id, 'online')

async def remove_client(user_id, session_id, websocket):
    if user_id in connected_clients and session_id in connected_clients[user_id]:
        del connected_clients[user_id][session_id]
        
        if not connected_clients[user_id]:
            del connected_clients[user_id]
            await broadcast_user_status(user_id, 'offline')
    
    if websocket in admin_sessions:
        admin_sessions.discard(websocket)

def get_online_users_ids():
    return list(connected_clients.keys())

async def send_to_user(user_id, message_dict):
    """Envía un mensaje a todas las sesiones de un usuario."""
    if user_id in connected_clients:
        payload = json.dumps(message_dict)
        dead_sessions = []
        for sid, ws in connected_clients[user_id].items():
            try:
                await ws.send(payload)
            except:
                dead_sessions.append(sid)
        
        # Limpieza simple de sesiones muertas detectadas al enviar
        for sid in dead_sessions:
            if sid in connected_clients[user_id]:
                del connected_clients[user_id][sid]

async def broadcast_to_list(user_ids_list, message_dict):
    """Envía mensaje a una lista de IDs de usuario."""
    payload = json.dumps(message_dict)
    for uid in user_ids_list:
        if uid in connected_clients:
            for ws in connected_clients[uid].values():
                try: await ws.send(payload)
                except: pass

async def broadcast_global(message_dict):
    """Envía mensaje a TODOS los conectados."""
    payload = json.dumps(message_dict)
    for sessions in connected_clients.values():
        for ws in sessions.values():
            try: await ws.send(payload)
            except: pass

async def broadcast_user_status(user_id, status):
    """Notifica a los administradores sobre cambios de estado."""
    if not admin_sessions:
        return
        
    timestamp = datetime.now().isoformat()
    message = json.dumps({
        "type": "user_status_change",
        "payload": {"user_id": user_id, "status": status, "timestamp": timestamp}
    })
    
    dead_sockets = set()
    for ws in admin_sessions:
        try: await ws.send(message)
        except: dead_sockets.add(ws)
    admin_sessions.difference_update(dead_sockets)

async def broadcast_log_to_admins(log_entry):
    """Específico para logs del sistema."""
    if not admin_sessions: return
    
    payload = json.dumps({"type": "server_log_debug", "log": log_entry})
    dead_sockets = set()
    for ws in admin_sessions:
        try: await ws.send(payload)
        except: dead_sockets.add(ws)
    admin_sessions.difference_update(dead_sockets)