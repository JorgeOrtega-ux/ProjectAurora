// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';
import { openChat } from './chat-manager.js';

let sidebarItems = []; // Lista completa de items descargados
let currentFilter = 'all'; // Filtro de badge activo
let currentSearchQuery = ''; // Búsqueda actual

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

    // Indicadores visuales
    const isPinned = parseInt(item.is_pinned) === 1;
    const isFavorite = parseInt(item.is_favorite) === 1;
    
    let indicatorsHtml = '';
    if (isPinned || isFavorite) {
        indicatorsHtml = '<div class="chat-item-indicators">';
        if (isFavorite) indicatorsHtml += '<span class="material-symbols-rounded icon-indicator favorite">star</span>';
        if (isPinned) indicatorsHtml += '<span class="material-symbols-rounded icon-indicator pinned">push_pin</span>';
        indicatorsHtml += '</div>';
    }

    // Datos extra para el menú
    const pinnedAttr = isPinned ? 'true' : 'false';
    const favAttr = isFavorite ? 'true' : 'false';

    return `
    <div class="chat-item ${isActive}" data-action="select-chat" data-uuid="${item.uuid}" data-type="${item.type}">
        
        <div class="chat-item-avatar-wrapper ${shapeClass}" data-role="${role}">
            <img src="${avatarSrc}" alt="Avatar">
        </div>

        <div class="chat-item-info">
            <div class="chat-item-top">
                <span class="chat-item-name">${escapeHtml(item.name)}</span>
                <span class="chat-item-time">${time}</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span class="chat-item-preview" style="${previewStyle}">${lastMsg}</span>
                ${badgeHtml}
            </div>
        </div>

        ${indicatorsHtml}

        <button class="chat-hover-btn" 
            data-action="open-chat-menu" 
            data-uuid="${item.uuid}" 
            data-type="${item.type}"
            data-pinned="${pinnedAttr}"
            data-fav="${favAttr}">
            <span class="material-symbols-rounded">expand_more</span>
        </button>
    </div>`;
}

// ==========================================
// FILTRADO Y RENDERIZADO DE LA LISTA
// ==========================================

function renderSidebarList() {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    container.innerHTML = '';

    // 1. Filtrar
    const filteredItems = sidebarItems.filter(item => {
        // A. Filtro por Badge (Pestaña)
        let passesBadge = true;
        if (currentFilter === 'unread') {
            passesBadge = parseInt(item.unread_count) > 0;
        } else if (currentFilter === 'community') {
            passesBadge = (item.type === 'community');
        } else if (currentFilter === 'private') {
            passesBadge = (item.type === 'private');
        } else if (currentFilter === 'favorites') {
            passesBadge = (parseInt(item.is_favorite) === 1);
        }

        // B. Filtro por Búsqueda (Input) - Se aplica SOBRE el filtro de badge
        let passesSearch = true;
        if (currentSearchQuery) {
            passesSearch = item.name.toLowerCase().includes(currentSearchQuery.toLowerCase());
        }

        return passesBadge && passesSearch;
    });

    // 2. Renderizar
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

// ==========================================
// CARGA DE DATOS
// ==========================================

async function loadSidebarList() {
    const res = await postJson('api/communities_handler.php', { action: 'get_sidebar_list' });
    
    if (res.success) {
        sidebarItems = res.list;
        renderSidebarList(); // Renderizar aplicando filtros actuales
    } else {
        sidebarItems = [];
        renderSidebarList();
    }

    if (window.ACTIVE_CHAT_UUID) {
        const itemData = sidebarItems.find(c => c.uuid === window.ACTIVE_CHAT_UUID);
        openChat(window.ACTIVE_CHAT_UUID, itemData || null);
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
// GESTIÓN DE MENÚ FLOTANTE CONTEXTUAL
// ==========================================

function showChatMenu(btn, uuid, type, isPinned, isFav) {
    document.querySelector('.chat-popover-menu')?.remove();

    const pinText = isPinned ? 'Desfijar chat' : 'Fijar chat';
    const pinIconStyle = isPinned ? 'color:#1976d2;' : '';
    
    const favText = isFav ? 'Quitar favorito' : 'Marcar favorito';
    const favIconStyle = isFav ? 'color:#fbc02d;' : '';

    let specificOptions = '';
    let deleteOption = '';

    if (type === 'private') {
        specificOptions = `
            <div class="chat-popover-item danger">
                <span class="material-symbols-rounded">block</span> Bloquear
            </div>
            <div class="chat-popover-item danger">
                <span class="material-symbols-rounded">person_remove</span> Eliminar amigo
            </div>
        `;
        deleteOption = `
            <div class="chat-popover-item danger" data-action="delete-chat-conversation" data-uuid="${uuid}">
                <span class="material-symbols-rounded">delete</span> Eliminar chat
            </div>
        `;
    } else {
        specificOptions = `
            <div class="chat-popover-item danger" data-action="leave-community" data-uuid="${uuid}">
                <span class="material-symbols-rounded">logout</span> Abandonar grupo
            </div>
        `;
    }

    const menu = document.createElement('div');
    menu.className = 'chat-popover-menu';
    menu.innerHTML = `
        <div class="chat-popover-item" data-action="toggle-pin-chat" data-uuid="${uuid}" data-type="${type}">
            <span class="material-symbols-rounded" style="${pinIconStyle}">push_pin</span> ${pinText}
        </div>
        <div class="chat-popover-item" data-action="toggle-fav-chat" data-uuid="${uuid}" data-type="${type}">
            <span class="material-symbols-rounded" style="${favIconStyle}">star</span> ${favText}
        </div>
        ${deleteOption}
        <div class="chat-popover-separator"></div>
        ${specificOptions}
    `;

    const rect = btn.getBoundingClientRect();
    menu.style.top = (rect.bottom + 5) + 'px';
    
    if (rect.right + 200 > window.innerWidth) {
        menu.style.right = (window.innerWidth - rect.right) + 'px';
    } else {
        menu.style.left = (rect.left - 150) + 'px';
    }

    document.body.appendChild(menu);
    btn.classList.add('active'); 

    setTimeout(() => {
        const closeHandler = (e) => {
            if (!menu.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                menu.remove();
                btn.classList.remove('active');
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 0);
}

// --- ACCIONES DE MENÚ ---

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

// ==========================================
// MANEJO DE FILTROS Y BÚSQUEDA
// ==========================================

function initSidebarFilters() {
    const container = document.querySelector('.chat-sidebar-badges');
    const searchInput = document.getElementById('sidebar-search-input');

    // 1. Listeners para Badges
    if (container) {
        container.addEventListener('click', (e) => {
            const badge = e.target.closest('.sidebar-badge');
            if (badge) {
                // Actualizar visual
                container.querySelectorAll('.sidebar-badge').forEach(b => b.classList.remove('active'));
                badge.classList.add('active');

                // Actualizar estado
                currentFilter = badge.dataset.filter || 'all';
                
                // Renderizar
                renderSidebarList();
            }
        });
    }

    // 2. Listener para Input de Búsqueda
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.trim();
            renderSidebarList();
        });
    }
}

// ==========================================
// LISTENERS GENERALES
// ==========================================

function initListListeners() {
    document.getElementById('my-communities-list')?.addEventListener('click', (e) => {
        const chatMenuBtn = e.target.closest('[data-action="open-chat-menu"]');
        if (chatMenuBtn) {
            e.preventDefault();
            e.stopPropagation(); 
            const isPinned = chatMenuBtn.dataset.pinned === 'true';
            const isFav = chatMenuBtn.dataset.fav === 'true';
            showChatMenu(chatMenuBtn, chatMenuBtn.dataset.uuid, chatMenuBtn.dataset.type, isPinned, isFav);
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
        // Acciones públicas
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
        }

        // Acciones del menú
        const pinAction = e.target.closest('[data-action="toggle-pin-chat"]');
        if (pinAction) {
            document.querySelector('.chat-popover-menu')?.remove();
            await togglePinChat(pinAction.dataset.uuid, pinAction.dataset.type);
        }

        const favAction = e.target.closest('[data-action="toggle-fav-chat"]');
        if (favAction) {
            document.querySelector('.chat-popover-menu')?.remove();
            await toggleFavChat(favAction.dataset.uuid, favAction.dataset.type);
        }

        const leaveAction = e.target.closest('[data-action="leave-community"]');
        if (leaveAction) {
            document.querySelector('.chat-popover-menu')?.remove();
            await leaveCommunity(leaveAction.dataset.uuid);
        }
        
        // [NUEVO] Acción Eliminar Chat Privado
        const deleteChatAction = e.target.closest('[data-action="delete-chat-conversation"]');
        if (deleteChatAction) {
            e.preventDefault();
            const uuid = deleteChatAction.dataset.uuid;
            document.querySelector('.chat-popover-menu')?.remove();
            
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
        }
    });

    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'new_chat_message' || type === 'private_message') {
            loadSidebarList(); // Recargar siempre para actualizar orden
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
    loadSidebarList(); 
    loadPublicCommunities(); 
    initJoinByCode(); 
    initSidebarFilters(); // [NUEVO] Inicializar los filtros de sidebar
    
    if (!window.communitiesListenersInit) {
        initListListeners();
        window.communitiesListenersInit = true;
    }
}