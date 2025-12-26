/**
 * public/assets/js/modules/settings/devices-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { Dialog } from '../../core/dialog-manager.js'; // <--- IMPORTADO

const escapeHtml = (text) => {
    if (!text) return text;
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};

export const DevicesController = (function() {

    let isLoading = false;

    async function loadDevices() {
        const container = document.getElementById('devices-list-container');
        if (!container) return;

        if (isLoading) return;
        isLoading = true;

        try {
            const formData = new FormData();
            formData.append('action', 'get_sessions');

            const res = await ApiService.post('settings-handler.php', formData);

            if (res.success) {
                renderList(res.sessions);
            } else {
                container.innerHTML = `<div style="padding:20px; text-align:center;">${I18n.t('js.devices.load_error')}</div>`;
            }

        } catch (e) {
            console.error(e);
            container.innerHTML = `<div style="padding:20px; text-align:center;">${I18n.t('js.devices.connection_error')}</div>`;
        } finally {
            isLoading = false;
        }
    }

    function renderList(sessions) {
        const container = document.getElementById('devices-list-container');
        if (!container) return;

        if (sessions.length === 0) {
            container.innerHTML = `<div style="padding:24px; text-align:center; color:#666;">${I18n.t('js.devices.empty')}</div>`;
            return;
        }

        let html = '';
        sessions.forEach((s, index) => {
            let icon = 'devices';
            const plat = (s.platform || '').toLowerCase();
            if (plat.includes('win') || plat.includes('mac') || plat.includes('linux')) icon = 'computer';
            if (plat.includes('android') || plat.includes('iphone')) icon = 'smartphone';

            const safePlatform = escapeHtml(s.platform);
            const safeBrowser = escapeHtml(s.browser);
            const safeIp = escapeHtml(s.ip);
            const safeDate = escapeHtml(s.created_at);

            const activeBadge = s.is_current 
                ? `<span style="font-size:11px; background:#e8f5e9; color:#2e7d32; padding:2px 6px; border-radius:4px; font-weight:600; margin-left:8px;">${I18n.t('js.devices.current_device')}</span>` 
                : '';

            html += `
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">${icon}</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">
                                ${safePlatform} - ${safeBrowser} ${activeBadge}
                            </h2>
                            <p class="component-card__description">
                                IP: ${safeIp} <br>
                                <span style="font-size:12px; color:#999;">Iniciado: ${safeDate}</span>
                            </p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        ${!s.is_current ? 
                            `<button type="button" class="component-button btn-revoke-one" data-id="${escapeHtml(s.id)}">${I18n.t('js.devices.btn_revoke')}</button>` 
                            : ''
                        }
                    </div>
                </div>
                ${index < sessions.length - 1 ? '<hr class="component-divider">' : ''}
            `;
        });

        container.innerHTML = html;
        bindEvents();
    }

    function bindEvents() {
        document.querySelectorAll('.btn-revoke-one').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const id = e.target.dataset.id;
                if(!id) return;

                const originalText = e.target.innerText;
                e.target.innerText = '...';
                e.target.disabled = true;

                const formData = new FormData();
                formData.append('action', 'revoke_session');
                formData.append('token_id', id);

                try {
                    const res = await ApiService.post('settings-handler.php', formData);
                    if(res.success) {
                        Toast.show(I18n.t('js.devices.revoke_success'), 'success');
                        loadDevices(); 
                    } else {
                        Toast.show(res.message, 'error');
                        e.target.innerText = originalText;
                        e.target.disabled = false;
                    }
                } catch(err) {
                    Toast.show(I18n.t('js.devices.connection_error'), 'error');
                    e.target.innerText = originalText;
                    e.target.disabled = false;
                }
            });
        });
    }

    async function init() {
        const container = document.getElementById('devices-list-container');
        if (!container) return;

        loadDevices();
        
        const btnAll = document.getElementById('btn-revoke-all');
        if(btnAll) {
            const newBtnAll = btnAll.cloneNode(true);
            btnAll.parentNode.replaceChild(newBtnAll, btnAll);
            
            newBtnAll.addEventListener('click', async () => {
                
                // --- NUEVO SISTEMA DE DIÁLOGO ---
                const confirmed = await Dialog.confirm({
                    title: '¿Cerrar todas las sesiones?',
                    message: I18n.t('js.devices.confirm_revoke_all'),
                    type: 'danger',
                    confirmText: 'Cerrar todas',
                    cancelText: 'Cancelar'
                });
                
                if (!confirmed) return;
                // --------------------------------

                const formData = new FormData();
                formData.append('action', 'revoke_all_sessions');
                
                try {
                    const res = await ApiService.post('settings-handler.php', formData);
                    if(res.success) {
                        window.location.href = window.BASE_PATH + 'login';
                    } else {
                        Toast.show(res.message, 'error');
                    }
                } catch(err) {
                    Toast.show(I18n.t('js.devices.connection_error'), 'error');
                }
            });
        }
    }

    return { init };
})();