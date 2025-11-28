// public/assets/js/modules/friends-manager.js

import { t } from '../../core/i18n-manager.js';
import { postJson, setButtonLoading } from '../../core/utilities.js';

function triggerNotificationReload() {
    document.dispatchEvent(new CustomEvent('reload-notifications'));
}

function updateUIButtons(userId, state) {
    const container = document.getElementById(`actions-${userId}`);
    if (!container) return;

    let html = '';
    switch (state) {
        case 'friends':
            // [MODIFICADO] Añadido botón de Mensaje
            html = `
                <button class="btn-add-friend" data-action="send-dm" data-uid="${userId}" style="margin-right:4px;">
                    <span class="material-symbols-rounded" style="font-size:16px;">chat</span>
                </button>
                <button class="btn-add-friend btn-remove-friend" data-uid="${userId}">${t('search.actions.remove')}</button>
            `;
            break;
        case 'request_sent':
            html = `<button class="btn-add-friend btn-cancel-request" data-uid="${userId}">${t('search.actions.cancel')}</button>`;
            break;
        case 'request_received':
            html = `
                <button class="btn-accept-request" data-uid="${userId}">${t('search.actions.accept')}</button>
                <button class="btn-decline-request" data-uid="${userId}">${t('search.actions.decline')}</button>
            `;
            break;
        case 'none':
        default:
            html = `<button class="btn-add-friend" data-uid="${userId}">${t('search.actions.add')}</button>`;
            break;
    }
    container.innerHTML = html;
}

// ... (Funciones sendFriendRequest, cancelRequest, removeFriend, respondRequest se mantienen IGUAL) ...
// ... Copiar del archivo original o asumir que existen ...

async function sendFriendRequest(targetId, btn) { /* Lógica existente */ 
    setButtonLoading(btn, true);
    try {
        const res = await postJson('api/friends_handler.php', { action: 'send_request', target_id: targetId });
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(t('notifications.request_sent'), 'success');
            updateUIButtons(targetId, 'request_sent');
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false);
        }
    } catch (e) { setButtonLoading(btn, false); }
}

async function cancelRequest(targetId, btn) { /* Lógica existente */ 
    setButtonLoading(btn, true);
    try {
        const res = await postJson('api/friends_handler.php', { action: 'cancel_request', target_id: targetId });
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(t('notifications.request_cancelled'), 'info');
            updateUIButtons(targetId, 'none');
            triggerNotificationReload(); 
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false);
        }
    } catch (e) { setButtonLoading(btn, false); }
}

async function removeFriend(targetId, btn) { /* Lógica existente */ 
    setButtonLoading(btn, true);
    try {
        const res = await postJson('api/friends_handler.php', { action: 'remove_friend', target_id: targetId });
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(t('notifications.friend_removed'), 'info');
            updateUIButtons(targetId, 'none');
            triggerNotificationReload(); 
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false);
        }
    } catch (e) { setButtonLoading(btn, false); }
}

async function respondRequest(actionType, btn, senderId) { /* Lógica existente */ 
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="small-spinner"></div>';
    btn.disabled = true;
    try {
        const res = await postJson('api/friends_handler.php', { action: actionType, sender_id: senderId });
        if (res.success) {
            triggerNotificationReload(); 
            if (actionType === 'accept_request') {
                if (window.alertManager) window.alertManager.showAlert(t('notifications.now_friends'), 'success');
                updateUIButtons(senderId, 'friends');
            } else {
                if (window.alertManager) window.alertManager.showAlert(t('notifications.request_declined'), 'info');
                updateUIButtons(senderId, 'none');
            }
        } else {
            btn.innerHTML = originalContent;
            btn.disabled = false;
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
        }
    } catch (e) { btn.innerHTML = originalContent; btn.disabled = false; }
}

// [NUEVO] Función para iniciar chat
async function startDirectChat(targetUid) {
    // Solicitar al backend que inicie/verifique chat y nos de el UUID
    // Usamos el ID numérico (uid del dataset) para buscar, el backend nos devuelve UUID
    const res = await postJson('api/friends_handler.php', { action: 'start_chat', target_id: targetUid });
    
    if (res.success && res.uuid) {
        // Redirigir al router de DM
        if(window.navigateTo) window.navigateTo(`dm/${res.uuid}`);
        else window.location.href = `${window.BASE_PATH}dm/${res.uuid}`;
    } else {
        alert(res.message || 'Error iniciando chat');
    }
}

function initSocketListener() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        if (type === 'friend_request') updateUIButtons(payload.sender_id, 'request_received');
        if (type === 'friend_accepted') updateUIButtons(payload.accepter_id, 'friends');
        if (type === 'request_cancelled' || type === 'request_declined' || type === 'friend_removed') updateUIButtons(payload.sender_id, 'none');
    });
}

function initClickListeners() {
    document.body.addEventListener('click', async (e) => {
        const target = e.target;
        
        // [NUEVO] Listener para botón de mensaje
        const dmBtn = target.closest('[data-action="send-dm"]');
        if (dmBtn) {
            e.preventDefault();
            await startDirectChat(dmBtn.dataset.uid);
            return;
        }

        const addBtn = target.closest('.btn-add-friend');
        if (addBtn && !target.closest('.btn-remove-friend') && !target.closest('.btn-cancel-request') && !target.closest('[data-action="send-dm"]') && !addBtn.disabled) {
            e.preventDefault();
            await sendFriendRequest(addBtn.dataset.uid, addBtn);
            return; 
        }

        const cancelBtn = target.closest('.btn-cancel-request');
        if (cancelBtn) {
            e.preventDefault();
            await cancelRequest(cancelBtn.dataset.uid, cancelBtn);
            return; 
        }

        const removeBtn = target.closest('.btn-remove-friend');
        if (removeBtn) {
            e.preventDefault();
            if(confirm(t('search.actions.remove_confirm') || '¿Seguro que quieres eliminar a este amigo?')) {
                await removeFriend(removeBtn.dataset.uid, removeBtn);
            }
            return;
        }

        const acceptBtn = target.closest('.btn-accept-request');
        if (acceptBtn) {
            e.preventDefault();
            await respondRequest('accept_request', acceptBtn, acceptBtn.dataset.uid);
            return;
        }

        const declineBtn = target.closest('.btn-decline-request');
        if (declineBtn) {
            e.preventDefault();
            await respondRequest('decline_request', declineBtn, declineBtn.dataset.uid);
            return;
        }
    });
}

export function initFriendsManager() {
    initClickListeners();
    initSocketListener();
}