// public/assets/js/modules/communities/channels-manager.js

import { CommunityApi } from '../../services/api-service.js';

// Cache para evitar re-fetching constante de canales
const channelsCache = {};

// [MODIFICADO] Eliminado voiceState

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

// [MODIFICADO] Eliminada función updateVoiceState

// Función helper para renderizar un item individual de canal (Reutilizada por ambos grupos)
function renderChannelItem(ch, communityUuid, userRole, isAdmin) {
    const isChActive = (ch.uuid === window.ACTIVE_CHANNEL_UUID) ? 'active' : '';
    // [MODIFICADO] Se elimina verificación isVoice. Todo es texto.
    
    // Icono
    const icon = 'tag';
    
    // Badges de no leídos (solo texto)
    const unreadCount = parseInt(ch.unread_count || 0);
    let badgeHtml = '';
    let unreadClass = '';

    if (unreadCount > 0) {
        const displayCount = unreadCount > 99 ? '99+' : unreadCount;
        badgeHtml = `<span class="channel-unread-badge">${displayCount}</span>`;
        unreadClass = 'has-unread';
    }

    // [MODIFICADO] Eliminada lógica de Estado de Voz (voiceUsersHtml, voiceActiveClass, disconnectBtn)

    // Botón eliminar (Admin)
    let deleteBtn = '';
    if (isAdmin) {
        deleteBtn = `
        <button class="channel-action-btn delete" data-action="delete-channel" data-uuid="${ch.uuid}" title="Eliminar canal">
            <span class="material-symbols-rounded">close</span>
        </button>`;
    }

    // Data Action: Siempre select-channel
    const actionType = 'select-channel';

    return `
    <div class="channel-item-wrapper" style="margin-bottom:2px;">
        <div class="channel-item ${isChActive} ${unreadClass}" 
             data-action="${actionType}" 
             data-uuid="${ch.uuid}" 
             data-community="${communityUuid}"
             data-type="${ch.type}"
             data-status="${ch.status || 'active'}"
             style="margin: 0 8px;">
            
            <span class="material-symbols-rounded channel-icon">${icon}</span>
            <span class="channel-name">${escapeHtml(ch.name)}</span>
            
            ${badgeHtml}
            ${deleteBtn}
        </div>
    </div>`;
}

export function renderChannelList(container, communityUuid, channels, userRole) {
    if (!container) return;

    const isAdmin = ['admin', 'founder'].includes(userRole);
    let html = '';

    if (channels && channels.length > 0) {
        
        // [MODIFICADO] Eliminado filtrado de voz.
        // Asumimos que todos los canales que llegan son de texto.
        
        const renderGroup = (title, items) => {
            if (items.length === 0) return '';
            
            let itemsHtml = items.map(ch => renderChannelItem(ch, communityUuid, userRole, isAdmin)).join('');
            
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

        // Renderizar un solo grupo
        html += renderGroup('Canales', channels);

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

    // [MODIFICADO] Eliminado confirm. Tipo forzado a texto.
    const type = 'text';

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