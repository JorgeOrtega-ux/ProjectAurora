/**
 * public/assets/js/modules/settings/devices-controller.js
 * Gestiona la lista de sesiones activas y revocación.
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

export const DevicesController = (function() {

    async function loadDevices() {
        const container = document.getElementById('devices-list-container');
        if (!container) return;

        try {
            const formData = new FormData();
            formData.append('action', 'get_sessions');

            const res = await ApiService.post('settings-handler.php', formData);

            if (res.success) {
                renderList(res.sessions);
            } else {
                container.innerHTML = `<div style="padding:20px; text-align:center;">Error al cargar sesiones.</div>`;
            }

        } catch (e) {
            console.error(e);
            container.innerHTML = `<div style="padding:20px; text-align:center;">Error de conexión.</div>`;
        }
    }

    function renderList(sessions) {
        const container = document.getElementById('devices-list-container');
        if (!container) return;

        if (sessions.length === 0) {
            container.innerHTML = `<div style="padding:24px; text-align:center; color:#666;">No hay sesiones activas registradas.</div>`;
            return;
        }

        let html = '';
        sessions.forEach((s, index) => {
            // Icono según plataforma (básico)
            let icon = 'devices';
            const plat = s.platform.toLowerCase();
            if (plat.includes('win') || plat.includes('mac') || plat.includes('linux')) icon = 'computer';
            if (plat.includes('android') || plat.includes('iphone')) icon = 'smartphone';

            const activeBadge = s.is_current 
                ? `<span style="font-size:11px; background:#e8f5e9; color:#2e7d32; padding:2px 6px; border-radius:4px; font-weight:600; margin-left:8px;">Este dispositivo</span>` 
                : '';

            html += `
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">${icon}</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">
                                ${s.platform} - ${s.browser} ${activeBadge}
                            </h2>
                            <p class="component-card__description">
                                IP: ${s.ip} <br>
                                <span style="font-size:12px; color:#999;">Iniciado: ${s.created_at}</span>
                            </p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        ${!s.is_current ? 
                            `<button type="button" class="component-button btn-revoke-one" data-id="${s.id}">Cerrar sesión</button>` 
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
        // Botones individuales
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
                        Toast.show('Sesión cerrada correctamente', 'success');
                        loadDevices(); // Recargar lista
                    } else {
                        Toast.show(res.message, 'error');
                        e.target.innerText = originalText;
                        e.target.disabled = false;
                    }
                } catch(err) {
                    Toast.show('Error de conexión', 'error');
                }
            });
        });
    }

    async function init() {
        // Detectar si estamos en la vista
        if (document.getElementById('devices-list-container')) {
            loadDevices();
            
            const btnAll = document.getElementById('btn-revoke-all');
            if(btnAll) {
                btnAll.addEventListener('click', async () => {
                    if(!confirm('¿Seguro que quieres cerrar sesión en TODOS los dispositivos? Deberás iniciar sesión de nuevo.')) return;

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
                        Toast.show('Error de conexión', 'error');
                    }
                });
            }
        }
    }

    return { init };
})();