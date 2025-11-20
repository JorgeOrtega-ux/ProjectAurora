// public/assets/js/social-manager.js

const API_SOCIAL = (window.BASE_PATH || '/ProjectAurora/') + 'api/social_handler.php';
const WS_URL = 'ws://localhost:8080'; 

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

export class SocialManager {
    constructor() {
        this.socket = null;
        this.initListeners();
        this.initWebSocket(); 
        this.loadNotifications(); 
    }

    // --- WEBSOCKET CONNECT ---
    initWebSocket() {
        if (!window.USER_ID) return; 

        this.socket = new WebSocket(WS_URL);

        this.socket.onopen = () => {
            console.log('[WS] Conectado.');
            this.socket.send(JSON.stringify({ type: 'auth', user_id: window.USER_ID }));
        };

        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleSocketMessage(data);
            } catch (e) {
                console.error('[WS] Error parseando mensaje', e);
            }
        };

        this.socket.onclose = () => {
            setTimeout(() => this.initWebSocket(), 5000);
        };
    }

    // --- MANEJO DE MENSAJES EN VIVO ---
    handleSocketMessage(data) {
        const { type, payload } = data;
        const alertMgr = window.alertManager;

        // 1. SOLICITUD RECIBIDA
        if (type === 'friend_request') {
            if (alertMgr) alertMgr.showAlert(payload.message, 'info');
            this.loadNotifications(); 

            // Inyectar botones en Search Page
            const actionContainer = document.getElementById(`actions-${payload.sender_id}`);
            if (actionContainer) {
                actionContainer.innerHTML = `
                    <button class="btn-accept-request" data-uid="${payload.sender_id}">Aceptar</button>
                    <button class="btn-decline-request" data-uid="${payload.sender_id}">Rechazar</button>
                `;
            }
        }

        // 2. SOLICITUD ACEPTADA
        if (type === 'friend_accepted') {
            if (alertMgr) alertMgr.showAlert(payload.message, 'success');
            this.loadNotifications(); // Nueva notificación de aceptación

            const actionContainer = document.getElementById(`actions-${payload.accepter_id}`);
            if (actionContainer) {
                actionContainer.innerHTML = `
                    <button class="btn-add-friend btn-remove-friend" data-uid="${payload.accepter_id}">Eliminar amigo</button>
                `;
            }
        }

        // 3. SOLICITUD CANCELADA
        if (type === 'request_cancelled') {
            this.loadNotifications(); // Quitar la notificación
            
            const actionContainer = document.getElementById(`actions-${payload.sender_id}`);
            if (actionContainer) {
                actionContainer.innerHTML = `
                    <button class="btn-add-friend" data-uid="${payload.sender_id}">Agregar a amigos</button>
                `;
            }
        }

        // 4. SOLICITUD RECHAZADA
        if (type === 'request_declined') {
             // Quien rechazó fue el otro, actualizamos mi UI para permitir enviar de nuevo
             const actionContainer = document.getElementById(`actions-${payload.sender_id}`);
             if (actionContainer) {
                actionContainer.innerHTML = `
                    <button class="btn-add-friend" data-uid="${payload.sender_id}">Agregar a amigos</button>
                `;
             }
        }

        // 5. AMIGO ELIMINADO
        if (type === 'friend_removed') {
            this.loadNotifications(); // Limpiar rastros
            const actionContainer = document.getElementById(`actions-${payload.sender_id}`);
            if (actionContainer) {
                actionContainer.innerHTML = `
                    <button class="btn-add-friend" data-uid="${payload.sender_id}">Agregar a amigos</button>
                `;
            }
        }
    }

    // --- LISTENERS ---
    initListeners() {
        document.body.addEventListener('click', async (e) => {
            const target = e.target;
            
            // Enviar
            if (target.closest('.btn-add-friend') && !target.closest('.btn-remove-friend') && !target.closest('.btn-cancel-request') && !target.closest('.disabled')) {
                e.preventDefault();
                await this.sendFriendRequest(target.closest('.btn-add-friend').dataset.uid, target.closest('.btn-add-friend'));
                return; 
            }
            // Cancelar
            if (target.closest('.btn-cancel-request')) {
                e.preventDefault();
                await this.cancelRequest(target.closest('.btn-cancel-request').dataset.uid, target.closest('.btn-cancel-request'));
                return; 
            }
            // Eliminar
            if (target.closest('.btn-remove-friend')) {
                e.preventDefault();
                if(confirm('¿Seguro que quieres eliminar a este amigo?')) {
                    await this.removeFriend(target.closest('.btn-remove-friend').dataset.uid, target.closest('.btn-remove-friend'));
                }
                return;
            }
            // Aceptar
            if (target.closest('.btn-accept-request') || target.closest('[data-action="accept-req"]')) {
                e.preventDefault();
                const btn = target.closest('.btn-accept-request') || target.closest('[data-action="accept-req"]');
                const uid = btn.dataset.uid || btn.closest('.notification-item')?.dataset.sid;
                const nid = btn.closest('.notification-item')?.dataset.nid || 0;
                await this.respondRequest('accept_request', btn, uid, nid);
                return;
            }
            // Rechazar
            if (target.closest('.btn-decline-request') || target.closest('[data-action="decline-req"]')) {
                e.preventDefault();
                const btn = target.closest('.btn-decline-request') || target.closest('[data-action="decline-req"]');
                const uid = btn.dataset.uid || btn.closest('.notification-item')?.dataset.sid;
                const nid = btn.closest('.notification-item')?.dataset.nid || 0;
                await this.respondRequest('decline_request', btn, uid, nid);
                return;
            }
            // Marcar leídas
            if (target.closest('.notifications-action')) {
                this.markAllRead();
                return;
            }
        });
    }

    async sendFriendRequest(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'send_request', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Solicitud enviada', 'success');
                const container = document.getElementById(`actions-${targetId}`);
                if(container) container.innerHTML = `<button class="btn-add-friend btn-cancel-request" data-uid="${targetId}">Cancelar solicitud</button>`;
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
                this.setLoading(btn, false, 'Agregar a amigos');
            }
        } catch (e) { this.setLoading(btn, false, 'Agregar a amigos'); }
    }

    async cancelRequest(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'cancel_request', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Solicitud cancelada', 'info');
                const container = document.getElementById(`actions-${targetId}`);
                if(container) container.innerHTML = `<button class="btn-add-friend" data-uid="${targetId}">Agregar a amigos</button>`;
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
                this.setLoading(btn, false, 'Cancelar solicitud');
            }
        } catch (e) { this.setLoading(btn, false, 'Cancelar solicitud'); }
    }

    async removeFriend(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'remove_friend', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Amigo eliminado', 'info');
                const container = document.getElementById(`actions-${targetId}`);
                if(container) container.innerHTML = `<button class="btn-add-friend" data-uid="${targetId}">Agregar a amigos</button>`;
                
                // [FIX] Recargar notificaciones para borrar rastros antiguos
                this.loadNotifications(); 
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
                this.setLoading(btn, false, 'Eliminar amigo');
            }
        } catch (e) { this.setLoading(btn, false, 'Eliminar amigo'); }
    }

    async respondRequest(actionType, btn, senderId, notifId = 0) {
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<div class="small-spinner"></div>';
        btn.disabled = true;

        try {
            const res = await this.fetchApi({ 
                action: actionType, 
                notification_id: notifId,
                sender_id: senderId 
            });

            if (res.success) {
                // [FIX] Recarga inmediata de notificaciones para que desaparezca la solicitud
                this.loadNotifications();

                const container = document.getElementById(`actions-${senderId}`);
                if (actionType === 'accept_request') {
                    if (window.alertManager) window.alertManager.showAlert('¡Ahora son amigos!', 'success');
                    if (container) container.innerHTML = `<button class="btn-add-friend btn-remove-friend" data-uid="${senderId}">Eliminar amigo</button>`;
                } else {
                    if (window.alertManager) window.alertManager.showAlert('Solicitud rechazada', 'info');
                    if (container) container.innerHTML = `<button class="btn-add-friend" data-uid="${senderId}">Agregar a amigos</button>`;
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

    // ... (loadNotifications, markAllRead, renderNotifications, renderEmptyState, fetchApi, setLoading MISMOS QUE ANTES) ...
    async loadNotifications() {
        try {
            const res = await this.fetchApi({ action: 'get_notifications' });
            if (res.success) { this.renderNotifications(res.notifications); }
        } catch (e) { }
    }
    async markAllRead() {
        const container = document.querySelector('.notifications-bottom');
        container.innerHTML = ''; 
        this.renderEmptyState(container);
        await this.fetchApi({ action: 'mark_read_all' });
    }
    renderNotifications(notifs) {
        const container = document.querySelector('.notifications-bottom');
        if (!container) return;
        if (notifs.length === 0) { this.renderEmptyState(container); return; }
        let html = '';
        notifs.forEach(n => {
            const avatar = n.sender_avatar ? (window.BASE_PATH || '/ProjectAurora/') + n.sender_avatar : null;
            const avatarHtml = avatar ? `<img src="${avatar}" class="notif-avatar">` : `<span class="material-symbols-rounded notif-default-icon">person</span>`;
            let actionsHtml = '';
            if (n.type === 'friend_request') {
                actionsHtml = `
                    <div class="notif-actions">
                        <button class="notif-btn accept" data-action="accept-req">Aceptar</button>
                        <button class="notif-btn decline" data-action="decline-req">Rechazar</button>
                    </div>
                `;
            }
            html += `
                <div class="notification-item" data-nid="${n.id}" data-sid="${n.related_id}">
                    <div class="notif-left"><div class="notif-img-container">${avatarHtml}</div></div>
                    <div class="notif-content">
                        <p class="notif-text">${n.message}</p>
                        ${actionsHtml}
                        <span class="notif-time">${new Date(n.created_at).toLocaleDateString()} ${new Date(n.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                </div>`;
        });
        container.innerHTML = `<div class="notif-list-wrapper">${html}</div>`;
    }
    renderEmptyState(container) {
        container.innerHTML = `<div class="notifications-empty"><span class="material-symbols-rounded empty-icon">notifications_off</span><p>No hay nada nuevo por el momento</p></div>`;
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
    async fetchApi(data) {
        const response = await fetch(API_SOCIAL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify(data)
        });
        return await response.json();
    }
}