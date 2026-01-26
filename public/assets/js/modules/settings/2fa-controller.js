/**
 * public/assets/js/modules/settings/2fa-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { DialogDefinitions } from '../../core/dialog-definitions.js';

// Atajo
const SettingsAPI = ApiService.Routes.Settings;

let qrCodeInstance = null;

export const TwoFactorController = {
    init: () => {
        console.log("TwoFactorController: Inicializado");

        const btnVerify = document.getElementById('btn-confirm-2fa');
        const btnDisable = document.getElementById('btn-disable-2fa');
        const inputCode = document.getElementById('input-2fa-verify');
        
        const qrContainer = document.getElementById('qr-container');
        const mainContainer = document.getElementById('step-qr-container');
        
        if (qrContainer && mainContainer && !mainContainer.classList.contains('disabled-interactive')) {
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

        // --- CAMBIO: Listener global para foco automático ---
        document.addEventListener('ui:accordion-opened', (e) => {
            // El ID '3' corresponde al paso de validación en el HTML
            if (e.detail && e.detail.id === '3') { 
                setTimeout(() => {
                    const input = document.getElementById('input-2fa-verify');
                    if(input) input.focus();
                }, 200);
            }
        });
        // ---------------------------------------------------

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
                formData.append('code', rawCode);

                try {
                    // USO DE API ROUTES
                    const res = await ApiService.post(SettingsAPI.Enable2FA, formData);

                    if (res.success) {
                        const stepQrContainer = document.getElementById('step-qr-container');
                        if(stepQrContainer) {
                            stepQrContainer.classList.remove('active');
                            stepQrContainer.classList.add('disabled'); 
                            stepQrContainer.style.display = 'none'; 
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

        // DESACTIVAR 2FA
        if (btnDisable) {
            btnDisable.addEventListener('click', async () => {
                const confirmed = await Dialog.confirm(DialogDefinitions.TwoFactor.DISABLE);
                if (!confirmed) return;

                const originalText = btnDisable.innerText;
                setLoading(btnDisable, true, I18n.t('js.2fa.disabling'));

                const formData = new FormData();
                // Acción automática

                try {
                    // USO DE API ROUTES
                    const res = await ApiService.post(SettingsAPI.Disable2FA, formData);
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

// Se eliminó la función initAccordion() de aquí.

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
        // USO DE API ROUTES
        const res = await ApiService.post(SettingsAPI.GetRecoveryStatus);
        if (res.success && countDisplay) {
            countDisplay.innerText = res.count;
        }
    } catch (e) { console.error(e); }

    if (btnShowRegen) {
        btnShowRegen.addEventListener('click', async () => {
            const confirmed = await Dialog.confirm(DialogDefinitions.TwoFactor.REGENERATE);
            if (!confirmed) return;

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
            formData.append('password', password);

            try {
                // USO DE API ROUTES
                const res = await ApiService.post(SettingsAPI.RegenerateRecoveryCodes, formData);
                
                if (res.success) {
                    Toast.show(I18n.t('js.2fa.codes_generated'), 'success');
                    areaRegen.classList.remove('active');
                    areaRegen.classList.add('disabled');
                    btnShowRegen.classList.remove('disabled');

                    if (listNewCodes && res.recovery_codes) {
                        listNewCodes.innerHTML = res.recovery_codes.map(c => `<span>${c}</span>`).join('');
                        areaNewCodes.classList.remove('disabled');
                        areaNewCodes.classList.add('active');
                    }
                    if(countDisplay) countDisplay.innerText = '10'; 
                    inputPass.value = '';
                    
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
    try {
        // USO DE API ROUTES (Sin formData)
        const res = await ApiService.post(SettingsAPI.Init2FA);

        if (res.success && res.otpauth_url) {
            container.innerHTML = '';
            
            if (window.QRCodeStyling) {
                qrCodeInstance = new QRCodeStyling({
                    width: 150,
                    height: 150,
                    type: "svg",
                    data: res.otpauth_url,
                    image: "",
                    dotsOptions: { color: "#000000", type: "rounded" },
                    cornersSquareOptions: { type: "extra-rounded", color: "#000000" },
                    cornersDotOptions: { type: "dot", color: "#000000" },
                    backgroundOptions: { color: "#ffffff" },
                    imageOptions: { crossOrigin: "anonymous", margin: 0 }
                });
                
                qrCodeInstance.append(container);
            }

            const manualInput = document.getElementById('manual-secret-input');
            if (manualInput && res.secret) {
                manualInput.value = res.secret;
            }

        } else {
            const boxQr = container.closest('.box-qr');
            if (boxQr) {
                boxQr.style.display = 'none';
            }

            const mainContainer = document.getElementById('step-qr-container');
            if(mainContainer) {
                mainContainer.classList.add('disabled-interactive');
            }
        }
    } catch (error) {
        console.error(error);
        const boxQr = container.closest('.box-qr');
        if (boxQr) boxQr.style.display = 'none';
        Toast.show(I18n.t('js.core.connection_error'), 'error');
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