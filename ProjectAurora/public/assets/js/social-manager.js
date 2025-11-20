// public/assets/js/social-manager.js

const API_SOCIAL = (window.BASE_PATH || '/ProjectAurora/') + 'api/social_handler.php';
const WS_URL = 'ws://localhost:8080'; // URL de tu script Python

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

export class SocialManager {
    constructor() {
        this.socket = null;
        this.initListeners();
        this.initWebSocket(); // Iniciar socket
        this.loadNotifications(); // Carga inicial HTTP
    }

    // --- WEBSOCKET CONNECT ---
    initWebSocket() {
        if (!window.USER_ID) return; // No conectar si no hay login

        this.socket = new WebSocket(WS_URL);

        this.socket.onopen = () => {
            console.log('[WS] Conectado.');
            // Identificarse
            this.socket.send(JSON.stringify({
                type: 'auth',
                user_id: window.USER_ID
            }));
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
            console.log('[WS] Desconectado. Reintentando en 5s...');
            setTimeout(() => this.initWebSocket(), 5000);
        };
    }

    // --- MANEJO DE MENSAJES EN VIVO ---
    handleSocketMessage(data) {
        const { type, payload } = data;
        const alertMgr = window.alertManager;

        // 1. Solicitud de Amistad Recibida
        if (type === 'friend_request') {
            if (alertMgr) alertMgr.showAlert(payload.message, 'info');
            this.loadNotifications(); // Refrescar la lista de notificaciones

            // ACTUALIZAR BOTÓN EN TIEMPO REAL (Si estoy viendo al usuario en búsqueda)
            const btn = document.querySelector(`.btn-add-friend[data-uid="${payload.sender_id}"]`);
            if (btn) {
                btn.textContent = 'Solicitud recibida';
                btn.classList.add('disabled');
                btn.disabled = true;
            }
        }

        // 2. Solicitud Aceptada
        if (type === 'friend_accepted') {
            if (alertMgr) alertMgr.showAlert(payload.message, 'success');
            
            // Actualizar botón si estoy en la lista
            const btn = document.querySelector(`.btn-add-friend[data-uid="${payload.accepter_id}"]`);
            if (btn) {
                btn.textContent = 'Eliminar amigo';
                btn.className = 'btn-add-friend btn-remove-friend';
                btn.disabled = false;
            }
        }

        // 3. Solicitud Cancelada (Sender se arrepintió)
        if (type === 'request_cancelled') {
            // Si yo tenía un botón "Solicitud recibida" o "Aceptar", volver a "Agregar"
            const btn = document.querySelector(`.btn-add-friend[data-uid="${payload.sender_id}"]`);
            if (btn) {
                btn.textContent = 'Agregar a amigos';
                btn.className = 'btn-add-friend';
                btn.disabled = false;
                this.loadNotifications(); // Quitar la notificación de la lista
            }
        }

        // 4. Amigo Eliminado
        if (type === 'friend_removed') {
            const btn = document.querySelector(`.btn-add-friend[data-uid="${payload.sender_id}"]`);
            if (btn) {
                btn.textContent = 'Agregar a amigos';
                btn.className = 'btn-add-friend';
                btn.disabled = false;
            }
        }
    }

    // ... (EL RESTO DEL CÓDIGO SIGUE IGUAL QUE TU ORIGINAL) ...
    
    initListeners() {
        document.body.addEventListener('click', async (e) => {
            const target = e.target;
            
            // --- 1. ENVIAR SOLICITUD ---
            if (target.closest('.btn-add-friend') && !target.closest('.btn-remove-friend') && !target.closest('.btn-cancel-request') && !target.closest('.disabled')) {
                const btn = target.closest('.btn-add-friend');
                e.preventDefault();
                const uid = btn.dataset.uid;
                await this.sendFriendRequest(uid, btn);
                return; 
            }

            // --- 2. CANCELAR SOLICITUD ---
            if (target.closest('.btn-cancel-request')) {
                const btn = target.closest('.btn-cancel-request');
                e.preventDefault();
                const uid = btn.dataset.uid;
                await this.cancelRequest(uid, btn);
                return; 
            }

            // --- 3. ELIMINAR AMIGO ---
            if (target.closest('.btn-remove-friend')) {
                const btn = target.closest('.btn-remove-friend');
                e.preventDefault();
                const uid = btn.dataset.uid;
                if(confirm('¿Seguro que quieres eliminar a este amigo?')) {
                    await this.removeFriend(uid, btn);
                }
                return;
            }

            // --- 4. ACCIONES DE NOTIFICACIÓN ---
            if (target.closest('[data-action="accept-req"]')) {
                e.preventDefault();
                await this.respondRequest('accept_request', target.closest('[data-action="accept-req"]'));
                return;
            }
            if (target.closest('[data-action="decline-req"]')) {
                e.preventDefault();
                await this.respondRequest('decline_request', target.closest('[data-action="decline-req"]'));
                return;
            }
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
                btn.innerHTML = 'Cancelar solicitud';
                btn.className = 'btn-add-friend btn-cancel-request'; 
                btn.disabled = false; 
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
                this.setLoading(btn, false, 'Agregar a amigos');
            }
        } catch (e) {
            this.setLoading(btn, false, 'Agregar a amigos');
        }
    }

    async cancelRequest(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'cancel_request', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Solicitud cancelada', 'info');
                btn.innerHTML = 'Agregar a amigos';
                btn.className = 'btn-add-friend';
                btn.disabled = false;
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
                this.setLoading(btn, false, 'Cancelar solicitud');
            }
        } catch (e) {
            this.setLoading(btn, false, 'Cancelar solicitud');
        }
    }

    async removeFriend(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'remove_friend', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Amigo eliminado', 'info');
                btn.innerHTML = 'Agregar a amigos';
                btn.className = 'btn-add-friend'; 
                btn.disabled = false;
            } else {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
                this.setLoading(btn, false, 'Eliminar amigo');
            }
        } catch (e) {
            this.setLoading(btn, false, 'Eliminar amigo');
        }
    }

    async respondRequest(actionType, btn) {
        const notifItem = btn.closest('.notification-item');
        const notifId = notifItem.dataset.nid;
        const senderId = notifItem.dataset.sid;

        notifItem.style.opacity = '0.5';

        try {
            const res = await this.fetchApi({ 
                action: actionType, 
                notification_id: notifId,
                sender_id: senderId 
            });

            if (res.success) {
                notifItem.remove();
                if (actionType === 'accept_request') {
                    if (window.alertManager) window.alertManager.showAlert('¡Ahora son amigos!', 'success');
                    const searchBtn = document.querySelector(`.btn-add-friend[data-uid="${senderId}"]`);
                    if (searchBtn) {
                        searchBtn.textContent = 'Eliminar amigo';
                        searchBtn.className = 'btn-add-friend btn-remove-friend';
                        searchBtn.disabled = false;
                    }
                } else {
                    if (window.alertManager) window.alertManager.showAlert('Solicitud rechazada', 'info');
                    // Si rechazamos, el botón en UI de búsqueda vuelve a estado normal
                    const searchBtn = document.querySelector(`.btn-add-friend[data-uid="${senderId}"]`);
                    if (searchBtn) {
                        searchBtn.textContent = 'Agregar a amigos';
                        searchBtn.className = 'btn-add-friend';
                        searchBtn.disabled = false;
                    }
                }
            }
        } catch (e) {
            notifItem.style.opacity = '1';
        }
    }

    async loadNotifications() {
        try {
            const res = await this.fetchApi({ action: 'get_notifications' });
            if (res.success) {
                this.renderNotifications(res.notifications);
            }
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

        if (notifs.length === 0) {
            this.renderEmptyState(container);
            return;
        }

        let html = '';
        notifs.forEach(n => {
            const avatar = n.sender_avatar ? (window.BASE_PATH || '/ProjectAurora/') + n.sender_avatar : null;
            const avatarHtml = avatar 
                ? `<img src="${avatar}" class="notif-avatar">` 
                : `<span class="material-symbols-rounded notif-default-icon">person</span>`;
            
            let actionsHtml = '';
            if (n.type === 'friend_request') {
                actionsHtml = `
                    <div class="notif-actions">
                        <button class="notif-btn accept" data-action="accept-req">Aceptar</button>
                        <button class="notif-btn decline" data-action="decline-req">Rechazar</button>
                    </div>
                `;
            }

            // Mensaje HTML seguro
            html += `
                <div class="notification-item" data-nid="${n.id}" data-sid="${n.related_id}">
                    <div class="notif-left">
                        <div class="notif-img-container">${avatarHtml}</div>
                    </div>
                    <div class="notif-content">
                        <p class="notif-text">${n.message}</p>
                        ${actionsHtml}
                        <span class="notif-time">${new Date(n.created_at).toLocaleDateString()} ${new Date(n.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                </div>
            `;
        });
        container.innerHTML = `<div class="notif-list-wrapper">${html}</div>`;
    }

    renderEmptyState(container) {
        container.innerHTML = `
            <div class="notifications-empty">
                <span class="material-symbols-rounded empty-icon">notifications_off</span>
                <p>No hay nada nuevo por el momento</p>
            </div>
        `;
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