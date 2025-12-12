/**
 * AuthController.js
 * Lógica de UI para autenticación utilizando AuthService.
 * Actualizado con sistema de Toasts, soporte para 2FA y validaciones dinámicas.
 */

import { AuthService } from './api-services.js';
import { Toast } from './toast-service.js';

let countdownInterval;

// Reemplazamos la antigua lógica que insertaba HTML con el sistema Toast
const showError = (message) => {
    Toast.error(message);
};

const handleAuthResponse = (result, buttons) => {
    if (result.redirect) {
        window.location.href = result.redirect;
        return;
    }

    if (result.status === 'success') {
        Toast.success(result.message || window.t('api.success.valid_data'));
    } else {
        showError(result.message || window.t('global.error'));
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            // Restauramos texto según el botón
            if(btn.id === 'btn-verify-backup') btn.textContent = window.t('auth.2fa.use_backup_btn');
            else if(btn.id === 'btn-verify-totp' || btn.id === 'btn-verify-2fa-login') btn.textContent = window.t('global.verify');
            else btn.textContent = window.t('global.continue'); 
        });
    }
};

const startTimer = (timerElementId, buttonElementId) => {
    const timerDisplay = document.getElementById(timerElementId);
    const button = document.getElementById(buttonElementId);

    if (!timerDisplay || !button) return;

    let timeLeft = 60;
    
    button.style.pointerEvents = 'none';
    button.style.color = '#999';
    timerDisplay.textContent = `(${timeLeft})`;

    if (countdownInterval) clearInterval(countdownInterval);

    countdownInterval = setInterval(() => {
        timeLeft--;
        timerDisplay.textContent = `(${timeLeft})`;

        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            timerDisplay.textContent = ''; 
            button.style.pointerEvents = 'auto';
            button.style.color = '#000'; 
            button.style.textDecoration = 'underline';
        }
    }, 1000);
};

// --- NUEVA FUNCIÓN: Verificar Login con 2FA ---
const verify2faLogin = async (code) => {
    const formData = new FormData();
    formData.append('action', 'verify_2fa_login');
    formData.append('code', code);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    formData.append('csrf_token', csrfToken);
    
    const res = await fetch(window.BASE_PATH + 'api/auth_handler.php', { method: 'POST', body: formData });
    return await res.json();
};

const processAuthAction = async (actionType, data) => {
    const buttons = document.querySelectorAll('.btn-primary');
    buttons.forEach(btn => {
        btn.disabled = true; 
        btn.style.opacity = '0.7';
        btn.dataset.originalText = btn.textContent;
        btn.textContent = window.t('global.processing');
    });

    try {
        let result;

        switch (actionType) {
            case 'login':
                result = await AuthService.login(data.email, data.password);
                break;
            case 'register_step_1':
                result = await AuthService.registerStep1(data.email, data.password);
                break;
            case 'register_step_2':
                result = await AuthService.registerStep2(data.username);
                break;
            case 'verify_code':
                result = await AuthService.verifyCode(data.code);
                break;
            case 'resend_verification_code':
                result = await AuthService.resendVerificationCode();
                if (result.status === 'success') {
                    startTimer('register-timer', 'btn-resend-code');
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.textContent = window.t('auth.register.verify_button'); 
                    });
                    Toast.success(result.message);
                    return; 
                }
                break;
            case 'request_password_reset':
                result = await AuthService.requestPasswordReset(data.email);
                if (result.status === 'success') {
                    const resendContainer = document.getElementById('resend-container');
                    if(resendContainer) {
                        resendContainer.style.display = 'block';
                        startTimer('recover-timer', 'btn-resend-link');
                    }
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.textContent = window.t('api.success.code_sent'); 
                    });
                    Toast.success(result.message || window.t('api.success.link_generated'));
                    return; 
                }
                break;
            case 'reset_password':
                result = await AuthService.resetPassword(data.token, data.password);
                break;
            case 'verify_2fa_login':
                result = await verify2faLogin(data.code);
                break;
            default:
                throw new Error('Acción no reconocida');
        }

        handleAuthResponse(result, buttons);

    } catch (error) {
        console.error("Error en AuthController:", error);
        showError(window.t('js.error.connection'));
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.textContent = btn.dataset.originalText || window.t('global.retry');
        });
    }
};

const setupAuthListeners = () => {
    // Configuración por defecto si no existe
    const config = window.SERVER_CONFIG || {};
    const minPass = config.min_password_length || 8;
    const minUser = config.min_username_length || 6;
    const maxEmail = config.max_email_length || 255;
    
    // Configuración dinámica de dominios
    let allowedDomains = [];
    if(config.allowed_email_domains && config.allowed_email_domains.trim() !== "") {
        allowedDomains = config.allowed_email_domains.split(',').map(d => d.trim().toLowerCase());
    }
    // Si la lista está vacía, se permiten todos (la validación de JS lo ignorará)

    const oldAlerts = document.querySelectorAll('.alert.error, .alert.success');
    oldAlerts.forEach(el => el.style.display = 'none');

    if(document.getElementById('register-timer')) {
        startTimer('register-timer', 'btn-resend-code');
    }

    /* --- LÓGICA DE TOGGLE 2FA (NUEVO) --- */
    const viewTotp = document.getElementById('view-totp');
    const viewBackup = document.getElementById('view-backup');
    
    if (viewTotp && viewBackup) {
        document.getElementById('trigger-show-backup')?.addEventListener('click', (e) => {
            e.preventDefault();
            viewTotp.style.display = 'none';
            viewBackup.style.display = 'block';
            document.getElementById('2fa-backup-input')?.focus();
        });

        document.getElementById('trigger-show-totp')?.addEventListener('click', (e) => {
            e.preventDefault();
            viewBackup.style.display = 'none';
            viewTotp.style.display = 'block';
            document.getElementById('2fa-code-input')?.focus();
        });
    }

    document.addEventListener('click', (e) => {
        
        // A) LOGIN
        if (e.target && e.target.id === 'btn-login') {
            e.preventDefault();
            const email = document.getElementById('email');
            const password = document.getElementById('password');

            if(email && password && email.value && password.value) {
                processAuthAction('login', { email: email.value, password: password.value });
            } else {
                showError(window.t('js.error.complete_fields'));
            }
        }

        // B) REGISTRO PASO 1
        if (e.target && e.target.id === 'btn-register-step-1') {
            e.preventDefault();
            const email = document.getElementById('email');
            const password = document.getElementById('password');

            if(email && password && email.value && password.value) {
                const emailVal = email.value.trim();
                const passVal = password.value;
                
                if (!emailVal.includes('@')) {
                    showError(window.t('api.error.email_format')); return;
                }
                const lastAtPos = emailVal.lastIndexOf('@');
                const localPart = emailVal.substring(0, lastAtPos);
                const domainPart = emailVal.substring(lastAtPos + 1).toLowerCase();

                if (localPart.length < 4) {
                    showError(window.t('api.error.email_format')); return; 
                }
                
                // VALIDACIÓN DINÁMICA DE DOMINIOS
                if (allowedDomains.length > 0) {
                    if (!allowedDomains.includes(domainPart)) {
                        showError(window.t('api.error.email_domain')); return;
                    }
                }
                
                if (emailVal.length > maxEmail) {
                    showError(window.t('api.error.email_format') + ` (Max ${maxEmail})`); return;
                }
                
                // VALIDACIÓN DINÁMICA DE PASSWORD
                if (passVal.length < minPass) {
                    showError(window.t('api.error.password_short', minPass)); return;
                }

                processAuthAction('register_step_1', { email: emailVal, password: passVal });
            } else {
                showError(window.t('js.error.complete_fields'));
            }
        }

        // C) REGISTRO PASO 2
        if (e.target && e.target.id === 'btn-register-step-2') {
            e.preventDefault();
            const username = document.getElementById('username');

            if(username && username.value) {
                const userVal = username.value.trim();
                
                // VALIDACIÓN DINÁMICA DE USERNAME
                if (userVal.length < minUser) {
                    showError(window.t('api.error.username_short', minUser)); return;
                }
                
                processAuthAction('register_step_2', { username: userVal });
            } else {
                showError(window.t('js.error.complete_fields')); 
            }
        }

        // D) VERIFICACIÓN EMAIL
        if (e.target && e.target.id === 'btn-verify') {
            e.preventDefault();
            const code = document.getElementById('code');

            if(code && code.value) {
                processAuthAction('verify_code', { code: code.value });
            } else {
                showError(window.t('js.error.complete_fields'));
            }
        }

        // D-2) REENVIAR CÓDIGO
        const resendBtn = e.target.closest('#btn-resend-code');
        if (resendBtn) {
            e.preventDefault();
            processAuthAction('resend_verification_code', {});
        }

        // E) LOGOUT
        const logoutBtn = e.target.closest('#btn-logout');
        if (logoutBtn) {
            e.preventDefault(); 
            AuthService.logout();
        }

        // F) SOLICITUD DE RECUPERACIÓN
        if (e.target && e.target.id === 'btn-recover-request') {
            e.preventDefault();
            const email = document.getElementById('recover-email');

            if(email && email.value) {
                processAuthAction('request_password_reset', { email: email.value.trim() });
            } else {
                showError(window.t('js.error.complete_fields'));
            }
        }

        // F-2) REENVIAR ENLACE RECUPERACIÓN
        const resendLinkBtn = e.target.closest('#btn-resend-link');
        if (resendLinkBtn) {
            e.preventDefault();
            const email = document.getElementById('recover-email');
            if(email && email.value) {
                processAuthAction('request_password_reset', { email: email.value.trim() });
            }
        }

        // G) GUARDAR NUEVA CONTRASEÑA
        if (e.target && e.target.id === 'btn-recover-reset') {
            e.preventDefault();
            const pass1 = document.getElementById('new-password');
            const pass2 = document.getElementById('confirm-password');
            const token = document.getElementById('reset-token');

            if(pass1 && pass2 && token && pass1.value && pass2.value) {
                if (pass1.value !== pass2.value) {
                    showError(window.t('js.error.pass_mismatch'));
                    return;
                }
                // VALIDACIÓN DINÁMICA DE PASSWORD
                if (pass1.value.length < minPass) {
                    showError(window.t('api.error.password_short', minPass));
                    return;
                }
                processAuthAction('reset_password', { 
                    token: token.value, 
                    password: pass1.value 
                });
            } else {
                showError(window.t('js.error.complete_fields'));
            }
        }

        // H) VERIFICAR 2FA
        if (e.target && (e.target.id === 'btn-verify-totp' || e.target.id === 'btn-verify-2fa-login' || e.target.id === 'btn-verify-backup')) {
            e.preventDefault();
            // Lógica unificada para inputs
            let inputId = '2fa-code-input';
            if (e.target.id === 'btn-verify-backup') inputId = '2fa-backup-input';
            
            const codeInput = document.getElementById(inputId);
            if(codeInput && codeInput.value) {
                processAuthAction('verify_2fa_login', { code: codeInput.value.trim() });
            } else {
                showError(window.t('js.error.complete_fields'));
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const btns = [
                'btn-login', 'btn-register-step-1', 'btn-register-step-2', 
                'btn-verify', 'btn-recover-request', 'btn-recover-reset'
            ];
            
            const viewBackup = document.getElementById('view-backup');
            if(viewBackup && viewBackup.style.display !== 'none') {
                btns.push('btn-verify-backup');
            } else {
                btns.push('btn-verify-totp');
                btns.push('btn-verify-2fa-login');
            }

            for(let id of btns){
                const btn = document.getElementById(id);
                if(btn && !btn.disabled && document.body.contains(btn) && btn.offsetParent !== null) { 
                    btn.click(); 
                    break; 
                }
            }
        }
    });
};

export const initAuthController = () => {
    console.log('AuthController: Inicializado (vía ApiService + Toasts).');
    setupAuthListeners();
};