import json
import logging
import os
import db_sync
import connection_manager

logger = logging.getLogger(__name__)

# [SEGURIDAD] Eliminado valor por defecto 'default_secret'.
BRIDGE_SECRET = os.getenv('BRIDGE_SECRET')

# [SEGURIDAD] Validación estricta al iniciar el módulo.
# Si no hay secreto o es el inseguro, el servidor fallará intencionalmente.
if not BRIDGE_SECRET or BRIDGE_SECRET == 'default_secret':
    logger.critical("⛔ SEGURIDAD: BRIDGE_SECRET no está configurado o es inseguro.")
    raise ValueError("CRITICAL: BRIDGE_SECRET must be set in .env with a secure value.")

async def handle_auth(token):
    """Verifica token y devuelve datos del usuario o None."""
    return await db_sync.verify_token_and_get_session(token)

async def handle_chat_message(user_id, user_info, payload):
    target_uuid = payload.get('target_uuid')
    message_text = payload.get('message')
    context = payload.get('context', 'community')
    
    if not target_uuid or not message_text:
        return

    # 1. Check Anti-Spam
    if await db_sync.is_spamming(user_id):
        await connection_manager.send_to_user(user_id, {
            "type": "error", 
            "message": "Estás enviando mensajes demasiado rápido."
        })
        return 

    # 2. Procesar y guardar en Buffer (Validando Mute y Bloqueos)
    # db_sync ahora valida 'muted_until' usando NOW()
    result = await db_sync.process_chat_message(user_id, user_info, payload)
    
    if not result['success']:
        # Si está muteado, aquí se envía el mensaje de error al cliente
        if result.get('error'):
            await connection_manager.send_to_user(user_id, {
                "type": "error", 
                "message": result['error']
            })
        return

    # 3. Broadcast del mensaje
    message_payload = result['payload']
    socket_msg = {
        "type": "private_message" if context == 'private' else "new_chat_message",
        "payload": message_payload
    }

    if context == 'private':
        # Enviar a emisor y receptor
        receiver_id = message_payload['receiver_id']
        await connection_manager.send_to_user(receiver_id, socket_msg)
        await connection_manager.send_to_user(user_id, socket_msg)
    else:
        # Enviar a miembros de la comunidad
        community_id = message_payload['community_id']
        member_ids = await db_sync.get_community_members(community_id)
        await connection_manager.broadcast_to_list(member_ids, socket_msg)

async def handle_typing_event(user_id, payload):
    target_uuid = payload.get('target_uuid')
    if not target_uuid: return
    
    # Resolver ID numérico del target
    target_id = await db_sync.get_user_id_by_uuid(target_uuid)
    if target_id:
        response = {
            "type": "typing",
            "payload": {"sender_id": user_id}
        }
        await connection_manager.send_to_user(target_id, response)

# [MODIFICADO] Eliminada función handle_voice_disconnect

# --- PHP BRIDGE HANDLER ---
async def handle_bridge_message(msg_json):
    """Maneja mensajes provenientes de PHP (Notificaciones, etc)."""
    try:
        full_payload = json.loads(msg_json)
    except json.JSONDecodeError:
        return

    # Auth
    if full_payload.get('auth_token') != BRIDGE_SECRET:
        logger.warning("⛔ Intento no autorizado en Bridge.")
        return 

    target = str(full_payload.get('target_id'))
    msg_type = full_payload.get('type')
    payload_data = full_payload.get('payload', {})

    # Lógica de Flush Check (Triggered by PHP)
    if msg_type in ['new_chat_message', 'private_message']:
        await db_sync.trigger_buffer_check(msg_type, payload_data, target)

    # Routing de Broadcast Global
    if target == 'global':
        await connection_manager.broadcast_global(full_payload)
    
    # Routing de Broadcast Comunidad
    elif target == 'community_broadcast':
        community_id = payload_data.get('community_id')
        msg_out = {"type": msg_type, "payload": payload_data.get('message_data')}
        
        # Obtenemos miembros para broadcast
        member_ids = await db_sync.get_community_members(community_id)
        await connection_manager.broadcast_to_list(member_ids, msg_out)
        
    else:
        # Mensaje directo a usuario o evento específico
        # [MODIFICADO] Eliminado bloque de voice_channel_update

        if msg_type == 'message_edited':
             msg_out = {"type": "message_edited", "payload": payload_data.get('message_data')}
             await connection_manager.send_to_user(target, msg_out)
             
        elif msg_type == 'private_message':
             msg_out = {"type": "private_message", "payload": payload_data.get('message_data')}
             await connection_manager.send_to_user(target, msg_out)
             # También al sender si está conectado (aunque PHP ya lo intenta, por seguridad)
             sender_id = str(payload_data.get('sender_id'))
             if sender_id and sender_id != target:
                 await connection_manager.send_to_user(sender_id, msg_out)

        # [NUEVO] Manejo de Reacciones en tiempo real
        elif msg_type == 'message_reaction_update':
             # Payload esperado: { message_uuid, reactions: {...}, actor_id, context }
             msg_out = {"type": "message_reaction_update", "payload": payload_data.get('message_data')}
             await connection_manager.send_to_user(target, msg_out)

        # [NUEVO] Manejo de Desconexión Forzada (Kick/Ban)
        elif msg_type == 'force_disconnect':
             msg_out = {"type": "force_disconnect", "payload": payload_data}
             await connection_manager.send_to_user(target, msg_out)
             
        else:
             # Default fallback
             await connection_manager.send_to_user(target, full_payload)