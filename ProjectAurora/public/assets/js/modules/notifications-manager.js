// public/assets/js/notifications-manager.js

import { t } from '../core/i18n-manager.js';

const API_NOTIFICATIONS = (window.BASE_PATH || '/ProjectAurora/') + 'api/notifications_handler.php';

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

export class NotificationsManager {
    constructor() {
        this.loadNotifications();
        this.initSocketListener();
        this.initGlobalListeners(); 
    }

    initSocketListener() {
        document.addEventListener('socket-message', (e) => {
            const { type, payload } = e.detail;
            const alertMgr = window.alertManager;

            if (type === 'friend_request') {
                if (alertMgr) alertMgr.showAlert(payload.message, 'info');
                this.loadNotifications();
            }

            if (type === 'friend_accepted') {
                if (alertMgr) alertMgr.showAlert(payload.message, 'success');
                this.loadNotifications();
            }

            if (type === 'request_cancelled' || type === 'friend_removed') {
                this.loadNotifications();
            }
        });
        
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
        const dots = document.querySelectorAll('.unread-dot');
        dots.forEach(d => d.remove());
        this.updateBadge(0);

        await this.fetchApi({ action: 'mark_read_all' });
        this.loadNotifications(); 
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
                actionsHtml = `
                    <div class="notif-actions">
                        <button class="notif-btn accept" data-action="accept-req">${t('search.actions.accept')}</button>
                        <button class="notif-btn decline" data-action="decline-req">${t('search.actions.decline')}</button>
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
        container.innerHTML = `<div class="notifications-empty"><span class="material-symbols-rounded empty-icon">notifications_off</span><p>${t('header.no_notifications')}</p></div>`;
    }

    async fetchApi(data) {
        const response = await fetch(API_NOTIFICATIONS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify(data)
        });
        return await response.json();
    }
}