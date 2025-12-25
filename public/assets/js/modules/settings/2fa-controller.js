/**
 * public/assets/js/modules/settings/2fa-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

export const TwoFactorController = {
    init: () => {
        console.log("TwoFactorController: Inicializado (Final Design - 150px)");

        const btnVerify = document.getElementById('btn-confirm-2fa');
        const btnDisable = document.getElementById('btn-disable-2fa');
        const inputCode = document.getElementById('input-2fa-verify');
        
        // 1. Auto-carga del QR al iniciar el módulo
        const qrContainer = document.getElementById('qr-container');
        if (qrContainer) {
            loadQrCode(qrContainer);
        }

        // 2. Manejo del botón COPIAR (Delegación de eventos)
        const contentArea = document.getElementById('2fa-content-area');
        if (contentArea) {
            contentArea.addEventListener('click', (e) => {
                const btnCopy = e.target.closest('[data-action="copy-input"]');
                if (btnCopy) {
                    const targetId = btnCopy.dataset.target;
                    const input = document.getElementById(targetId);
                    if (input && input.value) {
                        navigator.clipboard.writeText(input.value).then(() => {
                            Toast.show('Copiado al portapapeles', 'info');
                        }).catch(() => {
                            Toast.show('Error al copiar', 'error');
                        });
                    }
                }
            });
        }

        // Formato automático del input (000 000)
        if (inputCode) {
            inputCode.addEventListener('input', (e) => {
                let val = e.target.value.replace(/\D/g, '');
                if (val.length > 3) val = val.slice(0,3) + ' ' + val.slice(3,6);
                e.target.value = val;
            });
        }

        // Evento: Verificar
        if (btnVerify) {
            btnVerify.addEventListener('click', async () => {
                const rawCode = inputCode.value.replace(/\s/g, ''); // Quitar espacios para enviar
                
                if (rawCode.length < 6) {
                    Toast.show(I18n.t('js.2fa.fill_code'), 'warning');
                    inputCode.focus();
                    return;
                }

                const originalText = btnVerify.innerText;
                setLoading(btnVerify, true, I18n.t('js.2fa.verifying'));

                const formData = new FormData();
                formData.append('action', 'enable_2fa');
                formData.append('code', rawCode);

                try {
                    const res = await ApiService.post('settings-handler.php', formData);

                    if (res.success) {
                        // Ocultar paso QR
                        const stepQr = document.getElementById('step-qr');
                        if(stepQr) {
                            stepQr.classList.remove('active');
                            stepQr.classList.add('disabled'); 
                            stepQr.style.display = 'none'; 
                        }
                        
                        // Mostrar paso Success
                        const stepSuccess = document.getElementById('step-success');
                        if(stepSuccess) {
                            stepSuccess.classList.remove('disabled');
                            stepSuccess.classList.add('active');
                            stepSuccess.style.display = ''; 
                        }

                        // Llenar códigos de recuperación
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

        // Evento: Desactivar
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
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        Toast.show(res.message, 'error');
                        setLoading(btnDisable, false, originalText);
                    }
                } catch (error) {
                    Toast.show(I18n.t('js.2fa.error_connection'), 'error');
                    setLoading(btnDisable, false, originalText);
                }
            });
        }
    }
};

async function loadQrCode(container) {
    const formData = new FormData();
    formData.append('action', 'init_2fa');

    try {
        const res = await ApiService.post('settings-handler.php', formData);

        if (res.success && res.otpauth_url) {
            
            // 1. Limpiar contenedor previo
            container.innerHTML = '';

            // 2. Generar QR con estilos redondos y tamaño ajustado (150px)
            if (window.QRCodeStyling) {
                const qrCode = new QRCodeStyling({
                    width: 150,  // TAMAÑO 150px
                    height: 150, // TAMAÑO 150px
                    type: "svg",
                    data: res.otpauth_url,
                    image: "",
                    dotsOptions: { 
                        color: "#000000", 
                        type: "rounded" // Puntos redondos
                    },
                    cornersSquareOptions: {
                        type: "extra-rounded" // Marco del ojo redondo
                    },
                    cornersDotOptions: {
                        type: "dot" // Punto del ojo redondo
                    },
                    backgroundOptions: { 
                        color: "#ffffff" 
                    },
                    imageOptions: { 
                        crossOrigin: "anonymous", 
                        margin: 0 
                    }
                });
                qrCode.append(container);
            }

            // 3. Mostrar Secreto Manual
            const manualInput = document.getElementById('manual-secret-input');
            if (manualInput && res.secret) {
                manualInput.value = res.secret;
            }

            // Auto-focus
            const inputVerify = document.getElementById('input-2fa-verify');
            if(inputVerify) setTimeout(() => inputVerify.focus(), 500);

        } else {
            container.innerHTML = `<p style="color:var(--color-error); font-size:13px; text-align:center; padding:10px;">${res.message || 'Error desconocido'}</p>`;
        }
    } catch (error) {
        console.error(error);
        container.innerHTML = `<p style="color:var(--color-error); font-size:13px; text-align:center;">Error de conexión.</p>`;
    }
}

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