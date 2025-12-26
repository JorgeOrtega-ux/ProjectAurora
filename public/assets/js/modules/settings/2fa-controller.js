/**
 * public/assets/js/modules/settings/2fa-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { Dialog } from '../../core/dialog-manager.js'; // <--- IMPORTADO

export const TwoFactorController = {
    init: () => {
        console.log("TwoFactorController: Inicializado");

        const btnVerify = document.getElementById('btn-confirm-2fa');
        const btnDisable = document.getElementById('btn-disable-2fa');
        const inputCode = document.getElementById('input-2fa-verify');
        
        const qrContainer = document.getElementById('qr-container');
        if (qrContainer) {
            loadQrCode(qrContainer);
        }

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

        if (inputCode) {
            inputCode.addEventListener('input', (e) => {
                let val = e.target.value.replace(/\D/g, '');
                if (val.length > 3) val = val.slice(0,3) + ' ' + val.slice(3,6);
                e.target.value = val;
            });
        }

        // Verificar 2FA
        if (btnVerify) {
            btnVerify.addEventListener('click', async () => {
                const rawCode = inputCode.value.replace(/\s/g, ''); 
                
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
                        const stepQr = document.getElementById('step-qr');
                        if(stepQr) {
                            stepQr.classList.remove('active');
                            stepQr.classList.add('disabled'); 
                            stepQr.style.display = 'none'; 
                        }
                        
                        const stepSuccess = document.getElementById('step-success');
                        if(stepSuccess) {
                            stepSuccess.classList.remove('disabled');
                            stepSuccess.classList.add('active');
                            stepSuccess.style.display = ''; 
                        }

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

        // DESACTIVAR 2FA (MODIFICADO CON DIALOG)
        if (btnDisable) {
            btnDisable.addEventListener('click', async () => {
                
                // --- NUEVO SISTEMA DE DIÁLOGO ---
                const confirmed = await Dialog.confirm({
                    title: '¿Desactivar 2FA?',
                    message: I18n.t('js.2fa.confirm_disable'),
                    type: 'danger',
                    confirmText: 'Desactivar',
                    cancelText: 'Cancelar'
                });

                if (!confirmed) return;
                // --------------------------------

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

        if (document.getElementById('recovery-count-display')) {
            initRecoveryLogic();
        }
    }
};

async function initRecoveryLogic() {
    const countDisplay = document.getElementById('recovery-count-display');
    const btnShowRegen = document.getElementById('btn-show-regen-area');
    const areaRegen = document.getElementById('regen-confirmation-area');
    const btnCancelRegen = document.getElementById('btn-cancel-regen');
    const btnSubmitRegen = document.getElementById('btn-submit-regen');
    const inputPass = document.getElementById('regen-password-input');
    const areaNewCodes = document.getElementById('new-codes-area');
    const listNewCodes = document.getElementById('new-recovery-codes-list');

    try {
        const formData = new FormData();
        formData.append('action', 'get_recovery_status');
        const res = await ApiService.post('settings-handler.php', formData);
        if (res.success && countDisplay) {
            countDisplay.innerText = res.count;
        }
    } catch (e) { console.error(e); }

    if (btnShowRegen) {
        btnShowRegen.addEventListener('click', () => {
            areaRegen.classList.remove('disabled');
            areaRegen.classList.add('active');
            btnShowRegen.classList.add('disabled'); 
            if(inputPass) setTimeout(() => inputPass.focus(), 100);
        });
    }

    if (btnCancelRegen) {
        btnCancelRegen.addEventListener('click', () => {
            areaRegen.classList.remove('active');
            areaRegen.classList.add('disabled');
            btnShowRegen.classList.remove('disabled');
            if(inputPass) inputPass.value = '';
        });
    }

    if (btnSubmitRegen) {
        btnSubmitRegen.addEventListener('click', async () => {
            const password = inputPass.value;
            if(!password) {
                Toast.show(I18n.t('js.auth.fill_all'), 'warning');
                return;
            }

            const originalText = btnSubmitRegen.innerText;
            setLoading(btnSubmitRegen, true, I18n.t('js.2fa.generating'));
            inputPass.disabled = true;

            const formData = new FormData();
            formData.append('action', 'regenerate_recovery_codes');
            formData.append('password', password);

            try {
                const res = await ApiService.post('settings-handler.php', formData);
                
                if (res.success) {
                    Toast.show(I18n.t('js.2fa.codes_generated'), 'success');
                    areaRegen.classList.remove('active');
                    areaRegen.classList.add('disabled');

                    if (listNewCodes && res.recovery_codes) {
                        listNewCodes.innerHTML = res.recovery_codes.map(c => `<span>${c}</span>`).join('');
                        areaNewCodes.classList.remove('disabled');
                        areaNewCodes.classList.add('active');
                    }
                    if(countDisplay) countDisplay.innerText = '10'; 
                    
                } else {
                    Toast.show(res.message, 'error');
                    setLoading(btnSubmitRegen, false, originalText);
                    inputPass.disabled = false;
                    inputPass.focus();
                }

            } catch (error) {
                console.error(error);
                Toast.show(I18n.t('js.core.connection_error'), 'error');
                setLoading(btnSubmitRegen, false, originalText);
                inputPass.disabled = false;
            }
        });
    }
}

async function loadQrCode(container) {
    const formData = new FormData();
    formData.append('action', 'init_2fa');

    try {
        const res = await ApiService.post('settings-handler.php', formData);

        if (res.success && res.otpauth_url) {
            container.innerHTML = '';
            if (window.QRCodeStyling) {
                const qrCode = new QRCodeStyling({
                    width: 150,
                    height: 150,
                    type: "svg",
                    data: res.otpauth_url,
                    image: "",
                    dotsOptions: { color: "#000000", type: "rounded" },
                    cornersSquareOptions: { type: "extra-rounded" },
                    cornersDotOptions: { type: "dot" },
                    backgroundOptions: { color: "#ffffff" },
                    imageOptions: { crossOrigin: "anonymous", margin: 0 }
                });
                qrCode.append(container);
            }

            const manualInput = document.getElementById('manual-secret-input');
            if (manualInput && res.secret) {
                manualInput.value = res.secret;
            }
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