// public/assets/js/modules/chat-actions.js

import { ChatApi } from '../services/api-service.js';
import { t } from '../core/i18n-manager.js';
import { updateMessageReactions } from './chat-renderer.js';

// Variables para control de throttling
let lastTypingSent = 0;
const TYPING_THROTTLE = 2000;

export async function sendReadSignal(chatUuid, chatType, channelUuid = null) {
    if (!chatUuid) return;
    await ChatApi.markAsRead(chatUuid, chatType, channelUuid);
    const event = new CustomEvent('local-chat-read', { detail: { uuid: chatUuid } });
    document.dispatchEvent(event);
}

export async function sendMessage(params) {
    const { targetUuid, context, message, replyToUuid, channelUuid, attachments } = params;

    if (!targetUuid) return { success: false, message: 'No target UUID' };

    if (attachments && attachments.length > 0) {
        try {
            const data = await ChatApi.sendMessage(params);
            if (data.success) {
                return { success: true };
            } else {
                return { success: false, message: data.message || 'Error al enviar archivo' };
            }
        } catch (e) {
            console.error(e);
            return { success: false, message: t('global.error_connection') };
        }
    } 
    else {
        const socket = window.socketService ? window.socketService.socket : null;
        
        if (socket && socket.readyState === WebSocket.OPEN) {
            const payload = {
                type: 'chat_message',
                payload: { 
                    target_uuid: targetUuid, 
                    context: context,
                    message: message 
                }
            };
            if (channelUuid && context === 'community') {
                payload.payload.channel_uuid = channelUuid;
            }
            if (replyToUuid) {
                payload.payload.reply_to_uuid = replyToUuid;
            }
            socket.send(JSON.stringify(payload));
            return { success: true };
        } else {
            return { success: false, message: 'Sin conexión al servidor de chat (Socket cerrado).' };
        }
    }
}

export function handleTypingEvent(chatUuid, chatType) {
    if (!chatUuid || chatType !== 'private') return;
    
    const now = Date.now();
    if (now - lastTypingSent > TYPING_THROTTLE) {
        lastTypingSent = now;
        
        if (window.socketService && window.socketService.socket && window.socketService.socket.readyState === WebSocket.OPEN) {
            const payload = {
                type: 'typing',
                payload: { 
                    target_uuid: chatUuid 
                }
            };
            window.socketService.socket.send(JSON.stringify(payload));
        }
    }
}

export async function saveEditMessage(msgUuid, newText, context, targetUuid, channelUuid) {
    try {
        const res = await ChatApi.editMessage(msgUuid, newText, context, targetUuid, channelUuid);
        return res; 
    } catch (e) {
        console.error(e);
        return { success: false, message: t('global.error_connection') };
    }
}

export async function handleDeleteMessage(msgUuid, context, targetUuid) {
    if (!confirm(t('global.are_you_sure') || '¿Estás seguro de eliminar este mensaje?')) {
        return { success: false, cancelled: true };
    }
    try {
        const res = await ChatApi.deleteMessage(msgUuid, context, targetUuid);
        return res;
    } catch (e) {
        console.error(e);
        return { success: false, message: t('global.error_connection') };
    }
}

export async function handleReportMessage(msgUuid, context, targetUuid) {
    const reason = prompt(t('chat.report_reason') || 'Razón del reporte:');
    if (!reason) return { success: false, cancelled: true };

    try {
        const res = await ChatApi.reportMessage(msgUuid, reason, context, targetUuid);
        return res;
    } catch (e) {
        console.error(e);
        return { success: false, message: t('global.error_connection') };
    }
}

/**
 * [MODIFICADO] Maneja la reacción a un mensaje usando ChatApi.
 */
/**
 * [MODIFICADO] Maneja la reacción a un mensaje con lógica de Toggle/Switch.
 */
export async function handleReactionAction(msgUuid, reactionKey, context, targetUuid) {
    // 1. Feedback Optimista: Calcular el nuevo estado antes de que responda el servidor
    const msgRow = document.getElementById(`msg-${msgUuid}`);
    
    if (msgRow) {
        let currentReactions = {};
        let previousUserReactionKey = null;
        
        const bubbles = msgRow.querySelectorAll('.reaction-bubble');
        bubbles.forEach(b => {
            const key = b.dataset.reactionKey;
            const count = parseInt(b.querySelector('.reaction-count').innerText) || 0;
            const isReactedByMe = b.classList.contains('reacted'); // Detectar si yo le di clic

            if (key) {
                if (isReactedByMe) {
                    previousUserReactionKey = key; // Guardamos cuál era mi reacción anterior
                }
                // Guardamos el estado completo para el renderizador
                currentReactions[key] = {
                    count: count,
                    user_reacted: isReactedByMe
                };
            }
        });

        // APLICAR LÓGICA DE INTERCAMBIO (TOGGLE/SWITCH)
        if (previousUserReactionKey === reactionKey) {
            // CASO A: Clic en la misma reacción -> QUITAR (Toggle Off)
            if (currentReactions[reactionKey]) {
                currentReactions[reactionKey].count = Math.max(0, currentReactions[reactionKey].count - 1);
                currentReactions[reactionKey].user_reacted = false;
                
                // Si llega a 0, la eliminamos del objeto para que no se renderice
                if (currentReactions[reactionKey].count === 0) {
                    delete currentReactions[reactionKey];
                }
            }
        } else {
            // CASO B: Cambio de reacción o Nueva reacción
            
            // 1. Si tenía una reacción vieja diferente, la quitamos
            if (previousUserReactionKey && currentReactions[previousUserReactionKey]) {
                currentReactions[previousUserReactionKey].count = Math.max(0, currentReactions[previousUserReactionKey].count - 1);
                currentReactions[previousUserReactionKey].user_reacted = false;
                
                if (currentReactions[previousUserReactionKey].count === 0) {
                    delete currentReactions[previousUserReactionKey];
                }
            }

            // 2. Añadimos la nueva reacción
            if (!currentReactions[reactionKey]) {
                currentReactions[reactionKey] = { count: 0, user_reacted: false };
            }
            currentReactions[reactionKey].count++;
            currentReactions[reactionKey].user_reacted = true;
        }
        
        // Actualizar la UI inmediatamente
        updateMessageReactions(msgUuid, currentReactions);
    }

    // 2. Enviar petición al servidor (que hará la validación final en BD)
    try {
        const data = await ChatApi.reactMessage(msgUuid, reactionKey, context, targetUuid);
        
        if (!data.success) {
            console.error("Error al reaccionar:", data.message);
            // Opcional: Aquí podrías revertir la UI si falla, recargando los mensajes
        }
        return data;

    } catch (e) {
        console.error("Error de conexión al reaccionar", e);
        return { success: false };
    }
}