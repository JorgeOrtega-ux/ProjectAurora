// public/assets/js/modules/chat-manager.js

import { ChatApi, CommunityApi } from '../services/api-service.js';
import { t } from '../core/i18n-manager.js';
import * as Renderer from './chat-renderer.js';
import * as ChatActions from './chat-actions.js';

// Estado del Chat
let currentChatUuid = null;
let currentChatId = null; 
let currentChatType = 'community';
let currentChannelUuid = null;
let currentChatData = null; 

let replyingToMessageUuid = null;
let replyingToMessageData = null;
let selectedFiles = [];

// Estado de Paginación
let currentOffset = 0;
const MESSAGES_PER_PAGE = 50;
let isLoadingMessages = false;
let hasMoreMessages = true;

// Estado de UI local (Typing indicators visuals)
let typingTimeout = null;
const TYPING_DISPLAY_TIME = 3000;

// Banderas de Inicialización Global
let areSocketListenersInit = false;
let areGlobalActionsInit = false;

// --- GESTIÓN DE APERTURA DE CHAT ---

export async function openChat(uuid, chatData = null) {
    const isSameChat = (currentChatUuid === uuid);
    const isSameChannel = (chatData && chatData.channel_uuid === currentChannelUuid);
    
    // Si ya tenemos datos, es el mismo chat y tenemos el estado del canal, solo actualizamos UI
    if (isSameChat && isSameChannel && chatData && chatData.channel_status) {
        Renderer.updateChatInterface(chatData);
        return;
    }

    // Si nos pasan chatData, usamos SU tipo. Si no, usamos el global o fallback.
    let type = (chatData && chatData.type) ? chatData.type : (window.ACTIVE_CHAT_TYPE || 'community');

    // Si no hay datos (recarga) O si es comunidad y falta el estado del canal, forzamos la carga completa.
    if (!chatData || (type === 'community' && !chatData.channel_status)) {
        let res;

        if (type === 'private') {
            res = await CommunityApi.getUserChatByUuid(uuid);
            if (res.success) {
                chatData = res.data;
                chatData.type = 'private';
            }
        } else {
            // 1. Obtener info básica de comunidad
            res = await CommunityApi.getByUuid(uuid);
            
            if (res.success) {
                chatData = res.data || res.community;
                chatData.type = 'community';

                // Resolver estado del canal activo al recargar
                const targetChannelUuid = window.ACTIVE_CHANNEL_UUID || chatData.default_channel_uuid;
                
                if (targetChannelUuid) {
                    // Obtenemos detalles completos para saber el status real del canal
                    const detailsRes = await CommunityApi.getDetails(uuid);
                    if (detailsRes.success && detailsRes.channels) {
                        const activeChannel = detailsRes.channels.find(c => c.uuid === targetChannelUuid);
                        if (activeChannel) {
                            chatData.channel_uuid = activeChannel.uuid;
                            chatData.channel_name = activeChannel.name;
                            chatData.channel_status = activeChannel.status; 
                            
                            // Sincronizar variable global
                            window.ACTIVE_CHANNEL_UUID = activeChannel.uuid;
                        }
                    }
                }
            }
        }

        if (!res || !res.success) {
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
            return;
        }
    }

    currentChatUuid = chatData.uuid;
    currentChatId = chatData.id; 
    currentChatType = chatData.type || 'community';
    currentChannelUuid = chatData.channel_uuid || null;
    currentChatData = chatData; 
    
    window.ACTIVE_CHAT_UUID = uuid;
    window.ACTIVE_CHAT_TYPE = currentChatType;
    window.ACTIVE_CHANNEL_UUID = currentChannelUuid;

    currentOffset = 0;
    hasMoreMessages = true;
    isLoadingMessages = false;

    clearTimeout(typingTimeout);
    Renderer.resetHeaderStatus(chatData.type === 'private');

    document.dispatchEvent(new CustomEvent('chat-opened', { detail: chatData }));

    Renderer.updateChatInterface(chatData);
    
    // Solo cargar mensajes si NO está en mantenimiento
    if (chatData.status !== 'maintenance' && chatData.channel_status !== 'maintenance') {
        loadChatMessages(uuid, currentChatType, true);
    }

    const prefix = (currentChatType === 'private') ? 'dm' : 'c';
    let newUrl = `${window.BASE_PATH}${prefix}/${uuid}`;
    
    if (currentChatType === 'community' && currentChannelUuid) {
        newUrl += `/${currentChannelUuid}`;
    }

    if (window.location.pathname !== newUrl) {
        window.history.pushState({ section: `${prefix}/${uuid}` }, '', newUrl);
    }
}

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

    const res = await ChatApi.getMessages(uuid, type, currentOffset, currentChannelUuid, MESSAGES_PER_PAGE);

    const loadingMoreSpinner = container.querySelector('.chat-loading-more');
    if (loadingMoreSpinner) loadingMoreSpinner.remove();

    if (res.success) {
        const messages = res.messages;
        if (messages.length < MESSAGES_PER_PAGE) hasMoreMessages = false;
        currentOffset += messages.length;

        if (isInitialLoad) {
            container.innerHTML = '';
            Renderer.renderEmptyChatState(container, currentChatData, currentChatType);

            if (messages.length > 0) {
                Renderer.processAndRenderBatch(container, messages, true, currentChatType);
                Renderer.scrollToBottom();
            }
        } else {
            const prevHeight = container.scrollHeight;
            Renderer.processAndRenderBatch(container, messages, false, currentChatType);
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

// --- GESTIÓN DE ARCHIVOS ADJUNTOS ---

function initAttachmentListeners() {
    const fileInput = document.getElementById('chat-file-input');
    const attachBtn = document.getElementById('btn-attach-file');
    const container = document.getElementById('attachment-preview-area');
    const grid = document.getElementById('preview-grid');
    
    if (attachBtn && fileInput) {
        attachBtn.onclick = () => fileInput.click();
        fileInput.onchange = (e) => {
            const files = Array.from(e.target.files);
            if (selectedFiles.length + files.length > 4) {
                if (window.alertManager) window.alertManager.showAlert("Máximo 4 imágenes.", "warning");
                return;
            }
            selectedFiles = [...selectedFiles, ...files];
            
            Renderer.renderAttachmentPreview(selectedFiles, container, grid);
            
            // Re-asignar listeners de eliminación
            grid.querySelectorAll('.preview-remove').forEach(btn => {
                btn.onclick = (e) => {
                    selectedFiles.splice(parseInt(e.target.dataset.index), 1);
                    Renderer.renderAttachmentPreview(selectedFiles, container, grid);
                };
            });
            
            fileInput.value = ''; 
        };
    }
}

function clearAttachments() {
    selectedFiles = [];
    const container = document.getElementById('attachment-preview-area');
    const grid = document.getElementById('preview-grid');
    Renderer.renderAttachmentPreview(selectedFiles, container, grid);
}

// --- VISUALES DE TYPING ---

function triggerTypingIndicator() {
    Renderer.showTypingIndicator();
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        Renderer.resetHeaderStatus();
    }, TYPING_DISPLAY_TIME);
}

// --- ACCIONES PRINCIPALES (Bridge con ChatActions) ---

async function performSendMessage() {
    if (!currentChatUuid) return;
    
    const input = document.querySelector('.chat-message-input');
    const text = input.value.trim();
    
    if (!text && selectedFiles.length === 0) return;

    // Preparar UI si hay archivos (carga)
    let btn = null;
    let originalIcon = '';
    
    if (selectedFiles.length > 0) {
        btn = document.getElementById('btn-send-message');
        originalIcon = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-rounded">sync</span>'; 
    }

    const params = {
        targetUuid: currentChatUuid,
        context: currentChatType,
        message: text,
        replyToUuid: replyingToMessageUuid,
        attachments: selectedFiles,
        channelUuid: (currentChannelUuid && currentChatType === 'community') ? currentChannelUuid : null
    };

    // Llamar a la lógica de acciones
    const result = await ChatActions.sendMessage(params);

    if (result.success) {
        // Limpieza de UI
        input.value = '';
        input.focus();
        clearAttachments();
        disableReplyMode();
    } else {
        if(window.alertManager) window.alertManager.showAlert(result.message || 'Error al enviar', 'error');
    }

    // Restaurar botón si hubo archivos
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalIcon;
    }
}

function enableReplyMode(msgUuid, senderName, messageText) {
    replyingToMessageUuid = msgUuid; 
    replyingToMessageData = { user: senderName, text: messageText };
    Renderer.updateReplyUI(true, replyingToMessageData);
    document.querySelector('.chat-message-input')?.focus();
}

function disableReplyMode() {
    replyingToMessageUuid = null;
    replyingToMessageData = null;
    Renderer.updateReplyUI(false);
}

// --- CALLBACKS PARA EL POPUP DE OPCIONES ---

async function onSaveEditMessage(newText, editContainer, contentWrapper, msgUuid) {
    const saveBtn = editContainer.querySelector('[data-action="save-edit-message"]');
    saveBtn.disabled = true;
    saveBtn.textContent = '...';

    const result = await ChatActions.saveEditMessage(msgUuid, newText, currentChatType, currentChatUuid, currentChannelUuid);

    if (result.success) {
        editContainer.remove();
        contentWrapper.style.display = 'block';
    } else {
        if(window.alertManager) window.alertManager.showAlert(result.message, 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = t('chat.save_edit');
    }
}

async function onDeleteMessage(msgUuid) {
    const result = await ChatActions.handleDeleteMessage(msgUuid, currentChatType, currentChatUuid);
    
    if (result.success) {
        // Opacidad inmediata para feedback visual
        const msgEl = document.getElementById(`msg-${msgUuid}`);
        if (msgEl) msgEl.style.opacity = '0.5';
    } else if (!result.cancelled && result.message) {
        if(window.alertManager) window.alertManager.showAlert(result.message, 'error');
    }
}

async function onReportMessage(msgUuid) {
    const result = await ChatActions.handleReportMessage(msgUuid, currentChatType, currentChatUuid);
    
    if (result.success) {
        if(window.alertManager) window.alertManager.showAlert(t('chat.report_success') || 'Reporte enviado.', 'success');
    } else if (!result.cancelled && result.message) {
        if(window.alertManager) window.alertManager.showAlert(result.message, 'error');
    }
}

// --- LISTENERS ---

function initSocketListeners() {
    if (areSocketListenersInit) return;

    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        const message = e.detail.message; 

        if (type === 'error') {
            if (window.alertManager) {
                window.alertManager.showAlert(message || payload?.message || "Error desconocido", "error");
            } else {
                console.error("Error del servidor:", message);
            }
            return;
        }
        
        if (type === 'typing') {
            if (currentChatType === 'private' && currentChatId && parseInt(payload.sender_id) === parseInt(currentChatId)) {
                triggerTypingIndicator();
            }
            return;
        }
        
        if (type === 'message_edited') {
            const msgUuid = payload.uuid;
            const newContent = payload.new_content;
            
            const msgRow = document.getElementById(`msg-${msgUuid}`);
            if (msgRow) {
                const textEl = msgRow.querySelector('.message-text');
                const timeEl = msgRow.querySelector('.message-time');
                
                if (textEl) textEl.textContent = newContent; 
                
                if (timeEl && !timeEl.querySelector('.edited-tag')) {
                    const editTag = document.createElement('small');
                    editTag.className = 'edited-tag';
                    editTag.style.marginLeft = '4px';
                    editTag.style.color = '#999';
                    editTag.textContent = t('chat.edited_tag') || '(editado)';
                    timeEl.appendChild(editTag);
                }
                
                const bubble = msgRow.querySelector('.message-bubble');
                bubble.style.transition = 'background-color 0.3s';
                const originalBg = bubble.style.backgroundColor;
                bubble.style.backgroundColor = '#fff9c4'; 
                setTimeout(() => {
                    bubble.style.backgroundColor = originalBg;
                }, 500);
                
                const optBtn = msgRow.querySelector('.message-options-btn');
                if(optBtn) optBtn.dataset.text = newContent;
            }
            return;
        }

        if (type === 'new_chat_message' || type === 'private_message') {
            
            if (currentChatType === 'private' && currentChatId && parseInt(payload.sender_id) === parseInt(currentChatId)) {
                Renderer.resetHeaderStatus();
                clearTimeout(typingTimeout);
            }

            let isForCurrentChat = false;

            if (currentChatType === 'community' && type === 'new_chat_message') {
                if (payload.community_uuid === currentChatUuid) {
                    if (payload.channel_uuid && payload.channel_uuid === currentChannelUuid) {
                        isForCurrentChat = true;
                    }
                }
            } 
            else if (currentChatType === 'private' && type === 'private_message') {
                if (parseInt(payload.sender_id) === parseInt(window.USER_ID) && payload.target_uuid === currentChatUuid) {
                    isForCurrentChat = true;
                }
                else {
                    const activeUuid = currentChatUuid || window.ACTIVE_CHAT_UUID;
                    if (payload.sender_uuid && payload.sender_uuid === activeUuid) {
                         isForCurrentChat = true;
                    }
                    else if (currentChatId && parseInt(payload.sender_id) === parseInt(currentChatId)) {
                        isForCurrentChat = true;
                    }
                }
            }

            if (isForCurrentChat) {
                const container = document.querySelector('.chat-messages-area');
                if (container) {
                    Renderer.appendSingleMessage(container, payload, currentChatType);
                    Renderer.scrollToBottom();
                    currentOffset++;

                    if (document.hasFocus()) {
                        setTimeout(() => {
                            ChatActions.sendReadSignal(currentChatUuid, currentChatType, currentChannelUuid);
                        }, 500); 
                    }
                }
            }
        }

        if (type === 'message_deleted' || type === 'private_message_deleted') {
            const msgUuid = payload.message_id; 
            const msgEl = document.getElementById(`msg-${msgUuid}`);
            if (msgEl) {
                const wrapper = document.createElement('div');
                const dummyMsg = { uuid: msgUuid, status: 'deleted', sender_id: payload.sender_id };
                const isMe = (parseInt(payload.sender_id) === parseInt(window.USER_ID));
                wrapper.innerHTML = Renderer.createDeletedMessageHTML(dummyMsg, isMe, msgUuid);
                msgEl.replaceWith(wrapper.firstElementChild);
            }
        }
    });
    
    areSocketListenersInit = true;
}

function initDOMListeners() {
    const input = document.querySelector('.chat-message-input');
    const sendBtn = document.getElementById('btn-send-message');
    
    if (input) {
        input.onkeydown = (e) => { 
            ChatActions.handleTypingEvent(currentChatUuid, currentChatType); 
            if (e.key === 'Enter') { e.preventDefault(); performSendMessage(); } 
        };
        
        input.oninput = () => ChatActions.handleTypingEvent(currentChatUuid, currentChatType);
    }
    
    if (sendBtn) {
        sendBtn.onclick = (e) => { e.preventDefault(); performSendMessage(); };
    }

    const messagesArea = document.querySelector('.chat-messages-area');
    if (messagesArea) {
        messagesArea.addEventListener('scroll', handleChatScroll);
    }

    const mainArea = document.querySelector('.chat-main-area');
    if (mainArea) {
        mainArea.addEventListener('click', () => {
            if (currentChatUuid) {
                ChatActions.sendReadSignal(currentChatUuid, currentChatType, currentChannelUuid);
            }
        });
    }
}

function initGlobalActionListeners() {
    if (areGlobalActionsInit) return;

    document.body.addEventListener('click', async (e) => {
        
        if (e.target.closest('#btn-back-to-list')) {
            const layout = document.querySelector('.chat-layout-container');
            if (layout) layout.classList.remove('chat-active');
            
            window.ACTIVE_CHAT_UUID = null;
            window.ACTIVE_CHANNEL_UUID = null; 
            currentChatUuid = null;
            currentChannelUuid = null; 
            currentChatId = null; 
            
            document.dispatchEvent(new CustomEvent('reset-chat-view'));
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
        }
        
        const msgOptBtn = e.target.closest('[data-action="msg-options"]');
        if (msgOptBtn) { 
            e.stopPropagation();
            const uuid = msgOptBtn.dataset.uuid;
            const senderId = msgOptBtn.dataset.senderId;
            const isMe = (parseInt(senderId) === parseInt(window.USER_ID));
            const createdAt = msgOptBtn.dataset.createdAt;
            
            Renderer.showMessagePopover(
                msgOptBtn, 
                uuid, 
                msgOptBtn.dataset.user, 
                msgOptBtn.dataset.text,
                isMe,
                createdAt,
                // On Reply
                () => enableReplyMode(uuid, msgOptBtn.dataset.user, msgOptBtn.dataset.text), 
                
                // On Edit: Habilita UI y pasa callback de guardado
                () => Renderer.enableEditMessageUI(uuid, msgOptBtn.dataset.text, async (newText, editContainer, contentWrapper) => {
                    await onSaveEditMessage(newText, editContainer, contentWrapper, uuid);
                }), 
                
                // On Delete
                () => onDeleteMessage(uuid), 
                
                // On Report
                () => onReportMessage(uuid)
            );
        }

        if (e.target.closest('#btn-cancel-reply')) disableReplyMode();
        
        if (e.target.closest('[data-action="toggle-group-info"]')) { 
            e.preventDefault(); 
            const sidebar = document.getElementById('chat-info-panel');
            if(sidebar) {
                sidebar.classList.toggle('d-none');
                if(!sidebar.classList.contains('d-none') && currentChatUuid) {
                    document.dispatchEvent(new CustomEvent('reload-group-info', { detail: { uuid: currentChatUuid } }));
                }
            }
        }
        if (e.target.closest('[data-action="close-group-info"]')) { 
            e.preventDefault(); 
            document.getElementById('chat-info-panel')?.classList.add('d-none'); 
        }
    });

    areGlobalActionsInit = true;
}

export function initChatManager() {
    // 1. Listeners que dependen del DOM actual (siempre se reinician al cargar main)
    initAttachmentListeners();
    initDOMListeners();

    // 2. Listeners globales de socket (una sola vez)
    initSocketListeners();

    // 3. Listeners globales de acciones (una sola vez)
    initGlobalActionListeners();
    
    // Al abrir el chat inicialmente, limpiar attachments si había
    clearAttachments();
    disableReplyMode();
}