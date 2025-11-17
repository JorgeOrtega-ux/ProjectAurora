// assets/js/auth-manager.js

const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

export function initAuthManager() {
    document.body.addEventListener('click', async (e) => {
        // --- REGISTRO ---
        if (e.target.closest('#btn-register-submit')) {
            e.preventDefault();
            await handleAuth('register');
        }

        // --- LOGIN ---
        if (e.target.closest('#btn-login-submit')) {
            e.preventDefault();
            await handleAuth('login');
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

async function handleAuth(action) {
    const emailId = action === 'register' ? 'register-email' : 'login-email';
    const passId = action === 'register' ? 'register-password' : 'login-password';
    const errorDivId = action === 'register' ? 'register-error' : 'login-error';

    const emailInput = document.getElementById(emailId);
    const passInput = document.getElementById(passId);
    const errorDiv = document.getElementById(errorDivId);

    // --- HELPERS ---
    const showError = (msg) => {
        if (errorDiv) {
            errorDiv.innerText = msg;
            errorDiv.classList.add('active');
        } else {
            alert(msg);
        }
    };

    const clearErrors = () => {
        if (errorDiv) {
            errorDiv.innerText = '';
            errorDiv.classList.remove('active');
        }
        // Quitar borde rojo
        if (emailInput) emailInput.classList.remove('input-error');
        if (passInput) passInput.classList.remove('input-error');
    };

    // 1. Limpiar errores previos al intentar de nuevo
    clearErrors();

    if (!emailInput || !passInput) return;

    // 2. Validación Local (Campos vacíos)
    let hasEmptyFields = false;

    if (!emailInput.value.trim()) {
        emailInput.classList.add('input-error');
        hasEmptyFields = true;
    }
    if (!passInput.value.trim()) {
        passInput.classList.add('input-error');
        hasEmptyFields = true;
    }

    if (hasEmptyFields) {
        showError('Por favor completa todos los campos marcados.');
        return;
    }

    const email = emailInput.value;
    const password = passInput.value;

    const btnId = action === 'register' ? 'btn-register-submit' : 'btn-login-submit';
    const btn = document.getElementById(btnId);
    const originalText = btn.innerText;
    
    btn.innerText = 'Procesando...';
    btn.disabled = true;

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, email, password })
        });

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error('Error del servidor: Respuesta no válida.');
        }

        if (result.success) {
            window.location.href = API_BASE_PATH;
        } else {
            // 3. Manejo de Errores del Servidor
            showError(result.message);

            // Lógica inteligente para resaltar el input correcto según el mensaje
            const msgLower = result.message.toLowerCase();

            // Si el error menciona "correo" (ej: "El correo ya está registrado")
            if (msgLower.includes('correo') || msgLower.includes('email')) {
                emailInput.classList.add('input-error');
            }
            // Si el error menciona "credenciales" o "contraseña" (ej: "Credenciales incorrectas")
            // En login, si fallan las credenciales, por seguridad resaltamos ambos o solo password.
            // Aquí resaltamos ambos para indicar que la combinación falló.
            else if (msgLower.includes('credenciales') || msgLower.includes('contraseña') || msgLower.includes('password')) {
                emailInput.classList.add('input-error');
                passInput.classList.add('input-error');
            } 
            // Fallback: Si no sabemos qué es, resaltamos ambos para llamar la atención
            else {
                emailInput.classList.add('input-error');
                passInput.classList.add('input-error');
            }
        }

    } catch (error) {
        console.error('Auth Error:', error);
        showError(error.message || 'Ocurrió un error inesperado');
    } finally {
        if(btn) {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }
}