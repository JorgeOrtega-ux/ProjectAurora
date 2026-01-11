/**
 * public/assets/js/auth-controller.js
 * Maneja el registro y autenticación vía AJAX (Fetch)
 * Usa DELEGACIÓN DE EVENTOS para soportar contenido dinámico (SPA).
 */

import { navigateTo } from './core/url-manager.js';

export function initAuthController() {
    console.log('Auth Controller: Iniciado (Modo Delegación)');

    // === DELEGACIÓN DE EVENTOS ===
    // Escuchamos clics en todo el documento, pero solo actuamos si es en nuestros botones.
    document.addEventListener('click', function(e) {
        
        // 1. Detectar clic en Botones de Registro (buscamos el .closest por si clican en el ícono/span)
        const btnStep1 = e.target.closest('#btn-register-step1');
        const btnStep2 = e.target.closest('#btn-register-step2');
        const btnVerify = e.target.closest('#btn-verify');
        const btnLogin = e.target.closest('#btn-login-action');

        // Evitamos doble disparo si hubiera otros listeners
        if (btnStep1) { handleRegisterStep(e, 1); return; }
        if (btnStep2) { handleRegisterStep(e, 2); return; }
        if (btnVerify) { handleRegisterStep(e, 3); return; }
        if (btnLogin) { handleLogin(e); return; }
    });

    // Listener global para tecla ENTER en los inputs
    document.addEventListener('keypress', function (e) {
        if (e.target.classList.contains('component-text-input') && e.key === 'Enter') {
            e.preventDefault();
            // Busca el botón principal dentro del mismo formulario visible
            const form = e.target.closest('.component-stage-form');
            if (form) {
                const activeBtn = form.querySelector('.component-button.primary');
                if (activeBtn && !activeBtn.disabled) activeBtn.click();
            }
        }
    });
}

// --- MANEJO DE REGISTRO (SPA) ---
async function handleRegisterStep(e, step) {
    e.preventDefault(); 
    
    // Obtenemos el botón desde el evento (e.target podría ser el icono, así que usamos closest)
    const btn = e.target.closest('button');
    if(!btn) return;

    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const originalText = btn.innerHTML;
    
    // Buscamos contenedores de error (pueden haberse recargado, así que los buscamos frescos)
    const errorContainer = document.getElementById('auth-error-container');
    const errorText = document.getElementById('auth-error-text');
    
    if(errorContainer) errorContainer.style.display = 'none';
    setLoading(btn, true);

    const formData = new FormData();

    try {
        if (step === 1) {
            const emailElem = document.getElementById('reg-email');
            const passElem = document.getElementById('reg-password');
            // Validación robusta por si los elementos no están en el DOM
            if (!emailElem || !passElem) throw new Error("Error de formulario: Recarga la página.");
            
            const email = emailElem.value;
            const password = passElem.value;
            if(!email || !password) throw new Error("Completa todos los campos.");
            
            formData.append('email', email);
            formData.append('password', password);
            formData.append('action', 'register_step_1');
        } 
        else if (step === 2) {
            const userElem = document.getElementById('reg-username');
            if (!userElem) throw new Error("Error de formulario: Recarga la página.");

            const username = userElem.value;
            if(!username) throw new Error("Ingresa un nombre de usuario.");

            formData.append('username', username);
            formData.append('action', 'register_step_2');
        } 
        else if (step === 3) {
            const codeElem = document.getElementById('reg-code');
            if (!codeElem) throw new Error("Error de formulario: Recarga la página.");

            const code = codeElem.value;
            if(!code) throw new Error("Ingresa el código de verificación.");

            formData.append('code', code);
            formData.append('action', 'verify_account');
        }

        const response = await fetch(basePath, {
            method: 'POST',
            body: formData
        });

        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            // A veces PHP devuelve errores fatales en HTML
            const text = await response.text();
            console.error("Respuesta no JSON:", text);
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
        console.error("Auth Error:", error);
        if(errorContainer && errorText) {
            errorText.textContent = error.message;
            errorContainer.style.display = 'flex';
        } else {
            alert(error.message);
        }
    } finally {
        // Restaurar estado si el botón sigue existiendo (en SPA a veces desaparece)
        if (document.body.contains(btn)) {
            setLoading(btn, false, originalText);
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