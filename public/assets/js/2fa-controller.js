// public/assets/js/2fa-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';
import { Toast } from './toast-controller.js';

export class TwoFactorController {
    constructor() {
        this.init();
    }

    init() {
        // Escuchar cuando el Router SPA carga la vista
        window.addEventListener('viewLoaded', (e) => {
            if (e.detail.url.includes('/settings/2fa-setup')) {
                this.load2FAData();
            }
        });

        // En caso de que se recargue la página directamente en la vista
        if (window.location.pathname.includes('/settings/2fa-setup')) {
            this.load2FAData();
        }

        document.body.addEventListener('click', (e) => {
            // Manejo de los Acordeones (Corregido el selector que empieza con número)
            const accordionHeader = e.target.closest('.component-accordion-header');
            if (accordionHeader && accordionHeader.closest('[id="2fa-content-area"]')) {
                const item = accordionHeader.closest('.component-accordion-item');
                if (item) item.classList.toggle('active');
            }

            // Botón Copiar al portapapeles
            const copyBtn = e.target.closest('[data-action="copy-input"]');
            if (copyBtn && document.getElementById(copyBtn.dataset.target)) {
                const input = document.getElementById(copyBtn.dataset.target);
                navigator.clipboard.writeText(input.value).then(() => {
                    Toast.show('Clave copiada al portapapeles', 'success');
                });
            }

            // Activar 2FA
            if (e.target.closest('#btn-confirm-2fa')) {
                this.enable2FA(e.target.closest('#btn-confirm-2fa'));
            }

            // Menú de Regenerar Códigos
            if (e.target.closest('#btn-show-regen-area')) {
                document.getElementById('regen-confirmation-area').classList.remove('disabled');
                document.getElementById('new-codes-area').classList.add('disabled');
            }
            if (e.target.closest('#btn-cancel-regen')) {
                document.getElementById('regen-confirmation-area').classList.add('disabled');
                document.getElementById('regen-password-input').value = '';
            }
            if (e.target.closest('#btn-submit-regen')) {
                this.regenerateCodes(e.target.closest('#btn-submit-regen'));
            }

            // Desactivar 2FA
            if (e.target.closest('#btn-disable-2fa')) {
                window.dialogController.open('dialog-confirm-password-2fa');
            }
            if (e.target.closest('#btn-confirm-pass-action-2fa')) {
                this.disable2FA(e.target.closest('#btn-confirm-pass-action-2fa'));
            }
        });
    }

    async load2FAData() {
        try {
            const res = await ApiService.get(API_ROUTES.SETTINGS.INIT_2FA);
            if (res.success) {
                if (res.enabled) {
                    const countEl = document.getElementById('recovery-count-display');
                    if (countEl) countEl.textContent = res.codes_count;
                } else {
                    // Cargar QR y Clave (Se carga directo la URI desde PHP)
                    const qrContainer = document.getElementById('qr-container');
                    if (qrContainer) qrContainer.innerHTML = `<img src="${res.qr}" alt="QR Code" style="width: 100%; height: 100%; border-radius: 4px;">`;
                    
                    const secretInput = document.getElementById('manual-secret-input');
                    if (secretInput) secretInput.value = res.secret;
                }
            }
        } catch (e) {
            console.error("Error cargando 2FA", e);
        }
    }

    async enable2FA(btn) {
        const codeInput = document.getElementById('input-2fa-verify');
        const code = codeInput ? codeInput.value : '';
        if (!code) return Toast.show('Ingresa el código temporal', 'error');

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const origText = btn.textContent;
        btn.innerHTML = '<div class="component-spinner-button"></div>';
        btn.disabled = true;

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.ENABLE_2FA, { code, csrf_token: csrfToken });
            if (res.success) {
                document.getElementById('step-qr-container').classList.add('disabled');
                document.getElementById('step-success').classList.remove('disabled');
                
                const grid = document.getElementById('recovery-codes-list');
                if (grid) grid.innerHTML = res.recovery_codes.map(c => `<div class="component-chip">${c}</div>`).join('');
                Toast.show('2FA activado de forma segura', 'success');
            } else {
                Toast.show(res.message || 'Código incorrecto', 'error');
                btn.innerHTML = origText;
                btn.disabled = false;
            }
        } catch (e) {
            Toast.show('Error al comunicar con el servidor', 'error');
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    }

    async regenerateCodes(btn) {
        const passwordInput = document.getElementById('regen-password-input');
        const password = passwordInput ? passwordInput.value : '';
        if (!password) return Toast.show('Ingresa tu contraseña para continuar', 'error');

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const origText = btn.textContent;
        btn.innerHTML = '<div class="component-spinner-button"></div>';
        btn.disabled = true;

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.REGEN_2FA, { password, csrf_token: csrfToken });
            if (res.success) {
                document.getElementById('regen-confirmation-area').classList.add('disabled');
                passwordInput.value = '';
                
                document.getElementById('new-codes-area').classList.remove('disabled');
                const grid = document.getElementById('new-recovery-codes-list');
                if (grid) grid.innerHTML = res.recovery_codes.map(c => `<div class="component-chip">${c}</div>`).join('');
                
                const countEl = document.getElementById('recovery-count-display');
                if (countEl) countEl.textContent = '10';
                
                Toast.show('Nuevos códigos generados', 'success');
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (e) {
            Toast.show('Error al generar los códigos', 'error');
        } finally {
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    }

    async disable2FA(btn) {
        const passwordInput = document.getElementById('input-confirm-password-2fa');
        const password = passwordInput ? passwordInput.value : '';
        if (!password) return Toast.show('Se requiere la contraseña actual', 'error');

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const origText = btn.textContent;
        btn.innerHTML = '<div class="component-spinner-button"></div>';
        btn.disabled = true;

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.DISABLE_2FA, { password, csrf_token: csrfToken });
            if (res.success) {
                window.dialogController.close('dialog-confirm-password-2fa');
                Toast.show('2FA desactivado correctamente', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                Toast.show(res.message, 'error');
                btn.innerHTML = origText;
                btn.disabled = false;
            }
        } catch (e) {
            Toast.show('Error al desactivar el servicio', 'error');
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    }
}