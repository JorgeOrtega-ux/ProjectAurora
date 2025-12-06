// public/assets/js/modules/community-actions.js

import { CommunityApi, ChatApi, FriendApi } from '../services/api-service.js';
import { t } from '../core/i18n-manager.js';

/**
 * Maneja la actualización de la lista lateral cuando llega un mensaje por socket.
 * Determina si el item existe, lo mueve al inicio, actualiza contadores o hace fetch si es nuevo.
 * * @param {Object} payload - Datos del mensaje recibido por socket
 * @param {Array} currentSidebarItems - Lista actual de items del sidebar
 * @param {Function} refreshUICallback - Función para repintar la UI (lista y rail)
 * @returns {Promise<Array>} - Retorna la lista actualizada
 */
export async function handleSidebarUpdate(payload, currentSidebarItems, refreshUICallback) {
    let sidebarItems = [...currentSidebarItems]; // Copia para inmutabilidad superficial

    // Determinar UUID del chat
    let uuid = payload.community_uuid || (parseInt(payload.sender_id) === parseInt(window.USER_ID) ? payload.target_uuid : payload.sender_uuid);
    
    // Determinar texto del mensaje
    let messageText = payload.message;
    if (payload.type === 'image' || (payload.attachments && payload.attachments.length > 0)) {
        messageText = '📷 Imagen';
    }
    
    if (payload.context === 'community' && payload.sender_id != window.USER_ID) {
        messageText = `${payload.sender_username}: ${messageText}`;
    } else if (payload.sender_id == window.USER_ID) {
        messageText = `Tú: ${messageText}`;
    }

    const dataItem = sidebarItems.find(i => i.uuid === uuid);
    const isActiveChat = (uuid === window.ACTIVE_CHAT_UUID);
    
    // Verificar si el canal activo coincide (para comunidades)
    let isChannelActive = true;
    if (payload.channel_uuid && window.ACTIVE_CHANNEL_UUID && payload.channel_uuid !== window.ACTIVE_CHANNEL_UUID) {
        isChannelActive = false;
    }
    
    const windowHasFocus = document.hasFocus();

    if (dataItem) {
        // ACTUALIZAR ITEM EXISTENTE
        dataItem.last_message = messageText;
        dataItem.last_message_at = new Date().toISOString();
        
        // Si estaba archivado, desarchivar al recibir mensaje nuevo
        if (parseInt(dataItem.is_archived) === 1) {
            dataItem.is_archived = 0;
        }

        // Manejo de contadores no leídos
        if (!isActiveChat || (isActiveChat && !isChannelActive) || !windowHasFocus) {
            dataItem.unread_count = (parseInt(dataItem.unread_count) || 0) + 1;
            
            // Actualizar badge específico del canal en el DOM si es necesario (Manipulación DOM mínima necesaria aquí)
            if (payload.channel_uuid) {
                const channelEl = document.querySelector(`.channel-item[data-uuid="${payload.channel_uuid}"]`);
                if (channelEl) {
                     let badge = channelEl.querySelector('.channel-unread-badge');
                     if (!badge) {
                         badge = document.createElement('span'); 
                         badge.className = 'channel-unread-badge'; 
                         badge.innerText = '0';
                         const delBtn = channelEl.querySelector('.channel-action-btn');
                         if(delBtn) channelEl.insertBefore(badge, delBtn); else channelEl.appendChild(badge);
                         channelEl.classList.add('has-unread');
                     }
                     let count = parseInt(badge.innerText) || 0; 
                     badge.innerText = count + 1;
                }
            }
        }
        
        // Mover al inicio
        sidebarItems = sidebarItems.filter(i => i.uuid !== uuid);
        sidebarItems.unshift(dataItem);
        
        if (refreshUICallback) refreshUICallback(sidebarItems);
        return sidebarItems;

    } else {
        // ITEM NUEVO (No está en la lista local) -> Fetch y agregar
        let displayName, displayPfp, displayRole;
        let needsFetch = false;

        if (payload.context === 'community') {
            needsFetch = true;
        } else {
            // Si es DM y me lo envían, tengo los datos en el payload
            if (parseInt(payload.sender_id) !== parseInt(window.USER_ID)) {
                displayName = payload.sender_username; 
                displayPfp = payload.sender_profile_picture; 
                displayRole = payload.sender_role;
            } else {
                // Si yo lo envié (desde otro dispositivo), necesito hacer fetch de la info del receptor
                needsFetch = true;
            }
        }

        if (needsFetch) { 
            const fetchedItem = await fetchCommunityInfo(uuid, payload, messageText);
            if (fetchedItem && !sidebarItems.find(i => i.uuid === fetchedItem.uuid)) {
                sidebarItems.unshift(fetchedItem);
                if (refreshUICallback) refreshUICallback(sidebarItems);
            }
            return sidebarItems;
        } 
        
        // Si tenemos datos suficientes sin fetch
        const newItem = {
            uuid: uuid, 
            name: displayName || 'Usuario', 
            profile_picture: displayPfp,
            type: payload.context, 
            role: displayRole || 'user', 
            last_message: messageText,
            last_message_at: payload.created_at || new Date().toISOString(),
            unread_count: (parseInt(payload.sender_id) !== parseInt(window.USER_ID)) ? 1 : 0,
            is_pinned: 0, 
            is_favorite: 0, 
            is_blocked_by_me: 0, 
            is_archived: 0
        };
        
        sidebarItems.unshift(newItem);
        if (refreshUICallback) refreshUICallback(sidebarItems);
        return sidebarItems;
    }
}

/**
 * Helper interno para obtener información de un chat nuevo
 */
async function fetchCommunityInfo(uuid, payload, messageText) {
    try {
        let res;
        if (payload.context === 'private') {
            res = await CommunityApi.getUserChatByUuid(uuid);
        } else {
            res = await CommunityApi.getByUuid(uuid);
        }

        if (res.success) {
            const data = res.data || res.community;
            return {
                uuid: data.uuid, 
                name: data.name || data.community_name || data.username,
                profile_picture: data.profile_picture, 
                type: payload.context, 
                role: data.role || 'member',
                last_message: messageText, 
                last_message_at: payload.created_at || new Date().toISOString(),
                unread_count: 0, 
                is_pinned: 0, 
                is_favorite: 0, 
                is_blocked_by_me: 0, 
                is_archived: 0,
                friend_status: data.friend_status || 'none',
                is_verified: data.is_verified || 0
            };
        }
    } catch (e) { 
        console.error("Error fetching community info:", e); 
    }
    return null;
}

// --- ACCIONES DE CHAT ---

export async function togglePinChat(uuid, type, reloadCallback) {
    const res = await CommunityApi.togglePin(uuid, type);
    if (res.success) {
        if (reloadCallback) reloadCallback();
    } else if(window.alertManager) {
        window.alertManager.showAlert(res.message, 'warning');
    }
}

export async function toggleFavChat(uuid, type, reloadCallback) {
    const res = await CommunityApi.toggleFavorite(uuid, type);
    if (res.success) {
        if (reloadCallback) reloadCallback();
    } else if(window.alertManager) {
        window.alertManager.showAlert(res.message, 'error');
    }
}

export async function toggleArchiveChat(uuid, type, sidebarItems, reloadCallback) {
    const res = await CommunityApi.toggleArchive(uuid, type);
    if (res.success) {
        // Actualización optimista local
        const item = sidebarItems.find(i => i.uuid === uuid);
        if (item) item.is_archived = (parseInt(item.is_archived) === 1) ? 0 : 1;
        
        if (reloadCallback) reloadCallback();
        
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
    } else if(window.alertManager) {
        window.alertManager.showAlert(res.message, 'error');
    }
}

export async function leaveCommunity(uuid, currentActiveUuid, reloadCallback) {
    if (!confirm('¿Estás seguro de que quieres salir de este grupo?')) return;
    
    const res = await CommunityApi.leaveCommunity(uuid);
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        
        if (currentActiveUuid === uuid) {
             window.ACTIVE_CHAT_UUID = null;
             if(window.navigateTo) window.navigateTo('main'); 
             else window.location.href = window.BASE_PATH + 'main';
        } else {
            if (reloadCallback) reloadCallback();
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

// --- ACCIONES DE USUARIO EN CHAT (BLOQUEO/AMIGOS) ---

export async function blockUserFromChat(uuid) {
    if (!confirm(t('friends.confirm_block') || '¿Seguro que quieres bloquear a este usuario?')) return;
    
    const resInfo = await CommunityApi.getUserChatByUuid(uuid);
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const resBlock = await FriendApi.blockUser(targetId);
        if (resBlock.success) {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'success');
            window.location.reload(); 
        } else {
            if(window.alertManager) window.alertManager.showAlert(resBlock.message, 'error');
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert("No se pudo identificar al usuario.", 'error');
    }
}

export async function unblockUserFromChat(uuid) {
    const resInfo = await CommunityApi.getUserChatByUuid(uuid);
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const resUnblock = await FriendApi.unblockUser(targetId);
        if (resUnblock.success) {
            if(window.alertManager) window.alertManager.showAlert(resUnblock.message, 'success');
            window.location.reload(); 
        } else {
            if(window.alertManager) window.alertManager.showAlert(resUnblock.message, 'error');
        }
    }
}

export async function addFriendFromChat(uuid, reloadCallback) {
    const resInfo = await CommunityApi.getUserChatByUuid(uuid);
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const res = await FriendApi.sendRequest(targetId);
        if (res.success) {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
            if (reloadCallback) reloadCallback();
        } else {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        }
    }
}

export async function removeFriendFromChat(uuid, reloadCallback) {
    if (!confirm(t('search.actions.remove_confirm') || '¿Seguro que quieres eliminar a este amigo?')) return;
    
    const resInfo = await CommunityApi.getUserChatByUuid(uuid);
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const res = await FriendApi.removeFriend(targetId);
        if (res.success) {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
            if (reloadCallback) reloadCallback();
        } else {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        }
    }
}

export async function cancelRequestFromChat(uuid, reloadCallback) {
    const resInfo = await CommunityApi.getUserChatByUuid(uuid);
    if (resInfo.success) {
        const targetId = resInfo.data.id;
        const res = await FriendApi.cancelRequest(targetId);
        if (res.success) {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
            if (reloadCallback) reloadCallback();
        } else {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        }
    }
}

export async function deleteChatConversation(uuid, currentActiveUuid, reloadCallback) {
    if(!confirm('¿Seguro que quieres eliminar este chat? Solo se borrará para ti.')) return;
    
    const res = await ChatApi.deleteConversation(uuid);
    if(res.success) { 
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success'); 
        
        if (currentActiveUuid === uuid) { 
            window.ACTIVE_CHAT_UUID = null; 
            if(window.navigateTo) window.navigateTo('main'); 
            else window.location.href = window.BASE_PATH + 'main'; 
        } else { 
            if (reloadCallback) reloadCallback(); 
        } 
    } else { 
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error'); 
    }
}

// --- [NUEVO] MODERACIÓN DE COMUNIDAD (KICK/BAN/MUTE) ---

// Función auxiliar para llamar a la API de comunidades
async function callModerationApi(payload) {
    try {
        const response = await fetch('api/communities_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            body: JSON.stringify({
                ...payload,
                csrf_token: window.CSRF_TOKEN || ''
            })
        });
        return await response.json();
    } catch (e) {
        console.error("Moderation API Error:", e);
        return { success: false, message: 'Error de conexión' };
    }
}

export async function kickMember(communityUuid, targetUuid, reloadCallback) {
    if (!confirm('¿Estás seguro de que quieres EXPULSAR a este usuario de la comunidad?')) return;
    
    const res = await callModerationApi({
        action: 'kick_member',
        community_uuid: communityUuid,
        target_uuid: targetUuid
    });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (reloadCallback) reloadCallback();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

export async function banMember(communityUuid, targetUuid, reloadCallback) {
    const reason = prompt("Por favor, ingresa la razón del BANEO:");
    if (reason === null) return; // Cancelado

    // [MODIFICADO] Preguntar duración
    const duration = prompt("Duración de la suspensión? (Opciones: 12h, 1d, 3d, 1w)\nDeja vacío para PERMANENTE.", "");
    if (duration === null) return; // Cancelado

    const payload = {
        action: 'ban_member',
        community_uuid: communityUuid,
        target_uuid: targetUuid,
        reason: reason
    };

    if (duration && duration.trim() !== '') {
        payload.duration = duration.trim();
    } else {
        payload.duration = 'permanent';
    }
    
    const res = await callModerationApi(payload);

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (reloadCallback) reloadCallback();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

export async function muteMember(communityUuid, targetUuid, reloadCallback) {
    const duration = prompt("Minutos de silencio (Mute):", "5");
    if (duration === null) return; // Cancelado
    
    const res = await callModerationApi({
        action: 'mute_member',
        community_uuid: communityUuid,
        target_uuid: targetUuid,
        duration: duration
    });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (reloadCallback) reloadCallback();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

// --- GESTOR GLOBAL DE EVENTOS DE COMUNIDAD PARA BÚSQUEDA ---
// Se auto-ejecuta al importar el módulo para escuchar clics en todo el documento.
(function initSearchListeners() {
    // Evitar doble inicialización
    if (window.hasInitSearchCommunityListeners) return;
    window.hasInitSearchCommunityListeners = true;

    document.addEventListener('click', async (e) => {
        // Solo nos interesa si el clic fue en (o dentro de) un botón de nuestras clases
        
        // 1. UNIRSE A COMUNIDAD PÚBLICA
        const joinBtn = e.target.closest('[data-action="join-public-community-search"]');
        if (joinBtn) {
            e.preventDefault();
            joinBtn.disabled = true;
            const originalText = joinBtn.innerText;
            joinBtn.innerHTML = '<span class="material-symbols-rounded spinning" style="font-size:16px;">sync</span>';
            
            try {
                const response = await fetch('api/communities_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || ''},
                    body: JSON.stringify({
                        action: 'join_public', 
                        community_id: joinBtn.dataset.id, 
                        csrf_token: window.CSRF_TOKEN || ''
                    })
                });
                const res = await response.json();
                
                if(res.success){
                    if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                    // Cambiar visualmente el botón a "Unido"
                    joinBtn.innerText = 'Unido';
                    joinBtn.style.backgroundColor = 'var(--success-color, #2ecc71)';
                    // Actualizar sidebar si es necesario
                    document.dispatchEvent(new CustomEvent('refresh-sidebar-request'));
                } else {
                    if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                    joinBtn.disabled = false;
                    joinBtn.innerText = originalText;
                }
            } catch(err){
                console.error(err);
                joinBtn.disabled = false;
                joinBtn.innerText = 'Error';
            }
            return;
        }

        // 2. SALIR DE COMUNIDAD (Desde búsqueda)
        const leaveBtn = e.target.closest('[data-action="leave-community-search"]');
        if (leaveBtn) {
            e.preventDefault();
            if(!confirm("¿Estás seguro de que quieres salir de esta comunidad?")) return;
            
            try {
                const response = await fetch('api/communities_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || ''},
                    body: JSON.stringify({
                        action: 'leave_community', 
                        community_id: leaveBtn.dataset.id, 
                        uuid: leaveBtn.dataset.uuid, 
                        csrf_token: window.CSRF_TOKEN || ''
                    })
                });
                const res = await response.json();
                
                if(res.success){
                    if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
                    document.dispatchEvent(new CustomEvent('refresh-sidebar-request'));
                    // Eliminar la tarjeta visualmente (o reducir opacidad)
                    const card = leaveBtn.closest('.community-result-item');
                    if(card) card.style.opacity = '0.5'; 
                } else {
                    if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                }
            } catch(err){ console.error(err); }
            return;
        }

        // 3. UNIRSE A COMUNIDAD PRIVADA
        const privateBtn = e.target.closest('[data-action="join-private-community-search"]');
        if (privateBtn) {
            e.preventDefault();
            const name = privateBtn.dataset.name;
            if(window.navigateTo) {
                window.navigateTo('join-community?community=' + encodeURIComponent(name));
            } else {
                window.location.href = 'join-community?community=' + encodeURIComponent(name);
            }
            return;
        }

        // 4. VER COMUNIDAD
        const viewBtn = e.target.closest('[data-action="view-community"]');
        if (viewBtn) {
            e.preventDefault();
            const uuid = viewBtn.dataset.uuid;
            if(window.loadCommunity) {
                window.loadCommunity(uuid);
            } else if(window.navigateTo) {
                window.navigateTo('community?uuid=' + uuid);
            }
        }
    });
})();