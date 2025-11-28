// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';
import { openChat } from './chat-manager.js';

let sidebarItems = [];

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
// RENDERIZADO DE LA LISTA LATERAL (UNIFICADA)
// ==========================================

function renderChatListItem(item) {
    const isPrivate = (item.type === 'private');
    
    // Avatar logic
    const avatar = item.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + item.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(item.name);
        
    const isActive = (item.uuid === window.ACTIVE_CHAT_UUID) ? 'active' : '';
    
    // Last message logic
    let lastMsg = item.last_message ? escapeHtml(item.last_message) : (item.last_message_at ? 'Imagen' : (isPrivate ? "Nuevo chat" : "Haz clic para entrar"));
    if (isPrivate && !item.last_message && !item.last_message_at) lastMsg = "Iniciar conversación";

    const time = item.last_message_at ? formatChatTime(item.last_message_at) : '';
    const unreadCount = parseInt(item.unread_count || 0);
    
    const badgeHtml = (unreadCount > 0 && isActive === '') 
        ? `<div class="unread-counter">${unreadCount > 99 ? '99+' : unreadCount}</div>` 
        : '';

    const previewStyle = (unreadCount > 0 && isActive === '') ? 'font-weight: 700; color: #000;' : '';
    
    // Estilo visual: Avatar circular para user, cuadrado redondeado para comunidad
    const avatarStyle = isPrivate ? 'border-radius: 50%;' : 'border-radius: 12px;';

    return `
    <div class="chat-item ${isActive}" data-action="select-chat" data-uuid="${item.uuid}" data-type="${item.type}">
        <img src="${avatar}" class="chat-item-avatar" style="${avatarStyle}" alt="Avatar">
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
    </div>`;
}

// ==========================================
// RENDERIZADO DE EXPLORER (Comunidades Públicas)
// ==========================================
function renderCommunityCard(comm, isJoined) {
    const avatar = comm.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
    
    const bannerStyle = comm.banner_picture ? 
        `background-image: url('${(window.BASE_PATH || '/ProjectAurora/') + comm.banner_picture}');` : 
        'background-color: #eee;';

    let actionBtn = isJoined ? 
        `<button class="component-button" disabled>Unido</button>` : 
        `<button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">Unirse</button>`;

    return `
    <div class="comm-card">
        <div class="comm-banner" style="${bannerStyle}"></div>
        <div class="comm-content">
            <div class="comm-header-row">
                <div class="comm-avatar-container">
                    <img src="${avatar}" class="comm-avatar-img" alt="${escapeHtml(comm.community_name)}">
                </div>
                <div class="comm-actions">
                    ${actionBtn}
                </div>
            </div>
            <div class="comm-info">
                <h3 class="comm-title">${escapeHtml(comm.community_name)}</h3>
                <div class="comm-badges">
                    <span class="comm-badge"><span class="material-symbols-rounded" style="font-size:14px; margin-right:4px;">group</span>${comm.member_count} miembros</span>
                    <span class="comm-badge">Publico</span>
                </div>
                <p class="comm-desc" style="margin-top:8px;">${escapeHtml(comm.description || 'Sin descripción')}</p>
            </div>
        </div>
    </div>`;
}

// ==========================================
// CARGA DE DATOS
// ==========================================

async function loadSidebarList() {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    // Usamos la nueva acción unificada
    const res = await postJson('api/communities_handler.php', { action: 'get_sidebar_list' });
    
    container.innerHTML = ''; 

    if (res.success && res.list.length > 0) {
        sidebarItems = res.list;
        container.innerHTML = res.list.map(c => renderChatListItem(c)).join('');
    } else {
        sidebarItems = []; // Asegurar que esté vacío si no hay datos
        container.innerHTML = `<p style="text-align:center; color:#999; padding:20px;">No tienes chats ni comunidades.</p>`;
    }

    // [CORRECCIÓN CRÍTICA] 
    // Si hay un UUID activo (cargado por router/loader), intentamos abrir el chat.
    // Si el chat NO está en sidebarItems (porque es un DM nuevo sin mensajes), pasamos NULL en data.
    // chat-manager.js tiene lógica para hacer fetch si data es null.
    if (window.ACTIVE_CHAT_UUID) {
        const itemData = sidebarItems.find(c => c.uuid === window.ACTIVE_CHAT_UUID);
        // Pasamos itemData si existe, o null. openChat se encargará de buscarlo si es null.
        openChat(window.ACTIVE_CHAT_UUID, itemData || null);
    }
}

async function loadPublicCommunities() {
    const container = document.getElementById('public-communities-list');
    if (!container) return;
    const res = await postJson('api/communities_handler.php', { action: 'get_public_communities' });
    if (res.success && res.communities.length > 0) {
        container.innerHTML = res.communities.map(c => renderCommunityCard(c, false)).join('');
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; grid-column:1/-1;">No hay comunidades públicas disponibles.</p>`;
    }
}

// ==========================================
// LISTENERS
// ==========================================

function initListListeners() {
    // Click en sidebar item
    document.getElementById('my-communities-list')?.addEventListener('click', (e) => {
        const item = e.target.closest('.chat-item');
        if (item) {
            const uuid = item.dataset.uuid;
            const type = item.dataset.type; // community o private
            
            // Pasar datos ya cargados para evitar fetch
            const itemData = sidebarItems.find(c => c.uuid === uuid);
            
            // Asegurar que el tipo esté correcto en el objeto
            if(itemData && !itemData.type) itemData.type = type;
            
            openChat(uuid, itemData);
        }
    });

    // Unirse desde explorer (igual)
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
                loadSidebarList(); // Recargar sidebar
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false, 'Unirse');
            }
        }
    });

    // Escuchar socket para actualizar lista
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        // Manejar tanto new_chat_message (comunidad) como private_message
        if (type === 'new_chat_message' || type === 'private_message') {
            const targetUuid = payload.target_uuid || payload.community_uuid;
            
            // Buscar item en sidebar
            const item = document.querySelector(`.chat-item[data-uuid="${targetUuid}"]`);
            
            if (item) {
                // Actualizar preview y tiempo
                const previewEl = item.querySelector('.chat-item-preview');
                const timeEl = item.querySelector('.chat-item-time');
                if (previewEl) previewEl.textContent = payload.message ? payload.message : '📷 [Imagen]';
                if (timeEl) timeEl.textContent = formatChatTime(new Date());

                // Mover al principio
                const list = document.getElementById('my-communities-list');
                list.prepend(item);

                // Badge de no leídos (Si no es el chat activo)
                if (targetUuid !== window.ACTIVE_CHAT_UUID) {
                    if (previewEl) { previewEl.style.fontWeight = '700'; previewEl.style.color = '#000'; }
                    let badge = item.querySelector('.unread-counter');
                    if (!badge) {
                        badge = document.createElement('div');
                        badge.className = 'unread-counter';
                        badge.textContent = '0';
                        if(previewEl.parentNode) previewEl.parentNode.appendChild(badge);
                    }
                    badge.textContent = parseInt(badge.textContent) + 1;
                }
            } else {
                // Si el item no existe (ej: nuevo DM recibido), recargar lista completa
                loadSidebarList();
            }
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
    
    if (!window.communitiesListenersInit) {
        initListListeners();
        window.communitiesListenersInit = true;
    }
}