// assets/js/auth-manager.js

const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

/**
 * Inicializa los listeners para formularios de Login/Registro y Logout.
 */
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

            // UI Feedback (Spinner pequeño)
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

    const emailInput = document.getElementById(emailId);
    const passInput = document.getElementById(passId);

    if (!emailInput || !passInput) return;

    const email = emailInput.value;
    const password = passInput.value;

    if (!email || !password) {
        alert('Por favor completa todos los campos');
        return;
    }

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

        // Intentamos parsear la respuesta
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error('Error del servidor: Respuesta no válida.');
        }

        if (result.success) {
            // Redirigir al home al tener éxito
            window.location.href = API_BASE_PATH;
        } else {
            alert(result.message);
        }

    } catch (error) {
        console.error('Auth Error:', error);
        alert(error.message || 'Ocurrió un error inesperado');
    } finally {
        if(btn) {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }
}