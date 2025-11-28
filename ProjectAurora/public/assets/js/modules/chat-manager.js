// public/assets/js/modules/chat-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';

// Estado del Chat
let currentCommunityUuid = null;
let currentCommunityId = null;
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

export async function openChat(uuid, communityData = null) {
    if (!communityData) {
        const res = await postJson('api/communities_handler.php', { action: 'get_community_by_uuid', uuid });
        if (res.success) {
            communityData = res.community;
        } else {
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
            return;
        }
    }

    currentCommunityId = communityData.id;
    currentCommunityUuid = communityData.uuid;
    window.ACTIVE_COMMUNITY_UUID = uuid;

    // Resetear Paginación
    currentOffset = 0;
    hasMoreMessages = true;
    isLoadingMessages = false;

    // Actualizar UI
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    const activeItem = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
    if (activeItem) {
        activeItem.classList.add('active');
        const badge = activeItem.querySelector('.unread-counter');
        if(badge) badge.remove();
        
        const preview = activeItem.querySelector('.chat-item-preview');
        if(preview) { preview.style.fontWeight = 'normal'; preview.style.color = ''; }
    }

    updateChatInterface(communityData);
    
    // Carga inicial
    loadChatMessages(uuid, true);

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

        disableReplyMode();
        clearAttachments();
        
        const infoPanel = document.getElementById('chat-info-panel');
        if (infoPanel && !infoPanel.classList.contains('d-none')) {
            loadCommunityDetails(comm.uuid);
        }

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

async function loadChatMessages(uuid, isInitialLoad = false) {
    const container = document.querySelector('.chat-messages-area');
    if (!container) return;

    if (isLoadingMessages || (!hasMoreMessages && !isInitialLoad)) return;
    isLoadingMessages = true;

    if (isInitialLoad) {
        container.innerHTML = '<div class="small-spinner" style="margin:auto;"></div>';
    } else {
        // Mostrar spinner pequeño arriba
        const loader = document.createElement('div');
        loader.className = 'chat-loading-more';
        loader.innerHTML = '<div class="small-spinner"></div>';
        container.prepend(loader);
    }

    const res = await postJson('api/chat_handler.php', { 
        action: 'get_messages', 
        community_uuid: uuid,
        limit: MESSAGES_PER_PAGE,
        offset: currentOffset
    });

    // Remover spinner de carga superior si existe
    const loadingMoreSpinner = container.querySelector('.chat-loading-more');
    if (loadingMoreSpinner) loadingMoreSpinner.remove();

    if (res.success) {
        const messages = res.messages;
        
        // Actualizar banderas
        if (messages.length < MESSAGES_PER_PAGE) {
            hasMoreMessages = false;
        }
        currentOffset += messages.length;

        if (isInitialLoad) {
            container.innerHTML = '';
            processAndRenderBatch(container, messages, true); // true = append (abajo)
            scrollToBottom();
            
            // Añadir listener de scroll una sola vez
            container.onscroll = handleChatScroll;
        } else {
            // Guardar altura previa para mantener posición
            const prevHeight = container.scrollHeight;
            
            // Renderizar lote anterior (arriba)
            processAndRenderBatch(container, messages, false); // false = prepend (arriba)
            
            // Restaurar posición de scroll
            const newHeight = container.scrollHeight;
            container.scrollTop = newHeight - prevHeight;
        }

    } else {
        if (isInitialLoad) {
            container.innerHTML = `<div style="text-align:center; color:#999; margin-top:20px;">Error: ${res.message}</div>`;
        }
    }

    isLoadingMessages = false;
}

function handleChatScroll(e) {
    const container = e.target;
    // Si el usuario llega arriba (scrollTop 0) y hay más mensajes
    if (container.scrollTop === 0 && hasMoreMessages && !isLoadingMessages) {
        loadChatMessages(currentCommunityUuid, false);
    }
}

/**
 * Procesa un lote de mensajes e inserta divisores de fecha
 */
function processAndRenderBatch(container, messages, isAppend) {
    if (messages.length === 0) return;

    let htmlBatch = '';
    let lastDateInBatch = null;

    // Recorremos los mensajes del lote. 
    // Nota: 'messages' viene cronológico (viejo -> nuevo) desde el backend.
    
    messages.forEach((msg, index) => {
        const msgDate = getDateString(msg.created_at);
        
        // Si la fecha cambia respecto al anterior en este lote, insertamos divisor
        if (msgDate !== lastDateInBatch) {
            htmlBatch += createDateDivider(msgDate);
            lastDateInBatch = msgDate;
        }
        
        htmlBatch += createMessageHTML(msg);
    });

    if (isAppend) {
        // Carga inicial o nuevos mensajes: Añadir al final
        container.insertAdjacentHTML('beforeend', htmlBatch);
    } else {
        // Carga historial: Añadir al principio
        // Problema: Al prepender, el primer mensaje del lote (el más viejo) tendrá su divisor.
        // Pero el que ERA el primero en el DOM (ahora será subsecuente) también tiene divisor.
        // Necesitamos chequear la frontera.
        
        // 1. Obtener la fecha del primer mensaje que YA estaba en el DOM
        const firstElement = container.firstElementChild;
        let existingTopDate = null;
        if (firstElement && firstElement.classList.contains('chat-date-divider')) {
            existingTopDate = firstElement.innerText.trim(); // Obtener fecha del texto
        }

        // 2. Obtener la fecha del ÚLTIMO mensaje del NUEVO lote
        const lastMsgOfNewBatch = messages[messages.length - 1];
        const lastMsgDate = getDateString(lastMsgOfNewBatch.created_at);

        // 3. Insertar el nuevo HTML al principio
        container.insertAdjacentHTML('afterbegin', htmlBatch);

        // 4. Corrección visual: Si la fecha del último del nuevo lote es IGUAL 
        // a la fecha del divisor que ya estaba, ese divisor sobra (ahora es redundante).
        if (lastMsgDate === existingTopDate) {
            // Buscamos el divisor que bajó (que era el primero antes)
            // Estará justo después del bloque que acabamos de insertar
            // Una forma segura es buscar todos los divisores y borrar los duplicados adyacentes,
            // pero para optimizar:
            
            // El 'firstElement' que guardamos antes sigue siendo referencia válida en memoria,
            // pero ahora está más abajo en el DOM. Lo removemos.
            if (firstElement) firstElement.remove();
        }
    }
}

function createDateDivider(dateStr) {
    return `
        <div class="chat-date-divider">
            <span>${dateStr}</span>
        </div>
    `;
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
                ${!isMe ? `<div style="font-size:11px; font-weight:700; color:#e91e63; margin-bottom:2px;">${msg.sender_username}</div>` : ''}
                ${attachmentsHtml} 
                ${msg.message ? `<div class="message-text" style="word-wrap: break-word; line-height: 1.4;">${escapeHtml(msg.message)}</div>` : ''}
                <div class="message-time" style="font-size:10px; color:#999; text-align:right; margin-top:4px;">${timeStr}</div>
            </div>
            ${optionsBtn} 
        </div>
    `;
}

function createDeletedMessageHTML(msg, isMe) {
    return `
        <div class="message-row ${isMe ? 'message-own' : 'message-other'}" id="msg-${msg.id}" style="display:flex; flex-direction:${isMe ? 'row-reverse' : 'row'}; margin-bottom:12px; gap:4px; align-items:flex-start; opacity: 0.6;">
             <div class="message-bubble" style="max-width: 70%; padding: 8px 12px; border-radius: 12px; background-color: #f5f5f5; border: 1px solid #e0e0e0; color: #666; font-style: italic; font-size: 13px;">
                <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">block</span>
                ${t('chat.message_deleted') || 'Este mensaje ha sido eliminado'}
            </div>
        </div>
    `;
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
        div.innerHTML = `<img src="${url}"><div class="preview-remove" data-index="${index}">✕</div>`;
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
                payload: { community_uuid: currentCommunityUuid, message: text }
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
    if (container) container.classList.add('d-none');
}

function showMessagePopover(btn, msgId, user, text) {
    closeMessagePopover();
    const senderId = btn.dataset.senderId;
    const createdAt = btn.dataset.createdAt;
    const isMe = (parseInt(senderId) === parseInt(window.USER_ID));
    
    let canDelete = false;
    if (isMe) {
        const msgDate = new Date(createdAt);
        const diffHours = (new Date() - msgDate) / 1000 / 60 / 60;
        canDelete = (diffHours < 24);
    }

    let extraOptions = '';
    if (isMe && canDelete) {
        extraOptions = `<div class="message-option-item danger" data-action="delete-message" style="color:#d32f2f;"><span class="material-symbols-rounded" style="font-size: 18px;">delete</span>${t('chat.actions.delete') || 'Eliminar'}</div>`;
    } else if (!isMe) {
        extraOptions = `<div class="message-option-item" data-action="report-message" style="color:#f57c00;"><span class="material-symbols-rounded" style="font-size: 18px;">flag</span>${t('chat.actions.report') || 'Reportar'}</div>`;
    }

    const popover = document.createElement('div');
    popover.className = 'message-options-popover';
    popover.innerHTML = `<div class="message-option-item" data-action="reply-message"><span class="material-symbols-rounded" style="font-size: 18px;">reply</span>${t('chat.actions.reply') || 'Responder'}</div>${extraOptions}`;

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

    popover.querySelector('[data-action="reply-message"]').addEventListener('click', () => { enableReplyMode(msgId, user, text); closeMessagePopover(); });
    const delBtn = popover.querySelector('[data-action="delete-message"]');
    if (delBtn) delBtn.addEventListener('click', () => { if (confirm(t('global.are_you_sure'))) deleteMessage(msgId); closeMessagePopover(); });
    const repBtn = popover.querySelector('[data-action="report-message"]');
    if (repBtn) repBtn.addEventListener('click', () => { const reason = prompt(t('chat.report_reason') || "Razón:"); if (reason) reportMessage(msgId, reason); closeMessagePopover(); });
}

function closeMessagePopover() {
    const existing = document.querySelector('.message-options-popover');
    if (existing) existing.remove();
}

async function deleteMessage(msgId) {
    const res = await postJson('api/chat_handler.php', { action: 'delete_message', message_id: msgId });
    if (!res.success && window.alertManager) window.alertManager.showAlert(res.message, 'error');
}

async function reportMessage(msgId, reason) {
    const res = await postJson('api/chat_handler.php', { action: 'report_message', message_id: msgId, reason: reason });
    if (window.alertManager) window.alertManager.showAlert(res.message, res.success ? 'success' : 'error');
}

// ==========================================
// INFO SIDEBAR (Copiar lógica de previous file si necesario, resumido aquí)
// ==========================================
function toggleGroupInfo() {
    const sidebar = document.getElementById('chat-info-panel');
    if (!sidebar) return;
    if (sidebar.classList.contains('d-none')) {
        sidebar.classList.remove('d-none');
        setTimeout(() => sidebar.classList.add('active'), 10);
        if (currentCommunityUuid) loadCommunityDetails(currentCommunityUuid);
    } else {
        sidebar.classList.remove('active');
        setTimeout(() => sidebar.classList.add('d-none'), 300);
    }
}

async function loadCommunityDetails(uuid) {
    const res = await postJson('api/communities_handler.php', { action: 'get_community_details', uuid: uuid });
    if (res.success) {
        document.getElementById('info-group-name').textContent = res.info.community_name;
        document.getElementById('info-group-desc').textContent = res.info.description || 'Sin descripción';
        document.getElementById('info-member-count').textContent = `(${res.members.length})`;
        
        if (res.info.profile_picture) document.getElementById('info-group-img').src = (window.BASE_PATH || '/ProjectAurora/') + res.info.profile_picture;
        else document.getElementById('info-group-img').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(res.info.community_name)}`;

        const mList = document.getElementById('info-members-list');
        mList.innerHTML = res.members.map(m => `
            <div class="info-member-item">
                <img src="${m.profile_picture ? (window.BASE_PATH || '/ProjectAurora/')+m.profile_picture : 'https://ui-avatars.com/api/?name='+encodeURIComponent(m.username)}" class="info-member-avatar">
                <div class="info-member-details"><span class="info-member-name">${escapeHtml(m.username)}</span><span class="info-member-role">${m.role}</span></div>
            </div>`).join('');
            
        const fGrid = document.getElementById('info-files-grid');
        fGrid.innerHTML = res.files.length ? res.files.map((f, i) => `<img src="${(window.BASE_PATH||'/ProjectAurora/')+f.file_path}" class="info-file-thumb" data-action="view-media" data-index="${i}">`).join('') : '<div class="info-no-files">Sin archivos</div>';
        
        if(res.files.length) {
             const viewerItems = res.files.map(f => ({ src: (window.BASE_PATH||'/ProjectAurora/')+f.file_path, type: f.file_type, user: {name:f.username, avatar:''}, date:'' }));
             fGrid.dataset.mediaItems = JSON.stringify(viewerItems);
        }
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
    window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
}

// ==========================================
// INICIALIZACIÓN Y LISTENERS
// ==========================================

function initChatListeners() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        
        // Mensaje nuevo: solo si estamos en ese chat
        if (type === 'new_chat_message') {
            if (payload.community_uuid === currentCommunityUuid) {
                // Verificar fecha para insertar divisor
                const container = document.querySelector('.chat-messages-area');
                if (container) {
                    const lastMsg = container.lastElementChild;
                    if(lastMsg && lastMsg.querySelector('.message-time')) {
                        // Aquí simplificamos: el server manda timestamp. 
                        // En realidad, para el mensaje "en vivo", simplemente lo añadimos al final.
                        // El cálculo de fecha se hace al renderizar.
                        // Si la fecha hoy es diferente a la del último mensaje renderizado, añadimos divisor.
                        const msgDate = getDateString(payload.created_at);
                        // Truco rápido: buscar último divisor
                        const dividers = container.querySelectorAll('.chat-date-divider span');
                        let lastDivDate = dividers.length > 0 ? dividers[dividers.length-1].innerText : '';
                        
                        if (msgDate !== lastDivDate) {
                            container.insertAdjacentHTML('beforeend', createDateDivider(msgDate));
                        }
                    } else if (!container.firstElementChild) {
                        // Si es el primer mensaje absoluto
                        container.insertAdjacentHTML('beforeend', createDateDivider(getDateString(payload.created_at)));
                    }
                    
                    container.insertAdjacentHTML('beforeend', createMessageHTML(payload));
                    scrollToBottom();
                    currentOffset++; // Importante incrementar offset
                }
            }
        }

        if (type === 'message_update' && payload.status === 'deleted') {
            const msgEl = document.getElementById(`msg-${payload.id}`);
            if (msgEl) {
                msgEl.style.opacity = '0.6';
                msgEl.innerHTML = `<div class="message-bubble" style="max-width:70%;padding:8px 12px;border-radius:12px;background-color:#f5f5f5;border:1px solid #e0e0e0;color:#666;font-style:italic;font-size:13px;"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;margin-right:4px;">block</span>${t('chat.message_deleted')||'Eliminado'}</div>`;
            }
        }
    });

    const input = document.querySelector('.chat-message-input');
    const sendBtn = document.getElementById('btn-send-message');
    if (input) input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); sendMessage(); } });
    if (sendBtn) sendBtn.addEventListener('click', (e) => { e.preventDefault(); sendMessage(); });
}

function initListeners() {
    document.body.addEventListener('click', async (e) => {
        if (e.target.closest('#btn-back-to-list')) handleMobileBack();
        
        const msgOptBtn = e.target.closest('[data-action="msg-options"]');
        if (msgOptBtn) { e.stopPropagation(); showMessagePopover(msgOptBtn, msgOptBtn.dataset.id, msgOptBtn.dataset.user, msgOptBtn.dataset.text); }

        if (e.target.closest('#btn-cancel-reply')) disableReplyMode();
        if (e.target.closest('[data-action="toggle-group-info"]')) { e.preventDefault(); toggleGroupInfo(); }
        if (e.target.closest('[data-action="close-group-info"]')) { e.preventDefault(); toggleGroupInfo(); }
    });
}

export function initChatManager() {
    initAttachmentListeners();
    initChatListeners();
    initListeners();
}