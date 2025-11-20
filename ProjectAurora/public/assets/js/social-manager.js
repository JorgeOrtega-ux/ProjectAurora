// public/assets/js/social-manager.js

const API_SOCIAL = (window.BASE_PATH || '/ProjectAurora/') + 'api/social_handler.php';

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

export class SocialManager {
    constructor() {
        this.initListeners();
        this.loadNotifications(); 
        setInterval(() => this.loadNotifications(), 30000);
    }

    initListeners() {
        document.body.addEventListener('click', async (e) => {
            const target = e.target;
            
            // --- 1. ENVIAR SOLICITUD ---
            // Verificamos que sea el botón de agregar Y que no sea de eliminar ni cancelar
            if (target.closest('.btn-add-friend') && !target.closest('.btn-remove-friend') && !target.closest('.btn-cancel-request')) {
                const btn = target.closest('.btn-add-friend');
                if (btn.disabled) return;
                e.preventDefault();
                const uid = btn.dataset.uid;
                await this.sendFriendRequest(uid, btn);
                return; // <--- IMPORTANTE: Detiene la ejecución aquí
            }

            // --- 2. CANCELAR SOLICITUD (YO LA ENVIÉ) ---
            if (target.closest('.btn-cancel-request')) {
                const btn = target.closest('.btn-cancel-request');
                e.preventDefault();
                const uid = btn.dataset.uid;
                await this.cancelRequest(uid, btn);
                return; // <--- IMPORTANTE: Detiene la ejecución aquí
            }

            // --- 3. ELIMINAR AMIGO (YA SOMOS AMIGOS) ---
            if (target.closest('.btn-remove-friend')) {
                const btn = target.closest('.btn-remove-friend');
                e.preventDefault();
                const uid = btn.dataset.uid;
                if(confirm('¿Seguro que quieres eliminar a este amigo?')) {
                    await this.removeFriend(uid, btn);
                }
                return; // <--- IMPORTANTE
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

    // 1. ENVIAR
    async sendFriendRequest(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'send_request', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Solicitud enviada', 'success');
                
                // CAMBIO DE ESTADO: Ahora se convierte en botón de cancelar
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

    // 2. CANCELAR
    async cancelRequest(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'cancel_request', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Solicitud cancelada', 'info');
                
                // RESETEAR ESTADO: Vuelve a ser botón de agregar
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

    // 3. ELIMINAR AMIGO
    async removeFriend(targetId, btn) {
        this.setLoading(btn, true);
        try {
            const res = await this.fetchApi({ action: 'remove_friend', target_id: targetId });
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert('Amigo eliminado', 'info');
                
                // RESETEAR ESTADO
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

    // 4. RESPONDER (ACEPTAR/RECHAZAR DESDE NOTIF)
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
                // Lógica visual: Actualizar el botón si está visible en la pantalla de búsqueda
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

            html += `
                <div class="notification-item" data-nid="${n.id}" data-sid="${n.related_id}">
                    <div class="notif-left">
                        <div class="notif-img-container">${avatarHtml}</div>
                    </div>
                    <div class="notif-content">
                        <p class="notif-text">${n.message}</p>
                        ${actionsHtml}
                        <span class="notif-time">${new Date(n.created_at).toLocaleDateString()}</span>
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