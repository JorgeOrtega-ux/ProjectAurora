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

// Estado de UI local
let typingTimeout = null;
const TYPING_DISPLAY_TIME = 3000;

// Banderas de Inicialización Global
let areSocketListenersInit = false;
let areGlobalActionsInit = false;

// --- GESTIÓN DE APERTURA DE CHAT ---

export async function openChat(uuid, chatData = null) {
    // ---------------------------------------------------------
    // ANÁLISIS ULTRA A FONDO PARA EVITAR RECARGAS (FIXED)
    // ---------------------------------------------------------
    
    // 1. ¿Es el mismo UUID principal (Usuario o Comunidad)?
    const isSameEntity = (currentChatUuid === uuid);

    if (isSameEntity) {
        // CASO A: Chat Privado
        // Si estoy en privado y el ID es el mismo, NO RECARGAR.
        // (En privado no hay canales, así que es suficiente validación).
        if (currentChatType === 'private') {
            console.log('🛑 Anti-Reload: Ya estás en este chat privado.');
            document.querySelector('.chat-message-input')?.focus();
            return; 
        }

        // CASO B: Comunidad
        if (currentChatType === 'community') {
            // Verificamos si la intención es cambiar de canal.
            // Si chatData es null, asumimos que se clickeó el icono de la comunidad (mismo canal actual).
            const targetChannelUuid = (chatData && chatData.channel_uuid) ? chatData.channel_uuid : null;

            // 1. Si no especifican canal (targetChannelUuid es null), significa que clickearon
            // la comunidad general. Si ya estamos dentro, nos quedamos donde estamos.
            if (!targetChannelUuid) {
                console.log('🛑 Anti-Reload: Ya estás en esta comunidad (Canal actual mantenido).');
                document.querySelector('.chat-message-input')?.focus();
                return;
            }

            // 2. Si ESPECIFICAN un canal, validamos si es el mismo que el actual.
            if (targetChannelUuid === currentChannelUuid) {
                console.log('🛑 Anti-Reload: Ya estás en este canal específico.');
                document.querySelector('.chat-message-input')?.focus();
                return;
            }
        }
    }
    // ---------------------------------------------------------

    // Si pasamos aquí, es un chat nuevo o un canal diferente. Procedemos a cargar.

    // Si nos pasan chatData, usamos SU tipo. Si no, usamos el global o fallback.
    let type = (chatData && chatData.type) ? chatData.type : (window.ACTIVE_CHAT_TYPE || 'community');

    // Si no hay datos (recarga forzada o click desde lista simple) O es comunidad sin estado de canal
    if (!chatData || (type === 'community' && !chatData.channel_status)) {
        let res;

        // Intentamos deducir el tipo si no viene explícito
        // (Nota: Si venimos de un click de amigo, el type suele ser 'private' implícito en la lógica de UI,
        // pero aquí aseguramos la carga de datos).
        
        // Estrategia de carga: intentamos cargar como si fuera lo que creemos que es.
        // Si falla y es un error 404, el backend maneja eso.
        
        // Pero para ser más precisos: Si el input dice que es 'private' o si no dice nada
        // y parece un UUID de usuario (esto es difícil de saber solo con el string, así que confiamos en el flujo).
        
        // Lógica estándar de fetch:
        if (type === 'private') {
            res = await CommunityApi.getUserChatByUuid(uuid);
            if (res.success) {
                chatData = res.data;
                chatData.type = 'private';
            }
        } else {
            // Asumimos comunidad por defecto si no es privado
            res = await CommunityApi.getByUuid(uuid);
            
            if (res.success) {
                chatData = res.data || res.community;
                chatData.type = 'community';

                // Resolver canal activo
                const targetChannelUuid = window.ACTIVE_CHANNEL_UUID || chatData.default_channel_uuid;
                
                if (targetChannelUuid) {
                    const detailsRes = await CommunityApi.getDetails(uuid);
                    if (detailsRes.success && detailsRes.channels) {
                        const activeChannel = detailsRes.channels.find(c => c.uuid === targetChannelUuid);
                        if (activeChannel) {
                            chatData.channel_uuid = activeChannel.uuid;
                            chatData.channel_name = activeChannel.name;
                            chatData.channel_status = activeChannel.status; 
                            window.ACTIVE_CHANNEL_UUID = activeChannel.uuid;
                        }
                    }
                }
            }
        }

        if (!res || !res.success) {
            console.error("Error cargando chat o chat no existe");
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
            return;
        }
    }

    // Actualización de Estado Global
    currentChatUuid = chatData.uuid;
    currentChatId = chatData.id; 
    currentChatType = chatData.type || 'community';
    currentChannelUuid = chatData.channel_uuid || null;
    currentChatData = chatData; 
    
    window.ACTIVE_CHAT_UUID = uuid;
    window.ACTIVE_CHAT_TYPE = currentChatType;
    window.ACTIVE_CHANNEL_UUID = currentChannelUuid;

    // Resetear UI
    currentOffset = 0;
    hasMoreMessages = true;
    isLoadingMessages = false;

    clearTimeout(typingTimeout);
    Renderer.resetHeaderStatus(chatData.type === 'private');

    document.dispatchEvent(new CustomEvent('chat-opened', { detail: chatData }));

    Renderer.updateChatInterface(chatData);
    
    if (chatData.status !== 'maintenance' && chatData.channel_status !== 'maintenance') {
        loadChatMessages(uuid, currentChatType, true);
    }

    // Actualizar URL
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

// --- ACCIONES PRINCIPALES ---

async function performSendMessage() {
    if (!currentChatUuid) return;
    
    const input = document.querySelector('.chat-message-input');
    const text = input.value.trim();
    
    if (!text && selectedFiles.length === 0) return;

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

    const result = await ChatActions.sendMessage(params);

    if (result.success) {
        input.value = '';
        input.focus();
        clearAttachments();
        disableReplyMode();
    } else {
        if(window.alertManager) window.alertManager.showAlert(result.message || 'Error al enviar', 'error');
    }

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

// --- CALLBACKS & LISTENERS ---

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

function initSocketListeners() {
    if (areSocketListenersInit) return;

    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        const message = e.detail.message; 

        if (type === 'error') {
            if (window.alertManager) window.alertManager.showAlert(message || payload?.message || "Error desconocido", "error");
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
                setTimeout(() => { bubble.style.backgroundColor = originalBg; }, 500);
                
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
                    if (payload.sender_uuid && payload.sender_uuid === activeUuid) isForCurrentChat = true;
                    else if (currentChatId && parseInt(payload.sender_id) === parseInt(currentChatId)) isForCurrentChat = true;
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
    
    if (sendBtn) sendBtn.onclick = (e) => { e.preventDefault(); performSendMessage(); };

    const messagesArea = document.querySelector('.chat-messages-area');
    if (messagesArea) messagesArea.addEventListener('scroll', handleChatScroll);

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
                msgOptBtn, uuid, msgOptBtn.dataset.user, msgOptBtn.dataset.text, isMe, createdAt,
                () => enableReplyMode(uuid, msgOptBtn.dataset.user, msgOptBtn.dataset.text), 
                () => Renderer.enableEditMessageUI(uuid, msgOptBtn.dataset.text, async (newText, editContainer, contentWrapper) => {
                    await onSaveEditMessage(newText, editContainer, contentWrapper, uuid);
                }), 
                () => onDeleteMessage(uuid), 
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
    initAttachmentListeners();
    initDOMListeners();
    initSocketListeners();
    initGlobalActionListeners();
    clearAttachments();
    disableReplyMode();
}