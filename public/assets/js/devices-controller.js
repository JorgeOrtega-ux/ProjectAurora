// public/assets/js/devices-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';
import { Toast } from './toast-controller.js';

export class DevicesController {
    constructor() {
        this.init();
    }

    init() {
        // Escuchar cuando el Router SPA carga la vista
        window.addEventListener('viewLoaded', (e) => {
            if (e.detail.url.includes('/settings/devices')) {
                this.loadDevices();
            }
        });

        // En caso de recargar la página directamente
        if (window.location.pathname.includes('/settings/devices')) {
            this.loadDevices();
        }

        // Delegación de eventos de clicks
        document.body.addEventListener('click', (e) => {
            // Revocar dispositivo individual
            const btnRevoke = e.target.closest('[data-action="revoke-device"]');
            if (btnRevoke) {
                e.preventDefault();
                this.revokeDevice(btnRevoke.dataset.session, btnRevoke);
            }

            // Revocar todas las demás
            const btnRevokeAll = e.target.closest('#btn-revoke-all');
            if (btnRevokeAll) {
                e.preventDefault();
                this.revokeAllDevices(btnRevokeAll);
            }
        });
    }

    async loadDevices() {
        const container = document.getElementById('devices-list-container');
        if (!container) return;

        try {
            const res = await ApiService.get(API_ROUTES.SETTINGS.GET_DEVICES);
            
            if (res.success && res.devices) {
                this.renderDevices(res.devices, container);
            } else {
                container.innerHTML = `<div style="padding: 24px; text-align: center; color: var(--color-error);">${res.message || 'Error al cargar dispositivos'}</div>`;
            }
        } catch (error) {
            console.error(error);
            container.innerHTML = `<div style="padding: 24px; text-align: center; color: var(--color-error);">Error de conexión al cargar dispositivos</div>`;
        }
    }

    renderDevices(devices, container) {
        if (devices.length === 0) {
            container.innerHTML = `<div style="padding: 24px; text-align: center; color: var(--text-secondary);">No hay sesiones activas.</div>`;
            return;
        }

        let html = '';

        devices.forEach((device, index) => {
            // Asignar icono según OS
            let icon = 'computer'; // Default Desktop
            let osLower = device.os.toLowerCase();
            if (osLower.includes('ios') || osLower.includes('android')) {
                icon = 'smartphone';
            }

            // Badge de sesión actual
            const currentBadge = device.is_current 
                ? `<span style="background-color: var(--color-success-bg); color: var(--color-success); font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 600; margin-left: 8px;">Este dispositivo</span>` 
                : '';

            // Botón de acción (Oculto si es el actual, o deshabilitado visualmente)
            const actionButton = device.is_current
                ? `<span style="color: var(--color-success); font-weight: 500; font-size: 14px; padding-right: 8px;">Sesión Activa</span>`
                : `<button type="button" class="component-button" data-action="revoke-device" data-session="${device.session_id}">Cerrar sesión</button>`;

            // Fecha formateada (Muy básico)
            const date = new Date(device.last_activity);
            const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            html += `
                <div class="component-group-item" id="device-item-${device.session_id}">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">${icon}</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title" style="display: flex; align-items: center;">
                                ${device.os} - ${device.browser} ${currentBadge}
                            </h2>
                            <p class="component-card__description">
                                IP: ${device.ip_address} <br>
                                Última actividad: ${dateStr}
                            </p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        ${actionButton}
                    </div>
                </div>
            `;

            // Agregamos el divisor excepto al último
            if (index < devices.length - 1) {
                html += `<hr class="component-divider">`;
            }
        });

        container.innerHTML = html;
    }

    async revokeDevice(sessionId, btn) {
        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button dark-spinner"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.REVOKE_DEVICE, { session_id: sessionId, csrf_token: csrfToken });
            
            if (res.success) {
                Toast.show('Sesión cerrada correctamente.', 'success');
                // Remover el elemento del DOM con una animación suave
                const item = document.getElementById(`device-item-${sessionId}`);
                if (item) {
                    item.style.opacity = '0';
                    setTimeout(() => {
                        // Recargamos la lista completa para limpiar posibles divisores sueltos
                        this.loadDevices(); 
                    }, 300);
                }
            } else {
                Toast.show(res.message, 'error');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch (error) {
            Toast.show('Error al intentar cerrar la sesión', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async revokeAllDevices(btn) {
        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button" style="border-top-color: #d32f2f;"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.REVOKE_ALL_DEVICES, { csrf_token: csrfToken });
            
            if (res.success) {
                Toast.show('Todas las demás sesiones han sido cerradas.', 'success');
                this.loadDevices(); // Recargar la lista (debería dejar solo la actual)
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error al intentar cerrar las sesiones', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }
}