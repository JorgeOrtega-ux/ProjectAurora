// public/assets/js/modules/chat-actions.js

import { ChatApi } from '../services/api-service.js';
import { t } from '../core/i18n-manager.js';

// Variables para control de throttling
let lastTypingSent = 0;
const TYPING_THROTTLE = 2000;

/**
 * Marca el chat como leído en el servidor y dispara evento local para actualizar UI.
 */
export async function sendReadSignal(chatUuid, chatType, channelUuid = null) {
    if (!chatUuid) return;
    
    // Llamada a API (silenciosa)
    await ChatApi.markAsRead(chatUuid, chatType, channelUuid);
    
    // Evento para limpiar contadores en sidebar
    const event = new CustomEvent('local-chat-read', { detail: { uuid: chatUuid } });
    document.dispatchEvent(event);
}

/**
 * Maneja la lógica de envío de mensajes.
 * Decide si usar HTTP (FormData) si hay archivos, o WebSockets si es solo texto.
 * * @param {Object} params - { targetUuid, context, message, replyToUuid, channelUuid, attachments }
 * @returns {Promise<Object>} - { success: boolean, message: string }
 */
export async function sendMessage(params) {
    const { targetUuid, context, message, replyToUuid, channelUuid, attachments } = params;

    if (!targetUuid) return { success: false, message: 'No target UUID' };

    // 1. Si hay archivos, FORZAR uso de API (FormData)
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
    // 2. Si es solo texto, intentar usar SOCKET para velocidad
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
            
            // Añadir campos opcionales
            if (channelUuid && context === 'community') {
                payload.payload.channel_uuid = channelUuid;
            }
            
            if (replyToUuid) {
                payload.payload.reply_to_uuid = replyToUuid;
            }
            
            socket.send(JSON.stringify(payload));
            
            // Asumimos éxito optimista con Socket
            return { success: true };
        } else {
            return { success: false, message: 'Sin conexión al servidor de chat (Socket cerrado).' };
        }
    }
}

/**
 * Envía evento de "escribiendo..." con throttling para no saturar.
 */
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

/**
 * Guarda la edición de un mensaje existente.
 */
export async function saveEditMessage(msgUuid, newText, context, targetUuid, channelUuid) {
    try {
        const res = await ChatApi.editMessage(msgUuid, newText, context, targetUuid, channelUuid);
        return res; // { success, message }
    } catch (e) {
        console.error(e);
        return { success: false, message: t('global.error_connection') };
    }
}

/**
 * Elimina un mensaje (Soft delete).
 */
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

/**
 * Reporta un mensaje a moderación.
 */
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