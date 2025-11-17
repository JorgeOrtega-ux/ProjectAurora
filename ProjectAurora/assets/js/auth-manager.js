// assets/js/auth-manager.js

const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

export function initAuthManager() {
    document.body.addEventListener('click', async (e) => {
        
        // --- REGISTRO PASO 1 (Email/Pass) ---
        if (e.target.closest('#btn-register-step1')) {
            e.preventDefault();
            await handleRegisterStep('step1', 'register_step_1', 'register/additional-data');
        }

        // --- REGISTRO PASO 2 (Username) ---
        if (e.target.closest('#btn-register-step2')) {
            e.preventDefault();
            await handleRegisterStep('step2', 'register_step_2', 'register/verification-account');
        }

        // --- REGISTRO PASO 3 (Verificación Final) ---
        if (e.target.closest('#btn-register-step3')) {
            e.preventDefault();
            await handleRegisterStep('step3', 'register_final', 'main');
        }

        // --- LOGIN ---
        if (e.target.closest('#btn-login-submit')) {
            e.preventDefault();
            await handleLogin();
        }

        // --- LOGOUT ---
        const logoutBtn = e.target.closest('.menu-link-logout');
        if (logoutBtn) {
            e.preventDefault(); 
            if (logoutBtn.dataset.processing === "true") return;
            logoutBtn.dataset.processing = "true";

            const iconContainer = document.createElement('div');
            iconContainer.className = 'menu-link-icon'; 
            const spinner = document.createElement('div');
            spinner.className = 'small-spinner';
            iconContainer.appendChild(spinner);
            logoutBtn.appendChild(iconContainer);

            setTimeout(() => {
                window.location.href = API_BASE_PATH + 'config/logout.php';
            }, 50);
        }
    });
}

// --- MANEJADOR DE PASOS DE REGISTRO ---
async function handleRegisterStep(stepName, apiAction, nextRoute) {
    let payload = { action: apiAction };
    let btnId, errorId, inputIds = [];

    // Recolectar datos según el paso
    if (stepName === 'step1') {
        const emailIn = document.getElementById('reg-email');
        const passIn = document.getElementById('reg-password');
        if (!emailIn || !passIn) return;
        
        payload.email = emailIn.value;
        payload.password = passIn.value;
        btnId = 'btn-register-step1';
        errorId = 'register-error';
        inputIds = ['reg-email', 'reg-password'];
    } 
    else if (stepName === 'step2') {
        const userIn = document.getElementById('reg-username');
        if (!userIn) return;

        payload.username = userIn.value;
        btnId = 'btn-register-step2';
        errorId = 'register-error-2';
        inputIds = ['reg-username'];
    } 
    else if (stepName === 'step3') {
        const codeIn = document.getElementById('reg-code');
        if (!codeIn) return;

        payload.code = codeIn.value;
        btnId = 'btn-register-step3';
        errorId = 'register-error-3';
        inputIds = ['reg-code'];
    }

    // Validar campos vacíos visualmente
    let hasEmpty = false;
    inputIds.forEach(id => {
        const el = document.getElementById(id);
        el.classList.remove('input-error');
        if(!el.value.trim()) {
            el.classList.add('input-error');
            hasEmpty = true;
        }
    });

    if (hasEmpty) {
        const errDiv = document.getElementById(errorId);
        if(errDiv) {
            errDiv.innerText = "Por favor completa los campos requeridos.";
            errDiv.classList.add('active');
        }
        return;
    }

    await sendAuthRequest(payload, btnId, errorId, nextRoute);
}

// Función genérica para enviar petición y navegar
async function sendAuthRequest(payload, btnId, errorId, nextPath) {
    const btn = document.getElementById(btnId);
    const errorDiv = document.getElementById(errorId);
    const originalText = btn.innerText;

    // UI Loading
    btn.innerText = 'Procesando...';
    btn.disabled = true;
    if(errorDiv) {
        errorDiv.innerText = '';
        errorDiv.classList.remove('active');
    }

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error("Respuesta inválida:", text);
            throw new Error('Error del servidor: Respuesta no válida.');
        }

        if (result.success) {
            // Navegación
            if (nextPath === 'main') {
                window.location.href = API_BASE_PATH;
            } else {
                // Forzar cambio de URL y recarga del módulo (SPA)
                // Asumimos que url-manager maneja popstate, así que empujamos estado y disparamos evento
                const fullPath = API_BASE_PATH + nextPath;
                window.history.pushState({ section: nextPath }, '', fullPath);
                
                // Disparar evento manual para que url-manager detecte el cambio si usa popstate
                // OJO: popstate solo salta con back/forward. Para ir adelante manualmente llamamos a navigateTo si es posible,
                // pero como no exportamos 'navigateTo' globalmente, forzamos un reload o usamos un truco:
                
                // Truco para disparar el router sin modificar url-manager:
                // Simulamos clic en un enlace oculto o recargamos si es necesario. 
                // Dado que el script es módulo, lo más fácil es reload si no tenemos acceso a navigateTo,
                // PERO lo ideal es que 'url-manager' exponga navigateTo a window. 
                // SI NO: location.href recarga la página, lo cual es seguro.
                
                window.location.href = fullPath; 
            }
        } else {
            if(errorDiv) {
                errorDiv.innerText = result.message;
                errorDiv.classList.add('active');
            } else {
                alert(result.message);
            }
        }

    } catch (error) {
        if(errorDiv) {
            errorDiv.innerText = error.message || 'Error desconocido';
            errorDiv.classList.add('active');
        }
    } finally {
        if(btn) {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }
}

async function handleLogin() {
    const emailInput = document.getElementById('login-email');
    const passInput = document.getElementById('login-password');
    const errorDiv = document.getElementById('login-error');

    // Limpiar
    if(errorDiv) errorDiv.classList.remove('active');
    emailInput.classList.remove('input-error');
    passInput.classList.remove('input-error');

    if (!emailInput.value.trim() || !passInput.value.trim()) {
        if(!emailInput.value.trim()) emailInput.classList.add('input-error');
        if(!passInput.value.trim()) passInput.classList.add('input-error');
        return;
    }

    const btn = document.getElementById('btn-login-submit');
    const originalText = btn.innerText;
    btn.innerText = 'Iniciando...';
    btn.disabled = true;

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'login', 
                email: emailInput.value, 
                password: passInput.value 
            })
        });

        const res = await response.json();
        if (res.success) {
            window.location.href = API_BASE_PATH;
        } else {
            if(errorDiv) {
                errorDiv.innerText = res.message;
                errorDiv.classList.add('active');
            }
            emailInput.classList.add('input-error');
            passInput.classList.add('input-error');
        }
    } catch (e) {
        if(errorDiv) {
            errorDiv.innerText = "Error de conexión";
            errorDiv.classList.add('active');
        }
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}