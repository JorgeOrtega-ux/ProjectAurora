// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';
import { openChat } from './chat-manager.js';

let sidebarItems = []; 
let currentFilter = 'all'; 
let currentSearchQuery = ''; 

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

// ==========================================
// RENDERIZADO DE UN ITEM
// ==========================================

function renderChatListItem(item) {
    const isPrivate = (item.type === 'private');
    
    const avatarSrc = item.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + item.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.name);
        
    const isActive = (item.uuid === window.ACTIVE_CHAT_UUID) ? 'active' : '';
    
    let lastMsg = item.last_message ? escapeHtml(item.last_message) : (item.last_message_at ? 'Imagen' : (isPrivate ? "Nuevo chat" : "Haz clic para entrar"));
    if (isPrivate && !item.last_message && !item.last_message_at) lastMsg = "Iniciar conversación";

    const time = item.last_message_at ? formatChatTime(item.last_message_at) : '';
    const unreadCount = parseInt(item.unread_count || 0);
    
    const badgeHtml = (unreadCount > 0 && isActive === '') 
        ? `<div class="unread-counter">${unreadCount > 99 ? '99+' : unreadCount}</div>` 
        : '';

    const previewStyle = (unreadCount > 0 && isActive === '') ? 'font-weight: 700; color: #000;' : '';
    
    const role = item.role || 'user'; 
    const shapeClass = isPrivate ? '' : 'community-shape'; 

    const isPinned = parseInt(item.is_pinned) === 1;
    const isFavorite = parseInt(item.is_favorite) === 1;
    const isBlocked = parseInt(item.is_blocked_by_me) > 0;
    
    let indicatorsIcons = '';
    if (isFavorite) indicatorsIcons += '<span class="material-symbols-rounded icon-indicator favorite">star</span>';
    if (isPinned) indicatorsIcons += '<span class="material-symbols-rounded icon-indicator pinned">push_pin</span>';
   
    const pinnedAttr = isPinned ? 'true' : 'false';
    const favAttr = isFavorite ? 'true' : 'false';
    const blockedAttr = isBlocked ? 'true' : 'false';

    return `
    <div class="chat-item ${isActive}" 
         id="sidebar-item-${item.uuid}" 
         data-action="select-chat" 
         data-uuid="${item.uuid}" 
         data-type="${item.type}"
         data-pinned="${pinnedAttr}" 
         data-fav="${favAttr}" 
         data-blocked="${blockedAttr}">
        
        <div class="chat-item-avatar-wrapper ${shapeClass}" data-role="${role}">
            <img src="${avatarSrc}" alt="Avatar">
        </div>

        <div class="chat-item-info">
            <div class="chat-item-top">
                <span class="chat-item-name">${escapeHtml(item.name)}</span>
                <span class="chat-item-time">${time}</span>
            </div>
            
            <div class="chat-item-bottom">
                <span class="chat-item-preview" style="${previewStyle}">${lastMsg}</span>
                
                <div class="chat-item-actions">
                    ${badgeHtml}
                    ${indicatorsIcons}
                    </div>
            </div>
        </div>
    </div>`;
}

function renderSidebarList() {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    container.innerHTML = '';

    const filteredItems = sidebarItems.filter(item => {
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
        container.innerHTML = filteredItems.map(c => renderChatListItem(c)).join('');
    } else {
        let msg = 'No hay chats que mostrar.';
        if (currentFilter === 'unread') msg = 'No tienes mensajes sin leer.';
        if (currentFilter === 'favorites') msg = 'No tienes favoritos aún.';
        if (currentSearchQuery) msg = 'No se encontraron resultados.';
        container.innerHTML = `<p style="text-align:center; color:#999; padding:20px; font-size:13px;">${msg}</p>`;
    }
}

function handleSidebarUpdate(payload) {
    const uuid = payload.community_uuid || payload.target_uuid;
    const container = document.getElementById('my-communities-list');
    const existingItem = container.querySelector(`.chat-item[data-uuid="${uuid}"]`);

    if (existingItem) {
        const previewEl = existingItem.querySelector('.chat-item-preview');
        const timeEl = existingItem.querySelector('.chat-item-time');
        
        let messageText = payload.message;
        if (payload.type === 'image' || (payload.attachments && payload.attachments.length > 0)) {
            messageText = '📷 Imagen';
        }
        
        if (payload.context === 'community' && payload.sender_id != window.USER_ID) {
            messageText = `${payload.sender_username}: ${messageText}`;
        } else if (payload.sender_id == window.USER_ID) {
            messageText = `Tú: ${messageText}`;
        }

        if (previewEl) {
            previewEl.textContent = messageText;
            if (uuid !== window.ACTIVE_CHAT_UUID) {
                previewEl.style.fontWeight = '700';
                previewEl.style.color = '#000';
            } else {
                previewEl.style.fontWeight = 'normal';
                previewEl.style.color = '';
            }
        }

        if (timeEl) {
            timeEl.textContent = formatChatTime(new Date());
        }

        const isActiveChat = (uuid === window.ACTIVE_CHAT_UUID);
        const windowHasFocus = document.hasFocus();

        if (!isActiveChat || (isActiveChat && !windowHasFocus)) {
            let badge = existingItem.querySelector('.unread-counter');
            if (!badge) {
                const actionsContainer = existingItem.querySelector('.chat-item-actions');
                if (actionsContainer) {
                    badge = document.createElement('div');
                    badge.className = 'unread-counter';
                    badge.textContent = '1';
                    actionsContainer.prepend(badge);
                }
            } else {
                let count = parseInt(badge.textContent) || 0;
                badge.textContent = count + 1 > 99 ? '99+' : count + 1;
            }
        }

        if (existingItem.style.display !== 'none') {
            container.prepend(existingItem);
        }

        const dataItem = sidebarItems.find(i => i.uuid === uuid);
        if (dataItem) {
            dataItem.last_message = messageText;
            dataItem.last_message_at = new Date().toISOString();
            if (!isActiveChat || (isActiveChat && !windowHasFocus)) {
                dataItem.unread_count = (parseInt(dataItem.unread_count) || 0) + 1;
            }
            sidebarItems = sidebarItems.filter(i => i.uuid !== uuid);
            sidebarItems.unshift(dataItem);
        }

    } else {
        loadSidebarList(false);
    }
}

async function loadSidebarList(shouldOpenActive = false) {
    const res = await postJson('api/communities_handler.php', { action: 'get_sidebar_list' });
    
    if (res.success) {
        sidebarItems = res.list;
        renderSidebarList();
    } else {
        sidebarItems = [];
        renderSidebarList();
    }

    if (window.ACTIVE_CHAT_UUID) {
        const itemData = sidebarItems.find(c => c.uuid === window.ACTIVE_CHAT_UUID);
        if (itemData) {
            const activeEl = document.querySelector(`.chat-item[data-uuid="${window.ACTIVE_CHAT_UUID}"]`);
            if (activeEl) {
                activeEl.classList.add('active');
                activeEl.querySelector('.unread-counter')?.remove();
            }
        }
        if (shouldOpenActive) {
            openChat(window.ACTIVE_CHAT_UUID, itemData || null);
        }
    }
}

function renderCommunityCard(comm, isJoined) {
    const avatar = comm.profile_picture ? (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
    const bannerStyle = comm.banner_picture ? `background-image: url('${(window.BASE_PATH || '/ProjectAurora/') + comm.banner_picture}');` : 'background-color: #eee;';
    let actionBtn = isJoined ? `<button class="component-button" disabled>Unido</button>` : `<button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">Unirse</button>`;
    return `<div class="comm-card"><div class="comm-banner" style="${bannerStyle}"></div><div class="comm-content"><div class="comm-header-row"><div class="comm-avatar-container"><img src="${avatar}" class="comm-avatar-img" alt="${escapeHtml(comm.community_name)}"></div><div class="comm-actions">${actionBtn}</div></div><div class="comm-info"><h3 class="comm-title">${escapeHtml(comm.community_name)}</h3><div class="comm-badges"><span class="comm-badge"><span class="material-symbols-rounded" style="font-size:14px; margin-right:4px;">group</span>${comm.member_count} miembros</span><span class="comm-badge">Publico</span></div><p class="comm-desc" style="margin-top:8px;">${escapeHtml(comm.description || 'Sin descripción')}</p></div></div></div>`;
}

async function loadPublicCommunities() {
    const container = document.getElementById('public-communities-list');
    if (!container) return;
    const res = await postJson('api/communities_handler.php', { action: 'get_public_communities' });
    if (res.success && res.communities.length > 0) container.innerHTML = res.communities.map(c => renderCommunityCard(c, false)).join('');
    else container.innerHTML = `<p style="text-align:center; color:#999; grid-column:1/-1;">No hay comunidades públicas disponibles.</p>`;
}

// ==========================================
// GESTIÓN DE MENÚ FLOTANTE Y HOVER
// ==========================================

function showChatMenu(btn, uuid, type, isPinned, isFav, isBlocked) {
    document.querySelector('.dynamic-popover')?.remove();

    const pinText = isPinned ? 'Desfijar chat' : 'Fijar chat';
    const pinIconStyle = isPinned ? 'color:#1976d2;' : '';
    
    const favText = isFav ? 'Quitar favorito' : 'Marcar favorito';
    const favIconStyle = isFav ? 'color:#fbc02d;' : '';

    let specificOptions = '';
    let deleteOption = '';

    const createItem = (action, icon, text, style = '', danger = false) => {
        const textColor = danger ? 'color: #d32f2f;' : '';
        const iconColor = danger ? 'color: #d32f2f;' : 'color: #333;';
        const finalIconStyle = style ? style : iconColor;

        return `
        <div class="menu-link" data-action="${action}" data-uuid="${uuid}" data-type="${type}">
            <div class="menu-link-icon">
                <span class="material-symbols-rounded" style="${finalIconStyle}">${icon}</span>
            </div>
            <div class="menu-link-text" style="${textColor}">${text}</div>
        </div>`;
    };

    if (type === 'private') {
        if (isBlocked) {
            specificOptions = `
                ${createItem('unblock-user-chat', 'lock_open', 'Desbloquear', '', true)}
                ${createItem('remove-friend-chat', 'person_remove', 'Eliminar amigo', '', true)}
            `;
        } else {
            specificOptions = `
                ${createItem('block-user-chat', 'block', t('friends.block_user') || 'Bloquear', '', true)}
                ${createItem('remove-friend-chat', 'person_remove', 'Eliminar amigo', '', true)}
            `;
        }
        deleteOption = createItem('delete-chat-conversation', 'delete', 'Eliminar chat', '', true);
    } else {
        specificOptions = createItem('leave-community', 'logout', 'Abandonar grupo', '', true);
    }

    const menu = document.createElement('div');
    menu.className = 'popover-module dynamic-popover body-title active';
    
    menu.innerHTML = `
        <div class="menu-content">
            <div class="menu-list">
                ${createItem('toggle-pin-chat', 'push_pin', pinText, pinIconStyle)}
                ${createItem('toggle-fav-chat', 'star', favText, favIconStyle)}
                ${deleteOption}
                <div class="component-divider" style="margin: 4px 0;"></div>
                ${specificOptions}
            </div>
        </div>
    `;

    // [CAMBIO IMPORTANTE] Se inyecta dentro del contenedor de acciones
    const container = btn.parentElement; 
    container.appendChild(menu);
    btn.classList.add('active'); 

    setTimeout(() => {
        const closeHandler = (e) => {
            if (!menu.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                menu.remove();
                btn.classList.remove('active');
                if (!btn.matches(':hover') && !btn.closest('.chat-item:hover')) {
                    btn.remove();
                }
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 0);
}

async function togglePinChat(uuid, type) {
    const res = await postJson('api/communities_handler.php', { action: 'toggle_pin', uuid, type });
    if (res.success) loadSidebarList(); 
    else if(window.alertManager) window.alertManager.showAlert(res.message, 'warning');
}

async function toggleFavChat(uuid, type) {
    const res = await postJson('api/communities_handler.php', { action: 'toggle_favorite', uuid, type });
    if (res.success) loadSidebarList(); 
    else if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
}

async function leaveCommunity(uuid) {
    if (!confirm('¿Estás seguro de que quieres salir de este grupo?')) return;
    const res = await postJson('api/communities_handler.php', { action: 'leave_community', uuid: uuid });
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (window.ACTIVE_CHAT_UUID === uuid) {
             window.ACTIVE_CHAT_UUID = null;
             if(window.navigateTo) window.navigateTo('main'); else window.location.href = window.BASE_PATH + 'main';
        } else {
            loadSidebarList();
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

async function blockUserFromChat(uuid) {
    if (!confirm(t('friends.confirm_block') || '¿Seguro que quieres bloquear a este usuario?')) return;
    const resInfo = await postJson('api/communities_handler.php', { action: 'get_user_chat_by_uuid', uuid: uuid });
    
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const resBlock = await postJson('api/friends_handler.php', { action: 'block_user', target_id: targetId });
        
        if (resBlock.success) {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'success');
            window.location.reload(); 
        } else {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'error');
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert("No se pudo identificar al usuario.", 'error');
    }
}

async function unblockUserFromChat(uuid) {
    const resInfo = await postJson('api/communities_handler.php', { action: 'get_user_chat_by_uuid', uuid: uuid });
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const resUnblock = await postJson('api/friends_handler.php', { action: 'unblock_user', target_id: targetId });
        
        if (resUnblock.success) {
            if(window.alertManager) window.alertManager.showAlert(resUnblock.message, 'success');
            window.location.reload(); 
        } else {
            if(window.alertManager) window.alertManager.showAlert(resUnblock.message, 'error');
        }
    }
}

function initSidebarFilters() {
    const container = document.querySelector('.chat-sidebar-badges');
    const searchInput = document.getElementById('sidebar-search-input');

    if (container) {
        container.addEventListener('click', (e) => {
            const badge = e.target.closest('.sidebar-badge');
            if (badge) {
                container.querySelectorAll('.sidebar-badge').forEach(b => b.classList.remove('active'));
                badge.classList.add('active');
                currentFilter = badge.dataset.filter || 'all';
                renderSidebarList();
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.trim();
            renderSidebarList();
        });
    }
}

// ==========================================
// LISTENERS (HOVER Y CLICS)
// ==========================================

function initListListeners() {
    const listContainer = document.getElementById('my-communities-list');

    // Inyección dinámica del botón al entrar con el mouse
    listContainer?.addEventListener('mouseover', (e) => {
        const item = e.target.closest('.chat-item');
        if (!item) return;

        const actionsDiv = item.querySelector('.chat-item-actions');
        if (!actionsDiv || actionsDiv.querySelector('.chat-hover-btn')) return;

        const btn = document.createElement('button');
        btn.className = 'chat-hover-btn';
        btn.dataset.action = 'open-chat-menu';
        
        btn.dataset.uuid = item.dataset.uuid;
        btn.dataset.type = item.dataset.type;
        btn.dataset.pinned = item.dataset.pinned;
        btn.dataset.fav = item.dataset.fav;
        btn.dataset.blocked = item.dataset.blocked;
        
        btn.innerHTML = '<span class="material-symbols-rounded">expand_more</span>';
        
        actionsDiv.appendChild(btn);
    });

    // Eliminación del botón al salir con el mouse
    listContainer?.addEventListener('mouseout', (e) => {
        const item = e.target.closest('.chat-item');
        if (!item) return;

        if (item.contains(e.relatedTarget)) return;

        const btn = item.querySelector('.chat-hover-btn');
        if (btn && !btn.classList.contains('active')) {
            btn.remove();
        }
    });

    document.getElementById('my-communities-list')?.addEventListener('click', (e) => {
        const chatMenuBtn = e.target.closest('[data-action="open-chat-menu"]');
        if (chatMenuBtn) {
            e.preventDefault();
            e.stopPropagation(); 
            const isPinned = chatMenuBtn.dataset.pinned === 'true';
            const isFav = chatMenuBtn.dataset.fav === 'true';
            const isBlocked = chatMenuBtn.dataset.blocked === 'true';
            showChatMenu(chatMenuBtn, chatMenuBtn.dataset.uuid, chatMenuBtn.dataset.type, isPinned, isFav, isBlocked);
            return;
        }

        const item = e.target.closest('.chat-item');
        if (item) {
            const uuid = item.dataset.uuid;
            const type = item.dataset.type; 
            
            const itemData = sidebarItems.find(c => c.uuid === uuid);
            if(itemData && !itemData.type) itemData.type = type;
            
            openChat(uuid, itemData);
        }
    });

    document.body.addEventListener('click', async (e) => {
        const joinBtn = e.target.closest('[data-action="join-public-community"]');
        if (joinBtn) {
            const id = joinBtn.dataset.id;
            setButtonLoading(joinBtn, true);
            const res = await postJson('api/communities_handler.php', { action: 'join_public', community_id: id });
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                const card = joinBtn.closest('.comm-card');
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
                loadSidebarList(); 
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false, 'Unirse');
            }
            return;
        }

        const blockBtn = e.target.closest('[data-action="block-user-chat"]');
        if (blockBtn) {
            e.preventDefault();
            document.querySelector('.dynamic-popover')?.remove();
            await blockUserFromChat(blockBtn.dataset.uuid);
            return;
        }

        const unblockBtn = e.target.closest('[data-action="unblock-user-chat"]');
        if (unblockBtn) {
            e.preventDefault();
            document.querySelector('.dynamic-popover')?.remove();
            await unblockUserFromChat(unblockBtn.dataset.uuid);
            return;
        }

        const pinAction = e.target.closest('[data-action="toggle-pin-chat"]');
        if (pinAction) {
            document.querySelector('.dynamic-popover')?.remove();
            await togglePinChat(pinAction.dataset.uuid, pinAction.dataset.type);
            return;
        }

        const favAction = e.target.closest('[data-action="toggle-fav-chat"]');
        if (favAction) {
            document.querySelector('.dynamic-popover')?.remove();
            await toggleFavChat(favAction.dataset.uuid, favAction.dataset.type);
            return;
        }

        const leaveAction = e.target.closest('[data-action="leave-community"]');
        if (leaveAction) {
            document.querySelector('.dynamic-popover')?.remove();
            await leaveCommunity(leaveAction.dataset.uuid);
            return;
        }
        
        const deleteChatAction = e.target.closest('[data-action="delete-chat-conversation"]');
        if (deleteChatAction) {
            e.preventDefault();
            const uuid = deleteChatAction.dataset.uuid;
            document.querySelector('.dynamic-popover')?.remove();
            
            if(!confirm('¿Seguro que quieres eliminar este chat? Solo se borrará para ti.')) return;
            const res = await postJson('api/chat_handler.php', { action: 'delete_conversation', target_uuid: uuid });
            if(res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                if (window.ACTIVE_CHAT_UUID === uuid) {
                     window.ACTIVE_CHAT_UUID = null;
                     if(window.navigateTo) window.navigateTo('main'); else window.location.href = window.BASE_PATH + 'main';
                } else {
                    loadSidebarList();
                }
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            return;
        }
    });

    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'new_chat_message' || type === 'private_message') {
            handleSidebarUpdate(payload);
        }
    });

    document.addEventListener('local-chat-read', (e) => {
        const uuid = e.detail.uuid;
        const item = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
        
        if (item) {
            const badge = item.querySelector('.unread-counter');
            if (badge) badge.remove();
            
            const preview = item.querySelector('.chat-item-preview');
            if (preview) {
                preview.style.fontWeight = 'normal';
                preview.style.color = '';
            }
            
            const dataItem = sidebarItems.find(i => i.uuid === uuid);
            if (dataItem) dataItem.unread_count = 0;
        }
    });
}

function initJoinByCode() {
    const btn = document.querySelector('[data-action="submit-join-community"]');
    if (!btn) return;
    const input = document.querySelector('[data-input="community-code"]');
    
    if(input) {
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            if (v.length > 12) v = v.slice(0, 12);
            const parts = [];
            if (v.length > 0) parts.push(v.slice(0, 4));
            if (v.length > 4) parts.push(v.slice(4, 8));
            if (v.length > 8) parts.push(v.slice(8, 12));
            e.target.value = parts.join('-');
        });
    }

    btn.onclick = async () => {
        if (input.value.length < 14) return alert('Código incompleto.');
        setButtonLoading(btn, true);
        const res = await postJson('api/communities_handler.php', { action: 'join_by_code', access_code: input.value });
        if (res.success) {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
            if(window.navigateTo) window.navigateTo('main'); else window.location.href = window.BASE_PATH + 'main';
        } else {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false, 'Unirse');
        }
    };
}

export function initCommunitiesManager() {
    loadSidebarList(true); 
    
    loadPublicCommunities(); 
    initJoinByCode(); 
    initSidebarFilters(); 
    
    if (!window.communitiesListenersInit) {
        initListListeners();
        window.communitiesListenersInit = true;
    }
}