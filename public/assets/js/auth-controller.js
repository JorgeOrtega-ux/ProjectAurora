/**
 * public/assets/js/auth-controller.js
 * Maneja el registro y autenticación vía AJAX (Fetch)
 * Usa DELEGACIÓN DE EVENTOS para soportar contenido dinámico (SPA).
 */

import { navigateTo } from './core/url-manager.js';

export function initAuthController() {
    console.log('Auth Controller: Iniciado (Modo Delegación)');

    // === DELEGACIÓN DE EVENTOS ===
    document.addEventListener('click', function(e) {
        const btnStep1 = e.target.closest('#btn-register-step1');
        const btnStep2 = e.target.closest('#btn-register-step2');
        const btnVerify = e.target.closest('#btn-verify');
        const btnLogin = e.target.closest('#btn-login-action');

        if (btnStep1) { handleRegisterStep(e, 1); return; }
        if (btnStep2) { handleRegisterStep(e, 2); return; }
        if (btnVerify) { handleRegisterStep(e, 3); return; }
        if (btnLogin) { handleLogin(e); return; }
    });

    // Listener global para tecla ENTER en los inputs
    document.addEventListener('keypress', function (e) {
        if (e.target.classList.contains('component-text-input') && e.key === 'Enter') {
            e.preventDefault();
            const form = e.target.closest('.component-stage-form');
            if (form) {
                const activeBtn = form.querySelector('.component-button.primary');
                if (activeBtn && !activeBtn.disabled) activeBtn.click();
            }
        }
    });
}

// --- VALIDACIONES FRONTEND ---
function validateEmailRules(email) {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        throw new Error("El formato del correo no es válido.");
    }

    const parts = email.split('@');
    if (parts.length !== 2) throw new Error("Formato de correo inválido.");
    
    const [localPart, domainPart] = parts;

    if (localPart.length < 6 || localPart.length > 64) {
        throw new Error("La parte antes del @ debe tener entre 6 y 64 caracteres.");
    }

    if (domainPart.length > 255) {
        throw new Error("El dominio es demasiado largo.");
    }

    const allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com'];
    if (!allowedDomains.includes(domainPart.toLowerCase())) {
        throw new Error("Solo se permiten correos: Gmail, Outlook, Hotmail, Yahoo o iCloud.");
    }
}

function validatePasswordRules(password) {
    if (password.length < 12 || password.length > 64) {
        throw new Error("La contraseña debe tener entre 12 y 64 caracteres.");
    }
}

function validateUsernameRules(username) {
    if (username.length < 6 || username.length > 32) {
        throw new Error("El nombre de usuario debe tener entre 6 y 32 caracteres.");
    }
}

// --- HELPER PARA CSRF ---
function getCsrfToken(isRegister = true) {
    const id = isRegister ? 'reg-csrf' : 'login-csrf';
    const input = document.getElementById(id);
    return input ? input.value : '';
}

// --- MANEJO DE REGISTRO (SPA) ---
async function handleRegisterStep(e, step) {
    e.preventDefault(); 
    
    const btn = e.target.closest('button');
    if(!btn) return;

    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const originalText = btn.innerHTML;
    
    const errorContainer = document.getElementById('auth-error-container');
    const errorText = document.getElementById('auth-error-text');
    
    if(errorContainer) errorContainer.style.display = 'none';

    const formData = new FormData();
    // NUEVO: Agregar Token CSRF
    formData.append('csrf_token', getCsrfToken(true));

    try {
        if (step === 1) {
            const emailElem = document.getElementById('reg-email');
            const passElem = document.getElementById('reg-password');
            if (!emailElem || !passElem) throw new Error("Error de formulario: Recarga la página.");
            
            const email = emailElem.value.trim();
            const password = passElem.value;
            
            if(!email || !password) throw new Error("Completa todos los campos.");
            
            // Validaciones locales
            validateEmailRules(email);
            validatePasswordRules(password);
            
            setLoading(btn, true); 
            
            formData.append('email', email);
            formData.append('password', password);
            formData.append('action', 'register_step_1');
        } 
        else if (step === 2) {
            const userElem = document.getElementById('reg-username');
            if (!userElem) throw new Error("Error de formulario: Recarga la página.");

            const username = userElem.value.trim();
            if(!username) throw new Error("Ingresa un nombre de usuario.");

            // Validaciones locales
            validateUsernameRules(username);

            setLoading(btn, true);

            formData.append('username', username);
            formData.append('action', 'register_step_2');
        } 
        else if (step === 3) {
            const codeElem = document.getElementById('reg-code');
            if (!codeElem) throw new Error("Error de formulario: Recarga la página.");

            const code = codeElem.value.trim();
            if(!code) throw new Error("Ingresa el código de verificación.");
            
            setLoading(btn, true);

            formData.append('code', code);
            formData.append('action', 'verify_account');
        }

        const response = await fetch(basePath, {
            method: 'POST',
            body: formData
        });

        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("Error del servidor (Respuesta inválida).");
        }

        const data = await response.json();

        if (data.status) {
            if (step === 3) {
                window.location.href = data.redirect || basePath;
            } else {
                let targetPath = data.redirect;
                if (targetPath.startsWith(window.location.origin)) {
                    targetPath = targetPath.replace(window.location.origin, '');
                }
                if (targetPath.startsWith(basePath)) {
                    targetPath = targetPath.substring(basePath.length);
                }
                if (targetPath.startsWith('/')) {
                    targetPath = targetPath.substring(1);
                }
                navigateTo(targetPath);
            }
        } else {
            throw new Error(data.message || "Error desconocido");
        }

    } catch (error) {
        if (document.body.contains(btn)) {
            setLoading(btn, false, originalText);
        }
        
        if(errorContainer && errorText) {
            errorText.textContent = error.message;
            errorContainer.style.display = 'flex';
        } else {
            alert(error.message);
        }
    }
}

// --- MANEJO DE LOGIN ---
async function handleLogin(e) {
    e.preventDefault();
    const btn = e.target.closest('button');
    if(!btn) return;

    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const originalText = btn.innerHTML;
    const errorContainer = document.getElementById('auth-error-container');
    const errorText = document.getElementById('auth-error-text');

    if(errorContainer) errorContainer.style.display = 'none';
    setLoading(btn, true);

    const formData = new FormData();
    // NUEVO: Agregar Token CSRF
    formData.append('csrf_token', getCsrfToken(false));

    const emailElem = document.getElementById('login-email');
    const passElem = document.getElementById('login-password');

    try {
        const email = emailElem ? emailElem.value : '';
        const password = passElem ? passElem.value : '';

        if(!email || !password) throw new Error("Ingresa correo y contraseña.");

        formData.append('email', email);
        formData.append('password', password);
        formData.append('action', 'login');

        const response = await fetch(basePath, { method: 'POST', body: formData });
        const data = await response.json();

        if (data.status) {
            window.location.href = data.redirect || basePath;
        } else {
            throw new Error(data.message || "Credenciales incorrectas");
        }
    } catch (error) {
        if(errorContainer && errorText) {
            errorText.textContent = error.message;
            errorContainer.style.display = 'flex';
        }
    } finally {
        if (document.body.contains(btn)) {
            setLoading(btn, false, originalText);
        }
    }
}

function setLoading(btn, isLoading, originalText = '') {
    if(!btn) return;
    if (isLoading) {
        btn.disabled = true;
        btn.style.minWidth = btn.offsetWidth + 'px'; 
        btn.innerHTML = '<span class="spinner" style="width: 16px; height: 16px; border-width: 2px; margin-right: 8px; display: inline-block; vertical-align: middle;"></span> Procesando...';
    } else {
        btn.disabled = false;
        btn.style.minWidth = '';
        btn.innerHTML = originalText;
    }
}