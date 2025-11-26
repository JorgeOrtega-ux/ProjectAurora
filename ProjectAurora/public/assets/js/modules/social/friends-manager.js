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
            html = `<button class="btn-add-friend btn-remove-friend" data-uid="${userId}">${t('search.actions.remove')}</button>`;
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

async function sendFriendRequest(targetId, btn) {
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

async function cancelRequest(targetId, btn) {
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

async function removeFriend(targetId, btn) {
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

async function respondRequest(actionType, btn, senderId) {
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
    } catch (e) {
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}

function initSocketListener() {
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;

        if (type === 'friend_request') {
            updateUIButtons(payload.sender_id, 'request_received');
        }
        if (type === 'friend_accepted') {
            updateUIButtons(payload.accepter_id, 'friends');
        }
        if (type === 'request_cancelled' || type === 'request_declined' || type === 'friend_removed') {
            updateUIButtons(payload.sender_id, 'none');
        }
    });
}

function initClickListeners() {
    document.body.addEventListener('click', async (e) => {
        const target = e.target;
        
        const addBtn = target.closest('.btn-add-friend');
        if (addBtn && !target.closest('.btn-remove-friend') && !target.closest('.btn-cancel-request') && !addBtn.disabled) {
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

        const acceptBtn = target.closest('.btn-accept-request') || target.closest('[data-action="accept-req"]');
        if (acceptBtn) {
            e.preventDefault();
            const uid = acceptBtn.dataset.uid || acceptBtn.closest('.notification-item')?.dataset.sid;
            await respondRequest('accept_request', acceptBtn, uid);
            return;
        }

        const declineBtn = target.closest('.btn-decline-request') || target.closest('[data-action="decline-req"]');
        if (declineBtn) {
            e.preventDefault();
            const uid = declineBtn.dataset.uid || declineBtn.closest('.notification-item')?.dataset.sid;
            await respondRequest('decline_request', declineBtn, uid);
            return;
        }
    });
}

export function initFriendsManager() {
    initClickListeners();
    initSocketListener();
}