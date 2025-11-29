// public/assets/js/modules/chat-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';

// Estado del Chat
let currentChatUuid = null;
let currentChatId = null; // [NUEVO] ID numérico para validación precisa
let currentChatType = 'community';
let replyingToMessageId = null;
let replyingToMessageData = null;
let selectedFiles = [];

// Estado de Paginación
let currentOffset = 0;
const MESSAGES_PER_PAGE = 50;
let isLoadingMessages = false;
let hasMoreMessages = true;

// ==========================================
// UTILIDADES INTERNAS
// ==========================================

function formatChatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function getDateString(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
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

export async function openChat(uuid, chatData = null) {
    if (!chatData) {
        let type = window.ACTIVE_CHAT_TYPE || 'community';
        let action = (type === 'private') ? 'get_user_chat_by_uuid' : 'get_community_by_uuid';
        
        const res = await postJson('api/communities_handler.php', { action: action, uuid });
        if (res.success) {
            chatData = res.data || res.community;
            chatData.type = type;
        } else {
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
            return;
        }
    }

    currentChatUuid = chatData.uuid;
    currentChatId = chatData.id; // [NUEVO] Guardamos el ID numérico
    currentChatType = chatData.type || 'community';
    window.ACTIVE_CHAT_UUID = uuid;
    window.ACTIVE_CHAT_TYPE = currentChatType;

    currentOffset = 0;
    hasMoreMessages = true;
    isLoadingMessages = false;

    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    const activeItem = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
    if (activeItem) {
        activeItem.classList.add('active');
        const badge = activeItem.querySelector('.unread-counter');
        if(badge) badge.remove();
        
        const preview = activeItem.querySelector('.chat-item-preview');
        if(preview) { preview.style.fontWeight = 'normal'; preview.style.color = ''; }
    }

    updateChatInterface(chatData);
    
    loadChatMessages(uuid, currentChatType, true);

    const prefix = (currentChatType === 'private') ? 'dm' : 'c';
    const newUrl = `${window.BASE_PATH}${prefix}/${uuid}`;
    if (window.location.pathname !== newUrl) {
        window.history.pushState({ section: `${prefix}/${uuid}` }, '', newUrl);
    }
}

function updateChatInterface(data) {
    const placeholder = document.getElementById('chat-placeholder');
    const interfaceDiv = document.getElementById('chat-interface');
    const img = document.getElementById('chat-header-img');
    const title = document.getElementById('chat-header-title');
    const status = document.getElementById('chat-header-status');
    const infoBtn = document.getElementById('btn-group-info-toggle');
    const headerInfo = document.getElementById('chat-header-info-clickable');
    
    // Elementos de entrada para bloquear si es necesario
    const inputArea = document.querySelector('.chat-input-area');
    const messageInput = document.querySelector('.chat-message-input');
    const sendBtn = document.getElementById('btn-send-message');
    const attachBtn = document.getElementById('btn-attach-file');
    
    const headerAvatarContainer = document.querySelector('.chat-avatar-container'); 

    if (data) {
        if (placeholder) placeholder.classList.add('d-none');
        if (interfaceDiv) interfaceDiv.classList.remove('d-none');
        
        const isPrivate = (data.type === 'private');
        const name = data.name || data.community_name || data.username;
        const pic = data.profile_picture;

        const avatarPath = pic ? 
            (window.BASE_PATH || '/ProjectAurora/') + pic : 
            `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}`;

        if (img) {
            img.src = avatarPath;
            img.style.borderRadius = isPrivate ? '50%' : '12px'; 
        }
        
        if (headerAvatarContainer) {
            headerAvatarContainer.removeAttribute('data-role');
            
            if (isPrivate && data.role) {
                headerAvatarContainer.setAttribute('data-role', data.role);
                headerAvatarContainer.classList.add('notif-img-container'); 
                headerAvatarContainer.style.width = '40px';
                headerAvatarContainer.style.height = '40px';
            } else {
                headerAvatarContainer.classList.remove('notif-img-container');
            }
        }

        if (title) title.textContent = name;
        
        if (status) {
            if (isPrivate) {
                status.textContent = 'Chat Directo'; 
            } else {
                status.textContent = `${data.member_count || 0} miembros`;
            }
        }

        // Resetear estado de inputs
        if (inputArea) inputArea.style.display = 'flex';
        if (messageInput) {
            messageInput.disabled = false;
            messageInput.placeholder = 'Escribe un mensaje...';
        }
        if (sendBtn) sendBtn.disabled = false;
        if (attachBtn) attachBtn.disabled = false;

        if (isPrivate) {
            if (infoBtn) infoBtn.style.display = 'none';
            if (headerInfo) headerInfo.style.pointerEvents = 'none'; 
            document.getElementById('chat-info-panel').classList.add('d-none'); 
            
            // Bloqueo por privacidad
            if (data.can_message === false) {
                if (messageInput) {
                    messageInput.disabled = true;
                    messageInput.placeholder = t('chat.error.privacy_placeholder') || 'Este usuario no permite mensajes de desconocidos.';
                    messageInput.value = '';
                }
                if (sendBtn) sendBtn.disabled = true;
                if (attachBtn) attachBtn.disabled = true;
            }

        } else {
            if (infoBtn) infoBtn.style.display = 'flex';
            if (headerInfo) headerInfo.style.pointerEvents = 'auto';
            const infoPanel = document.getElementById('chat-info-panel');
            if (infoPanel && infoPanel.classList.contains('active')) {
                document.dispatchEvent(new CustomEvent('reload-group-info', { detail: { uuid: data.uuid } }));
            }
        }

        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.add('chat-active');

        disableReplyMode();
        clearAttachments();

    } else {
        if (placeholder) placeholder.classList.remove('d-none');
        if (interfaceDiv) interfaceDiv.classList.add('d-none');
        disableReplyMode();
        clearAttachments();
    }
}

// ==========================================
// GESTIÓN DE MENSAJES Y SCROLL
// ==========================================

async function loadChatMessages(uuid, type, isInitialLoad = false) {
    const container = document.querySelector('.chat-messages-area');
    if (!container) return;

    if (isLoadingMessages || (!hasMoreMessages && !isInitialLoad)) return;
    isLoadingMessages = true;

    if (isInitialLoad) {
        container.innerHTML = '<div class="small-spinner" style="margin:auto;"></div>';
    } else {
        const loader = document.createElement('div');
        loader.className = 'chat-loading-more';
        loader.innerHTML = '<div class="small-spinner"></div>';
        container.prepend(loader);
    }

    const res = await postJson('api/chat_handler.php', { 
        action: 'get_messages', 
        target_uuid: uuid,
        context: type,
        limit: MESSAGES_PER_PAGE,
        offset: currentOffset
    });

    const loadingMoreSpinner = container.querySelector('.chat-loading-more');
    if (loadingMoreSpinner) loadingMoreSpinner.remove();

    if (res.success) {
        const messages = res.messages;
        if (messages.length < MESSAGES_PER_PAGE) hasMoreMessages = false;
        currentOffset += messages.length;

        if (isInitialLoad) {
            container.innerHTML = '';
            processAndRenderBatch(container, messages, true);
            scrollToBottom();
        } else {
            const prevHeight = container.scrollHeight;
            processAndRenderBatch(container, messages, false);
            const newHeight = container.scrollHeight;
            container.scrollTop = newHeight - prevHeight;
        }
    } else {
        if (isInitialLoad) container.innerHTML = `<div style="text-align:center; color:#999; margin-top:20px;">Error: ${res.message}</div>`;
    }

    isLoadingMessages = false;
}

function handleChatScroll(e) {
    const container = e.target;
    const header = document.querySelector('.chat-header');

    if (container.scrollTop > 10) {
        header?.classList.add('shadow');
    } else {
        header?.classList.remove('shadow');
    }

    if (container.scrollTop === 0 && hasMoreMessages && !isLoadingMessages) {
        loadChatMessages(currentChatUuid, currentChatType, false);
    }
}

function processAndRenderBatch(container, messages, isAppend) {
    if (messages.length === 0) return;
    let htmlBatch = '';
    let lastDateInBatch = null;

    messages.forEach((msg) => {
        const msgDate = getDateString(msg.created_at);
        if (msgDate !== lastDateInBatch) {
            htmlBatch += createDateDivider(msgDate);
            lastDateInBatch = msgDate;
        }
        htmlBatch += createMessageHTML(msg);
    });

    if (isAppend) container.insertAdjacentHTML('beforeend', htmlBatch);
    else {
        container.insertAdjacentHTML('afterbegin', htmlBatch);
    }
}

function createDateDivider(dateStr) {
    return `<div class="chat-date-divider"><span>${dateStr}</span></div>`;
}

function createMessageHTML(msg) {
    const myId = window.USER_ID; 
    const isMe = (parseInt(msg.sender_id) === parseInt(myId));
    
    if (msg.status === 'deleted') {
        return createDeletedMessageHTML(msg, isMe);
    }

    const timeStr = formatChatTime(msg.created_at);
    let avatarUrl = msg.sender_profile_picture 
        ? (window.BASE_PATH || '/ProjectAurora/') + msg.sender_profile_picture 
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(msg.sender_username)}`;

    const role = msg.sender_role || 'user';

    // Reply Logic
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
        replyHtml = `<div class="message-reply-preview"><span class="reply-preview-user">${escapeHtml(replyUser)}</span><span class="reply-preview-text">${replyText}</span></div>`;
    }

    // Attachments Logic
    let attachmentsHtml = '';
    if (msg.attachments && Array.isArray(msg.attachments) && msg.attachments.length > 0) {
        const count = msg.attachments.length;
        const viewerItems = msg.attachments.map(att => ({
            src: (window.BASE_PATH || '/ProjectAurora/') + att.path,
            type: att.type,
            user: { name: msg.sender_username, avatar: avatarUrl },
            date: getDateString(msg.created_at) + ' ' + timeStr
        }));
        const jsonStr = JSON.stringify(viewerItems).replace(/'/g, "&apos;").replace(/"/g, '&quot;');
        
        let imgs = '';
        msg.attachments.forEach((att, idx) => {
            const src = (window.BASE_PATH || '/ProjectAurora/') + att.path;
            imgs += `<img src="${src}" data-action="view-media" data-index="${idx}">`;
        });
        attachmentsHtml = `<div class="msg-attachments" data-count="${count}" data-media-items='${jsonStr}'>${imgs}</div>`;
    }

    const optionsBtn = `<button class="message-options-btn" data-action="msg-options" data-id="${msg.id}" data-user="${msg.sender_username}" data-text="${escapeHtml(msg.message)}" data-sender-id="${msg.sender_id}" data-created-at="${msg.created_at}"><span class="material-symbols-rounded" style="font-size: 18px;">more_vert</span></button>`;

    return `
        <div class="message-row ${isMe ? 'message-own' : 'message-other'}" id="msg-${msg.id}" style="display:flex; flex-direction:${isMe ? 'row-reverse' : 'row'}; margin-bottom:12px; gap:4px; align-items:flex-start;">
            ${!isMe ? `<div class="chat-message-avatar" data-role="${role}" title="${msg.sender_username}"><img src="${avatarUrl}" alt="${msg.sender_username}"></div>` : ''}
            <div class="message-bubble" style="max-width: 70%; padding: 8px 12px; border-radius: 12px; background-color: ${isMe ? '#dcf8c6' : '#fff'}; border: 1px solid ${isMe ? '#dcf8c6' : '#e0e0e0'}; position: relative; font-size: 14px; color: #333; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                ${replyHtml} 
                ${!isMe && currentChatType === 'community' ? `<div style="font-size:11px; font-weight:700; color:#e91e63; margin-bottom:2px;">${msg.sender_username}</div>` : ''}
                ${attachmentsHtml} 
                ${msg.message ? `<div class="message-text" style="word-wrap: break-word; line-height: 1.4;">${escapeHtml(msg.message)}</div>` : ''}
                <div class="message-time" style="font-size:10px; color:#999; text-align:right; margin-top:4px;">${timeStr}</div>
            </div>
            ${optionsBtn} 
        </div>
    `;
}

function createDeletedMessageHTML(msg, isMe) {
    return `<div class="message-row ${isMe ? 'message-own' : 'message-other'}" id="msg-${msg.id}" style="display:flex; flex-direction:${isMe ? 'row-reverse' : 'row'}; margin-bottom:12px; gap:4px; align-items:flex-start; opacity: 0.6;">
             <div class="message-bubble" style="max-width: 70%; padding: 8px 12px; border-radius: 12px; background-color: #f5f5f5; border: 1px solid #e0e0e0; color: #666; font-style: italic; font-size: 13px;">
                <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">block</span>
                ${t('chat.message_deleted') || 'Eliminado'}
            </div>
        </div>`;
}

// ==========================================
// GESTIÓN DE ENVÍO
// ==========================================

function initAttachmentListeners() {
    const fileInput = document.getElementById('chat-file-input');
    const attachBtn = document.getElementById('btn-attach-file');
    
    if (attachBtn && fileInput) {
        attachBtn.onclick = () => fileInput.click();
        fileInput.onchange = (e) => {
            const files = Array.from(e.target.files);
            if (selectedFiles.length + files.length > 4) {
                if (window.alertManager) window.alertManager.showAlert("Máximo 4 imágenes.", "warning");
                return;
            }
            selectedFiles = [...selectedFiles, ...files];
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
        div.innerHTML = `<img src="${url}"><div class="preview-remove" data-index="${index}">✕</div>`;
        grid.appendChild(div);
    });
    
    grid.querySelectorAll('.preview-remove').forEach(btn => {
        btn.onclick = (e) => {
            selectedFiles.splice(parseInt(e.target.dataset.index), 1);
            renderPreview();
        };
    });
}

function clearAttachments() {
    selectedFiles = [];
    renderPreview();
}

async function sendMessage() {
    if (!currentChatUuid) return;
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
        formData.append('target_uuid', currentChatUuid);
        formData.append('context', currentChatType);
        formData.append('message', text);
        if (replyingToMessageId) formData.append('reply_to_id', replyingToMessageId);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        selectedFiles.forEach(file => formData.append('attachments[]', file));

        try {
            const res = await fetch((window.BASE_PATH || '/ProjectAurora/') + 'api/chat_handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                input.value = '';
                clearAttachments();
                disableReplyMode();
            } else {
                if(window.alertManager) window.alertManager.showAlert(data.message || 'Error', 'error');
            }
        } catch (e) {
            console.error(e);
        }
        btn.disabled = false;
        btn.innerHTML = originalIcon;
    } else {
        if (window.socketService && window.socketService.socket && window.socketService.socket.readyState === WebSocket.OPEN) {
            const payload = {
                type: 'chat_message',
                payload: { 
                    target_uuid: currentChatUuid, 
                    context: currentChatType,
                    message: text 
                }
            };
            if (replyingToMessageId) payload.payload.reply_to_id = replyingToMessageId;
            
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
// RESPUESTAS Y ACCIONES
// ==========================================

function enableReplyMode(msgId, senderName, messageText) {
    replyingToMessageId = msgId;
    replyingToMessageData = { user: senderName, text: messageText };
    const container = document.getElementById('reply-preview-container');
    if (container) {
        document.getElementById('reply-target-user').textContent = senderName;
        document.getElementById('reply-target-text').textContent = messageText || '📷 [Imagen]';
        container.classList.remove('d-none');
    }
    document.querySelector('.chat-message-input')?.focus();
}

function disableReplyMode() {
    replyingToMessageId = null;
    replyingToMessageData = null;
    document.getElementById('reply-preview-container')?.classList.add('d-none');
}

function showMessagePopover(btn, msgId, user, text) {
    closeMessagePopover();
    const senderId = btn.dataset.senderId;
    const isMe = (parseInt(senderId) === parseInt(window.USER_ID));
    
    let extraOptions = '';
    
    if (isMe) {
        extraOptions += `<div class="message-option-item" data-action="delete-message" data-id="${msgId}" style="color:#d32f2f;">
            <span class="material-symbols-rounded" style="font-size: 18px;">delete</span> ${t('chat.actions.delete') || 'Eliminar'}
        </div>`;
    } else {
        extraOptions += `<div class="message-option-item" data-action="report-message" data-id="${msgId}" style="color:#f57c00;">
            <span class="material-symbols-rounded" style="font-size: 18px;">flag</span> ${t('chat.actions.report') || 'Reportar'}
        </div>`;
    }

    const popover = document.createElement('div');
    popover.className = 'message-options-popover';
    popover.innerHTML = `
        <div class="message-option-item" data-action="reply-message">
            <span class="material-symbols-rounded" style="font-size: 18px;">reply</span> ${t('chat.actions.reply') || 'Responder'}
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
    if (delBtn) delBtn.addEventListener('click', () => {
        handleDeleteMessage(msgId);
        closeMessagePopover();
    });

    const repBtn = popover.querySelector('[data-action="report-message"]');
    if (repBtn) repBtn.addEventListener('click', () => {
        handleReportMessage(msgId);
        closeMessagePopover();
    });
}

function closeMessagePopover() {
    document.querySelector('.message-options-popover')?.remove();
}

async function handleDeleteMessage(msgId) {
    if (!confirm(t('global.are_you_sure') || '¿Estás seguro de eliminar este mensaje?')) return;

    const res = await postJson('api/chat_handler.php', { 
        action: 'delete_message', 
        message_id: msgId,
        context: currentChatType,
        target_uuid: currentChatUuid
    });

    if (res.success) {
        const msgEl = document.getElementById(`msg-${msgId}`);
        if (msgEl) msgEl.style.opacity = '0.5';
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

async function handleReportMessage(msgId) {
    const reason = prompt(t('chat.report_reason') || 'Razón del reporte:');
    if (!reason) return;

    const res = await postJson('api/chat_handler.php', { 
        action: 'report_message', 
        message_id: msgId,
        reason: reason,
        context: currentChatType
    });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(t('chat.report_success') || 'Reporte enviado.', 'success');
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

async function handleDeleteConversation(uuid) {
    if(!confirm('¿Seguro que quieres eliminar este chat? Solo se borrará para ti.')) return;
    
    const res = await postJson('api/chat_handler.php', {
        action: 'delete_conversation',
        target_uuid: uuid
    });

    if(res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if(window.navigateTo) window.navigateTo('main');
        else window.location.href = window.BASE_PATH + 'main';
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

// ==========================================
// INICIALIZACIÓN
// ==========================================

function initChatListeners() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'new_chat_message' || type === 'private_message') {
            
            // [NUEVO] Lógica robusta de validación para renderizar el mensaje
            let isForCurrentChat = false;

            // 1. Mensaje de Comunidad: Fácil, el UUID de comunidad coincide
            if (currentChatType === 'community' && type === 'new_chat_message') {
                if (payload.community_uuid === currentChatUuid) {
                    isForCurrentChat = true;
                }
            } 
            // 2. Mensaje Privado
            else if (currentChatType === 'private' && type === 'private_message') {
                // Caso A: Mensaje enviado por mí (eco)
                // Debo estar en el chat cuyo UUID es el del receptor (target_uuid)
                if (parseInt(payload.sender_id) === parseInt(window.USER_ID) && payload.target_uuid === currentChatUuid) {
                    isForCurrentChat = true;
                }
                // Caso B: Mensaje recibido de otro
                // El remitente debe ser la persona con la que estoy hablando (currentChatId)
                else if (currentChatId && parseInt(payload.sender_id) === parseInt(currentChatId)) {
                    isForCurrentChat = true;
                }
            }

            // Si pasa la validación, inyectamos el mensaje
            if (isForCurrentChat) {
                const container = document.querySelector('.chat-messages-area');
                if (container) {
                    const msgDate = getDateString(payload.created_at);
                    const lastDiv = container.querySelectorAll('.chat-date-divider span');
                    let lastDivDate = lastDiv.length > 0 ? lastDiv[lastDiv.length-1].innerText : '';
                    
                    if (msgDate !== lastDivDate) container.insertAdjacentHTML('beforeend', createDateDivider(msgDate));
                    container.insertAdjacentHTML('beforeend', createMessageHTML(payload));
                    scrollToBottom();
                    currentOffset++;
                }
            }
        }

        if (type === 'message_deleted' || type === 'private_message_deleted') {
            const msgId = payload.message_id;
            const msgEl = document.getElementById(`msg-${msgId}`);
            if (msgEl) {
                const wrapper = document.createElement('div');
                const dummyMsg = { id: msgId, status: 'deleted', sender_id: payload.sender_id };
                const isMe = (parseInt(payload.sender_id) === parseInt(window.USER_ID));
                wrapper.innerHTML = createDeletedMessageHTML(dummyMsg, isMe);
                msgEl.replaceWith(wrapper.firstElementChild);
            }
        }
    });

    const input = document.querySelector('.chat-message-input');
    const sendBtn = document.getElementById('btn-send-message');
    if (input) input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); sendMessage(); } });
    if (sendBtn) sendBtn.addEventListener('click', (e) => { e.preventDefault(); sendMessage(); });

    const messagesArea = document.querySelector('.chat-messages-area');
    if (messagesArea) {
        messagesArea.addEventListener('scroll', handleChatScroll);
    }
}

function initListeners() {
    document.body.addEventListener('click', async (e) => {
        
        if (e.target.closest('#btn-back-to-list')) {
            const layout = document.querySelector('.chat-layout-container');
            if (layout) layout.classList.remove('chat-active');
            document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
            window.ACTIVE_CHAT_UUID = null;
            currentChatUuid = null;
            currentChatId = null; // Limpiar ID
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
        }
        
        const msgOptBtn = e.target.closest('[data-action="msg-options"]');
        if (msgOptBtn) { e.stopPropagation(); showMessagePopover(msgOptBtn, msgOptBtn.dataset.id, msgOptBtn.dataset.user, msgOptBtn.dataset.text); }

        if (e.target.closest('#btn-cancel-reply')) disableReplyMode();
        
        if (e.target.closest('[data-action="toggle-group-info"]')) { 
            e.preventDefault(); 
            const sidebar = document.getElementById('chat-info-panel');
            if(sidebar) {
                sidebar.classList.toggle('d-none');
                if(!sidebar.classList.contains('d-none') && currentChatType === 'community') {
                    document.dispatchEvent(new CustomEvent('reload-group-info', { detail: { uuid: currentChatUuid } }));
                }
            }
        }
        if (e.target.closest('[data-action="close-group-info"]')) { 
            e.preventDefault(); 
            document.getElementById('chat-info-panel')?.classList.add('d-none'); 
        }

        const delChatBtn = e.target.closest('[data-action="delete-chat-conversation"]');
        if (delChatBtn) {
            e.preventDefault();
            const uuid = delChatBtn.dataset.uuid;
            document.querySelector('.chat-popover-menu')?.remove();
            await handleDeleteConversation(uuid);
        }
    });
}

export function initChatManager() {
    initAttachmentListeners();
    initChatListeners();
    initListeners();
}