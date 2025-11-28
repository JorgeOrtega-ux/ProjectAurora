// public/assets/js/modules/chat-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';

// Estado del Chat
let currentCommunityUuid = null;
let currentCommunityId = null;
let replyingToMessageId = null;
let replyingToMessageData = null;
let selectedFiles = [];

// ==========================================
// UTILIDADES INTERNAS
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

function scrollToBottom() {
    const container = document.querySelector('.chat-messages-area');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// ==========================================
// LÓGICA PRINCIPAL DE APERTURA (Exportada)
// ==========================================

export async function openChat(uuid, communityData = null) {
    // Si no tenemos datos (venimos de URL o click sin datos previos), los buscamos
    if (!communityData) {
        const res = await postJson('api/communities_handler.php', { action: 'get_community_by_uuid', uuid });
        if (res.success) {
            communityData = res.community;
        } else {
            // Si falla, volver a main
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
            return;
        }
    }

    currentCommunityId = communityData.id;
    currentCommunityUuid = communityData.uuid;
    window.ACTIVE_COMMUNITY_UUID = uuid;

    // Actualizar UI de la lista (Visualmente activo)
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    const activeItem = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
    if (activeItem) {
        activeItem.classList.add('active');
        const badge = activeItem.querySelector('.unread-counter');
        if(badge) badge.remove();
        
        const preview = activeItem.querySelector('.chat-item-preview');
        if(preview) { 
            preview.style.fontWeight = 'normal'; 
            preview.style.color = ''; 
        }
    }

    updateChatInterface(communityData);
    loadChatMessages(uuid);

    // Actualizar URL sin recargar
    const newUrl = `${window.BASE_PATH}c/${uuid}`;
    if (window.location.pathname !== newUrl) {
        window.history.pushState({ section: 'c/'+uuid }, '', newUrl);
    }
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
            `https://ui-avatars.com/api/?name=${encodeURIComponent(comm.community_name)}`;

        if (img) img.src = avatarPath;
        if (title) title.textContent = comm.community_name;
        if (status) status.textContent = `${comm.member_count} miembros`;

        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.add('chat-active');

        const input = document.querySelector('.chat-message-input');
        if (input) input.focus();

        disableReplyMode();
        clearAttachments();
        
        // Recargar info sidebar si está abierto
        const infoPanel = document.getElementById('chat-info-panel');
        if (infoPanel && !infoPanel.classList.contains('d-none')) {
            loadCommunityDetails(comm.uuid);
        }

    } else {
        if (placeholder) placeholder.classList.remove('d-none');
        if (interfaceDiv) interfaceDiv.classList.add('d-none');
        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.remove('chat-active');
        disableReplyMode();
        clearAttachments();
    }
}

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

// ==========================================
// RENDERIZADO DE MENSAJES
// ==========================================

function appendMessageToUI(msg) {
    const container = document.querySelector('.chat-messages-area');
    if (!container) return;

    const myId = window.USER_ID; 
    const isMe = (parseInt(msg.sender_id) === parseInt(myId));
    
    if (msg.status === 'deleted') {
        renderDeletedMessage(container, msg, isMe);
        return;
    }

    const date = new Date(msg.created_at);
    const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    let avatarUrl = msg.sender_profile_picture 
        ? (window.BASE_PATH || '/ProjectAurora/') + msg.sender_profile_picture 
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(msg.sender_username)}`;

    const role = msg.sender_role || 'user';

    let replyHtml = '';
    if (msg.reply_to_id) {
        let replyText = '';
        const rawText = msg.reply_message ? escapeHtml(msg.reply_message) : '';
        const attachCount = parseInt(msg.reply_attachment_count || 0);
        
        if (msg.reply_type === 'image') {
            replyText = (attachCount > 1) ? `📷 [${attachCount} Imágenes]` : '📷 [Imagen]';
        } else if (msg.reply_type === 'mixed') {
            const prefix = (attachCount > 1) ? `📷 [${attachCount}] ` : '📷 ';
            replyText = prefix + (rawText || '[Imagen]');
        } else {
            replyText = rawText || '...';
        }

        const replyUser = msg.reply_sender_username || 'Usuario';
        replyHtml = `
            <div class="message-reply-preview">
                <span class="reply-preview-user">${escapeHtml(replyUser)}</span>
                <span class="reply-preview-text">${replyText}</span>
            </div>
        `;
    }

    // Grid de Imágenes
    let attachmentsHtml = '';
    if (msg.attachments && Array.isArray(msg.attachments) && msg.attachments.length > 0) {
        const count = msg.attachments.length;
        
        const viewerItems = msg.attachments.map(att => {
            const src = (window.BASE_PATH || '/ProjectAurora/') + att.path;
            return {
                src: src,
                type: att.type,
                user: { name: msg.sender_username, avatar: avatarUrl },
                date: new Date(msg.created_at).toLocaleDateString() + ' ' + timeStr
            };
        });
        
        const jsonStr = JSON.stringify(viewerItems).replace(/'/g, "&apos;").replace(/"/g, '&quot;');

        let imgs = '';
        msg.attachments.forEach((att, idx) => {
            const src = (window.BASE_PATH || '/ProjectAurora/') + att.path;
            imgs += `<img src="${src}" data-action="view-media" data-index="${idx}">`;
        });
        
        attachmentsHtml = `<div class="msg-attachments" data-count="${count}" data-media-items='${jsonStr}'>${imgs}</div>`;
    }

    const optionsBtn = `
        <button class="message-options-btn" data-action="msg-options" data-id="${msg.id}" data-user="${msg.sender_username}" data-text="${escapeHtml(msg.message)}" data-sender-id="${msg.sender_id}" data-created-at="${msg.created_at}">
            <span class="material-symbols-rounded" style="font-size: 18px;">more_vert</span>
        </button>
    `;

    const msgHtml = `
        <div class="message-row ${isMe ? 'message-own' : 'message-other'}" id="msg-${msg.id}" style="display:flex; flex-direction:${isMe ? 'row-reverse' : 'row'}; margin-bottom:12px; gap:4px; align-items:flex-start;">
            
            ${!isMe ? `
                <div class="chat-message-avatar" data-role="${role}" title="${msg.sender_username}">
                    <img src="${avatarUrl}" alt="${msg.sender_username}">
                </div>
            ` : ''}
            
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
                ${replyHtml} 
                ${!isMe ? `<div style="font-size:11px; font-weight:700; color:#e91e63; margin-bottom:2px;">${msg.sender_username}</div>` : ''}
                
                ${attachmentsHtml} 
                ${msg.message ? `<div class="message-text" style="word-wrap: break-word; line-height: 1.4;">${escapeHtml(msg.message)}</div>` : ''}
                
                <div class="message-time" style="font-size:10px; color:#999; text-align:right; margin-top:4px;">${timeStr}</div>
            </div>

            ${optionsBtn} 
        </div>
    `;

    container.insertAdjacentHTML('beforeend', msgHtml);
}

function renderDeletedMessage(container, msg, isMe) {
    const html = `
        <div class="message-row ${isMe ? 'message-own' : 'message-other'}" id="msg-${msg.id}" style="display:flex; flex-direction:${isMe ? 'row-reverse' : 'row'}; margin-bottom:12px; gap:4px; align-items:flex-start; opacity: 0.6;">
             <div class="message-bubble" style="
                max-width: 70%;
                padding: 8px 12px;
                border-radius: 12px;
                background-color: #f5f5f5;
                border: 1px solid #e0e0e0;
                color: #666;
                font-style: italic;
                font-size: 13px;
            ">
                <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">block</span>
                ${t('chat.message_deleted') || 'Este mensaje ha sido eliminado'}
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

// ==========================================
// GESTIÓN DE ENVÍO Y ADJUNTOS
// ==========================================

function initAttachmentListeners() {
    const fileInput = document.getElementById('chat-file-input');
    const attachBtn = document.getElementById('btn-attach-file');
    
    if (attachBtn && fileInput) {
        attachBtn.onclick = () => fileInput.click();
        
        fileInput.onchange = (e) => {
            const files = Array.from(e.target.files);
            
            if (selectedFiles.length + files.length > 4) {
                if (window.alertManager) window.alertManager.showAlert("Máximo 4 imágenes permitidas por mensaje.", "warning");
                return;
            }
            
            const validImages = files.filter(f => f.type.startsWith('image/'));
            if (validImages.length !== files.length) {
                if (window.alertManager) window.alertManager.showAlert("Solo se permiten archivos de imagen.", "warning");
            }

            selectedFiles = [...selectedFiles, ...validImages];
            renderPreview();
            fileInput.value = ''; 
        };
    }
}

function renderPreview() {
    const container = document.getElementById('attachment-preview-area');
    const grid = document.getElementById('preview-grid');
    
    if (!container || !grid) return;

    if (selectedFiles.length === 0) {
        container.classList.add('d-none');
        return;
    }
    
    container.classList.remove('d-none');
    grid.innerHTML = '';
    
    selectedFiles.forEach((file, index) => {
        const url = URL.createObjectURL(file);
        const div = document.createElement('div');
        div.className = 'preview-item';
        div.innerHTML = `
            <img src="${url}">
            <div class="preview-remove" data-index="${index}">✕</div>
        `;
        grid.appendChild(div);
    });
    
    grid.querySelectorAll('.preview-remove').forEach(btn => {
        btn.onclick = (e) => {
            const idx = parseInt(e.target.dataset.index);
            selectedFiles.splice(idx, 1);
            renderPreview();
        };
    });
}

function clearAttachments() {
    selectedFiles = [];
    renderPreview();
}

async function sendMessage() {
    if (!currentCommunityUuid) return;
    
    const input = document.querySelector('.chat-message-input');
    const text = input.value.trim();
    
    if (!text && selectedFiles.length === 0) return;

    if (selectedFiles.length > 0) {
        const btn = document.getElementById('btn-send-message');
        const originalIcon = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-rounded">sync</span>'; 

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('community_uuid', currentCommunityUuid);
        formData.append('message', text);
        if (replyingToMessageId) formData.append('reply_to_id', replyingToMessageId);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        selectedFiles.forEach(file => {
            formData.append('attachments[]', file);
        });

        try {
            const res = await fetch((window.BASE_PATH || '/ProjectAurora/') + 'api/chat_handler.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                input.value = '';
                clearAttachments();
                disableReplyMode();
            } else {
                if(window.alertManager) window.alertManager.showAlert(data.message || 'Error enviando imágenes', 'error');
            }
        } catch (e) {
            console.error(e);
            if(window.alertManager) window.alertManager.showAlert("Error de conexión", 'error');
        }
        
        btn.disabled = false;
        btn.innerHTML = originalIcon;

    } else {
        if (window.socketService && window.socketService.socket && window.socketService.socket.readyState === WebSocket.OPEN) {
            const payload = {
                type: 'chat_message',
                payload: {
                    community_uuid: currentCommunityUuid,
                    message: text
                }
            };

            if (replyingToMessageId) {
                payload.payload.reply_to_id = replyingToMessageId;
            }

            window.socketService.socket.send(JSON.stringify(payload));
            
            input.value = '';
            input.focus();
            disableReplyMode();

        } else {
            alert("Sin conexión al servidor de chat.");
        }
    }
}

// ==========================================
// RESPUESTAS Y ACCIONES DE MENSAJES
// ==========================================

function enableReplyMode(msgId, senderName, messageText) {
    replyingToMessageId = msgId;
    replyingToMessageData = { user: senderName, text: messageText };

    const container = document.getElementById('reply-preview-container');
    const userEl = document.getElementById('reply-target-user');
    const textEl = document.getElementById('reply-target-text');
    
    if (container && userEl && textEl) {
        userEl.textContent = senderName;
        textEl.textContent = messageText ? messageText : '📷 [Imagen]';
        container.classList.remove('d-none');
    }

    const input = document.querySelector('.chat-message-input');
    if (input) input.focus();
}

function disableReplyMode() {
    replyingToMessageId = null;
    replyingToMessageData = null;

    const container = document.getElementById('reply-preview-container');
    if (container) {
        container.classList.add('d-none');
    }
}

function showMessagePopover(btn, msgId, user, text) {
    closeMessagePopover();

    const senderId = btn.dataset.senderId;
    const createdAt = btn.dataset.createdAt;
    const isMe = (parseInt(senderId) === parseInt(window.USER_ID));
    
    let canDelete = false;
    if (isMe) {
        const msgDate = new Date(createdAt);
        const now = new Date();
        const diffHours = (now - msgDate) / 1000 / 60 / 60;
        canDelete = (diffHours < 24);
    }

    let extraOptions = '';
    
    if (isMe && canDelete) {
        extraOptions = `
            <div class="message-option-item danger" data-action="delete-message" style="color:#d32f2f;">
                <span class="material-symbols-rounded" style="font-size: 18px;">delete</span>
                ${t('chat.actions.delete') || 'Eliminar'}
            </div>
        `;
    } else if (!isMe) {
        extraOptions = `
            <div class="message-option-item" data-action="report-message" style="color:#f57c00;">
                <span class="material-symbols-rounded" style="font-size: 18px;">flag</span>
                ${t('chat.actions.report') || 'Reportar'}
            </div>
        `;
    }

    const popover = document.createElement('div');
    popover.className = 'message-options-popover';
    popover.innerHTML = `
        <div class="message-option-item" data-action="reply-message">
            <span class="material-symbols-rounded" style="font-size: 18px;">reply</span>
            ${t('chat.actions.reply') || 'Responder'}
        </div>
        ${extraOptions}
    `;

    const rect = btn.getBoundingClientRect();
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    
    popover.style.top = (rect.bottom + scrollTop) + 'px';
    popover.style.left = (rect.left - 100) + 'px'; 

    document.body.appendChild(popover);

    setTimeout(() => {
        const closeHandler = (e) => {
            if (!popover.contains(e.target)) {
                closeMessagePopover();
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 0);

    popover.querySelector('[data-action="reply-message"]').addEventListener('click', () => {
        enableReplyMode(msgId, user, text);
        closeMessagePopover();
    });

    const delBtn = popover.querySelector('[data-action="delete-message"]');
    if (delBtn) {
        delBtn.addEventListener('click', () => {
            if (confirm(t('global.are_you_sure'))) {
                deleteMessage(msgId);
            }
            closeMessagePopover();
        });
    }

    const repBtn = popover.querySelector('[data-action="report-message"]');
    if (repBtn) {
        repBtn.addEventListener('click', () => {
            const reason = prompt(t('chat.report_reason') || "Razón del reporte:");
            if (reason) {
                reportMessage(msgId, reason);
            }
            closeMessagePopover();
        });
    }
}

function closeMessagePopover() {
    const existing = document.querySelector('.message-options-popover');
    if (existing) existing.remove();
}

async function deleteMessage(msgId) {
    const res = await postJson('api/chat_handler.php', { action: 'delete_message', message_id: msgId });
    if (!res.success && window.alertManager) {
        window.alertManager.showAlert(res.message, 'error');
    }
}

async function reportMessage(msgId, reason) {
    const res = await postJson('api/chat_handler.php', { action: 'report_message', message_id: msgId, reason: reason });
    if (window.alertManager) {
        window.alertManager.showAlert(res.message, res.success ? 'success' : 'error');
    }
}

// ==========================================
// INFO SIDEBAR
// ==========================================

function toggleGroupInfo() {
    const sidebar = document.getElementById('chat-info-panel');
    if (!sidebar) return;

    if (sidebar.classList.contains('d-none')) {
        sidebar.classList.remove('d-none');
        setTimeout(() => sidebar.classList.add('active'), 10);
        
        if (currentCommunityUuid) {
            loadCommunityDetails(currentCommunityUuid);
        }
    } else {
        sidebar.classList.remove('active');
        setTimeout(() => sidebar.classList.add('d-none'), 300);
    }
}

async function loadCommunityDetails(uuid) {
    const nameEl = document.getElementById('info-group-name');
    const descEl = document.getElementById('info-group-desc');
    const imgEl = document.getElementById('info-group-img');
    const membersList = document.getElementById('info-members-list');
    const filesGrid = document.getElementById('info-files-grid');
    const countEl = document.getElementById('info-member-count');

    if (membersList) membersList.innerHTML = '<div class="small-spinner" style="margin: 20px auto;"></div>';
    
    const res = await postJson('api/communities_handler.php', { 
        action: 'get_community_details', 
        uuid: uuid 
    });

    if (res.success) {
        const info = res.info;
        if (nameEl) nameEl.textContent = info.community_name;
        if (descEl) descEl.textContent = info.description || 'Sin descripción';
        
        if (imgEl) {
            const avatarPath = info.profile_picture ? 
                (window.BASE_PATH || '/ProjectAurora/') + info.profile_picture : 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(info.community_name)}`;
            imgEl.src = avatarPath;
        }
        
        if (countEl) countEl.textContent = `(${res.members.length})`;

        // Miembros
        if (membersList) {
            membersList.innerHTML = '';
            res.members.forEach(m => {
                const mAvatar = m.profile_picture ? 
                    (window.BASE_PATH || '/ProjectAurora/') + m.profile_picture : 
                    `https://ui-avatars.com/api/?name=${encodeURIComponent(m.username)}`;
                
                const roleLabel = m.role === 'admin' ? 'Administrador' : (m.role === 'moderator' ? 'Moderador' : 'Miembro');
                
                membersList.innerHTML += `
                    <div class="info-member-item">
                        <img src="${mAvatar}" class="info-member-avatar">
                        <div class="info-member-details">
                            <span class="info-member-name">${escapeHtml(m.username)}</span>
                            <span class="info-member-role">${roleLabel}</span>
                        </div>
                    </div>
                `;
            });
        }

        // Archivos con Viewer
        if (filesGrid) {
            filesGrid.innerHTML = '';
            if (res.files.length > 0) {
                const viewerItems = res.files.map(f => {
                    const src = (window.BASE_PATH || '/ProjectAurora/') + f.file_path;
                    const pfp = f.profile_picture ? (window.BASE_PATH || '/ProjectAurora/') + f.profile_picture : `https://ui-avatars.com/api/?name=${encodeURIComponent(f.username)}`;
                    return {
                        src: src,
                        type: f.file_type,
                        user: { name: f.username, avatar: pfp },
                        date: new Date(f.created_at).toLocaleDateString()
                    };
                });

                filesGrid.dataset.mediaItems = JSON.stringify(viewerItems);

                res.files.forEach((f, idx) => {
                    const src = (window.BASE_PATH || '/ProjectAurora/') + f.file_path;
                    filesGrid.innerHTML += `
                        <img src="${src}" class="info-file-thumb" data-action="view-media" data-index="${idx}">
                    `;
                });
            } else {
                filesGrid.innerHTML = '<div class="info-no-files">No hay archivos recientes</div>';
                delete filesGrid.dataset.mediaItems;
            }
        }

    } else {
        if (window.alertManager) window.alertManager.showAlert("Error cargando detalles del grupo", "error");
    }
}

// ==========================================
// CONTROLADOR MÓVIL
// ==========================================

function handleMobileBack() {
    const layout = document.querySelector('.chat-layout-container');
    if (layout) layout.classList.remove('chat-active');
    
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    window.ACTIVE_COMMUNITY_UUID = null;
    currentCommunityUuid = null;
    disableReplyMode();
    clearAttachments();
    
    window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
}

// ==========================================
// INICIALIZACIÓN Y LISTENERS
// ==========================================

function initChatListeners() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        // Mensaje nuevo: solo si estamos en ese chat y activos
        if (type === 'new_chat_message') {
            if (payload.community_uuid === currentCommunityUuid) {
                appendMessageToUI(payload);
                scrollToBottom();
            }
        }

        // Actualización (borrado)
        if (type === 'message_update') {
            if (payload.status === 'deleted') {
                const msgEl = document.getElementById(`msg-${payload.id}`);
                if (msgEl) {
                    msgEl.style.opacity = '0.6';
                    msgEl.innerHTML = `
                        <div class="message-bubble" style="
                            max-width: 70%;
                            padding: 8px 12px;
                            border-radius: 12px;
                            background-color: #f5f5f5;
                            border: 1px solid #e0e0e0;
                            color: #666;
                            font-style: italic;
                            font-size: 13px;
                        ">
                            <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">block</span>
                            ${t('chat.message_deleted') || 'Este mensaje ha sido eliminado'}
                        </div>
                    `;
                }
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
    document.body.addEventListener('click', async (e) => {
        // Enviar mensaje
        // (Manejado arriba por ID directo, pero delegación útil para elementos dinámicos si fuera necesario)

        // Botón atrás móvil
        if (e.target.closest('#btn-back-to-list')) {
            handleMobileBack();
        }

        // Opciones de mensaje
        const msgOptBtn = e.target.closest('[data-action="msg-options"]');
        if (msgOptBtn) {
            e.stopPropagation(); 
            const msgId = msgOptBtn.dataset.id;
            const user = msgOptBtn.dataset.user;
            const text = msgOptBtn.dataset.text;
            showMessagePopover(msgOptBtn, msgId, user, text);
        }

        // Cancelar respuesta
        if (e.target.closest('#btn-cancel-reply')) {
            disableReplyMode();
        }
        
        // Sidebar Info
        if (e.target.closest('[data-action="toggle-group-info"]')) {
            e.preventDefault();
            toggleGroupInfo();
        }
        if (e.target.closest('[data-action="close-group-info"]')) {
            e.preventDefault();
            toggleGroupInfo();
        }
    });
}

export function initChatManager() {
    initAttachmentListeners();
    initChatListeners();
    initListeners();
}