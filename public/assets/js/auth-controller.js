/**
 * public/assets/js/auth-controller.js
 */

import { navigateTo } from './core/utils/url-manager.js';
import { ToastManager } from './core/components/toast-manager.js';
import { ApiService } from './core/services/api-service.js';
import { I18nManager } from './core/utils/i18n-manager.js';

let resendTimerInterval = null;
let recoveryTimerInterval = null;
let turnstileWidgetId = null;
let isRecoveryMode = false; // Estado local para el tipo de código

// Variable de estado para controlar el bloqueo
let isResendCooldownActive = false;

export function initAuthController() {
    renderTurnstile();

    document.addEventListener('spa:view_loaded', () => {
        setTimeout(renderTurnstile, 100);
        // Si cargamos directamente la vista de 2FA, poner foco
        if (window.location.pathname.includes('/verification-aditional')) {
            const input2fa = document.getElementById('2fa-code');
            if (input2fa) input2fa.focus();
        }
    });

    // Detección inteligente del timer
    const resendBtn = document.getElementById('btn-resend-code');
    
    // Solo si estamos en la pantalla de verificación (existe el botón)
    if (resendBtn) {
        // 1. Iniciar con bloqueo visual inmediato para evitar clicks prematuros
        resendBtn.classList.add('link-disabled'); 
        
        // 2. Consultar al servidor el tiempo real
        checkServerTimer();
    }

    document.body.addEventListener('click', async (e) => {
        const target = e.target;

        // LOGIN PASO 1
        const btnLogin = target.closest('#btn-login');
        if (btnLogin) {
            e.preventDefault();
            hideError('login-error');
            handleLoginStep1(btnLogin);
            return;
        }

        // LOGIN PASO 2 (2FA)
        const btnVerify2FA = target.closest('#btn-verify-2fa');
        if (btnVerify2FA) {
            e.preventDefault();
            // Limpiar error previo antes de validar
            hideError('login-step2-error'); 
            handleLoginStep2(btnVerify2FA);
            return;
        }

        // Toggle entre App y Código de Recuperación
        const btnToggleRecovery = target.closest('#toggle-recovery-mode');
        if (btnToggleRecovery) {
            e.preventDefault();
            toggleRecoveryMode(btnToggleRecovery);
            return;
        }

        // REGISTRO 1 -> 2
        const btnNext1 = target.closest('#btn-next-1');
        if (btnNext1) {
            e.preventDefault();
            hideError('register-step1-error');

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const tsToken = getTurnstileToken();

            if (!email || !password) {
                showError(btnNext1, 'register-step1-error', I18nManager.t('js.auth.fill_all'));
                return;
            }

            if (!tsToken) {
                showError(btnNext1, 'register-step1-error', 'Verificando seguridad...');
                return;
            }

            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            formData.append('cf-turnstile-response', tsToken);

            // [MODIFICACIÓN] HONEYPOT: Enviar la trampa al servidor
            const honeyPot = document.getElementById('website_url');
            if (honeyPot) {
                formData.append('website_url', honeyPot.value);
            }

            setLoading(btnNext1, true);

            try {
                const res = await ApiService.post(ApiService.Routes.Auth.RegisterStep1, formData);

                if (res.success) {
                    navigateTo(res.next_url);
                } else {
                    showError(btnNext1, 'register-step1-error', res.message);
                    setLoading(btnNext1, false);
                    resetTurnstile();
                }
            } catch (err) {
                console.error(err);
                showError(btnNext1, 'register-step1-error', I18nManager.t('js.auth.unexpected_error'));
                setLoading(btnNext1, false);
                resetTurnstile();
            }
            return;
        }

        // REGISTRO 2 -> 3
        const btnNext2 = target.closest('#btn-next-2');
        if (btnNext2) {
            e.preventDefault();
            hideError('register-step2-error');

            const username = document.getElementById('username').value;

            if (!username) {
                showError(btnNext2, 'register-step2-error', I18nManager.t('js.auth.choose_username'));
                return;
            }

            const formData = new FormData();
            formData.append('username', username);

            setLoading(btnNext2, true);

            try {
                const res = await ApiService.post(ApiService.Routes.Auth.RegisterStep2, formData);

                if (res.success) {
                    ToastManager.show(I18nManager.t('js.auth.code_sent'), 'info');
                    navigateTo(res.next_url);
                    // Aquí ya no llamamos a startResendTimer manualmente,
                    // al cargar la vista se ejecutará checkServerTimer
                } else {
                    showError(btnNext2, 'register-step2-error', res.message);
                    setLoading(btnNext2, false);
                }
            } catch (err) {
                showError(btnNext2, 'register-step2-error', I18nManager.t('js.auth.connection_error'));
                setLoading(btnNext2, false);
            }
            return;
        }

        // REGISTRO FINAL
        const btnFinish = target.closest('#btn-finish');
        if (btnFinish) {
            e.preventDefault();
            hideError('register-step3-error');

            const code = document.getElementById('verification_code').value;
            if (!code) {
                showError(btnFinish, 'register-step3-error', I18nManager.t('js.auth.enter_code'));
                return;
            }

            const formData = new FormData();
            formData.append('code', code);

            setLoading(btnFinish, true);

            try {
                const res = await ApiService.post(ApiService.Routes.Auth.RegisterComplete, formData);

                if (res.success) {
                    window.location.href = res.redirect;
                } else {
                    showError(btnFinish, 'register-step3-error', res.message);
                    setLoading(btnFinish, false);
                }
            } catch (err) {
                showError(btnFinish, 'register-step3-error', I18nManager.t('js.auth.verify_error'));
                setLoading(btnFinish, false);
            }
            return;
        }

        // REENVIAR CÓDIGO
        const btnResend = target.closest('#btn-resend-code');
        if (btnResend) {
            e.preventDefault();
            hideError('register-step3-error');

            // Verificación robusta usando la variable de estado
            if (isResendCooldownActive) {
                return;
            }

            const targetErrorNode = document.getElementById('btn-finish') || btnResend;
            const formData = new FormData();

            // Bloqueo visual inmediato
            btnResend.style.opacity = '0.5';

            try {
                const res = await ApiService.post(ApiService.Routes.Auth.ResendCode, formData);
                btnResend.style.opacity = '1';

                if (res.success) {
                    ToastManager.show(I18nManager.t('js.auth.resend_success'), 'success');
                    startResendTimer(60);
                } else {
                    showError(targetErrorNode, 'register-step3-error', res.message);
                }
            } catch (err) {
                btnResend.style.opacity = '1';
                showError(targetErrorNode, 'register-step3-error', I18nManager.t('js.auth.resend_error'));
            }
            return;
        }

        // RECUPERAR PASSWORD (SOLICITUD)
        const btnRequestReset = target.closest('#btn-request-reset');
        if (btnRequestReset) {
            e.preventDefault();
            hideError('recovery-error');

            const email = document.getElementById('email_recovery').value;
            if (!email) {
                showError(btnRequestReset, 'recovery-error', I18nManager.t('js.auth.enter_email'));
                return;
            }

            const formData = new FormData();
            formData.append('email', email);

            setLoading(btnRequestReset, true);

            try {
                const res = await ApiService.post(ApiService.Routes.Auth.RequestReset, formData);
                setLoading(btnRequestReset, false);

                if (res.success) {
                    ToastManager.show(I18nManager.t('js.auth.reset_link_sent'), 'success');
                    startRecoveryTimer(60);
                } else {
                    showError(btnRequestReset, 'recovery-error', res.message);
                }
            } catch (err) {
                setLoading(btnRequestReset, false);
                showError(btnRequestReset, 'recovery-error', I18nManager.t('js.auth.connection_error'));
            }
            return;
        }

        // REENVIAR RECUPERACIÓN
        const linkResendRecovery = target.closest('#link-resend-recovery');
        if (linkResendRecovery) {
            e.preventDefault();

            if (linkResendRecovery.classList.contains('link-disabled') || linkResendRecovery.style.pointerEvents === 'none') {
                return;
            }

            const email = document.getElementById('email_recovery').value;
            if (!email) return;

            const formData = new FormData();
            formData.append('email', email);

            linkResendRecovery.style.opacity = '0.5';

            try {
                const res = await ApiService.post(ApiService.Routes.Auth.RequestReset, formData);
                linkResendRecovery.style.opacity = '1';

                if (res.success) {
                    ToastManager.show(I18nManager.t('js.auth.reset_link_sent'), 'success');
                    startRecoveryTimer(60);
                } else {
                    ToastManager.show(res.message, 'error');
                }
            } catch (err) {
                linkResendRecovery.style.opacity = '1';
                ToastManager.show(I18nManager.t('js.auth.connection_error'), 'error');
            }
            return;
        }

        // RESET PASSWORD (SUBMIT)
        const btnSubmitNewPass = target.closest('#btn-submit-new-password');
        if (btnSubmitNewPass) {
            e.preventDefault();
            hideError('reset-pass-error');

            const token = document.getElementById('reset_token').value;
            const pass1 = document.getElementById('new_password').value;
            const pass2 = document.getElementById('confirm_password').value;

            if (!pass1 || !pass2) {
                showError(btnSubmitNewPass, 'reset-pass-error', I18nManager.t('js.auth.fill_all'));
                return;
            }
            if (pass1 !== pass2) {
                showError(btnSubmitNewPass, 'reset-pass-error', I18nManager.t('js.auth.pass_mismatch'));
                return;
            }

            const formData = new FormData();
            formData.append('token', token);
            formData.append('new_password', pass1);

            setLoading(btnSubmitNewPass, true);

            try {
                const res = await ApiService.post(ApiService.Routes.Auth.ResetPassword, formData);
                if (res.success) {
                    ToastManager.show(I18nManager.t('js.auth.pass_updated'), 'success');
                    setTimeout(() => {
                        window.location.href = window.BASE_PATH + 'login';
                    }, 1500);
                } else {
                    setLoading(btnSubmitNewPass, false);
                    showError(btnSubmitNewPass, 'reset-pass-error', res.message);
                }
            } catch (err) {
                setLoading(btnSubmitNewPass, false);
                showError(btnSubmitNewPass, 'reset-pass-error', I18nManager.t('js.auth.unexpected_error'));
            }
            return;
        }

        // LOGOUT
        const logoutBtn = target.closest('[data-action="logout"]');
        if (logoutBtn) {
            e.preventDefault();
            e.stopPropagation();

            if (logoutBtn.dataset.processing === "true") return;
            logoutBtn.dataset.processing = "true";

            addSpinnerToButton(logoutBtn);
            window.isManualLogout = true;

            try {
                await ApiService.post(ApiService.Routes.Auth.Logout);
                window.location.href = window.BASE_PATH + 'login';
            } catch (err) {
                console.error("Logout error:", err);
                window.isManualLogout = false;
                removeSpinnerFromButton(logoutBtn);
                logoutBtn.dataset.processing = "false";
            }
            return;
        }

        // UI INTERACTIONS
        const inputActionBtn = target.closest('.component-input-action');
        if (inputActionBtn) {
            handleUiActions(inputActionBtn, e);
        }
    });

    document.body.addEventListener('keypress', handleEnterKey);
}

// === Lógica de Toggle Recovery (Visual) ===
function toggleRecoveryMode(btn) {
    const input = document.getElementById('2fa-code');
    const label = document.getElementById('label-2fa-code');
    
    isRecoveryMode = !isRecoveryMode;

    if (isRecoveryMode) {
        input.placeholder = ' ';
        // Los códigos de recuperación son más largos
        input.maxLength = 10;
        input.value = '';
        input.focus();
        
        // Cambiar textos
        label.textContent = "Código de Recuperación";
        btn.textContent = "Usar aplicación de autenticación";
    } else {
        input.placeholder = ' ';
        input.maxLength = 6;
        input.value = '';
        input.focus();
        
        label.textContent = I18nManager.t('auth.2fa.field_code');
        btn.textContent = "Usar código de recuperación";
    }
}

// === HELPERS DE LOGICA LOGIN ===

async function handleLoginStep1(btn) {
    const inputs = document.querySelectorAll('#login-stage-1 input');
    const formData = new FormData();

    const tsToken = getTurnstileToken();
    let hasEmpty = false;

    inputs.forEach(input => {
        if (input.name && input.name !== 'cf-turnstile-response') {
            formData.append(input.name, input.value);
            if (input.required && !input.value) hasEmpty = true;
        }
    });

    if (hasEmpty) {
        showError(btn, 'login-error', I18nManager.t('js.auth.fill_all'));
        return;
    }

    if (!tsToken) {
        showError(btn, 'login-error', 'Verificando seguridad...');
        return;
    }
    formData.append('cf-turnstile-response', tsToken);

    setLoading(btn, true);
    try {
        const res = await ApiService.post(ApiService.Routes.Auth.Login, formData);

        if (res.success) {
            if (res.require_2fa) {
                // Cambio de URL sin recargar
                const newUrl = window.BASE_PATH + 'login/verification-aditional';
                window.history.pushState({ section: 'login/verification-aditional' }, '', newUrl);
                
                transitionTo2FA();
            } else {
                window.location.href = res.redirect;
            }
        } else {
            showError(btn, 'login-error', res.message);
            setLoading(btn, false);
            resetTurnstile();
        }
    } catch (e) {
        showError(btn, 'login-error', I18nManager.t('js.auth.connection_error'));
        setLoading(btn, false);
        resetTurnstile();
    }
}

async function handleLoginStep2(btn) {
    const input2fa = document.getElementById('2fa-code');
    const code = input2fa.value.trim();
    
    // Validar vacío y mostrar error en DIV
    if (!code) {
        showError(btn, 'login-step2-error', I18nManager.t('js.auth.enter_code'));
        input2fa.focus();
        return;
    }

    const formData = new FormData();
    formData.append('code', code);

    setLoading(btn, true);

    try {
        const res = await ApiService.post(ApiService.Routes.Auth.Verify2FA, formData);
        if (res.success) {
            window.location.href = res.redirect;
        } else {
            showError(btn, 'login-step2-error', res.message);
            setLoading(btn, false);
            input2fa.focus();
        }
    } catch (e) {
        showError(btn, 'login-step2-error', I18nManager.t('js.auth.connection_error'));
        setLoading(btn, false);
    }
}

function transitionTo2FA() {
    const stage1 = document.getElementById('login-stage-1');
    stage1.classList.add('disabled');

    const stage2 = document.getElementById('login-stage-2');
    stage2.classList.remove('disabled');

    document.getElementById('auth-title').innerText = I18nManager.t('auth.2fa.title');
    document.getElementById('auth-subtitle').innerText = I18nManager.t('auth.2fa.subtitle');

    const inputCode = document.getElementById('2fa-code');
    if (inputCode) inputCode.focus();
}

function handleUiActions(btn, e) {
    e.preventDefault();
    const action = btn.dataset.action;

    if (action === 'toggle-password') {
        const input = btn.parentElement.querySelector('input');
        const icon = btn.querySelector('.material-symbols-rounded');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    }
    else if (action === 'generate-username') {
        const input = document.getElementById('username');
        if (input) {
            const now = new Date();
            const suffix = now.getTime().toString().slice(-6);
            input.value = `User${suffix}`;
        }
    }
}

function handleEnterKey(e) {
    if (e.key === 'Enter') {
        const activeInput = document.activeElement;
        if (activeInput && activeInput.closest('.component-card')) {
            const stage2 = document.getElementById('login-stage-2');
            if (stage2 && !stage2.classList.contains('disabled')) {
                const btn2 = document.getElementById('btn-verify-2fa');
                if (btn2) btn2.click();
            } else {
                const btn1 = document.getElementById('btn-login');
                if (btn1) btn1.click();
            }
        }
    }
}

function setLoading(btn, isLoading) {
    if (isLoading) {
        btn.dataset.originalText = btn.innerText;
        btn.innerHTML = '<div class="spinner-sm"></div>';
        btn.disabled = true;
        btn.style.opacity = '0.8';
    } else {
        btn.innerText = btn.dataset.originalText || I18nManager.t('js.auth.continue');
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

function addSpinnerToButton(btn) {
    const spinnerContainer = document.createElement('div');
    spinnerContainer.className = 'menu-link-icon';
    spinnerContainer.id = 'logout-spinner';

    const spinner = document.createElement('div');
    spinner.className = 'spinner-sm';
    Object.assign(spinner.style, {
        borderColor: 'rgba(0, 0, 0, 0.1)',
        borderLeftColor: 'var(--text-primary)',
        width: '20px',
        height: '20px',
        borderWidth: '2px'
    });

    spinnerContainer.appendChild(spinner);
    btn.appendChild(spinnerContainer);
}

function removeSpinnerFromButton(btn) {
    const spinner = btn.querySelector('#logout-spinner');
    if (spinner) spinner.remove();
}

function showError(referenceNode, errorId, message) {
    let errorDiv = document.getElementById(errorId);
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = errorId;
        errorDiv.className = 'component-message component-message--error';
        if (referenceNode.nextSibling) referenceNode.parentNode.insertBefore(errorDiv, referenceNode.nextSibling);
        else referenceNode.parentNode.appendChild(errorDiv);
    }
    errorDiv.innerText = message;
}

function hideError(errorId) {
    const el = document.getElementById(errorId);
    if (el) el.remove();
}

function renderTurnstile() {
    const container = document.getElementById('turnstile-container');
    if (container && window.turnstile) {
        container.innerHTML = '';
        try {
            turnstileWidgetId = turnstile.render('#turnstile-container', {
                sitekey: window.TURNSTILE_SITE_KEY,
                theme: 'auto'
            });
        } catch (e) { }
    }
}

function getTurnstileToken() {
    if (window.turnstile && turnstileWidgetId !== null) return turnstile.getResponse(turnstileWidgetId);
    const input = document.querySelector('[name="cf-turnstile-response"]');
    return input ? input.value : '';
}

function resetTurnstile() {
    if (window.turnstile && turnstileWidgetId !== null) turnstile.reset(turnstileWidgetId);
}

// Función del temporizador usando la variable de estado
function startResendTimer(seconds) {
    const btn = document.getElementById('btn-resend-code');
    const timerSpan = document.getElementById('register-timer');
    if (!btn || !timerSpan) return;

    // 1. Activar bloqueo lógico
    isResendCooldownActive = true;

    // 2. Aplicar estado visual
    let timeLeft = seconds;
    btn.classList.add('link-disabled');
    btn.style.pointerEvents = 'none';
    btn.style.color = 'rgb(153, 153, 153)';
    timerSpan.textContent = `(${timeLeft})`;

    if (resendTimerInterval) clearInterval(resendTimerInterval);

    resendTimerInterval = setInterval(() => {
        timeLeft--;
        timerSpan.textContent = `(${timeLeft})`;
        
        if (timeLeft <= 0) {
            clearInterval(resendTimerInterval);
            
            // 3. Liberar bloqueo lógico y visual
            isResendCooldownActive = false;
            
            btn.classList.remove('link-disabled');
            btn.style.pointerEvents = 'auto';
            btn.style.color = '';
            timerSpan.textContent = '';
        }
    }, 1000);
}

function startRecoveryTimer(seconds) {
    const btnRequest = document.getElementById('btn-request-reset');
    const linkBack = document.getElementById('link-back-login');
    const linkResend = document.getElementById('link-resend-recovery');
    const timerSpan = document.getElementById('recovery-timer');
    const inputEmail = document.getElementById('email_recovery');

    if (btnRequest) { btnRequest.disabled = true; btnRequest.style.opacity = '0.5'; }
    if (linkBack) linkBack.style.display = 'none';

    if (linkResend) {
        linkResend.style.display = 'inline-block';
        linkResend.classList.add('link-disabled');
        linkResend.style.pointerEvents = 'none';
        linkResend.style.color = 'rgb(153, 153, 153)';
    }
    if (inputEmail) { inputEmail.disabled = true; inputEmail.style.opacity = '0.7'; }

    let timeLeft = seconds;
    if (timerSpan) timerSpan.textContent = `(${timeLeft})`;

    if (recoveryTimerInterval) clearInterval(recoveryTimerInterval);

    recoveryTimerInterval = setInterval(() => {
        timeLeft--;
        if (timerSpan) timerSpan.textContent = `(${timeLeft})`;

        if (timeLeft <= 0) {
            clearInterval(recoveryTimerInterval);
            if (btnRequest) { btnRequest.disabled = false; btnRequest.style.opacity = '1'; }
            if (linkResend) {
                linkResend.classList.remove('link-disabled');
                linkResend.style.pointerEvents = 'auto';
                linkResend.style.color = '';
                if (timerSpan) timerSpan.textContent = '';
            }
            if (linkBack) linkBack.style.display = 'inline-block';
            if (inputEmail) { inputEmail.disabled = false; inputEmail.style.opacity = '1'; }
        }
    }, 1000);
}

async function checkServerTimer() {
    try {
        // Llamamos al nuevo endpoint usando la ruta completa
        const res = await ApiService.post(ApiService.Routes.Auth.GetStatus);
        
        if (res.success && res.cooldown > 0) {
            // Si hay cooldown en el servidor, iniciamos el timer con ese tiempo exacto
            startResendTimer(res.cooldown);
        } else {
            // Si no hay cooldown (es 0), desbloqueamos el botón inmediatamente
            const btn = document.getElementById('btn-resend-code');
            const timerSpan = document.getElementById('register-timer');
            if (btn) {
                btn.classList.remove('link-disabled');
                btn.style.pointerEvents = 'auto';
                btn.style.color = '';
            }
            if (timerSpan) timerSpan.textContent = '';
        }
    } catch (e) {
        console.error("Error sincronizando timer:", e);
        // Fallback: Si falla la red, asumimos 0 para no bloquear al usuario eternamente
        startResendTimer(0); 
    }
}