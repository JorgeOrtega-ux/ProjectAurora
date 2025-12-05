import os
import logging
import json
import uuid
import hashlib
import aiomysql
import redis.asyncio as redis
from datetime import datetime
import asyncio

logger = logging.getLogger(__name__)

db_pool = None 
redis_client = None

# [FIX] Conjunto para controlar bloqueos de concurrencia y evitar Race Conditions
active_flushes = set()

DB_CONFIG = {
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASS'),
    'host': os.getenv('DB_HOST'),
    'db': os.getenv('DB_NAME'),
    'autocommit': True
}

REDIS_URL = os.getenv('REDIS_URL', 'redis://localhost')

# --- INICIALIZACIÓN ---

async def init_db_pool():
    global db_pool
    try:
        db_pool = await aiomysql.create_pool(**DB_CONFIG, minsize=1, maxsize=20)
        logger.info("✅ DB Pool inicializado.")
    except Exception as e:
        logger.critical(f"❌ Error DB: {e}")
        exit(1)

async def init_redis_pool():
    global redis_client
    try:
        redis_client = redis.from_url(REDIS_URL, encoding="utf-8", decode_responses=True)
        logger.info("✅ Redis inicializado.")
    except Exception as e:
        logger.critical(f"❌ Error Redis: {e}")
        exit(1)

# --- HELPERS ---

def generate_uuid_v4():
    return str(uuid.uuid4())

# --- AUTH & USER ---

async def verify_token_and_get_session(raw_token):
    if not db_pool: return None
    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()
    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                query = """
                    SELECT t.user_id, t.session_id, u.role, u.username, u.profile_picture, u.uuid 
                    FROM ws_auth_tokens t
                    JOIN users u ON t.user_id = u.id
                    WHERE t.token = %s AND t.expires_at > NOW()
                """
                await cur.execute(query, (token_hash,))
                row = await cur.fetchone()
                if row:
                    return (str(row[0]), str(row[1]), str(row[2]), str(row[3]), str(row[4]), str(row[5]))
                return None
    except Exception as e:
        logger.error(f"Error auth: {e}")
        return None

async def get_user_id_by_uuid(uuid_str):
    if not db_pool: return None
    async with db_pool.acquire() as conn:
        async with conn.cursor() as cur:
            await cur.execute("SELECT id FROM users WHERE uuid = %s", (uuid_str,))
            row = await cur.fetchone()
            return str(row[0]) if row else None

async def get_community_members(community_id):
    if not db_pool: return []
    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("SELECT user_id FROM community_members WHERE community_id = %s", (community_id,))
                rows = await cur.fetchall()
                return [str(r[0]) for r in rows]
    except Exception as e:
        logger.error(f"Error fetching members: {e}")
        return []

async def is_spamming(user_id):
    if not db_pool: return False
    try:
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("SELECT chat_msg_limit, chat_time_window FROM server_config WHERE id = 1")
                config = await cur.fetchone()
                limit = int(config[0]) if config and config[0] else 5
                seconds = int(config[1]) if config and config[1] else 10

                query = """
                    SELECT 
                    (SELECT COUNT(*) FROM community_messages WHERE user_id = %s AND created_at > (NOW() - INTERVAL %s SECOND)) +
                    (SELECT COUNT(*) FROM private_messages WHERE sender_id = %s AND created_at > (NOW() - INTERVAL %s SECOND)) 
                """
                await cur.execute(query, (user_id, seconds, user_id, seconds))
                count_row = await cur.fetchone()
                return (count_row[0] if count_row else 0) >= limit
    except Exception as e:
        logger.error(f"Error spam check: {e}")
        return False

# --- CHAT & BUFFER LOGIC ---

async def process_chat_message(user_id, user_info, payload):
    """Procesa el mensaje, valida permisos (Mute/Bloqueo) y lo encola en Redis."""
    target_uuid = payload.get('target_uuid')
    message_text = payload.get('message')
    context = payload.get('context', 'community')
    reply_to_uuid = payload.get('reply_to_uuid')
    channel_uuid = payload.get('channel_uuid')
    
    if not db_pool or not redis_client:
        return {'success': False, 'error': 'Server Error'}

    message_uuid = generate_uuid_v4()
    created_at = datetime.now().isoformat()
    
    async with db_pool.acquire() as conn:
        async with conn.cursor() as cur:
            
            # --- PRIVATE CHAT ---
            if context == 'private':
                # [MODIFICADO] Verificar 'account_status' para "Conversación Congelada"
                await cur.execute("SELECT id, account_status FROM users WHERE uuid = %s", (target_uuid,))
                receiver_row = await cur.fetchone()
                
                if not receiver_row: return {'success': False, 'error': 'User not found'}
                
                receiver_id = str(receiver_row[0])
                account_status = receiver_row[1]

                if receiver_id == user_id: return {'success': False}

                # [VALIDACIÓN] Si la cuenta no está activa, bloquear
                if account_status != 'active':
                    return {'success': False, 'error': 'Este usuario ya no está disponible.'}

                # [NUEVO] Verificar Bloqueos
                block_query = """
                    SELECT id FROM user_blocks 
                    WHERE (blocker_id = %s AND blocked_id = %s) 
                       OR (blocker_id = %s AND blocked_id = %s)
                """
                await cur.execute(block_query, (user_id, receiver_id, receiver_id, user_id))
                if await cur.fetchone():
                    return {'success': False, 'error': 'No puedes enviar mensajes a este usuario.'}

                # Validar Amistad y Privacidad
                privacy_query = """
                    SELECT 
                        COALESCE(up.message_privacy, 'friends') as privacy, 
                        (SELECT status FROM friendships WHERE (sender_id = %s AND receiver_id = %s) OR (sender_id = %s AND receiver_id = %s)) as status 
                    FROM users u 
                    LEFT JOIN user_preferences up ON u.id = up.user_id 
                    WHERE u.id = %s
                """
                await cur.execute(privacy_query, (user_id, receiver_id, receiver_id, user_id, receiver_id))
                priv_row = await cur.fetchone()
                
                privacy_setting = priv_row[0] if priv_row else 'friends'
                friend_status = priv_row[1] 

                if privacy_setting == 'nobody':
                     return {'success': False, 'error': 'Este usuario no acepta mensajes privados.'}
                
                if privacy_setting == 'friends' and friend_status != 'accepted':
                     return {'success': False, 'error': 'Solo amigos pueden enviar mensajes a este usuario.'}

                # Resolve Reply
                reply_data = await _resolve_reply(cur, reply_to_uuid, 'private', user_id, receiver_id)

                message_payload = {
                    "uuid": message_uuid, "target_uuid": target_uuid, "context": "private",
                    "message": message_text, "sender_id": user_id, 
                    "sender_uuid": user_info['uuid'], "sender_username": user_info['username'],
                    "sender_profile_picture": user_info['profile_picture'], "sender_role": user_info['role'],
                    "created_at": created_at, "type": "text", "status": "active",
                    "receiver_id": receiver_id, "attachments": [],
                    "reply_to_uuid": reply_to_uuid,
                    "reply_message": reply_data.get('message'), "reply_sender_username": reply_data.get('sender_username')
                }
                
                ids = sorted([int(user_id), int(receiver_id)])
                redis_key = f"chat:buffer:private:{ids[0]}:{ids[1]}"
                
                await redis_client.rpush(redis_key, json.dumps(message_payload))
                await _check_flush(redis_key, 'private')
                
                return {'success': True, 'payload': message_payload}

            # --- COMMUNITY CHAT ---
            else:
                check_query = """
                    SELECT 
                        c.id, 
                        c.status, 
                        cm.role,
                        (cm.muted_until IS NOT NULL AND cm.muted_until > NOW()) as is_muted,
                        cm.muted_until
                    FROM communities c 
                    JOIN community_members cm ON c.id = cm.community_id 
                    WHERE c.uuid = %s AND cm.user_id = %s
                """
                await cur.execute(check_query, (target_uuid, user_id))
                comm_row = await cur.fetchone()
                
                if not comm_row: 
                    return {'success': False, 'error': 'Access denied'}
                
                community_id = str(comm_row[0])
                comm_status = comm_row[1]
                user_role = comm_row[2]
                is_muted = comm_row[3]
                muted_until_str = str(comm_row[4]) if comm_row[4] else ""

                if is_muted:
                    return {'success': False, 'error': f'Estás silenciado hasta: {muted_until_str}'}

                is_immune = user_role in ['founder', 'administrator', 'admin', 'moderator']

                if comm_status == 'maintenance' and not is_immune:
                    return {'success': False, 'error': 'Community Maintenance'}

                # Resolver Canal
                channel_id = await _resolve_channel(cur, community_id, channel_uuid, is_immune)
                if not channel_id: return {'success': False, 'error': 'Channel Invalid'}

                reply_data = await _resolve_reply(cur, reply_to_uuid, 'community', channel_id=channel_id)

                message_payload = {
                    "uuid": message_uuid, "target_uuid": target_uuid, 
                    "community_uuid": target_uuid, "channel_uuid": channel_uuid,
                    "context": "community", "message": message_text,
                    "sender_id": user_id, "sender_uuid": user_info['uuid'],
                    "sender_username": user_info['username'], "sender_profile_picture": user_info['profile_picture'],
                    "sender_role": user_info['role'], "created_at": created_at, "type": "text",
                    "community_id": community_id, "channel_id": channel_id, "user_id": user_id,
                    "reply_to_uuid": reply_to_uuid, "attachments": [],
                    "reply_message": reply_data.get('message'), "reply_sender_username": reply_data.get('sender_username')
                }

                redis_key = f"chat:buffer:channel:{channel_id}"
                await redis_client.rpush(redis_key, json.dumps(message_payload))
                await _check_flush(redis_key, 'community')

                return {'success': True, 'payload': message_payload}

async def _resolve_channel(cur, community_id, channel_uuid, is_immune):
    channel_id = None
    if channel_uuid:
        await cur.execute("SELECT id, status FROM community_channels WHERE uuid = %s AND community_id = %s", (channel_uuid, community_id))
        chan_row = await cur.fetchone()
        if chan_row:
            if chan_row[1] == 'maintenance' and not is_immune: return None
            channel_id = str(chan_row[0])
    
    if not channel_id: # Fallback general
        await cur.execute("SELECT id, status FROM community_channels WHERE community_id = %s ORDER BY created_at ASC LIMIT 1", (community_id,))
        chan_row = await cur.fetchone()
        if chan_row:
             if chan_row[1] == 'maintenance' and not is_immune: return None
             channel_id = str(chan_row[0])
    return channel_id

async def _resolve_reply(cur, reply_uuid, context, user_id=None, receiver_id=None, channel_id=None):
    if not reply_uuid: return {}
    
    table = 'private_messages' if context == 'private' else 'community_messages'
    sender_field = 'm.sender_id' if context == 'private' else 'm.user_id'
    
    query = f"SELECT m.message, m.type, u.username FROM {table} m JOIN users u ON {sender_field} = u.id WHERE m.uuid = %s"
    await cur.execute(query, (reply_uuid,))
    row = await cur.fetchone()
    
    if row:
        return {'message': row[0], 'type': row[1], 'sender_username': row[2]}
    
    # Try Redis Cache check
    search_key = ""
    if context == 'private':
        ids = sorted([int(user_id), int(receiver_id)])
        search_key = f"chat:buffer:private:{ids[0]}:{ids[1]}"
    else:
        search_key = f"chat:buffer:channel:{channel_id}"
        
    if redis_client:
        cached_msgs = await redis_client.lrange(search_key, 0, -1)
        for json_m in cached_msgs:
            try:
                m = json.loads(json_m)
                if m.get('uuid') == reply_uuid:
                    return {'message': m.get('message'), 'type': m.get('type'), 'sender_username': m.get('sender_username')}
            except: continue
    return {}

async def _check_flush(redis_key, context):
    """
    Verifica si el buffer está lleno y dispara el flush.
    [FIX] Usa active_flushes para evitar tareas duplicadas inútiles.
    """
    try:
        # Optimización: No crear tarea si ya se está procesando esa key
        if redis_key in active_flushes:
            return

        size = await redis_client.llen(redis_key)
        if size >= 10:
            asyncio.create_task(flush_chat_buffer(redis_key, context))
    except Exception as e:
        logger.error(f"Error checking flush: {e}")

async def flush_chat_buffer(redis_key, context):
    """
    Vuelca el buffer de Redis a MySQL de forma atómica respecto a la tarea.
    [FIX] Implementación de Mutex para evitar Race Condition en ltrim.
    """
    if not db_pool or not redis_client: return

    # [FIX] Mecanismo de bloqueo
    if redis_key in active_flushes:
        logger.debug(f"⚠️ Flush saltado para {redis_key}, ya hay uno en progreso.")
        return
    
    active_flushes.add(redis_key)

    try:
        # 1. Obtener mensajes
        messages = await redis_client.lrange(redis_key, 0, 9)
        if not messages: return

        logger.info(f"💾 Flushing {len(messages)} msgs from {redis_key}")
        
        async with db_pool.acquire() as conn:
            async with conn.cursor() as cur:
                try:
                    await conn.begin()
                    for json_msg in messages:
                        msg = json.loads(json_msg)
                        
                        uuid_val = msg['uuid']
                        text = msg['message']
                        type_val = msg['type']
                        created_at = msg['created_at']
                        reply_to_uuid = msg.get('reply_to_uuid')
                        reply_to_id = None
                        
                        table = 'private_messages' if context == 'private' else 'community_messages'
                        if reply_to_uuid:
                            await cur.execute(f"SELECT id FROM {table} WHERE uuid = %s", (reply_to_uuid,))
                            row = await cur.fetchone()
                            if row: reply_to_id = row[0]

                        if context == 'private':
                            await cur.execute("""
                                INSERT IGNORE INTO private_messages (uuid, sender_id, receiver_id, message, type, reply_to_id, reply_to_uuid, created_at, is_edited) 
                                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 0)
                            """, (uuid_val, msg['sender_id'], msg['receiver_id'], text, type_val, reply_to_id, reply_to_uuid, created_at))
                        else:
                            await cur.execute("""
                                INSERT IGNORE INTO community_messages (uuid, community_id, channel_id, user_id, message, type, reply_to_id, reply_to_uuid, created_at, is_edited) 
                                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 0)
                            """, (uuid_val, msg['community_id'], msg['channel_id'], msg['user_id'], text, type_val, reply_to_id, reply_to_uuid, created_at))
                    
                    await conn.commit()
                    
                    # 2. Recorte Seguro
                    # Ahora es seguro hacer ltrim porque garantizamos que nadie más 
                    # ha modificado la cabeza de la lista mientras procesábamos.
                    await redis_client.ltrim(redis_key, 10, -1)
                    
                except Exception as e:
                    await conn.rollback()
                    logger.error(f"Flush DB Error: {e}")
                    # NOTA: No hacemos ltrim si falla la DB, para reintentar luego.

    except Exception as e:
        logger.error(f"Flush General Error: {e}")
    finally:
        # [FIX] Liberar bloqueo siempre
        if redis_key in active_flushes:
            active_flushes.remove(redis_key)
            
        # Revisión recursiva: Si mientras procesábamos llegaron más mensajes
        try:
            current_len = await redis_client.llen(redis_key)
            if current_len >= 10:
                asyncio.create_task(flush_chat_buffer(redis_key, context))
        except: pass

# --- VOICE LOGIC ---

async def remove_user_from_voice_channels(user_id):
    """Elimina usuario de cualquier canal de voz en Redis y devuelve info para notificar."""
    if not redis_client: return None
    try:
        keys = await redis_client.keys("voice_channel:*:users")
        for key in keys:
            if await redis_client.srem(key, user_id):
                # User found and removed
                parts = key.split(':')
                channel_id = parts[1]
                
                async with db_pool.acquire() as conn:
                    async with conn.cursor() as cur:
                        await cur.execute("SELECT uuid, community_id FROM community_channels WHERE id = %s", (channel_id,))
                        row = await cur.fetchone()
                        if row:
                            current_users = await redis_client.smembers(key)
                            return {
                                "community_id": str(row[1]),
                                "channel_uuid": str(row[0]),
                                "current_users": list(current_users)
                            }
    except Exception as e:
        logger.error(f"Voice Disconnect Error: {e}")
    return None

async def trigger_buffer_check(msg_type, payload_data, target):
    if msg_type == 'new_chat_message':
        cid = payload_data.get('message_data', {}).get('channel_id')
        if cid: await _check_flush(f"chat:buffer:channel:{cid}", 'community')
    elif msg_type == 'private_message':
        sid = int(payload_data.get('message_data', {}).get('sender_id'))
        rid = int(target)
        ids = sorted([sid, rid])
        await _check_flush(f"chat:buffer:private:{ids[0]}:{ids[1]}", 'private')