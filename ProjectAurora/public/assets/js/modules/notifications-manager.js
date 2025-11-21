// public/assets/js/notifications-manager.js

const API_SOCIAL = (window.BASE_PATH || '/ProjectAurora/') + 'api/social_handler.php';

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

export class NotificationsManager {
    constructor() {
        this.loadNotifications();
        this.initSocketListener();
        this.initGlobalListeners(); // Para marcar como leído
    }

    // --- LISTENERS ---

    initSocketListener() {
        document.addEventListener('socket-message', (e) => {
            const { type, payload } = e.detail;
            const alertMgr = window.alertManager;

            // Lógica de alertas y recarga de notificaciones
            if (type === 'friend_request') {
                if (alertMgr) alertMgr.showAlert(payload.message, 'info');
                this.loadNotifications();
            }

            if (type === 'friend_accepted') {
                if (alertMgr) alertMgr.showAlert(payload.message, 'success');
                this.loadNotifications();
            }

            // Si cancelan o eliminan, solo actualizamos la lista silenciosamente
            if (type === 'request_cancelled' || type === 'friend_removed') {
                this.loadNotifications();
            }
        });
        
        // Escuchar evento interno por si FriendsManager hace cambios y pide recargar
        document.addEventListener('reload-notifications', () => {
            this.loadNotifications();
        });
    }

    initGlobalListeners() {
        document.body.addEventListener('click', (e) => {
            if (e.target.closest('.notifications-action')) {
                this.markAllRead();
            }
        });
    }

    // --- API & RENDER ---

    async loadNotifications() {
        try {
            const res = await this.fetchApi({ action: 'get_notifications' });
            if (res.success) { 
                this.renderNotifications(res.notifications);
                this.updateBadge(res.unread_count); 
            }
        } catch (e) { 
            console.error('[Notifications] Error cargando:', e);
        }
    }

    async markAllRead() {
        // UI Optimista
        const dots = document.querySelectorAll('.unread-dot');
        dots.forEach(d => d.remove());
        this.updateBadge(0);

        await this.fetchApi({ action: 'mark_read_all' });
        this.loadNotifications(); // Recarga real para asegurar sincronización
    }

    updateBadge(count) {
        const btn = document.querySelector('[data-action="toggleModuleNotifications"]');
        if (!btn) return;

        let badge = btn.querySelector('.notification-badge');
        if (!badge) {
            badge = document.createElement('div');
            badge.className = 'notification-badge';
            btn.appendChild(badge);
        }

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    renderNotifications(notifs) {
        const container = document.querySelector('.menu-content-bottom'); 
        // Validamos que estemos en el contenedor correcto (el del módulo de notificaciones)
        if (!container || !container.closest('[data-module="moduleNotifications"]')) return;
        
        if (notifs.length === 0) { 
            this.renderEmptyState(container); 
            return; 
        }
        
        let html = '';
        notifs.forEach(n => {
            const avatar = n.sender_avatar ? (window.BASE_PATH || '/ProjectAurora/') + n.sender_avatar : null;
            const avatarHtml = avatar ? `<img src="${avatar}" class="notif-avatar">` : `<span class="material-symbols-rounded notif-default-icon">person</span>`;
            
            let actionsHtml = '';
            if (n.type === 'friend_request') {
                // Nota: Los botones tienen data-sid (sender id) para que FriendsManager los detecte
                actionsHtml = `
                    <div class="notif-actions">
                        <button class="notif-btn accept" data-action="accept-req">Aceptar</button>
                        <button class="notif-btn decline" data-action="decline-req">Rechazar</button>
                    </div>
                `;
            }

            const unreadDot = (parseInt(n.is_read) === 0) ? '<div class="unread-dot"></div>' : '';

            html += `
                <div class="notification-item" data-nid="${n.id}" data-sid="${n.related_id}">
                    <div class="notif-left"><div class="notif-img-container">${avatarHtml}</div></div>
                    <div class="notif-content">
                        <p class="notif-text">${n.message}</p>
                        ${actionsHtml}
                        <span class="notif-time">${new Date(n.created_at).toLocaleDateString()} ${new Date(n.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                    ${unreadDot}
                </div>`;
        });
        container.innerHTML = `<div class="notif-list-wrapper">${html}</div>`;
    }

    renderEmptyState(container) {
        container.innerHTML = `<div class="notifications-empty"><span class="material-symbols-rounded empty-icon">notifications_off</span><p>No hay nada nuevo por el momento</p></div>`;
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