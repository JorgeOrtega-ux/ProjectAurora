/**
 * public/assets/js/modules/settings/2fa-controller.js
 * Controlador para la configuración de Autenticación de Dos Factores
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

export const TwoFactorController = {
    init: () => {
        console.log("TwoFactorController: Inicializado");

        // Elementos del DOM
        const btnStart = document.getElementById('btn-start-2fa');
        const btnVerify = document.getElementById('btn-confirm-2fa');
        const btnDisable = document.getElementById('btn-disable-2fa');
        const inputCode = document.getElementById('input-2fa-verify');

        // Evento: Comenzar configuración (Generar QR)
        if (btnStart) {
            btnStart.addEventListener('click', async () => {
                const originalText = btnStart.innerText;
                setLoading(btnStart, true, I18n.t('js.2fa.generating'));

                const formData = new FormData();
                formData.append('action', 'init_2fa');

                try {
                    const res = await ApiService.post('settings-handler.php', formData);

                    if (res.success) {
                        // Cambiar de vista Intro -> QR
                        document.getElementById('step-intro').classList.add('disabled');
                        document.getElementById('step-intro').classList.remove('active');
                        
                        document.getElementById('step-qr').classList.remove('disabled');
                        document.getElementById('step-qr').classList.add('active');

                        // Inyectar QR
                        const qrDiv = document.getElementById('qr-container');
                        if (qrDiv) {
                            qrDiv.innerHTML = `<img src="${res.qr_url}" alt="QR Code" style="width: 200px; height: 200px; border-radius: 4px;">`;
                        }
                    } else {
                        Toast.show(res.message, 'error');
                        setLoading(btnStart, false, originalText);
                    }
                } catch (error) {
                    console.error(error);
                    Toast.show(I18n.t('js.core.connection_error'), 'error');
                    setLoading(btnStart, false, originalText);
                }
            });
        }

        // Evento: Verificar código y activar
        if (btnVerify) {
            btnVerify.addEventListener('click', async () => {
                const code = inputCode.value.trim();
                
                if (code.length < 6) {
                    Toast.show(I18n.t('js.2fa.fill_code'), 'warning');
                    inputCode.focus();
                    return;
                }

                const originalText = btnVerify.innerText;
                setLoading(btnVerify, true, I18n.t('js.2fa.verifying'));

                const formData = new FormData();
                formData.append('action', 'enable_2fa');
                formData.append('code', code);

                try {
                    const res = await ApiService.post('settings-handler.php', formData);

                    if (res.success) {
                        // Cambiar vista QR -> Success
                        document.getElementById('step-qr').classList.add('disabled');
                        document.getElementById('step-qr').classList.remove('active');
                        
                        document.getElementById('step-success').classList.remove('disabled');
                        document.getElementById('step-success').classList.add('active');

                        // Mostrar códigos de recuperación
                        const list = document.getElementById('recovery-codes-list');
                        if (list && res.recovery_codes) {
                            list.innerHTML = res.recovery_codes.map(c => `<span>${c}</span>`).join('');
                        }
                        
                        Toast.show(I18n.t('api.2fa_enabled'), 'success');
                    } else {
                        Toast.show(res.message, 'error');
                        setLoading(btnVerify, false, originalText);
                        inputCode.value = '';
                        inputCode.focus();
                    }
                } catch (error) {
                    console.error(error);
                    Toast.show(I18n.t('js.2fa.error_verify'), 'error');
                    setLoading(btnVerify, false, originalText);
                }
            });
        }

        // Evento: Desactivar 2FA
        if (btnDisable) {
            btnDisable.addEventListener('click', async () => {
                if (!confirm(I18n.t('js.2fa.confirm_disable'))) return;

                const originalText = btnDisable.innerText;
                setLoading(btnDisable, true, I18n.t('js.2fa.disabling'));

                const formData = new FormData();
                formData.append('action', 'disable_2fa');

                try {
                    const res = await ApiService.post('settings-handler.php', formData);

                    if (res.success) {
                        Toast.show(I18n.t('api.2fa_disabled'), 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        Toast.show(res.message, 'error');
                        setLoading(btnDisable, false, originalText);
                    }
                } catch (error) {
                    console.error(error);
                    Toast.show(I18n.t('js.2fa.error_connection'), 'error');
                    setLoading(btnDisable, false, originalText);
                }
            });
        }
    }
};

// Helper simple para estado de carga en botones
function setLoading(btn, isLoading, text) {
    if (isLoading) {
        btn.disabled = true;
        btn.innerText = text;
        btn.style.opacity = '0.7';
    } else {
        btn.disabled = false;
        btn.innerText = text;
        btn.style.opacity = '1';
    }
}