// public/assets/js/modules/chat-manager.js

import { ChatApi, CommunityApi } from '../services/api-service.js';
import { t } from '../core/i18n-manager.js';
import * as Renderer from './chat-renderer.js';
import * as ChatActions from './chat-actions.js';
import * as ChannelsManager from './communities/channels-manager.js';

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

// Estado del Input Pill (Expansión)
let isExpanded = false;
let savedAvailableWidth = 0;

// Banderas de Inicialización Global
let areSocketListenersInit = false;
let areGlobalActionsInit = false;

// --- GESTIÓN DE APERTURA DE CHAT ---

export async function openChat(uuid, chatData = null) {
    // ---------------------------------------------------------
    // ANÁLISIS ULTRA A FONDO PARA EVITAR RECARGAS
    // ---------------------------------------------------------
    
    // 1. ¿Es el mismo UUID principal (Usuario o Comunidad)?
    const isSameEntity = (currentChatUuid === uuid);

    if (isSameEntity) {
        // CASO A: Chat Privado
        if (currentChatType === 'private') {
            console.log('🛑 Anti-Reload: Ya estás en este chat privado.');
            focusInput();
            return; 
        }

        // CASO B: Comunidad
        if (currentChatType === 'community') {
            const targetChannelUuid = (chatData && chatData.channel_uuid) ? chatData.channel_uuid : null;

            if (!targetChannelUuid) {
                console.log('🛑 Anti-Reload: Ya estás en esta comunidad (Canal actual mantenido).');
                focusInput();
                return;
            }

            if (targetChannelUuid === currentChannelUuid) {
                console.log('🛑 Anti-Reload: Ya estás en este canal específico.');
                focusInput();
                return;
            }
        }
    }
    // ---------------------------------------------------------

    // [IMPORTANTE] Si cambiamos de chat o canal, asegurarnos de que el panel móvil se cierre
    document.getElementById('mobile-channels-panel')?.classList.add('d-none');

    // Si nos pasan chatData, usamos SU tipo. Si no, usamos el global o fallback.
    let type = (chatData && chatData.type) ? chatData.type : (window.ACTIVE_CHAT_TYPE || 'community');

    // Si no hay datos o es comunidad sin estado de canal
    if (!chatData || (type === 'community' && !chatData.channel_status)) {
        let res;
        
        if (type === 'private') {
            res = await CommunityApi.getUserChatByUuid(uuid);
            if (res.success) {
                chatData = res.data;
                chatData.type = 'private';
            }
        } else {
            res = await CommunityApi.getByUuid(uuid);
            
            if (res.success) {
                chatData = res.data || res.community;
                chatData.type = 'community';

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

    // [NUEVO] Controlar visibilidad del botón de menú móvil
    const mobileMenuBtn = document.getElementById('btn-mobile-sidebar-toggle');
    if (mobileMenuBtn) {
        if (currentChatType === 'community') {
            mobileMenuBtn.classList.remove('d-none');
        } else {
            mobileMenuBtn.classList.add('d-none');
        }
    }

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
            updateSendButtonState(); // Actualizar botón al adjuntar
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

// --- LÓGICA DE PILL INPUT (EXPANSIÓN) ---

function getInputValue() {
    const input = document.getElementById('chat-message-input');
    const textarea = document.getElementById('expandedTextarea');
    if (isExpanded && textarea) {
        return textarea.value;
    }
    return input ? input.value : '';
}

function clearInput() {
    const input = document.getElementById('chat-message-input');
    if (input) {
        input.value = '';
        input.style.display = '';
    }
    
    // Si estaba expandido, colapsar
    isExpanded = false;
    removeTextarea();
    updateSendButtonState();
}

function focusInput() {
    const input = document.getElementById('chat-message-input');
    const textarea = document.getElementById('expandedTextarea');
    if (isExpanded && textarea) {
        textarea.focus();
    } else if (input) {
        input.focus();
    }
}

function updateSendButtonState() {
    const btn = document.getElementById('btn-send-message');
    const text = getInputValue().trim();
    if (btn) {
        // Habilitar si hay texto O hay archivos adjuntos
        btn.disabled = (!text && selectedFiles.length === 0);
    }
}

function createTextarea(text) {
    const pill = document.getElementById('pill');
    if (!pill) return;

    removeTextarea(); // Limpiar previo si existe

    // [ACTUALIZADO] Usar nueva clase chat-pill-textarea-container
    const container = document.createElement('div');
    container.className = 'chat-pill-textarea-container';

    const textarea = document.createElement('textarea');
    textarea.id = 'expandedTextarea';
    textarea.placeholder = 'Escribe un mensaje...';
    textarea.value = text;
    textarea.rows = 3;

    container.appendChild(textarea);
    
    // [ACTUALIZADO] Usar nueva clase chat-pill-controls
    const controls = pill.querySelector('.chat-pill-controls');
    if (controls) {
        pill.insertBefore(container, controls);
    } else {
        pill.appendChild(container);
    }

    setTimeout(() => {
        textarea.focus();
        textarea.setSelectionRange(text.length, text.length);
    }, 0);

    // Listeners para el textarea dinámico
    textarea.addEventListener('input', (e) => {
        const input = document.getElementById('chat-message-input');
        if (input) input.value = e.target.value; // Sync inverso
        
        updateSendButtonState();
        checkAndUpdateExpanded();
        ChatActions.handleTypingEvent(currentChatUuid, currentChatType);
    });

    textarea.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            performSendMessage();
        }
        ChatActions.handleTypingEvent(currentChatUuid, currentChatType);
    });
}

function removeTextarea() {
    const pill = document.getElementById('pill');
    if (pill) {
        // [ACTUALIZADO] Buscar por la nueva clase
        const container = pill.querySelector('.chat-pill-textarea-container');
        if (container) container.remove();
    }
}

function checkAndUpdateExpanded() {
    const input = document.getElementById('chat-message-input');
    const measure = document.getElementById('measure');
    
    if (!input || !measure) return;

    let text = "";
    if (isExpanded) {
        const textarea = document.getElementById('expandedTextarea');
        text = textarea ? textarea.value : "";
    } else {
        text = input.value;
    }

    // Si no está expandido, guardamos el ancho disponible original
    if (!isExpanded) {
        savedAvailableWidth = input.offsetWidth;
    }

    if (text.length === 0) {
        if (isExpanded) {
            isExpanded = false;
            removeTextarea();
            input.style.display = '';
            input.focus();
        }
        return;
    }

    measure.textContent = text;
    const textWidth = measure.offsetWidth;
    
    // Usar ancho guardado si ya estamos expandidos (porque el input está oculto)
    const availableWidth = isExpanded ? savedAvailableWidth : input.offsetWidth;
    
    // Umbral de tolerancia para el padding/íconos
    const threshold = availableWidth - 20; 

    if (textWidth > threshold) {
        // EXPANDIR
        if (!isExpanded) {
            isExpanded = true;
            createTextarea(text);
            input.style.display = 'none';
        }
    } else {
        // CONTRAER
        if (isExpanded) {
            isExpanded = false;
            removeTextarea();
            input.style.display = '';
            input.focus();
            // Restaurar cursor al final
            input.setSelectionRange(text.length, text.length);
        }
    }
}


// --- ACCIONES PRINCIPALES ---

async function performSendMessage() {
    if (!currentChatUuid) return;
    
    // Guardia: Bloquear si cuenta eliminada
    if (currentChatType === 'private' && currentChatData?.account_status === 'deleted') {
        if(window.alertManager) window.alertManager.showAlert("No puedes enviar mensajes a este usuario.", 'error');
        return;
    }

    const text = getInputValue().trim();
    
    if (!text && selectedFiles.length === 0) return;

    let btn = null;
    let originalContent = '';
    
    // Si hay archivos, mostrar estado de carga
    if (selectedFiles.length > 0) {
        btn = document.getElementById('btn-send-message');
        if(btn) {
            originalContent = btn.innerHTML;
            btn.disabled = true;
            // Usar icono de sync o spinner
            btn.innerHTML = '<span class="material-symbols-rounded" style="animation: spin 1s linear infinite; font-size: 20px;">sync</span>'; 
        }
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
        clearInput(); // Esto resetea el pill y el input
        clearAttachments();
        disableReplyMode();
        focusInput();
    } else {
        if(window.alertManager) window.alertManager.showAlert(result.message || 'Error al enviar', 'error');
    }

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
    
    // Re-evaluar estado del botón (debería quedar disabled si se limpió todo)
    updateSendButtonState();
}

function enableReplyMode(msgUuid, senderName, messageText) {
    replyingToMessageUuid = msgUuid; 
    replyingToMessageData = { user: senderName, text: messageText };
    Renderer.updateReplyUI(true, replyingToMessageData);
    focusInput();
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
    const input = document.getElementById('chat-message-input');
    const sendBtn = document.getElementById('btn-send-message');
    
    if (input) {
        input.onkeydown = (e) => { 
            // Manejar Enter solo si NO está expandido (el textarea maneja su propio enter)
            // O si preferimos unificar, el input es type="text" así que enter siempre envía
            if (e.key === 'Enter') { 
                e.preventDefault(); 
                performSendMessage(); 
            } 
            ChatActions.handleTypingEvent(currentChatUuid, currentChatType); 
        };

        // Lógica de Pill Input
        input.addEventListener('input', () => {
            updateSendButtonState();
            checkAndUpdateExpanded();
            ChatActions.handleTypingEvent(currentChatUuid, currentChatType);
        });
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

    // --- LISTENERS PARA EL PANEL MÓVIL ---
    const mobileMenuBtn = document.getElementById('btn-mobile-sidebar-toggle');
    const mobilePanel = document.getElementById('mobile-channels-panel');

    // Botón para ABRIR
    if (mobileMenuBtn) {
        mobileMenuBtn.onclick = async () => {
            if (!mobilePanel) return;
            mobilePanel.classList.toggle('d-none');
            
            if (!mobilePanel.classList.contains('d-none')) {
                // Si abrimos el panel y estamos en una comunidad, renderizamos los canales
                if (currentChatType === 'community' && currentChatUuid) {
                     const listContainer = document.getElementById('mobile-channels-list');
                     if (listContainer) {
                         listContainer.innerHTML = '<div class="small-spinner" style="margin:20px auto;"></div>';
                         // Cargar usando ChannelsManager (usa caché interno si existe)
                         const res = await ChannelsManager.loadChannels(currentChatUuid);
                         // Renderizar en el contenedor móvil
                         ChannelsManager.renderChannelList(listContainer, currentChatUuid, res.channels, res.role);
                     }
                } else {
                     const listContainer = document.getElementById('mobile-channels-list');
                     if (listContainer) listContainer.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">Esta opción solo está disponible en comunidades.</div>';
                }
            }
        };
    }

    // Manejo de clic en canal dentro del panel móvil para cerrarlo
    if (mobilePanel) {
        mobilePanel.addEventListener('click', (e) => {
            const item = e.target.closest('.channel-item');
            if (item) {
                // Al hacer clic en un canal, cerramos el panel
                mobilePanel.classList.add('d-none');
            }
        });
    }

    // Listener para ocultar automáticamente el sidebar móvil al redimensionar a PC
    window.addEventListener('resize', () => {
        // 992px suele ser el breakpoint 'lg' en Bootstrap/CSS estándar
        if (window.innerWidth >= 992) {
            mobilePanel?.classList.add('d-none');
        }
    });
}

function initGlobalActionListeners() {
    if (areGlobalActionsInit) return;

    document.addEventListener('reset-chat-view', () => {
        currentChatUuid = null;
        currentChatId = null;
        currentChannelUuid = null;
        currentChatData = null;
        window.ACTIVE_CHAT_UUID = null;
        window.ACTIVE_CHANNEL_UUID = null;

        const chatInterface = document.getElementById('chat-interface');
        const welcomePlaceholder = document.getElementById('chat-placeholder-welcome');
        const selectPlaceholder = document.getElementById('chat-placeholder-select');
        const layout = document.querySelector('.chat-layout-container');

        if (layout) layout.classList.remove('chat-active');

        if (chatInterface) chatInterface.style.display = 'none'; 
        if (selectPlaceholder) selectPlaceholder.classList.add('d-none');
        if (welcomePlaceholder) welcomePlaceholder.classList.remove('d-none');
        
        // Cerrar panel móvil si estaba abierto y ocultar el botón
        document.getElementById('mobile-channels-panel')?.classList.add('d-none');
        document.getElementById('btn-mobile-sidebar-toggle')?.classList.add('d-none');
        
        clearInput(); // Resetear pill input
    });

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
        
        // --- MANEJO DE CLIC EN REACCIÓN (TOGGLE) ---
        const reactionBtn = e.target.closest('[data-action="toggle-reaction"]');
        if (reactionBtn) {
            e.stopPropagation();
            e.preventDefault();
            const msgUuid = reactionBtn.dataset.uuid;
            const emoji = reactionBtn.dataset.reactionKey;
            ChatActions.handleReactionAction(msgUuid, emoji, currentChatType, currentChatUuid);
            return;
        }
        
      const msgOptBtn = e.target.closest('[data-action="msg-options"]');
        if (msgOptBtn) { 
            e.stopPropagation();
            const uuid = msgOptBtn.dataset.uuid;
            const senderId = msgOptBtn.dataset.senderId;
            const isMe = (parseInt(senderId) === parseInt(window.USER_ID));
            const createdAt = msgOptBtn.dataset.createdAt;
            
            let currentReaction = null;
            const msgRow = document.getElementById(`msg-${uuid}`);
            if (msgRow) {
                const activeBubble = msgRow.querySelector('.reaction-bubble.reacted');
                if (activeBubble) {
                    currentReaction = activeBubble.dataset.reactionKey;
                }
            }

            Renderer.showMessagePopover(
                msgOptBtn, uuid, msgOptBtn.dataset.user, msgOptBtn.dataset.text, isMe, createdAt, currentReaction,
                () => enableReplyMode(uuid, msgOptBtn.dataset.user, msgOptBtn.dataset.text), 
                () => Renderer.enableEditMessageUI(uuid, msgOptBtn.dataset.text, async (newText, editContainer, contentWrapper) => {
                    await onSaveEditMessage(newText, editContainer, contentWrapper, uuid);
                }), 
                () => onDeleteMessage(uuid), 
                () => onReportMessage(uuid),
                (emoji) => {
                    ChatActions.handleReactionAction(uuid, emoji, currentChatType, currentChatUuid);
                }
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
    updateSendButtonState(); // Inicializar estado del botón
}