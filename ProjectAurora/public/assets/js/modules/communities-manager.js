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
    <div class="chat-item ${isActive}" id="sidebar-item-${item.uuid}" data-action="select-chat" data-uuid="${item.uuid}" data-type="${item.type}">
        
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
// ACTUALIZACIÓN PARCIAL (DOM OPTIMIZADO)
// ==========================================

function handleSidebarUpdate(payload) {
    const uuid = payload.community_uuid || payload.target_uuid;
    const container = document.getElementById('my-communities-list');
    const existingItem = container.querySelector(`.chat-item[data-uuid="${uuid}"]`);

    // 1. Si el item ya existe en el DOM, lo actualizamos y movemos
    if (existingItem) {
        // A. Actualizar texto del mensaje
        const previewEl = existingItem.querySelector('.chat-item-preview');
        const timeEl = existingItem.querySelector('.chat-item-time');
        
        let messageText = payload.message;
        if (payload.type === 'image' || (payload.attachments && payload.attachments.length > 0)) {
            messageText = '📷 Imagen';
        }
        
        // Si es un mensaje de otro en una comunidad, mostrar nombre
        if (payload.context === 'community' && payload.sender_id != window.USER_ID) {
            messageText = `${payload.sender_username}: ${messageText}`;
        } else if (payload.sender_id == window.USER_ID) {
            messageText = `Tú: ${messageText}`;
        }

        if (previewEl) {
            previewEl.textContent = messageText;
            // Si el chat NO está activo, poner negrita
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

        // B. Actualizar contador 
        // Solo incrementamos si NO es el chat activo, O si es el chat activo pero la ventana NO tiene foco
        const isActiveChat = (uuid === window.ACTIVE_CHAT_UUID);
        const windowHasFocus = document.hasFocus();

        if (!isActiveChat || (isActiveChat && !windowHasFocus)) {
            let badge = existingItem.querySelector('.unread-counter');
            if (!badge) {
                const infoContainer = existingItem.querySelector('.chat-item-info > div:last-child');
                if (infoContainer) {
                    badge = document.createElement('div');
                    badge.className = 'unread-counter';
                    badge.textContent = '1';
                    infoContainer.appendChild(badge);
                }
            } else {
                let count = parseInt(badge.textContent) || 0;
                badge.textContent = count + 1 > 99 ? '99+' : count + 1;
            }
        }

        // C. Mover al principio de la lista (Animación visual simple)
        if (existingItem.style.display !== 'none') {
            container.prepend(existingItem);
        }

        // D. Actualizar array en memoria para búsquedas futuras
        const dataItem = sidebarItems.find(i => i.uuid === uuid);
        if (dataItem) {
            dataItem.last_message = messageText;
            dataItem.last_message_at = new Date().toISOString();
            
            // Misma lógica de contador para la memoria
            if (!isActiveChat || (isActiveChat && !windowHasFocus)) {
                dataItem.unread_count = (parseInt(dataItem.unread_count) || 0) + 1;
            }
            
            sidebarItems = sidebarItems.filter(i => i.uuid !== uuid);
            sidebarItems.unshift(dataItem);
        }

    } else {
        // 2. Si es un chat nuevo que no está en la lista visible, recargamos
        loadSidebarList(false);
    }
}

// ==========================================
// CARGA DE DATOS
// ==========================================

async function loadSidebarList(shouldOpenActive = false) {
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
        
        // Actualizar clase activa visualmente siempre
        if (itemData) {
            const activeEl = document.querySelector(`.chat-item[data-uuid="${window.ACTIVE_CHAT_UUID}"]`);
            if (activeEl) {
                activeEl.classList.add('active');
                activeEl.querySelector('.unread-counter')?.remove();
            }
        }

        // Solo inicializar el chat si estamos en carga inicial
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
        // [FIX] Añadidos atributos data-action y data-uuid para que el listener los detecte
        specificOptions = `
            <div class="chat-popover-item danger" data-action="block-user-chat" data-uuid="${uuid}">
                <span class="material-symbols-rounded">block</span> ${t('friends.block_user') || 'Bloquear'}
            </div>
            <div class="chat-popover-item danger" data-action="remove-friend-chat" data-uuid="${uuid}">
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

// [NUEVO] Función para bloquear usuario desde el chat (usando UUID de chat)
async function blockUserFromChat(uuid) {
    if (!confirm(t('friends.confirm_block') || '¿Seguro que quieres bloquear a este usuario?')) return;
    
    // 1. Obtener ID real del usuario usando el UUID del chat
    const resInfo = await postJson('api/communities_handler.php', { action: 'get_user_chat_by_uuid', uuid: uuid });
    
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        // 2. Ejecutar bloqueo
        const resBlock = await postJson('api/friends_handler.php', { action: 'block_user', target_id: targetId });
        
        if (resBlock.success) {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'success');
            // Recargar para actualizar la interfaz
            window.location.reload(); 
        } else {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'error');
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert("No se pudo identificar al usuario.", 'error');
    }
}

// ==========================================
// MANEJO DE FILTROS Y BÚSQUEDA
// ==========================================

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
            return; // Detener ejecución
        }

        // [FIX] Listener para el botón "Bloquear" del menú flotante
        const blockBtn = e.target.closest('[data-action="block-user-chat"]');
        if (blockBtn) {
            e.preventDefault();
            document.querySelector('.chat-popover-menu')?.remove();
            await blockUserFromChat(blockBtn.dataset.uuid);
            return;
        }

        const pinAction = e.target.closest('[data-action="toggle-pin-chat"]');
        if (pinAction) {
            document.querySelector('.chat-popover-menu')?.remove();
            await togglePinChat(pinAction.dataset.uuid, pinAction.dataset.type);
            return; // Detener ejecución
        }

        const favAction = e.target.closest('[data-action="toggle-fav-chat"]');
        if (favAction) {
            document.querySelector('.chat-popover-menu')?.remove();
            await toggleFavChat(favAction.dataset.uuid, favAction.dataset.type);
            return; // Detener ejecución
        }

        const leaveAction = e.target.closest('[data-action="leave-community"]');
        if (leaveAction) {
            document.querySelector('.chat-popover-menu')?.remove();
            await leaveCommunity(leaveAction.dataset.uuid);
            return; // Detener ejecución
        }
        
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
            return; // Detener ejecución
        }
    });

    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'new_chat_message' || type === 'private_message') {
            handleSidebarUpdate(payload);
        }
    });

    // Escuchar evento local para limpiar badge cuando chat-manager marque como leído
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
            
            // Actualizar datos en memoria
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