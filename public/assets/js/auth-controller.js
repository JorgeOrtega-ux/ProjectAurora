/**
 * AuthController.js
 * Lógica de UI para autenticación utilizando AuthService.
 */

import { AuthService } from './api-services.js';

// Variable global para el intervalo del timer
let countdownInterval;

// Función para mostrar errores en la interfaz
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

// Función auxiliar para manejar la respuesta del servicio
const handleAuthResponse = (result, buttons) => {
    if (result.status === 'success') {
        window.location.href = result.redirect;
    } else {
        showError(result.message || 'Ocurrió un error desconocido.');
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.textContent = 'Continuar'; 
        });
    }
};

/**
 * Función para manejar el temporizador de reenvío (60s)
 * @param {string} timerElementId - ID del elemento span que muestra el contador
 * @param {string} buttonElementId - ID del enlace/botón que se debe deshabilitar/habilitar
 */
const startTimer = (timerElementId, buttonElementId) => {
    const timerDisplay = document.getElementById(timerElementId);
    const button = document.getElementById(buttonElementId);

    if (!timerDisplay || !button) return;

    let timeLeft = 60;
    
    // UI Inicial del timer
    button.style.pointerEvents = 'none';
    button.style.color = '#999';
    timerDisplay.textContent = `(${timeLeft})`;

    // Limpiar intervalo previo si existe
    if (countdownInterval) clearInterval(countdownInterval);

    countdownInterval = setInterval(() => {
        timeLeft--;
        timerDisplay.textContent = `(${timeLeft})`;

        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            timerDisplay.textContent = ''; // Borrar (0)
            button.style.pointerEvents = 'auto';
            button.style.color = '#000'; // Color activo
            button.style.textDecoration = 'underline';
        }
    }, 1000);
};

// Función asíncrona genérica para procesar formularios
const processAuthAction = async (actionType, data) => {
    // 1. UI: Bloquear botones
    const buttons = document.querySelectorAll('.btn-primary');
    buttons.forEach(btn => {
        btn.disabled = true; 
        btn.style.opacity = '0.7';
        btn.textContent = 'Procesando...';
    });

    try {
        let result;

        // 2. Lógica: Llamar al servicio correspondiente
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
                    // Reiniciar timer
                    startTimer('register-timer', 'btn-resend-code');
                    // Resetear botones UI manual porque no recarga página
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.textContent = 'Verificar y Crear Cuenta'; 
                    });
                    // Mostrar mensaje flotante nativo o simple
                    alert('Código reenviado correctamente: ' + (result.message.replace('Código reenviado: ', '') || 'Revisa tu correo'));
                    return; 
                }
                break;

            case 'request_password_reset':
                result = await AuthService.requestPasswordReset(data.email);
                
                if (result.status === 'success') {
                    // Mostrar contenedor de reenvío
                    const resendContainer = document.getElementById('resend-container');
                    if(resendContainer) {
                        resendContainer.style.display = 'block';
                        startTimer('recover-timer', 'btn-resend-link');
                    }

                    // Lógica para mostrar el link simulado
                    if (result.data && result.data.debug_link) {
                        const simResult = document.getElementById('simulation-result');
                        if(simResult) {
                            simResult.style.display = 'block';
                            simResult.innerHTML = `<strong>¡Simulación!</strong><br>Abre este link:<br><a href="${result.data.debug_link}">${result.data.debug_link}</a>`;
                        }
                    }
                    // No redirigimos automáticamente
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.textContent = 'Enviado';
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

        // 3. UI: Manejar respuesta
        handleAuthResponse(result, buttons);

    } catch (error) {
        console.error("Error en AuthController:", error);
        showError("Error de conexión local.");
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.textContent = 'Reintentar';
        });
    }
};

const setupAuthListeners = () => {
    // Inicializar timer si estamos en la vista de verificación al cargar
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
                showError("Por favor completa todos los campos.");
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
                    showError("Formato de correo inválido."); return;
                }
                const lastAtPos = emailVal.lastIndexOf('@');
                const localPart = emailVal.substring(0, lastAtPos);
                const domainPart = emailVal.substring(lastAtPos + 1).toLowerCase();

                if (localPart.length < 4) {
                    showError("El correo debe tener al menos 4 caracteres antes del @."); return;
                }
                if (!allowedDomains.includes(domainPart)) {
                    showError("Dominio no permitido. Use: gmail, outlook, hotmail, icloud o yahoo."); return;
                }
                if (passVal.length < 8) {
                    showError("La contraseña debe tener al menos 8 caracteres."); return;
                }

                processAuthAction('register_step_1', { email: emailVal, password: passVal });
            } else {
                showError("Por favor completa todos los campos.");
            }
        }

        // C) REGISTRO PASO 2
        if (e.target && e.target.id === 'btn-register-step-2') {
            e.preventDefault();
            const username = document.getElementById('username');

            if(username && username.value) {
                const userVal = username.value.trim();
                if (userVal.length < 6) {
                    showError("El nombre de usuario debe tener al menos 6 caracteres."); return;
                }
                processAuthAction('register_step_2', { username: userVal });
            } else {
                showError("Por favor escribe un nombre de usuario.");
            }
        }

        // D) VERIFICACIÓN
        if (e.target && e.target.id === 'btn-verify') {
            e.preventDefault();
            const code = document.getElementById('code');

            if(code && code.value) {
                processAuthAction('verify_code', { code: code.value });
            } else {
                showError("Por favor ingresa el código.");
            }
        }

        // D-2) REENVIAR CÓDIGO (NUEVO)
        // Usamos closest porque el span del timer está dentro del <a>
        const resendBtn = e.target.closest('#btn-resend-code');
        if (resendBtn) {
            e.preventDefault();
            processAuthAction('resend_verification_code', {});
        }

        // E) LOGOUT (Delegación de eventos usando closest)
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
                showError("Por favor ingresa tu correo electrónico.");
            }
        }

        // F-2) REENVIAR ENLACE RECUPERACIÓN (NUEVO)
        const resendLinkBtn = e.target.closest('#btn-resend-link');
        if (resendLinkBtn) {
            e.preventDefault();
            const email = document.getElementById('recover-email');
            if(email && email.value) {
                // Reutilizamos la acción request_password_reset para reenviar
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
                    showError("Las contraseñas no coinciden.");
                    return;
                }
                if (pass1.value.length < 8) {
                    showError("La contraseña debe tener al menos 8 caracteres.");
                    return;
                }
                processAuthAction('reset_password', { 
                    token: token.value, 
                    password: pass1.value 
                });
            } else {
                showError("Por favor completa todos los campos.");
            }
        }
    });

    // Soporte ENTER
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            // Se agregaron los botones de recuperación a la lista
            const btns = ['btn-login', 'btn-register-step-1', 'btn-register-step-2', 'btn-verify', 'btn-recover-request', 'btn-recover-reset'];
            for(let id of btns){
                const btn = document.getElementById(id);
                // Verificación extra para asegurar que el botón está visible en el DOM
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