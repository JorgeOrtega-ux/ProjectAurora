// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';

let myCommunities = [];
let currentCommunityId = null; 
let currentCommunityUuid = null; 

// ==========================================
// 1. LÓGICA DE MAIN.PHP
// ==========================================

function renderChatListItem(comm) {
    const avatar = comm.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
        
    const isActive = (comm.uuid === window.ACTIVE_COMMUNITY_UUID) ? 'active' : '';
    
    const preview = "Haz clic para entrar";
    const time = ""; 

    return `
    <div class="chat-item ${isActive}" data-action="select-chat" data-uuid="${comm.uuid}" data-id="${comm.id}">
        <img src="${avatar}" class="chat-item-avatar" alt="Avatar">
        <div class="chat-item-info">
            <div class="chat-item-top">
                <span class="chat-item-name">${comm.community_name}</span>
                <span class="chat-item-time">${time}</span>
            </div>
            <span class="chat-item-preview">${preview}</span>
        </div>
    </div>`;
}

function updateChatInterface(comm) {
    const placeholder = document.getElementById('chat-placeholder');
    const interfaceDiv = document.getElementById('chat-interface');
    
    const img = document.getElementById('chat-header-img');
    const title = document.getElementById('chat-header-title');
    const status = document.getElementById('chat-header-status');

    if (comm) {
        if (placeholder) placeholder.classList.add('d-none');
        if (interfaceDiv) interfaceDiv.classList.remove('d-none');
        
        const avatarPath = comm.profile_picture ? 
            (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
            `https://ui-avatars.com/api/?name=${comm.community_name}`;

        if (img) img.src = avatarPath;
        if (title) title.textContent = comm.community_name;
        if (status) status.textContent = `${comm.member_count} miembros`;

        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.add('chat-active');

        const input = document.querySelector('.chat-message-input');
        if (input) input.focus();

    } else {
        if (placeholder) placeholder.classList.remove('d-none');
        if (interfaceDiv) interfaceDiv.classList.add('d-none');
        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.remove('chat-active');
    }
}

// --- LOGICA DE MENSAJES ---

async function loadChatMessages(uuid) {
    const container = document.querySelector('.chat-messages-area');
    if (!container) return;

    container.innerHTML = '<div class="small-spinner" style="margin:auto;"></div>';

    const res = await postJson('api/chat_handler.php', { action: 'get_messages', community_uuid: uuid });

    if (res.success) {
        container.innerHTML = '';
        res.messages.forEach(msg => {
            appendMessageToUI(msg);
        });
        scrollToBottom();
    } else {
        container.innerHTML = `<div style="text-align:center; color:#999; margin-top:20px;">Error al cargar mensajes: ${res.message}</div>`;
    }
}

function appendMessageToUI(msg) {
    const container = document.querySelector('.chat-messages-area');
    if (!container) return;

    const myId = window.USER_ID; 
    const isMe = (parseInt(msg.sender_id) === parseInt(myId));
    
    const date = new Date(msg.created_at);
    const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    let avatarUrl = msg.sender_profile_picture 
        ? (window.BASE_PATH || '/ProjectAurora/') + msg.sender_profile_picture 
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(msg.sender_username)}`;

    const msgHtml = `
        <div class="message-row ${isMe ? 'message-own' : 'message-other'}" style="display:flex; flex-direction:${isMe ? 'row-reverse' : 'row'}; margin-bottom:12px; gap:10px; align-items:flex-end;">
            ${!isMe ? `<img src="${avatarUrl}" style="width:28px; height:28px; border-radius:50%; object-fit:cover; margin-bottom:4px;" title="${msg.sender_username}">` : ''}
            
            <div class="message-bubble" style="
                max-width: 70%;
                padding: 8px 12px;
                border-radius: 12px;
                background-color: ${isMe ? '#dcf8c6' : '#fff'};
                border: 1px solid ${isMe ? '#dcf8c6' : '#e0e0e0'};
                position: relative;
                font-size: 14px;
                color: #333;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            ">
                ${!isMe ? `<div style="font-size:11px; font-weight:700; color:#e91e63; margin-bottom:2px;">${msg.sender_username}</div>` : ''}
                <div class="message-text" style="word-wrap: break-word; line-height: 1.4;">${escapeHtml(msg.message)}</div>
                <div class="message-time" style="font-size:10px; color:#999; text-align:right; margin-top:4px;">${timeStr}</div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', msgHtml);
}

function scrollToBottom() {
    const container = document.querySelector('.chat-messages-area');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// --- SELECCIONAR CHAT ---

async function selectCommunity(uuid) {
    let comm = myCommunities.find(c => c.uuid === uuid);
    
    if (!comm) {
        const res = await postJson('api/communities_handler.php', { action: 'get_community_by_uuid', uuid });
        if (res.success) {
            comm = res.community;
        } else {
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
            return;
        }
    }

    currentCommunityId = comm.id;
    currentCommunityUuid = comm.uuid;
    window.ACTIVE_COMMUNITY_UUID = uuid;

    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    const activeItem = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
    if (activeItem) activeItem.classList.add('active');

    updateChatInterface(comm);
    loadChatMessages(uuid); 

    const newUrl = `${window.BASE_PATH}c/${uuid}`;
    if (window.location.pathname !== newUrl) {
        window.history.pushState({ section: 'c/'+uuid }, '', newUrl);
    }
}

function sendMessage() {
    if (!currentCommunityUuid) return;
    
    const input = document.querySelector('.chat-message-input');
    const text = input.value.trim();
    
    if (!text) return;

    if (window.socketService && window.socketService.socket && window.socketService.socket.readyState === WebSocket.OPEN) {
        window.socketService.socket.send(JSON.stringify({
            type: 'chat_message',
            payload: {
                community_uuid: currentCommunityUuid,
                message: text
            }
        }));
        
        input.value = '';
        input.focus();
    } else {
        alert("Sin conexión al servidor de chat.");
    }
}

function handleMobileBack() {
    const layout = document.querySelector('.chat-layout-container');
    if (layout) layout.classList.remove('chat-active');
    
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    window.ACTIVE_COMMUNITY_UUID = null;
    currentCommunityUuid = null;
    
    window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
}

// --- LISTENERS ---

async function loadMyCommunities() {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    const res = await postJson('api/communities_handler.php', { action: 'get_my_communities' });
    
    container.innerHTML = ''; 

    if (res.success && res.communities.length > 0) {
        myCommunities = res.communities;
        container.innerHTML = res.communities.map(c => renderChatListItem(c)).join('');
        
        if (window.ACTIVE_COMMUNITY_UUID) {
            selectCommunity(window.ACTIVE_COMMUNITY_UUID);
        }
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; padding:20px;">No te has unido a ninguna comunidad.</p>`;
    }
}

function initChatListeners() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'new_chat_message') {
            if (payload.community_uuid === currentCommunityUuid) {
                appendMessageToUI(payload);
                scrollToBottom();
            } else {
                console.log("Mensaje en otro chat:", payload.community_uuid);
            }
        }
    });

    const input = document.querySelector('.chat-message-input');
    const sendBtn = document.getElementById('btn-send-message');

    if (input) {
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', (e) => {
            e.preventDefault();
            sendMessage();
        });
    }
}

function initListeners() {
    document.getElementById('my-communities-list')?.addEventListener('click', (e) => {
        const item = e.target.closest('.chat-item');
        if (item) {
            selectCommunity(item.dataset.uuid);
        }
    });

    document.getElementById('btn-back-to-list')?.addEventListener('click', () => {
        handleMobileBack();
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
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false, 'Unirse');
            }
        }
    });
}

function renderCommunityCard(comm, isMyList) {
    const isPrivate = comm.privacy === 'private';
    const privacyText = isPrivate ? 'Privado' : 'Público';
    const memberText = comm.member_count + (comm.member_count === 1 ? ' Miembro' : ' Miembros');
    let buttonHtml = `<button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">Unirse</button>`;
    const bannerSrc = comm.banner_picture ? comm.banner_picture : 'https://picsum.photos/seed/generic/600/200';
    const avatarSrc = comm.profile_picture ? (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : null;
    const avatarHtml = avatarSrc ? `<img src="${avatarSrc}" class="comm-avatar-img" alt="${comm.community_name}">` : `<div class="comm-avatar-placeholder"><span class="material-symbols-rounded">groups</span></div>`;

    return `
    <div class="comm-card">
        <div class="comm-banner" style="background-image: url('${bannerSrc}');"></div>
        <div class="comm-content">
            <div class="comm-header-row"><div class="comm-avatar-container">${avatarHtml}</div><div class="comm-actions">${buttonHtml}</div></div>
            <div class="comm-info">
                <h3 class="comm-title">${comm.community_name}</h3>
                <p class="comm-desc">${comm.description || 'Sin descripción disponible.'}</p>
                <div class="comm-badges"><span class="comm-badge">${memberText}</span><span class="comm-badge">${privacyText}</span></div>
            </div>
        </div>
    </div>`;
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
        if (input.value.length < 14) { if(window.alertManager) window.alertManager.showAlert('Código incompleto.', 'warning'); return; }
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
    initChatListeners(); 
    
    if (!window.communitiesListenersInit) {
        initListeners();
        window.communitiesListenersInit = true;
    }
}