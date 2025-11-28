// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';
import { openChat } from './chat-manager.js'; // IMPORTAMOS LA FUNCIÓN

let myCommunities = [];

// ==========================================
// UTILIDADES (Duplicado menor para independencia)
// ==========================================

function formatChatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    
    const isToday = date.getDate() === now.getDate() && 
                    date.getMonth() === now.getMonth() && 
                    date.getFullYear() === now.getFullYear();
    
    return isToday 
        ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// ==========================================
// RENDERIZADO DE LA LISTA LATERAL (SIDEBAR)
// ==========================================

function renderChatListItem(comm) {
    const avatar = comm.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
        
    const isActive = (comm.uuid === window.ACTIVE_COMMUNITY_UUID) ? 'active' : '';
    
    const lastMsg = comm.last_message ? escapeHtml(comm.last_message) : (comm.last_message_at ? 'Imagen' : "Haz clic para entrar");
    const time = formatChatTime(comm.last_message_at);
    const unreadCount = parseInt(comm.unread_count || 0);
    
    const badgeHtml = (unreadCount > 0 && isActive === '') 
        ? `<div class="unread-counter">${unreadCount > 99 ? '99+' : unreadCount}</div>` 
        : '';

    const previewStyle = (unreadCount > 0 && isActive === '') ? 'font-weight: 700; color: #000;' : '';

    return `
    <div class="chat-item ${isActive}" data-action="select-chat" data-uuid="${comm.uuid}" data-id="${comm.id}">
        <img src="${avatar}" class="chat-item-avatar" alt="Avatar">
        <div class="chat-item-info">
            <div class="chat-item-top">
                <span class="chat-item-name">${comm.community_name}</span>
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
// RENDERIZADO DE EXPLORER (TARJETAS)
// ==========================================

function renderCommunityCard(comm, isJoined) {
    const avatar = comm.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
    
    const bannerStyle = comm.banner_picture ? 
        `background-image: url('${(window.BASE_PATH || '/ProjectAurora/') + comm.banner_picture}');` : 
        'background-color: #eee;';

    let actionBtn = '';
    if (isJoined) {
        actionBtn = `<button class="component-button" disabled>Unido</button>`;
    } else {
        actionBtn = `<button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">Unirse</button>`;
    }

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
                    <span class="comm-badge">
                        <span class="material-symbols-rounded" style="font-size:14px; margin-right:4px;">group</span>
                        ${comm.member_count} miembros
                    </span>
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

async function loadMyCommunities() {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    const res = await postJson('api/communities_handler.php', { action: 'get_my_communities' });
    
    container.innerHTML = ''; 

    if (res.success && res.communities.length > 0) {
        myCommunities = res.communities;
        container.innerHTML = res.communities.map(c => renderChatListItem(c)).join('');
        
        // Si hay un UUID activo globalmente, llamar al chat manager para abrirlo
        if (window.ACTIVE_COMMUNITY_UUID) {
            // Buscamos los datos completos en memoria para pasarlos y evitar un fetch extra
            const commData = myCommunities.find(c => c.uuid === window.ACTIVE_COMMUNITY_UUID);
            openChat(window.ACTIVE_COMMUNITY_UUID, commData);
        }
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; padding:20px;">No te has unido a ninguna comunidad.</p>`;
    }
}

async function loadPublicCommunities() {
    const container = document.getElementById('public-communities-list');
    if (!container) return;
    const res = await postJson('api/communities_handler.php', { action: 'get_public_communities' });
    if (res.success && res.communities.length > 0) {
        container.innerHTML = res.communities.map(c => renderCommunityCard(c, false)).join('');
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; grid-column:1/-1;">No hay comunidades públicas disponibles o ya te has unido a todas.</p>`;
    }
}

// ==========================================
// LISTENERS DE LISTA Y SOCKET (Actualizaciones de lista)
// ==========================================

function initListListeners() {
    // Click en item de la lista
    document.getElementById('my-communities-list')?.addEventListener('click', (e) => {
        const item = e.target.closest('.chat-item');
        if (item) {
            const uuid = item.dataset.uuid;
            const commData = myCommunities.find(c => c.uuid === uuid);
            openChat(uuid, commData);
        }
    });

    // Unirse desde Explorer
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
                // Recargar lista lateral
                loadMyCommunities();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false, 'Unirse');
            }
        }
    });

    // Escuchar mensajes para ACTUALIZAR LA LISTA (Mover al top, badge)
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'new_chat_message') {
            const item = document.querySelector(`.chat-item[data-uuid="${payload.community_uuid}"]`);
            if (item) {
                const previewEl = item.querySelector('.chat-item-preview');
                const timeEl = item.querySelector('.chat-item-time');
                
                if (previewEl) previewEl.textContent = payload.message ? payload.message : '📷 [Imagen]';
                if (timeEl) timeEl.textContent = formatChatTime(new Date());

                // Mover al principio
                const list = document.getElementById('my-communities-list');
                list.prepend(item);

                // Badge de no leídos
                if (payload.community_uuid !== window.ACTIVE_COMMUNITY_UUID && parseInt(payload.sender_id) !== parseInt(window.USER_ID)) {
                    if (previewEl) { 
                        previewEl.style.fontWeight = '700'; 
                        previewEl.style.color = '#000'; 
                    }
                    
                    let badge = item.querySelector('.unread-counter');
                    if (!badge) {
                        badge = document.createElement('div');
                        badge.className = 'unread-counter';
                        badge.textContent = '0';
                        if(previewEl.parentNode) previewEl.parentNode.appendChild(badge);
                    }
                    let count = parseInt(badge.textContent);
                    badge.textContent = count + 1;
                }
            }
        }
    });
}

function initJoinByCode() {
    const input = document.querySelector('[data-input="community-code"]');
    const btn = document.querySelector('[data-action="submit-join-community"]');
    if (!input || !btn) return;
    
    input.addEventListener('input', (e) => {
        let v = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        if (v.length > 12) v = v.slice(0, 12);
        const parts = [];
        if (v.length > 0) parts.push(v.slice(0, 4));
        if (v.length > 4) parts.push(v.slice(4, 8));
        if (v.length > 8) parts.push(v.slice(8, 12));
        e.target.value = parts.join('-');
    });
    
    btn.onclick = async () => {
        if (input.value.length < 14) { 
            if(window.alertManager) window.alertManager.showAlert('Código incompleto.', 'warning'); 
            return; 
        }
        setButtonLoading(btn, true);
        const res = await postJson('api/communities_handler.php', { action: 'join_by_code', access_code: input.value });
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
            if (window.navigateTo) window.navigateTo('main'); else window.location.href = (window.BASE_PATH || '/') + 'main';
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false, 'Unirse');
        }
    };
}

export function initCommunitiesManager() {
    loadMyCommunities(); 
    loadPublicCommunities(); 
    initJoinByCode(); 
    
    if (!window.communitiesListenersInit) {
        initListListeners();
        window.communitiesListenersInit = true;
    }
}