/**
 * AuthController.js
 * Lógica de UI para autenticación utilizando AuthService.
 */

import { AuthService } from './api-services.js';

let countdownInterval;

const showError = (message) => {
    let alertBox = document.querySelector('.alert.error');
    
    if (!alertBox) {
        const authCard = document.querySelector('.auth-card');
        if (authCard) {
            alertBox = document.createElement('div');
            alertBox.className = 'alert error';
            alertBox.style.marginTop = '16px';
            alertBox.style.marginBottom = '0';
            const footer = authCard.querySelector('.auth-footer');
            if (footer) {
                authCard.insertBefore(alertBox, footer);
            } else {
                authCard.appendChild(alertBox);
            }
        }
    }

    if (alertBox) {
        alertBox.textContent = message;
        alertBox.style.display = 'block';
        
        alertBox.animate([
            { transform: 'translateX(0)' },
            { transform: 'translateX(-5px)' },
            { transform: 'translateX(5px)' },
            { transform: 'translateX(0)' }
        ], { duration: 300 });
    } 
};

const handleAuthResponse = (result, buttons) => {
    if (result.status === 'success') {
        window.location.href = result.redirect;
    } else {
        // Usa el mensaje del backend si existe, sino un genérico traducido
        showError(result.message || window.t('global.error'));
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.textContent = window.t('global.continue'); 
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

const processAuthAction = async (actionType, data) => {
    const buttons = document.querySelectorAll('.btn-primary');
    buttons.forEach(btn => {
        btn.disabled = true; 
        btn.style.opacity = '0.7';
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
                    // Mensaje flotante simple, usa la respuesta del back que ya viene traducida si usaste el handler nuevo
                    alert(result.message);
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
                        btn.textContent = window.t('api.success.code_sent'); // O "Enviado"
                    });
                    return; 
                }
                break;

            case 'reset_password':
                result = await AuthService.resetPassword(data.token, data.password);
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
            btn.textContent = window.t('global.retry');
        });
    }
};

const setupAuthListeners = () => {
    if(document.getElementById('register-timer')) {
        startTimer('register-timer', 'btn-resend-code');
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
                const allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'yahoo.com'];

                if (!emailVal.includes('@')) {
                    showError(window.t('api.error.email_format')); return;
                }
                const lastAtPos = emailVal.lastIndexOf('@');
                const localPart = emailVal.substring(0, lastAtPos);
                const domainPart = emailVal.substring(lastAtPos + 1).toLowerCase();

                if (localPart.length < 4) {
                    showError(window.t('api.error.email_format')); return; 
                }
                if (!allowedDomains.includes(domainPart)) {
                    showError(window.t('api.error.email_domain')); return;
                }
                if (passVal.length < 8) {
                    showError(window.t('api.error.password_short')); return;
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
                if (userVal.length < 6) {
                    showError(window.t('api.error.username_short')); return;
                }
                processAuthAction('register_step_2', { username: userVal });
            } else {
                showError(window.t('js.error.complete_fields')); // "Por favor escribe un nombre de usuario"
            }
        }

        // D) VERIFICACIÓN
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
                if (pass1.value.length < 8) {
                    showError(window.t('api.error.password_short'));
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
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const btns = ['btn-login', 'btn-register-step-1', 'btn-register-step-2', 'btn-verify', 'btn-recover-request', 'btn-recover-reset'];
            for(let id of btns){
                const btn = document.getElementById(id);
                if(btn && !btn.disabled && document.body.contains(btn)) { 
                    btn.click(); 
                    break; 
                }
            }
        }
    });
};

export const initAuthController = () => {
    console.log('AuthController: Inicializado (vía ApiService).');
    setupAuthListeners();
};