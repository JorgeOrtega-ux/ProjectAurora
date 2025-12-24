import { navigateTo } from './core/url-manager.js';
import { Toast } from './core/toast-manager.js';
import { ApiService } from './core/api-service.js';
import { I18n } from './core/i18n-manager.js';

let resendTimerInterval = null;

export function initAuthController() {
    console.log("Auth Controller: Listo (SPA - Event Delegation - Generic Components)");

    const resendBtn = document.getElementById('btn-resend-code');
    if (resendBtn) {
        startResendTimer(60);
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

        // LOGIN PASO 2
        const btnVerify2FA = target.closest('#btn-verify-2fa');
        if (btnVerify2FA) {
            e.preventDefault();
            handleLoginStep2(btnVerify2FA);
            return;
        }

        // REGISTRO 1 -> 2
        const btnNext1 = target.closest('#btn-next-1');
        if (btnNext1) {
            e.preventDefault();
            hideError('register-step1-error');
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if(!email || !password) { 
                showError(btnNext1, 'register-step1-error', I18n.t('js.auth.fill_all')); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'register_step_1');
            formData.append('email', email);
            formData.append('password', password);

            setLoading(btnNext1, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    navigateTo(res.next_url); 
                } else {
                    showError(btnNext1, 'register-step1-error', res.message);
                    setLoading(btnNext1, false);
                }
            } catch (err) {
                console.error(err);
                showError(btnNext1, 'register-step1-error', I18n.t('js.auth.unexpected_error'));
                setLoading(btnNext1, false);
            }
            return;
        }

        // REGISTRO 2 -> 3
        const btnNext2 = target.closest('#btn-next-2');
        if (btnNext2) {
            e.preventDefault();
            hideError('register-step2-error');

            const username = document.getElementById('username').value;
            
            if(!username) { 
                showError(btnNext2.parentElement, 'register-step2-error', I18n.t('js.auth.choose_username')); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'initiate_verification');
            formData.append('username', username);

            setLoading(btnNext2, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    if(res.debug_code) console.log("Code:", res.debug_code);
                    
                    Toast.show(I18n.t('js.auth.code_sent'), 'info'); 
                    
                    navigateTo(res.next_url); 
                    setTimeout(() => startResendTimer(60), 500);
                } else {
                    showError(btnNext2.parentElement, 'register-step2-error', res.message);
                    setLoading(btnNext2, false);
                }
            } catch (err) {
                showError(btnNext2.parentElement, 'register-step2-error', I18n.t('js.auth.connection_error'));
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
            
            if(!code) { 
                showError(btnFinish, 'register-step3-error', I18n.t('js.auth.enter_code')); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'complete_register');
            formData.append('code', code);

            setLoading(btnFinish, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    window.location.href = res.redirect;
                } else {
                    showError(btnFinish, 'register-step3-error', res.message);
                    setLoading(btnFinish, false);
                }
            } catch (err) {
                showError(btnFinish, 'register-step3-error', I18n.t('js.auth.verify_error'));
                setLoading(btnFinish, false);
            }
            return;
        }

        // REENVIAR
        const btnResend = target.closest('#btn-resend-code');
        if (btnResend) {
            e.preventDefault();
            hideError('register-step3-error');
            
            if (btnResend.classList.contains('link-disabled') || btnResend.style.pointerEvents === 'none') {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'resend_code');

            btnResend.style.opacity = '0.5';
            
            try {
                const res = await ApiService.post('auth-handler.php', formData);
                btnResend.style.opacity = '1';

                if (res.success) {
                    Toast.show(I18n.t('js.auth.resend_success'), 'success');
                    
                    if(res.debug_code) console.log("New Code:", res.debug_code);
                    startResendTimer(60);
                } else {
                    showError(btnResend, 'register-step3-error', res.message);
                }
            } catch (err) {
                console.error(err);
                btnResend.style.opacity = '1';
                showError(btnResend, 'register-step3-error', I18n.t('js.auth.resend_error'));
            }
            return;
        }

        // RECUPERAR
        const btnRequestReset = target.closest('#btn-request-reset');
        if (btnRequestReset) {
            e.preventDefault();
            hideError('recovery-error');
            
            const email = document.getElementById('email_recovery').value;
            if(!email) {
                showError(btnRequestReset, 'recovery-error', I18n.t('js.auth.enter_email'));
                return;
            }

            const formData = new FormData();
            formData.append('action', 'request_reset');
            formData.append('email', email);

            setLoading(btnRequestReset, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                setLoading(btnRequestReset, false);

                if (res.success) {
                    Toast.show(I18n.t('js.auth.reset_link_sent'), 'success');
                    console.log("=== LINK ===", res.debug_link);
                    
                    const area = document.getElementById('recovery-message-area');
                    if(area) {
                        area.innerHTML = `<div class="component-message component-message--success">
                            Link: <br><a href="${res.debug_link}">${res.debug_link}</a>
                        </div>`;
                    }
                } else {
                    showError(btnRequestReset, 'recovery-error', res.message);
                }
            } catch (err) {
                setLoading(btnRequestReset, false);
                showError(btnRequestReset, 'recovery-error', I18n.t('js.auth.connection_error'));
            }
            return;
        }

        // RESET PASSWORD
        const btnSubmitNewPass = target.closest('#btn-submit-new-password');
        if (btnSubmitNewPass) {
            e.preventDefault();
            hideError('reset-pass-error');

            const token = document.getElementById('reset_token').value;
            const pass1 = document.getElementById('new_password').value;
            const pass2 = document.getElementById('confirm_password').value;

            if (!pass1 || !pass2) {
                showError(btnSubmitNewPass, 'reset-pass-error', I18n.t('js.auth.fill_all'));
                return;
            }
            if (pass1 !== pass2) {
                showError(btnSubmitNewPass, 'reset-pass-error', I18n.t('js.auth.pass_mismatch'));
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('token', token);
            formData.append('new_password', pass1);

            setLoading(btnSubmitNewPass, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    Toast.show(I18n.t('js.auth.pass_updated'), 'success');
                    setTimeout(() => {
                        window.location.href = window.BASE_PATH + 'login';
                    }, 1500);
                } else {
                    setLoading(btnSubmitNewPass, false);
                    showError(btnSubmitNewPass, 'reset-pass-error', res.message);
                }
            } catch (err) {
                setLoading(btnSubmitNewPass, false);
                showError(btnSubmitNewPass, 'reset-pass-error', I18n.t('js.auth.unexpected_error'));
            }
            return;
        }

        // LOGOUT
        const logoutBtn = target.closest('[data-action="logout"]');
        if (logoutBtn) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'logout');
            
            await ApiService.post('auth-handler.php', formData);
            window.location.href = window.BASE_PATH + 'login';
            return;
        }

        // UI INTERACTIONS (Nuevo Selector Generic)
        const inputActionBtn = target.closest('.component-input-action');
        if (inputActionBtn) {
            e.preventDefault();
            const action = inputActionBtn.dataset.action;
            
            if (action === 'toggle-password') {
                const input = inputActionBtn.parentElement.querySelector('input');
                const icon = inputActionBtn.querySelector('.material-symbols-rounded');
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
                    const day = String(now.getDate()).padStart(2, '0');
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const year = now.getFullYear();
                    const timestamp = now.getTime();
                    input.value = `User${day}${month}${year}${timestamp}`;
                }
            }
        }
    });

    document.body.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const activeInput = document.activeElement;
            // Buscar si estamos dentro de un componente de tarjeta auth
            if (activeInput && activeInput.closest('.component-card')) {
                const stage2 = document.getElementById('login-stage-2');
                if(stage2 && !stage2.classList.contains('disabled')) {
                    const btn2 = document.getElementById('btn-verify-2fa');
                    if(btn2) btn2.click();
                } else {
                    const btn1 = document.getElementById('btn-login');
                    if(btn1) btn1.click();
                }
            }
        }
    });
}

function setLoading(btn, isLoading) {
    if (isLoading) {
        btn.dataset.originalText = btn.innerText;
        btn.innerHTML = '<div class="spinner-sm"></div>'; 
        btn.disabled = true;
        btn.style.opacity = '0.8'; 
    } else {
        btn.innerText = btn.dataset.originalText || I18n.t('js.auth.continue');
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function handleLoginStep1(btn) {
    const inputs = document.querySelectorAll('#login-stage-1 input');
    const formData = new FormData();
    formData.append('action', 'login');
    
    let hasEmpty = false;
    inputs.forEach(input => {
        if(input.name) formData.append(input.name, input.value);
        if(input.required && !input.value) hasEmpty = true;
    });

    if(hasEmpty) { 
        showError(btn, 'login-error', I18n.t('js.auth.fill_all')); 
        return; 
    }

    setLoading(btn, true);
    try {
        const res = await ApiService.post('auth-handler.php', formData);
        
        if (res.success) {
            if (res.require_2fa) {
                const stage1 = document.getElementById('login-stage-1');
                stage1.classList.add('disabled');
                
                const stage2 = document.getElementById('login-stage-2');
                stage2.classList.remove('disabled');

                document.getElementById('auth-title').innerText = I18n.t('auth.2fa.title');
                document.getElementById('auth-subtitle').innerText = I18n.t('auth.2fa.subtitle');
                
                const inputCode = document.getElementById('2fa-code');
                if(inputCode) inputCode.focus();
            } else {
                window.location.href = res.redirect;
            }
        } else {
            showError(btn, 'login-error', res.message);
            setLoading(btn, false);
        }
    } catch(e) { 
        showError(btn, 'login-error', I18n.t('js.auth.connection_error'));
        setLoading(btn, false); 
    }
}

async function handleLoginStep2(btn) {
    const code = document.getElementById('2fa-code').value;
    if(!code) return;

    const formData = new FormData();
    formData.append('action', 'verify_2fa_login');
    formData.append('code', code);

    setLoading(btn, true);

    try {
        const res = await ApiService.post('auth-handler.php', formData);
        if (res.success) {
            window.location.href = res.redirect;
        } else {
            Toast.show(res.message, 'error'); 
            setLoading(btn, false);
            document.getElementById('2fa-code').value = '';
        }
    } catch (e) {
        Toast.show(I18n.t('js.auth.connection_error'), 'error');
        setLoading(btn, false);
    }
}

function showError(referenceNode, errorId, message) {
    let errorDiv = document.getElementById(errorId);
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = errorId;
        // Nueva clase genérica
        errorDiv.className = 'component-message component-message--error';
        
        // Insertar después del botón de referencia (o nodo)
        if(referenceNode.nextSibling) {
            referenceNode.parentNode.insertBefore(errorDiv, referenceNode.nextSibling);
        } else {
            referenceNode.parentNode.appendChild(errorDiv);
        }
    }
    errorDiv.innerText = message;
}

function hideError(errorId) {
    const el = document.getElementById(errorId);
    if (el) {
        el.remove(); 
    }
}

function startResendTimer(seconds) {
    const btn = document.getElementById('btn-resend-code');
    const timerSpan = document.getElementById('register-timer');
    
    if (!btn || !timerSpan) return;

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
            btn.classList.remove('link-disabled');
            btn.style.pointerEvents = 'auto';
            btn.style.color = ''; 
            timerSpan.textContent = ''; 
        }
    }, 1000);
}