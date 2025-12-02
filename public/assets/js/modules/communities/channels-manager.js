// public/assets/js/modules/communities/channels-manager.js

import { CommunityApi } from '../../services/api-service.js';

// Cache para evitar re-fetching constante de canales
const channelsCache = {};

// [NUEVO] Estado de Voz
// { channelUuid: [userId1, userId2, ...] }
const voiceState = {
    connectedUsers: {},
    currentUserChannelUuid: null // UUID del canal donde estoy conectado
};

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

export function getCachedChannels(uuid) {
    return channelsCache[uuid] || null;
}

export function clearChannelsCache(uuid) {
    if (channelsCache[uuid]) delete channelsCache[uuid];
}

export async function loadChannels(communityUuid) {
    const res = await CommunityApi.getDetails(communityUuid);

    if (res.success) {
        channelsCache[communityUuid] = res.channels;
        return {
            success: true,
            channels: res.channels,
            role: res.info.role || 'member'
        };
    } else {
        channelsCache[communityUuid] = []; // Fallback vacío
        return { success: false, channels: [], role: 'member' };
    }
}

// [NUEVO] Actualizar estado de voz desde WebSocket
export function updateVoiceState(channelUuid, users) {
    voiceState.connectedUsers[channelUuid] = users || [];
    
    // Verificar si estoy en la lista
    const myId = String(window.USER_ID);
    if (users.includes(myId)) {
        voiceState.currentUserChannelUuid = channelUuid;
    } else if (voiceState.currentUserChannelUuid === channelUuid) {
        // Si ya no estoy en la lista pero mi estado decía que sí, me desconecto
        voiceState.currentUserChannelUuid = null;
    }
}

// Función helper para renderizar un item individual de canal (Reutilizada por ambos grupos)
function renderChannelItem(ch, communityUuid, userRole, isAdmin) {
    const isChActive = (ch.uuid === window.ACTIVE_CHANNEL_UUID) ? 'active' : '';
    const isVoice = (ch.type === 'voice');
    
    // Icono
    const icon = isVoice ? 'volume_up' : 'tag';
    
    // Badges de no leídos (solo texto)
    const unreadCount = parseInt(ch.unread_count || 0);
    let badgeHtml = '';
    let unreadClass = '';

    if (unreadCount > 0 && !isVoice) {
        const displayCount = unreadCount > 99 ? '99+' : unreadCount;
        badgeHtml = `<span class="channel-unread-badge">${displayCount}</span>`;
        unreadClass = 'has-unread';
    }

    // Estado de Voz
    let voiceUsersHtml = '';
    let voiceActiveClass = '';
    let disconnectBtn = '';
    
    if (isVoice) {
        const connectedUsers = voiceState.connectedUsers[ch.uuid] || [];
        const isMeConnected = voiceState.currentUserChannelUuid === ch.uuid;
        
        if (isMeConnected) {
            voiceActiveClass = 'active-voice-channel'; // Clase CSS para resaltar en verde
            disconnectBtn = `
                <button class="channel-action-btn disconnect" data-action="leave-voice-channel" data-uuid="${ch.uuid}" title="Desconectar" style="color:#d32f2f; opacity:1;">
                    <span class="material-symbols-rounded">call_end</span>
                </button>`;
        }

        if (connectedUsers.length > 0) {
            let avatars = '';
            // Limitamos a mostrar algunos avatares
            connectedUsers.slice(0, 5).forEach(uid => {
                const avatarUrl = `https://ui-avatars.com/api/?name=${uid}&background=random&size=24`; 
                // Nota: Idealmente deberíamos tener info del usuario (nombre/foto) en el state, 
                // por ahora usamos un placeholder o fallback con ID.
                avatars += `<img src="${avatarUrl}" class="voice-user-avatar" title="User ${uid}" style="width:20px; height:20px; border-radius:50%; border:1px solid #fff; margin-left:-4px;">`;
            });
            
            if (connectedUsers.length > 5) {
                avatars += `<span style="font-size:10px; color:#666; margin-left:4px;">+${connectedUsers.length - 5}</span>`;
            }

            voiceUsersHtml = `<div class="voice-users-list" style="margin-top:4px; padding-left:28px; display:flex; align-items:center;">${avatars}</div>`;
        }
    }

    // Botón eliminar (Admin)
    let deleteBtn = '';
    if (isAdmin && !disconnectBtn) { // Si hay botón de desconectar, priorizarlo
        deleteBtn = `
        <button class="channel-action-btn delete" data-action="delete-channel" data-uuid="${ch.uuid}" title="Eliminar canal">
            <span class="material-symbols-rounded">close</span>
        </button>`;
    }

    // Data Action: Diferente para voz y texto
    const actionType = isVoice ? 'join-voice-channel' : 'select-channel';

    return `
    <div class="channel-item-wrapper" style="margin-bottom:2px;">
        <div class="channel-item ${isChActive} ${unreadClass} ${voiceActiveClass}" 
             data-action="${actionType}" 
             data-uuid="${ch.uuid}" 
             data-community="${communityUuid}"
             data-type="${ch.type}"
             data-status="${ch.status || 'active'}"
             style="margin: 0 8px;">
            
            <span class="material-symbols-rounded channel-icon">${icon}</span>
            <span class="channel-name">${escapeHtml(ch.name)}</span>
            
            ${badgeHtml}
            ${disconnectBtn}
            ${deleteBtn}
        </div>
        ${voiceUsersHtml}
    </div>`;
}

export function renderChannelList(container, communityUuid, channels, userRole) {
    if (!container) return;

    const isAdmin = ['admin', 'founder'].includes(userRole);
    let html = '';

    if (channels && channels.length > 0) {
        
        // 1. Separar canales
        const textChannels = channels.filter(ch => ch.type !== 'voice');
        const voiceChannels = channels.filter(ch => ch.type === 'voice');

        // Helper para renderizar grupo
        const renderGroup = (title, items) => {
            if (items.length === 0) return '';
            
            let itemsHtml = items.map(ch => renderChannelItem(ch, communityUuid, userRole, isAdmin)).join('');
            
            // Cabecera con data-action para el toggle (lógica en communities-manager.js)
            // Agregamos un icono de flecha para indicar expansión
            return `
            <div class="channel-category-group expanded">
                <div class="channel-category-header" 
                     data-action="toggle-channel-category" 
                     style="padding: 12px 16px; font-size: 12px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; user-select:none;">
                    <span>${title}</span>
                    <span class="material-symbols-rounded category-arrow" style="font-size:16px;">expand_more</span>
                </div>
                <div class="channel-category-list">
                    ${itemsHtml}
                </div>
            </div>`;
        };

        // 2. Renderizar grupos
        html += renderGroup('Canales de texto', textChannels);
        html += renderGroup('Canales de voz', voiceChannels);

    } else {
        html = `<div style="padding:20px; font-size:13px; color:#999; text-align:center;">No hay canales disponibles.</div>`;
    }

    if (isAdmin) {
        html += `
        <div class="channel-create-btn" data-action="create-channel-prompt" data-community="${communityUuid}" style="margin: 8px 16px;">
            <span class="material-symbols-rounded">add</span> Crear canal
        </div>`;
    }

    container.innerHTML = html;
}

export async function handleCreateChannel(communityUuid) {
    const name = prompt("Nombre del nuevo canal:");
    if (!name) return { success: false };
    
    const cleanName = name.trim().substring(0, 20);
    if (cleanName.length < 1) return { success: false };

    const type = confirm("¿Es un canal de VOZ?\nAceptar = Voz\nCancelar = Texto") ? 'voice' : 'text';

    const btn = document.querySelector(`[data-action="create-channel-prompt"]`);
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<div class="small-spinner" style="width:14px; height:14px; border-width:2px;"></div> Creando...';
        btn.style.pointerEvents = 'none';
    }

    const res = await CommunityApi.createChannel(communityUuid, cleanName, type);

    if (btn) {
        btn.innerHTML = originalHtml;
        btn.style.pointerEvents = 'auto';
    }

    if (res.success) {
        if (!channelsCache[communityUuid]) channelsCache[communityUuid] = [];
        channelsCache[communityUuid].push(res.channel);
        return { success: true, channels: channelsCache[communityUuid], message: res.message };
    } else {
        alert(res.message || "Error al crear canal");
        return { success: false, message: res.message };
    }
}

export async function handleDeleteChannel(channelUuid, communityUuid) {
    if (!confirm("¿Eliminar este canal y todos sus mensajes?")) return { success: false };

    const res = await CommunityApi.deleteChannel(channelUuid);

    if (res.success) {
        if (channelsCache[communityUuid]) {
            channelsCache[communityUuid] = channelsCache[communityUuid].filter(c => c.uuid !== channelUuid);
        }
        return { success: true, channels: channelsCache[communityUuid], message: res.message };
    } else {
        alert(res.message || "Error al eliminar");
        return { success: false, message: res.message };
    }
}