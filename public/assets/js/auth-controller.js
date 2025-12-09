/**
 * AuthController.js
 * Lógica de UI para autenticación utilizando AuthService.
 */

import { AuthService } from './api-services.js';

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
        case 'request_password_reset':
                result = await AuthService.requestPasswordReset(data.email);
                // Lógica especial para mostrar el link simulado
                if (result.status === 'success' && result.data && result.data.debug_link) {
                    const simResult = document.getElementById('simulation-result');
                    if(simResult) {
                        simResult.style.display = 'block';
                        simResult.innerHTML = `<strong>¡Simulación!</strong><br>Abre este link:<br><a href="${result.data.debug_link}">${result.data.debug_link}</a>`;
                    }
                    // No redirigimos automáticamente para que el usuario vea el link
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.textContent = 'Enviado';
                    });
                    return; // Salimos para no ejecutar handleAuthResponse estándar
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

        // E) LOGOUT (Delegación de eventos usando closest para detectar el div o sus hijos)
        const logoutBtn = e.target.closest('#btn-logout');
        if (logoutBtn) {
            e.preventDefault(); 
            AuthService.logout();
        }
    });

    // Soporte ENTER
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const btns = ['btn-login', 'btn-register-step-1', 'btn-register-step-2', 'btn-verify'];
            for(let id of btns){
                const btn = document.getElementById(id);
                if(btn && !btn.disabled) { btn.click(); break; }
            }
        }
    });
};

export const initAuthController = () => {
    console.log('AuthController: Inicializado (vía ApiService).');
    setupAuthListeners();
};