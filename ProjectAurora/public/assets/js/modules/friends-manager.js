// public/assets/js/friends-manager.js

const API_SOCIAL = (window.BASE_PATH || '/ProjectAurora/') + 'api/social_handler.php';

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

export class FriendsManager {
    constructor() {
        this.initClickListeners();
        this.initSocketListener();
    }

    // --- LISTENERS ---

    initSocketListener() {
        document.addEventListener('socket-message', (e) => {
            const { type, payload } = e.detail;

            // Actualizar botones en tiempo real según eventos del socket
            if (type === 'friend_request') {
                this.updateUIButtons(payload.sender_id, 'request_received');
            }
            if (type === 'friend_accepted') {
                this.updateUIButtons(payload.accepter_id, 'friends');
            }
            if (type === 'request_cancelled' || type === 'request_declined' || type === 'friend_removed') {
                this.updateUIButtons(payload.sender_id, 'none');
            }
        });
    }

    initClickListeners() {
        document.body.addEventListener('click', async (e) => {
            const target = e.target;
            
            // 1. Enviar Solicitud
            const addBtn = target.closest('.btn-add-friend');
            if (addBtn && !target.closest('.btn-remove-friend') && !target.closest('.btn-cancel-request') && !addBtn.disabled) {
                e.preventDefault();
                await this.sendFriendRequest(addBtn.dataset.uid, addBtn);
                return; 
            }

            // 2. Cancelar Solicitud
            const cancelBtn = target.closest('.btn-cancel-request');
            if (cancelBtn) {
                e.preventDefault();
                await this.cancelRequest(cancelBtn.dataset.uid, cancelBtn);
                return; 
            }

            // 3. Eliminar Amigo
            const removeBtn = target.closest('.btn-remove-friend');
            if (removeBtn) {
                e.preventDefault();
                if(confirm('¿Seguro que quieres eliminar a este amigo?')) {
                    await this.removeFriend(removeBtn.dataset.uid, removeBtn);
                }
                return;
            }

            // 4. Aceptar Solicitud (En perfil o notificación)
            const acceptBtn = target.closest('.btn-accept-request') || target.closest('[data-action="accept-req"]');
            if (acceptBtn) {
                e.preventDefault();
                const uid = acceptBtn.dataset.uid || acceptBtn.closest('.notification-item')?.dataset.sid;
                await this.respondRequest('accept_request', acceptBtn, uid);
                return;
            }

            // 5. Rechazar Solicitud (En perfil o notificación)
            const declineBtn = target.closest('.btn-decline-request') || target.closest('[data-action="decline-req"]');
            if (declineBtn) {
                e.preventDefault();
                const uid = declineBtn.dataset.uid || declineBtn.closest('.notification-item')?.dataset.sid;
                await this.respondRequest('decline_request', declineBtn, uid);
                return;
            }
        });
    }

    // --- ACCIONES DE API ---

    async sendFriendRequest(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'send_request', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Solicitud enviada', 'success');
                this.updateUIButtons(targetId, 'request_sent');
            } else {
                this.handleError(res, btn, 'Agregar a amigos');
            }
        } catch (e) { this.handleError(null, btn, 'Agregar a amigos'); }
    }

    async cancelRequest(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'cancel_request', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Solicitud cancelada', 'info');
                this.updateUIButtons(targetId, 'none');
                this.triggerNotificationReload(); // Para limpiar notificaciones si existieran
            } else {
                this.handleError(res, btn, 'Cancelar solicitud');
            }
        } catch (e) { this.handleError(null, btn, 'Cancelar solicitud'); }
    }

    async removeFriend(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'remove_friend', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Amigo eliminado', 'info');
                this.updateUIButtons(targetId, 'none');
                this.triggerNotificationReload(); 
            } else {
                this.handleError(res, btn, 'Eliminar amigo');
            }
        } catch (e) { this.handleError(null, btn, 'Eliminar amigo'); }
    }

    async respondRequest(actionType, btn, senderId) {
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<div class="small-spinner"></div>';
        btn.disabled = true;

        try {
            const res = await this.fetchApi({ action: actionType, sender_id: senderId });
            if (res.success) {
                this.triggerNotificationReload(); // Importante: actualiza la lista de notificaciones (quita los botones)

                if (actionType === 'accept_request') {
                    if (window.alertManager) window.alertManager.showAlert('¡Ahora son amigos!', 'success');
                    this.updateUIButtons(senderId, 'friends');
                } else {
                    if (window.alertManager) window.alertManager.showAlert('Solicitud rechazada', 'info');
                    this.updateUIButtons(senderId, 'none');
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

    // --- HELPERS UI ---

    updateUIButtons(userId, state) {
        const container = document.getElementById(`actions-${userId}`);
        if (!container) return;

        let html = '';
        switch (state) {
            case 'friends':
                html = `<button class="btn-add-friend btn-remove-friend" data-uid="${userId}">Eliminar amigo</button>`;
                break;
            case 'request_sent':
                html = `<button class="btn-add-friend btn-cancel-request" data-uid="${userId}">Cancelar solicitud</button>`;
                break;
            case 'request_received':
                html = `
                    <button class="btn-accept-request" data-uid="${userId}">Aceptar</button>
                    <button class="btn-decline-request" data-uid="${userId}">Rechazar</button>
                `;
                break;
            case 'none':
            default:
                html = `<button class="btn-add-friend" data-uid="${userId}">Agregar a amigos</button>`;
                break;
        }
        container.innerHTML = html;
    }

    triggerNotificationReload() {
        document.dispatchEvent(new CustomEvent('reload-notifications'));
    }

    setLoading(btn, isLoading, originalText = '') {
        if (isLoading) {
            btn.dataset.original = btn.textContent;
            btn.innerHTML = '<div class="small-spinner" style="border-color:#ccc; border-top-color:#000;"></div>';
            btn.disabled = true;
        } else {
            btn.innerHTML = originalText || btn.dataset.original;
            btn.disabled = false;
        }
    }

    handleError(res, btn, originalText) {
        if (window.alertManager && res) window.alertManager.showAlert(res.message, 'error');
        this.setLoading(btn, false, originalText);
    }

    async fetchApi(data) {
        const response = await fetch(API_SOCIAL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify(data)
        });
        return await response.json();
    }
}