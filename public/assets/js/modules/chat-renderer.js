// public/assets/js/modules/chat-renderer.js

import { t } from '../core/i18n-manager.js';

// --- HELPERS INTERNOS DE FORMATO ---

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

// --- HELPER DE REACCIONES ---
function getReactionsHTML(reactions) {
    if (!reactions || Object.keys(reactions).length === 0) return '';
    
    let html = '<div class="reactions-bar">';
    for (const [emoji, count] of Object.entries(reactions)) {
        if (count > 0) {
            html += `<span class="reaction-bubble">${emoji} <span class="reaction-count">${count}</span></span>`;
        }
    }
    html += '</div>';
    return html;
}

// --- GENERADORES DE HTML ---

export function createDateDivider(dateStr) {
    return `<div class="chat-date-divider"><span>${dateStr}</span></div>`;
}

export function createDeletedMessageHTML(msg, isMe, msgId) {
    return `
        <div class="message-row ${isMe ? 'message-own' : 'message-other'}" id="msg-${msgId}" style="opacity: 0.6;">
             <div class="message-bubble" style="background-color: #f5f5f5; border: 1px solid #e0e0e0; color: #666; font-style: italic;">
                <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">block</span>
                ${t('chat.message_deleted') || 'Eliminado'}
            </div>
        </div>`;
}

export function createMessageHTML(msg, currentChatType) {
    const myId = window.USER_ID; 
    const isMe = (parseInt(msg.sender_id) === parseInt(myId));
    const msgId = msg.uuid; 

    if (msg.status === 'deleted') {
        return createDeletedMessageHTML(msg, isMe, msgId);
    }

    const timeStr = formatChatTime(msg.created_at);
    let avatarUrl = msg.sender_profile_picture 
        ? (window.BASE_PATH || '/ProjectAurora/') + msg.sender_profile_picture 
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(msg.sender_username)}`;

    const role = msg.sender_role || 'user';
    
    // Manejo de tag de editado
    const editedHtml = (msg.is_edited) ? `<small class="edited-tag" style="margin-left:4px; color:#999;">${t('chat.edited_tag') || '(editado)'}</small>` : '';

    let replyHtml = '';
    if (msg.reply_to_uuid || msg.reply_to_id) {
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

    // Grid de imágenes
    let attachmentsHtml = '';
    if (msg.attachments && Array.isArray(msg.attachments) && msg.attachments.length > 0) {
        const totalCount = msg.attachments.length;
        const viewerItems = msg.attachments.map(att => ({
            src: (window.BASE_PATH || '/ProjectAurora/') + att.path,
            type: att.type,
            user: { name: msg.sender_username, avatar: avatarUrl },
            date: getDateString(msg.created_at) + ' ' + timeStr
        }));
        const jsonStr = JSON.stringify(viewerItems).replace(/'/g, "&apos;").replace(/"/g, '&quot;');
        const displayCount = Math.min(totalCount, 4);
        const gridType = displayCount; 

        let itemsHtml = '';
        for (let i = 0; i < displayCount; i++) {
            const att = msg.attachments[i];
            const src = (window.BASE_PATH || '/ProjectAurora/') + att.path;
            let overlayHtml = '';
            if (i === 3 && totalCount > 4) {
                const remaining = totalCount - 3; 
                overlayHtml = `<div class="more-images-overlay">+${remaining}</div>`;
            }
            itemsHtml += `
                <div class="attachment-item" data-action="view-media" data-index="${i}">
                    <img src="${src}" loading="lazy" onerror="this.style.display='none'; this.parentElement.classList.add('is-broken');">
                    ${overlayHtml}
                </div>
            `;
        }
        attachmentsHtml = `<div class="msg-attachments" data-grid="${gridType}" data-media-items='${jsonStr}'>${itemsHtml}</div>`;
    }

    // --- REACCIONES (NUEVO) ---
    // Se asume que msg.reactions puede venir como objeto {'👍': 1}
    // Si no viene, se renderiza vacío pero el contenedor debe existir para updates futuros si se desea, 
    // o se inyecta dinámicamente. Aquí usamos inyección condicional en getReactionsHTML.
    const reactionsHtml = getReactionsHTML(msg.reactions);

    const optionsBtn = `<button class="message-options-btn" data-action="msg-options" data-uuid="${msgId}" data-user="${msg.sender_username}" data-text="${escapeHtml(msg.message)}" data-sender-id="${msg.sender_id}" data-created-at="${msg.created_at}"><span class="material-symbols-rounded" style="font-size: 18px;">more_vert</span></button>`;

    return `
        <div class="message-row ${isMe ? 'message-own' : 'message-other'}" id="msg-${msgId}">
            ${!isMe ? `<div class="chat-message-avatar" data-role="${role}" title="${msg.sender_username}"><img src="${avatarUrl}" alt="${msg.sender_username}" data-img-type="user"></div>` : ''}
            <div class="message-bubble">
                ${replyHtml} 
                ${!isMe && currentChatType === 'community' ? `<div style="font-size:11px; font-weight:700; color:#e91e63; margin-bottom:2px;">${msg.sender_username}</div>` : ''}
                ${attachmentsHtml} 
                <div class="message-content-wrapper">
                    ${msg.message ? `<div class="message-text">${escapeHtml(msg.message)}</div>` : ''}
                    <div class="message-time">
                        ${timeStr} ${editedHtml}
                    </div>
                </div>
                ${reactionsHtml} </div>
            ${optionsBtn} 
        </div>
    `;
}

// --- MANIPULACIÓN DEL DOM ---

export function updateMessageReactions(msgUuid, reactionsData) {
    const msgRow = document.getElementById(`msg-${msgUuid}`);
    if (!msgRow) return;

    const bubble = msgRow.querySelector('.message-bubble');
    if (!bubble) return;

    // Buscar si ya existe la barra
    let bar = bubble.querySelector('.reactions-bar');
    
    // Generar nuevo HTML
    const newHtml = getReactionsHTML(reactionsData);

    if (bar) {
        if (!newHtml) {
            bar.remove(); // Si no hay reacciones, quitar barra
        } else {
            bar.outerHTML = newHtml; // Reemplazar
        }
    } else if (newHtml) {
        // Insertar al final de la burbuja
        bubble.insertAdjacentHTML('beforeend', newHtml);
    }
}

export function scrollToBottom() {
    const container = document.querySelector('.chat-messages-area');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

export function showTypingIndicator() {
    const status = document.getElementById('chat-header-status');
    if (!status) return;

    if (!status.classList.contains('typing-dots')) {
        status.textContent = ''; 
        status.classList.add('typing-active', 'typing-dots');
    }
}

export function resetHeaderStatus() {
    const status = document.getElementById('chat-header-status');
    if (!status) return;

    status.classList.remove('typing-active', 'typing-dots');
    status.textContent = status.dataset.originalText || 'Chat Directo';
}

export function renderEmptyChatState(container, chatData, chatType) {
    if (!chatData) return;
    
    let html = '';

    if (chatType === 'community') {
        const channelName = chatData.channel_name || 'General';
        html = `
            <div class="chat-empty-state community-welcome">
                <div class="chat-welcome-icon-container">
                    <span class="material-symbols-rounded">tag</span>
                </div>
                <div class="chat-empty-text">
                    <h3>Te damos la bienvenida a #${escapeHtml(channelName)}</h3>
                    <p>Aquí empieza el canal de #${escapeHtml(channelName)}.</p>
                </div>
            </div>
        `;
    } else {
        const name = chatData.name || chatData.username || 'Usuario';
        const avatar = chatData.profile_picture 
            ? (window.BASE_PATH || '/ProjectAurora/') + chatData.profile_picture 
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}`;

        html = `
            <div class="chat-empty-state private-empty">
                <div class="chat-empty-avatar">
                    <img src="${avatar}" alt="${escapeHtml(name)}" data-img-type="user">
                </div>
                <div class="chat-empty-text">
                    <h3>Aquí comienza tu historia con ${escapeHtml(name)}</h3>
                    <p>Envía un mensaje para iniciar la conversación.</p>
                    <span class="material-symbols-rounded chat-empty-icon">waving_hand</span>
                </div>
            </div>
        `;
    }

    container.insertAdjacentHTML('afterbegin', html);
}

export function processAndRenderBatch(container, messages, isAppend, currentChatType) {
    if (messages.length === 0) return;
    let htmlBatch = '';
    let lastDateInBatch = null;

    messages.forEach((msg) => {
        const msgDate = getDateString(msg.created_at);
        if (msgDate !== lastDateInBatch) {
            htmlBatch += createDateDivider(msgDate);
            lastDateInBatch = msgDate;
        }
        htmlBatch += createMessageHTML(msg, currentChatType);
    });

    if (isAppend) {
        container.insertAdjacentHTML('beforeend', htmlBatch);
    } else {
        const welcomeEl = container.querySelector('.chat-empty-state');
        if (welcomeEl) {
            welcomeEl.insertAdjacentHTML('afterend', htmlBatch);
        } else {
            container.insertAdjacentHTML('afterbegin', htmlBatch);
        }
    }
}

export function appendSingleMessage(container, msg, currentChatType) {
    if (!container) return;
    const msgDate = getDateString(msg.created_at);
    const lastDiv = container.querySelectorAll('.chat-date-divider span');
    let lastDivDate = lastDiv.length > 0 ? lastDiv[lastDiv.length-1].innerText : '';
    
    if (msgDate !== lastDivDate) {
        container.insertAdjacentHTML('beforeend', createDateDivider(msgDate));
    }
    container.insertAdjacentHTML('beforeend', createMessageHTML(msg, currentChatType));
}

export function updateChatInterface(data) {
    const placeholders = document.querySelectorAll('.chat-placeholder');
    const interfaceDiv = document.getElementById('chat-interface');
    const img = document.getElementById('chat-header-img');
    const title = document.getElementById('chat-header-title');
    const status = document.getElementById('chat-header-status');
    const infoBtn = document.getElementById('btn-group-info-toggle');
    const headerInfo = document.getElementById('chat-header-info-clickable');
    
    const inputArea = document.querySelector('.chat-input-area');
    const messageInput = document.querySelector('.chat-message-input');
    const sendBtn = document.getElementById('btn-send-message');
    const attachBtn = document.getElementById('btn-attach-file');
    const messagesArea = document.querySelector('.chat-messages-area');
    const headerAvatarContainer = document.querySelector('.chat-avatar-container'); 

    const maintenanceEl = document.getElementById('maintenance-overlay');
    if (maintenanceEl) maintenanceEl.remove();
    
    if (messagesArea) messagesArea.style.display = 'flex';
    if (inputArea) inputArea.style.display = 'flex';

    if (data) {
        if (placeholders.length > 0) placeholders.forEach(el => el.classList.add('d-none'));
        if (interfaceDiv) interfaceDiv.classList.remove('d-none');
        
        const isPrivate = (data.type === 'private');
        const communityName = data.name || data.community_name || data.username;
        const pic = data.profile_picture;

        let avatarPath;
        if (pic) {
            avatarPath = pic.startsWith('http') ? pic : (window.BASE_PATH || '/ProjectAurora/') + pic;
        } else {
            avatarPath = `https://ui-avatars.com/api/?name=${encodeURIComponent(communityName)}`;
        }

        if (img) {
            img.removeAttribute('data-has-fallback'); 
            img.src = avatarPath;
            img.style.borderRadius = isPrivate ? '50%' : '12px'; 
            img.setAttribute('data-img-type', isPrivate ? 'user' : 'community');
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

        if (title) {
            const verifiedBadge = (!isPrivate && parseInt(data.is_verified) === 1) 
                ? `<span class="material-symbols-rounded" style="font-size:18px; color:#1976d2; margin-left:6px; vertical-align: bottom;" title="Oficial">verified</span>` 
                : '';
            if (!isPrivate && data.channel_name) {
                title.innerHTML = `# ${escapeHtml(data.channel_name)} ${verifiedBadge}`;
            } else {
                title.innerHTML = `${escapeHtml(communityName)} ${verifiedBadge}`;
            }
        }
        
        if (status) {
            let defaultText;
            if (isPrivate) {
                defaultText = 'Chat Directo';
            } else {
                defaultText = data.channel_name ? communityName : `${data.member_count || 0} miembros`;
            }
            status.textContent = defaultText;
            status.dataset.originalText = defaultText;
            status.classList.remove('typing-active', 'typing-dots');
        }

        const isCommMaintenance = (data.status === 'maintenance');
        const isChannelMaintenance = (data.channel_status === 'maintenance');

        if (isCommMaintenance || isChannelMaintenance) {
            if (messagesArea) messagesArea.style.display = 'none';
            if (inputArea) inputArea.style.display = 'none';

            let maintTitle = '', maintDesc = '', maintIcon = 'engineering';
            if (isCommMaintenance) {
                maintTitle = t('status.maintenance_title') || 'Comunidad en Mantenimiento';
                maintDesc = t('status.community_maintenance_msg') || 'Esta comunidad se encuentra en mantenimiento.';
            } else {
                maintTitle = t('status.channel_maintenance_title') || 'Canal en Mantenimiento';
                maintDesc = t('status.channel_maintenance_msg') || 'Este canal no se encuentra habilitado.';
                maintIcon = 'cloud_off';
            }
            const maintHtml = `
                <div id="maintenance-overlay" style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; color:#666;">
                    <div style="width:80px; height:80px; background:#fff3e0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                        <span class="material-symbols-rounded" style="font-size:40px; color:#f57c00;">${maintIcon}</span>
                    </div>
                    <h2 style="font-size:20px; margin-bottom:8px; color:#333;">${maintTitle}</h2>
                    <p style="max-width:300px;">${maintDesc}</p>
                </div>
            `;
            interfaceDiv.insertAdjacentHTML('beforeend', maintHtml);
            if (infoBtn) infoBtn.style.display = 'flex'; 
            return; 
        }

        if (inputArea) inputArea.style.display = 'flex';
        if (messageInput) {
            messageInput.disabled = false;
            const phText = (!isPrivate && data.channel_name) ? `Enviar mensaje a #${data.channel_name}` : 'Escribe un mensaje...';
            messageInput.placeholder = phText;
        }
        if (sendBtn) sendBtn.disabled = false;
        if (attachBtn) attachBtn.disabled = false;

        if (infoBtn) infoBtn.style.display = 'flex';
        if (headerInfo) headerInfo.style.pointerEvents = 'auto';

        const infoPanel = document.getElementById('chat-info-panel');
        if (infoPanel && !infoPanel.classList.contains('d-none')) {
            document.dispatchEvent(new CustomEvent('reload-group-info', { detail: { uuid: data.uuid } }));
        }

        if (isPrivate) {
            if (data.can_message === false) {
                if (messageInput) {
                    messageInput.disabled = true;
                    messageInput.placeholder = t('chat.error.privacy_placeholder') || 'Este usuario no permite mensajes de desconocidos.';
                    messageInput.value = '';
                }
                if (sendBtn) sendBtn.disabled = true;
                if (attachBtn) attachBtn.disabled = true;
            }
        }
        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.add('chat-active');
    } else {
        if (placeholders.length > 0) {
            const selectPlaceholder = document.getElementById('chat-placeholder-select');
            if (selectPlaceholder) {
                selectPlaceholder.classList.remove('d-none');
                const welcomePlaceholder = document.getElementById('chat-placeholder-welcome');
                if (welcomePlaceholder) welcomePlaceholder.classList.add('d-none');
            } else {
                 placeholders.forEach(el => el.classList.remove('d-none'));
            }
        }
        if (interfaceDiv) interfaceDiv.classList.add('d-none');
    }
}

export function renderAttachmentPreview(selectedFiles, container, grid) {
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
}

export function updateReplyUI(isReplying, data = null) {
    const container = document.getElementById('reply-preview-container');
    if (!container) return;
    if (isReplying && data) {
        document.getElementById('reply-target-user').textContent = data.user;
        document.getElementById('reply-target-text').textContent = data.text || '📷 [Imagen]';
        container.classList.remove('d-none');
    } else {
        container.classList.add('d-none');
    }
}

export function showMessagePopover(btn, msgUuid, user, text, isMe, createdAt, onReply, onEdit, onDelete, onReport, onReact) {
    // Cerrar cualquier popover existente
    document.querySelector('.message-options-popover')?.remove();

    // Opciones dinámicas
    let editOption = '';
    let extraOptions = '';

    if (isMe && createdAt) {
        const msgTime = new Date(createdAt).getTime();
        const now = Date.now();
        if (now - msgTime < 600000) { // 10 mins
             editOption = `
            <div class="menu-link" data-action="edit-message-mode" data-uuid="${msgUuid}" data-text="${escapeHtml(text)}">
                <div class="menu-link-icon"><span class="material-symbols-rounded">edit</span></div>
                <div class="menu-link-text">${t('chat.actions.edit') || 'Editar'}</div>
            </div>`;
        }
    }

    if (isMe) {
        extraOptions += `
        <div class="menu-link" data-action="delete-message" data-uuid="${msgUuid}">
             <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#d32f2f;">delete</span></div>
             <div class="menu-link-text" style="color:#d32f2f;">${t('chat.actions.delete') || 'Eliminar'}</div>
        </div>`;
    } else {
        extraOptions += `
        <div class="menu-link" data-action="report-message" data-uuid="${msgUuid}">
             <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#f57c00;">flag</span></div>
             <div class="menu-link-text" style="color:#f57c00;">${t('chat.actions.report') || 'Reportar'}</div>
        </div>`;
    }

    const popover = document.createElement('div');
    popover.className = 'popover-module message-options-popover active';
    
    // [NUEVO] Selector de Emojis
    const emojis = ['👍', '❤️', '😂', '😮', '😢', '😡'];
    const emojiHtml = emojis.map(e => `<button class="reaction-btn" data-emoji="${e}">${e}</button>`).join('');

    popover.innerHTML = `
        <div class="menu-content">
            <div class="reaction-picker-container" style="padding: 8px 12px; display: flex; gap: 6px; border-bottom: 1px solid #eee;">
                ${emojiHtml}
            </div>
            <div class="menu-list">
                <div class="menu-link" data-action="reply-message">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">reply</span></div>
                    <div class="menu-link-text">${t('chat.actions.reply') || 'Responder'}</div>
                </div>
                ${editOption}
                ${extraOptions}
            </div>
        </div>
    `;
    
    // Posicionamiento
    const rect = btn.getBoundingClientRect();
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    
    popover.style.position = 'absolute';
    popover.style.top = (rect.bottom + scrollTop) + 'px';
    popover.style.left = (rect.left - 100) + 'px'; 
    popover.style.width = '240px'; // Un poco más ancho para emojis
    popover.style.zIndex = '1000'; 

    document.body.appendChild(popover);

    setTimeout(() => {
        const closeHandler = (e) => {
            if (!popover.contains(e.target)) {
                popover.remove();
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 0);

    // Asignar acciones
    const replyBtn = popover.querySelector('[data-action="reply-message"]');
    if (replyBtn) replyBtn.addEventListener('click', () => { onReply(); popover.remove(); });
    
    const editBtn = popover.querySelector('[data-action="edit-message-mode"]');
    if (editBtn) editBtn.addEventListener('click', () => { onEdit(); popover.remove(); });

    const delBtn = popover.querySelector('[data-action="delete-message"]');
    if (delBtn) delBtn.addEventListener('click', () => { onDelete(); popover.remove(); });

    const repBtn = popover.querySelector('[data-action="report-message"]');
    if (repBtn) repBtn.addEventListener('click', () => { onReport(); popover.remove(); });

    // [NUEVO] Listeners de Reacciones
    popover.querySelectorAll('.reaction-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const emoji = e.target.dataset.emoji;
            if (onReact) onReact(emoji);
            popover.remove();
        });
    });
}

export function enableEditMessageUI(msgUuid, currentText, onSave) {
    const msgRow = document.getElementById(`msg-${msgUuid}`);
    if (!msgRow) return;

    const bubble = msgRow.querySelector('.message-bubble');
    const contentWrapper = msgRow.querySelector('.message-content-wrapper');
    if (!contentWrapper) return;
    
    const editContainer = document.createElement('div');
    editContainer.className = 'edit-message-container';
    editContainer.style.minWidth = '200px';
    
    editContainer.innerHTML = `
        <div style="font-size:11px; font-weight:bold; color:#666; margin-bottom:4px;">${t('chat.editing_title') || 'Editando mensaje'}</div>
        <textarea class="edit-msg-input" style="width:100%; border:1px solid #ddd; border-radius:6px; padding:6px; font-family:inherit; font-size:14px; resize:none;" rows="2">${currentText}</textarea>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
            <button class="component-button small" data-action="cancel-edit-message" style="font-size:11px; height:28px; padding:0 8px;">${t('chat.cancel_edit') || 'Cancelar'}</button>
            <button class="component-button primary small" data-action="save-edit-message" data-uuid="${msgUuid}" style="font-size:11px; height:28px; padding:0 8px;">${t('chat.save_edit') || 'Guardar'}</button>
        </div>
    `;

    contentWrapper.style.display = 'none';
    bubble.appendChild(editContainer);
    
    const textarea = editContainer.querySelector('textarea');
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);

    editContainer.querySelector('[data-action="cancel-edit-message"]').onclick = () => {
        editContainer.remove();
        contentWrapper.style.display = 'block';
    };
    
    editContainer.querySelector('[data-action="save-edit-message"]').onclick = async () => {
        const newText = textarea.value.trim();
        if (!newText) return alert("El mensaje no puede estar vacío.");
        if (newText === currentText) { 
            editContainer.remove();
            contentWrapper.style.display = 'block';
            return;
        }
        
        const saveBtn = editContainer.querySelector('[data-action="save-edit-message"]');
        saveBtn.disabled = true;
        saveBtn.textContent = '...';

        await onSave(newText, editContainer, contentWrapper);
    };
}