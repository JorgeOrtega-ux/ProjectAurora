// public/assets/js/modules/communities/sidebar-renderer.js

import { t } from '../../core/i18n-manager.js';

// --- HELPERS INTERNOS ---

function formatChatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const isToday = date.getDate() === now.getDate() && date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
    return isToday ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : date.toLocaleDateString();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// --- RENDERIZADO DE ITEMS INDIVIDUALES ---

export function renderChatListItem(item, activeChatUuid) {
    const isPrivate = (item.type === 'private');
    
    const avatarSrc = item.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + item.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.name);
        
    const isActive = (isPrivate && item.uuid === activeChatUuid) ? 'active' : '';
    
    let lastMsg = item.last_message ? escapeHtml(item.last_message) : (item.last_message_at ? 'Imagen' : (isPrivate ? "Nuevo chat" : "Toca para ver canales"));
    if (isPrivate && !item.last_message && !item.last_message_at) lastMsg = "Iniciar conversación";

    const time = item.last_message_at ? formatChatTime(item.last_message_at) : '';
    const unreadCount = parseInt(item.unread_count || 0);
    
    const badgeHtml = (unreadCount > 0) 
        ? `<div class="unread-counter">${unreadCount > 99 ? '99+' : unreadCount}</div>` 
        : '';

    const previewStyle = (unreadCount > 0) ? 'font-weight: 700; color: #000;' : '';
    
    const role = item.role || 'user'; 
    const shapeClass = isPrivate ? '' : 'community-shape'; 

    const isPinned = parseInt(item.is_pinned) === 1;
    const isFavorite = parseInt(item.is_favorite) === 1;
    const isBlocked = parseInt(item.is_blocked_by_me) > 0;
    const isArchived = parseInt(item.is_archived) === 1;
    
    const friendStatus = item.friend_status || 'none';
    
    let indicatorsIcons = '';
    if (isFavorite) indicatorsIcons += '<span class="material-symbols-rounded icon-indicator favorite">star</span>';
    if (isPinned) indicatorsIcons += '<span class="material-symbols-rounded icon-indicator pinned">push_pin</span>';
   
    const pinnedAttr = isPinned ? 'true' : 'false';
    const favAttr = isFavorite ? 'true' : 'false';
    const blockedAttr = isBlocked ? 'true' : 'false';
    const archivedAttr = isArchived ? 'true' : 'false';

    const actionType = isPrivate ? 'select-chat' : 'enter-community';

    // Badge de Verificación
    const verifiedBadge = (!isPrivate && parseInt(item.is_verified) === 1) 
        ? `<span class="material-symbols-rounded" style="font-size:14px; color:#1976d2; margin-left:4px; flex-shrink:0;" title="Oficial">verified</span>` 
        : '';

    return `
    <div class="chat-item-wrapper" data-uuid="${item.uuid}">
        <div class="chat-item ${isActive}" 
             data-action="${actionType}" 
             data-uuid="${item.uuid}" 
             data-type="${item.type}"
             data-pinned="${pinnedAttr}" 
             data-fav="${favAttr}" 
             data-blocked="${blockedAttr}"
             data-archived="${archivedAttr}" 
             data-friend-status="${friendStatus}">
            
            <div class="chat-item-avatar-wrapper ${shapeClass}" data-role="${role}">
                <img src="${avatarSrc}" alt="Avatar" data-img-type="${isPrivate ? 'user' : 'community'}">
            </div>

            <div class="chat-item-info">
                <div class="chat-item-top">
                    <div style="display:flex; align-items:center; overflow:hidden; flex:1; margin-right:4px;">
                        <span class="chat-item-name" style="flex:0 1 auto; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(item.name)}</span>
                        ${verifiedBadge}
                    </div>
                    <span class="chat-item-time" style="flex-shrink:0;">${time}</span>
                </div>
                
                <div class="chat-item-bottom">
                    <span class="chat-item-preview" style="${previewStyle}">${lastMsg}</span>
                    
                    <div class="chat-item-actions">
                        ${badgeHtml}
                        ${indicatorsIcons}
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

// --- RENDERIZADO DEL RAIL LATERAL ---

export function renderRailIcons(items, currentFilter, activeChatUuid, onClickCallback) {
    const container = document.getElementById('rail-communities-list');
    if (!container) return;

    container.innerHTML = '';

    const filteredItems = items.filter(item => {
        const isArchived = parseInt(item.is_archived) === 1;
        
        if (currentFilter === 'archived') return isArchived;
        if (isArchived) return false;

        if (currentFilter === 'unread') return parseInt(item.unread_count) > 0;
        if (currentFilter === 'community') return (item.type === 'community');
        if (currentFilter === 'private') return (item.type === 'private');
        if (currentFilter === 'favorites') return (parseInt(item.is_favorite) === 1);
        
        return true; 
    });

    if (filteredItems.length === 0) return;

    filteredItems.forEach(item => {
        const avatarSrc = item.profile_picture ? 
            (window.BASE_PATH || '/ProjectAurora/') + item.profile_picture : 
            'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.name);
            
        const isActive = (item.uuid === activeChatUuid) ? 'active' : '';
        const unreadCount = parseInt(item.unread_count || 0);
        
        let badgeHtml = '';
        if (unreadCount > 0) {
            const countDisplay = unreadCount > 99 ? '99+' : unreadCount;
            badgeHtml = `
            <div style="
                position: absolute; top: -2px; right: -2px; background-color: #1976d2; color: #fff;
                font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px;
                display: flex; align-items: center; justify-content: center; padding: 0 4px;
                border: 2px solid #fff; z-index: 15; pointer-events: none; box-shadow: 0 1px 2px rgba(0,0,0,0.2);
            ">${countDisplay}</div>`;
        }

        const div = document.createElement('div');
        div.className = `rail-item ${isActive}`;
        div.dataset.uuid = item.uuid;
        div.dataset.type = item.type;
        div.title = item.name;

        const imgType = (item.type === 'private') ? 'user' : 'community';

        div.innerHTML = `
            <div class="rail-avatar">
                <img src="${avatarSrc}" alt="${escapeHtml(item.name)}" data-img-type="${imgType}">
            </div>
            ${badgeHtml}
        `;
        
        // Asignamos el evento click delegando la lógica al manager via callback
        div.addEventListener('click', () => {
            document.querySelectorAll('.rail-item').forEach(el => el.classList.remove('active'));
            div.classList.add('active');
            if (onClickCallback) onClickCallback(item);
        });

        container.appendChild(div);
    });
}

// --- RENDERIZADO DE LA LISTA LATERAL PRINCIPAL ---

export function restoreMainHeader() {
    const titleEl = document.querySelector('.chat-sidebar-title');
    const actionsEl = document.querySelector('.chat-sidebar-actions');
    const searchEl = document.querySelector('.chat-sidebar-search');
    const badgesEl = document.querySelector('.chat-sidebar-badges');

    if (titleEl) titleEl.textContent = 'Chats';
    if (actionsEl) actionsEl.style.display = 'flex';
    if (searchEl) searchEl.style.display = 'block';
    if (badgesEl) badgesEl.style.display = 'grid';
}

export function renderSidebarList(items, currentFilter, currentSearchQuery, activeChatUuid) {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    restoreMainHeader();
    container.innerHTML = '';

    const filteredItems = items.filter(item => {
        const isArchived = parseInt(item.is_archived) === 1;
        
        if (currentFilter === 'archived') {
            let passesSearch = true;
            if (currentSearchQuery) passesSearch = item.name.toLowerCase().includes(currentSearchQuery.toLowerCase());
            return isArchived && passesSearch;
        }

        if (isArchived) return false;

        let passesBadge = true;
        if (currentFilter === 'unread') passesBadge = parseInt(item.unread_count) > 0;
        else if (currentFilter === 'community') passesBadge = (item.type === 'community');
        else if (currentFilter === 'private') passesBadge = (item.type === 'private');
        else if (currentFilter === 'favorites') passesBadge = (parseInt(item.is_favorite) === 1);

        let passesSearch = true;
        if (currentSearchQuery) passesSearch = item.name.toLowerCase().includes(currentSearchQuery.toLowerCase());

        return passesBadge && passesSearch;
    });

    if (filteredItems.length > 0) {
        container.innerHTML = filteredItems.map(c => renderChatListItem(c, activeChatUuid)).join('');
    } else {
        let msg = 'No hay chats que mostrar.';
        if (currentFilter === 'unread') msg = 'No tienes mensajes sin leer.';
        if (currentFilter === 'favorites') msg = 'No tienes favoritos aún.';
        if (currentFilter === 'archived') msg = 'No tienes chats archivados.';
        if (currentSearchQuery) msg = 'No se encontraron resultados.';
        container.innerHTML = `<p style="text-align:center; color:#999; padding:20px; font-size:13px;">${msg}</p>`;
    }
}

// --- HEADER DE COMUNIDAD ---

export function setupCommunityHeader(communityItem) {
    const titleEl = document.querySelector('.chat-sidebar-title');
    const actionsEl = document.querySelector('.chat-sidebar-actions');
    const searchEl = document.querySelector('.chat-sidebar-search');
    const badgesEl = document.querySelector('.chat-sidebar-badges');

    if (actionsEl) actionsEl.style.display = 'none';
    if (searchEl) searchEl.style.display = 'none';
    if (badgesEl) badgesEl.style.display = 'none';

    if (titleEl) {
        const avatarSrc = communityItem.profile_picture ? 
            (window.BASE_PATH || '/ProjectAurora/') + communityItem.profile_picture : 
            'https://ui-avatars.com/api/?name=' + encodeURIComponent(communityItem.name);
            
        const verifiedBadge = (parseInt(communityItem.is_verified) === 1) 
            ? `<span class="material-symbols-rounded" style="font-size:16px; color:#1976d2; margin-left:4px;" title="Oficial">verified</span>` 
            : '';

        titleEl.innerHTML = `
            <div style="display:flex; align-items:center; gap:12px; width:100%;">
                <button class="component-icon-button" id="btn-sidebar-back" style="width:32px; height:32px; border:none;">
                    <span class="material-symbols-rounded">arrow_back</span>
                </button>
                <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">
                    <img src="${avatarSrc}" style="width:32px; height:32px; border-radius:8px; object-fit:cover; flex-shrink:0;" data-img-type="community">
                    <div style="display:flex; align-items:center; overflow:hidden;">
                        <span style="font-size:16px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(communityItem.name)}</span>
                        ${verifiedBadge}
                    </div>
                </div>
            </div>
        `;
    }
}