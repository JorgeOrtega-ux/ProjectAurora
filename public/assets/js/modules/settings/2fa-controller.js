/**
 * public/assets/js/modules/settings/2fa-controller.js
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';
import { DialogDefinitions } from '../../core/components/dialog-definitions.js';

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

        // Listener global para el área de contenido (Chips y Inputs de copia)
        const contentArea = document.getElementById('2fa-content-area');
        if (contentArea) {
            contentArea.addEventListener('click', (e) => {
                // [NUEVO] Acción para copiar CHIPS (Códigos de recuperación)
                const chip = e.target.closest('[data-action="copy-code"]');
                if (chip) {
                    const code = chip.dataset.value;
                    if (code) {
                        navigator.clipboard.writeText(code).then(() => {
                            ToastManager.show('Código copiado', 'info');
                        }).catch(() => ToastManager.show('Error al copiar', 'error'));
                    }
                    return;
                }

                // Acción legacy para copiar Inputs (Secret Key)
                const btnCopy = e.target.closest('[data-action="copy-input"]');
                if (btnCopy) {
                    const targetId = btnCopy.dataset.target;
                    const input = document.getElementById(targetId);
                    if (input && input.value) {
                        navigator.clipboard.writeText(input.value).then(() => {
                            ToastManager.show('Copiado al portapapeles', 'info');
                        }).catch(() => ToastManager.show('Error al copiar', 'error'));
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

        // Listener global para foco automático
        document.addEventListener('ui:accordion-opened', (e) => {
            if (e.detail && e.detail.id === '3') { 
                setTimeout(() => {
                    const input = document.getElementById('input-2fa-verify');
                    if(input) input.focus();
                }, 200);
            }
        });

        // Verificar 2FA
        if (btnVerify) {
            btnVerify.addEventListener('click', async () => {
                const rawCode = inputCode.value.replace(/\s/g, ''); 
                
                if (rawCode.length < 6) {
                    ToastManager.show(I18nManager.t('js.2fa.fill_code'), 'warning');
                    inputCode.focus();
                    return;
                }

                const originalText = btnVerify.innerText;
                setLoading(btnVerify, true, I18nManager.t('js.2fa.verifying'));

                const formData = new FormData();
                formData.append('code', rawCode);

                try {
                    const res = await ApiService.post(ApiService.Routes.Settings.Enable2FA, formData);

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

                        // [MODIFICADO] Generación de Chips para los códigos
                        const list = document.getElementById('recovery-codes-list');
                        if (list && res.recovery_codes) {
                            list.innerHTML = renderChips(res.recovery_codes);
                        }
                        
                        ToastManager.show(I18nManager.t('api.2fa_enabled'), 'success');
                    } else {
                        ToastManager.show(res.message, 'error');
                        setLoading(btnVerify, false, originalText);
                        inputCode.value = '';
                        inputCode.focus();
                    }
                } catch (error) {
                    console.error(error);
                    ToastManager.show(I18nManager.t('js.2fa.error_verify'), 'error');
                    setLoading(btnVerify, false, originalText);
                }
            });
        }

        // DESACTIVAR 2FA
        if (btnDisable) {
            btnDisable.addEventListener('click', async () => {
                const confirmed = await DialogManager.confirm(DialogDefinitions.TwoFactor.DISABLE);
                if (!confirmed) return;

                const originalText = btnDisable.innerText;
                setLoading(btnDisable, true, I18nManager.t('js.2fa.disabling'));

                const formData = new FormData();

                try {
                    const res = await ApiService.post(ApiService.Routes.Settings.Disable2FA, formData);
                    if (res.success) {
                        ToastManager.show(I18nManager.t('api.2fa_disabled'), 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        ToastManager.show(res.message, 'error');
                        setLoading(btnDisable, false, originalText);
                    }
                } catch (error) {
                    ToastManager.show(I18nManager.t('js.2fa.error_connection'), 'error');
                    setLoading(btnDisable, false, originalText);
                }
            });
        }

        if (document.getElementById('recovery-count-display')) {
            initRecoveryLogic();
        }
    }
};

// [NUEVO] Helper para renderizar los chips de códigos
function renderChips(codes) {
    return codes.map(code => `
        <div class="component-chip" data-action="copy-code" data-value="${code}">
            <span class="chip-text">${code}</span>
            <span class="material-symbols-rounded chip-icon">content_copy</span>
        </div>
    `).join('');
}

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
        const res = await ApiService.post(ApiService.Routes.Settings.GetRecoveryStatus);
        if (res.success && countDisplay) {
            countDisplay.innerText = res.count;
        }
    } catch (e) { console.error(e); }

    if (btnShowRegen) {
        btnShowRegen.addEventListener('click', async () => {
            const confirmed = await DialogManager.confirm(DialogDefinitions.TwoFactor.REGENERATE);
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
                ToastManager.show(I18nManager.t('js.auth.fill_all'), 'warning');
                return;
            }

            const originalText = btnSubmitRegen.innerText;
            setLoading(btnSubmitRegen, true, I18nManager.t('js.2fa.generating'));
            inputPass.disabled = true;

            const formData = new FormData();
            formData.append('password', password);

            try {
                const res = await ApiService.post(ApiService.Routes.Settings.RegenerateRecoveryCodes, formData);
                
                if (res.success) {
                    ToastManager.show(I18nManager.t('js.2fa.codes_generated'), 'success');
                    areaRegen.classList.remove('active');
                    areaRegen.classList.add('disabled');
                    btnShowRegen.classList.remove('disabled');

                    if (listNewCodes && res.recovery_codes) {
                        // [MODIFICADO] Usar renderChips
                        listNewCodes.innerHTML = renderChips(res.recovery_codes);
                        areaNewCodes.classList.remove('disabled');
                        areaNewCodes.classList.add('active');
                    }
                    if(countDisplay) countDisplay.innerText = '10'; 
                    inputPass.value = '';
                    
                } else {
                    ToastManager.show(res.message, 'error');
                    setLoading(btnSubmitRegen, false, originalText);
                    inputPass.disabled = false;
                    inputPass.focus();
                }

            } catch (error) {
                console.error(error);
                ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
                setLoading(btnSubmitRegen, false, originalText);
                inputPass.disabled = false;
            }
        });
    }
}

async function loadQrCode(container) {
    try {
        const res = await ApiService.post(ApiService.Routes.Settings.Init2FA);

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
        ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
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