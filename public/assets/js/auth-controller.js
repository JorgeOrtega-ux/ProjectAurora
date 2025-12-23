import { navigateTo } from './core/url-manager.js';

let resendTimerInterval = null;

export function initAuthController() {
    console.log("Auth Controller: Listo (SPA - Event Delegation)");

    const csrfToken = getCsrfToken();

    // Comprobar si entramos directamente a la pantalla de verificación para iniciar timer
    const resendBtn = document.getElementById('btn-resend-code');
    if (resendBtn) {
        startResendTimer(60);
    }

    // ============================================================
    // DELEGACIÓN DE EVENTOS (Maneja clics en elementos dinámicos)
    // ============================================================
    document.body.addEventListener('click', async (e) => {
        const target = e.target;

        // ------------------------------------------
        // 1. MANEJO DE LOGIN
        // ------------------------------------------
        const btnLogin = target.closest('#btn-login');
        if (btnLogin) {
            e.preventDefault();
            hideError('login-error'); 
            handleLogin(btnLogin, csrfToken);
            return;
        }

        // ------------------------------------------
        // 2. REGISTRO - PASO 1 (Email/Pass) -> PASO 2
        // ------------------------------------------
        const btnNext1 = target.closest('#btn-next-1');
        if (btnNext1) {
            e.preventDefault();
            hideError('register-step1-error');
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if(!email || !password) { 
                showError(btnNext1, 'register-step1-error', "Completa todos los campos"); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'register_step_1');
            formData.append('email', email);
            formData.append('password', password);
            formData.append('csrf_token', csrfToken);

            setLoading(btnNext1, true);

            try {
                const res = await fetchApi(formData);
                if (res.success) {
                    navigateTo(res.next_url); 
                } else {
                    showError(btnNext1, 'register-step1-error', res.message);
                    setLoading(btnNext1, false);
                }
            } catch (err) {
                console.error(err);
                showError(btnNext1, 'register-step1-error', "Ocurrió un error inesperado.");
                setLoading(btnNext1, false);
            }
            return;
        }

        // ------------------------------------------
        // 3. REGISTRO - PASO 2 (Username) -> PASO 3
        // ------------------------------------------
        const btnNext2 = target.closest('#btn-next-2');
        if (btnNext2) {
            e.preventDefault();
            hideError('register-step2-error');

            const username = document.getElementById('username').value;
            
            if(!username) { 
                // NOTA: btnNext2 está dentro de un div flexbox.
                // Insertamos el error DESPUÉS del contenedor padre para que quede abajo.
                showError(btnNext2.parentElement, 'register-step2-error', "Elige un nombre de usuario"); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'initiate_verification');
            formData.append('username', username);
            formData.append('csrf_token', csrfToken);

            setLoading(btnNext2, true);

            try {
                const res = await fetchApi(formData);
                if (res.success) {
                    if(res.debug_code) console.log("Code:", res.debug_code);
                    navigateTo(res.next_url); 
                    // Iniciar timer después de navegar
                    setTimeout(() => startResendTimer(60), 500);
                } else {
                    showError(btnNext2.parentElement, 'register-step2-error', res.message);
                    setLoading(btnNext2, false);
                }
            } catch (err) {
                showError(btnNext2.parentElement, 'register-step2-error', "Error de conexión.");
                setLoading(btnNext2, false);
            }
            return;
        }

        // ------------------------------------------
        // 4. REGISTRO - PASO 3 (Código) -> FINAL
        // ------------------------------------------
        const btnFinish = target.closest('#btn-finish');
        if (btnFinish) {
            e.preventDefault();
            hideError('register-step3-error');

            const code = document.getElementById('verification_code').value;
            
            if(!code) { 
                showError(btnFinish, 'register-step3-error', "Ingresa el código de verificación"); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'complete_register');
            formData.append('code', code);
            formData.append('csrf_token', csrfToken);

            setLoading(btnFinish, true);

            try {
                const res = await fetchApi(formData);
                if (res.success) {
                    window.location.href = res.redirect; // Recarga completa al Home
                } else {
                    showError(btnFinish, 'register-step3-error', res.message);
                    setLoading(btnFinish, false);
                }
            } catch (err) {
                showError(btnFinish, 'register-step3-error', "Error al verificar.");
                setLoading(btnFinish, false);
            }
            return;
        }

        // ------------------------------------------
        // 5. REENVIAR CÓDIGO
        // ------------------------------------------
        const btnResend = target.closest('#btn-resend-code');
        if (btnResend) {
            e.preventDefault();
            hideError('register-step3-error');
            
            // Verificar si está deshabilitado por estilo o clase
            if (btnResend.classList.contains('link-disabled') || btnResend.style.pointerEvents === 'none') {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'resend_code');
            formData.append('csrf_token', csrfToken);

            btnResend.style.opacity = '0.5';
            
            try {
                const res = await fetchApi(formData);
                btnResend.style.opacity = '1';

                if (res.success) {
                    alert("Nuevo código enviado.");
                    if(res.debug_code) console.log("New Code:", res.debug_code);
                    startResendTimer(60);
                } else {
                    showError(btnResend, 'register-step3-error', res.message);
                }
            } catch (err) {
                console.error(err);
                btnResend.style.opacity = '1';
                showError(btnResend, 'register-step3-error', "Error al reenviar código.");
            }
            return;
        }

        // ------------------------------------------
        // LOGOUT
        // ------------------------------------------
        const logoutBtn = target.closest('[data-action="logout"]');
        if (logoutBtn) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'logout');
            formData.append('csrf_token', csrfToken);
            await fetchApi(formData);
            window.location.href = window.BASE_PATH + 'login';
            return;
        }

        // ------------------------------------------
        // UI INTERACTIONS (Botones genéricos en inputs)
        // ------------------------------------------
        const inputActionBtn = target.closest('.btn-input-action');
        if (inputActionBtn) {
            e.preventDefault();
            const action = inputActionBtn.dataset.action;
            
            // ACCIÓN 1: Toggle Password
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
            
            // ACCIÓN 2: Generar Username
            else if (action === 'generate-username') {
                const input = document.getElementById('username');
                if (input) {
                    const now = new Date();
                    
                    // Formatear fecha DDMMYYYY
                    const day = String(now.getDate()).padStart(2, '0');
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const year = now.getFullYear();
                    
                    // Obtener timestamp (milisegundos)
                    const timestamp = now.getTime();

                    // Construir string
                    input.value = `User${day}${month}${year}${timestamp}`;
                }
            }
        }
    });

    // Enter en inputs
    document.body.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const activeInput = document.activeElement;
            if (activeInput && activeInput.closest('#loginContainer')) {
                const btn = document.getElementById('btn-login');
                if(btn) btn.click();
            }
        }
    });
}

// --- UTILIDADES ---

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function fetchApi(formData) {
    try {
        const response = await fetch(window.BASE_PATH + 'api/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        return await response.json();
    } catch (error) {
        console.error("API Error:", error);
        throw error;
    }
}

function setLoading(btn, isLoading) {
    if (isLoading) {
        btn.dataset.originalText = btn.innerText;
        btn.innerHTML = '<div class="spinner-sm"></div>'; // Spinner pequeño
        btn.disabled = true;
        btn.style.opacity = '0.8'; 
    } else {
        btn.innerText = btn.dataset.originalText || 'Continuar';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function handleLogin(btn, token) {
    const inputs = document.querySelectorAll('.auth-card input');
    const formData = new FormData();
    formData.append('action', 'login');
    formData.append('csrf_token', token);
    
    let hasEmpty = false;
    inputs.forEach(input => {
        if(input.name) formData.append(input.name, input.value);
        if(input.required && !input.value) hasEmpty = true;
    });

    if(hasEmpty) { 
        showError(btn, 'login-error', "Por favor llena todos los campos"); 
        return; 
    }

    setLoading(btn, true);
    try {
        const res = await fetchApi(formData);
        if (res.success) {
            window.location.href = res.redirect;
        } else {
            showError(btn, 'login-error', res.message);
            setLoading(btn, false);
        }
    } catch(e) { 
        showError(btn, 'login-error', "Error de conexión");
        setLoading(btn, false); 
    }
}

// --- NUEVAS FUNCIONES DE ERROR UI DINÁMICAS ---

/**
 * Crea y muestra un mensaje de error debajo del elemento de referencia.
 * @param {HTMLElement} referenceNode - El elemento (botón o contenedor) después del cual aparecerá el error.
 * @param {string} errorId - ID único para el div de error.
 * @param {string} message - El texto del mensaje.
 */
function showError(referenceNode, errorId, message) {
    // 1. Verificar si ya existe para actualizarlo
    let errorDiv = document.getElementById(errorId);
    
    // 2. Si no existe, crearlo dinámicamente
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = errorId;
        errorDiv.className = 'auth-inline-error';
        
        // Insertar DESPUÉS del nodo de referencia
        referenceNode.insertAdjacentElement('afterend', errorDiv);
    }
    
    // 3. Actualizar mensaje
    errorDiv.innerText = message;
}

/**
 * Elimina el elemento de error del DOM si existe.
 * @param {string} errorId 
 */
function hideError(errorId) {
    const el = document.getElementById(errorId);
    if (el) {
        el.remove(); // Eliminación completa del DOM
    }
}

// --- TIMER DE REENVÍO ---
function startResendTimer(seconds) {
    const btn = document.getElementById('btn-resend-code');
    const timerSpan = document.getElementById('register-timer');
    
    if (!btn || !timerSpan) return;

    let timeLeft = seconds;
    
    // Aplicar estado deshabilitado visualmente
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
            // Habilitar botón
            btn.classList.remove('link-disabled');
            btn.style.pointerEvents = 'auto';
            btn.style.color = ''; 
            timerSpan.textContent = ''; 
        }
    }, 1000);
}