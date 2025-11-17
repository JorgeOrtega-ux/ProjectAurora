document.addEventListener('DOMContentLoaded', () => {

    // Listener global para manejar eventos en elementos dinámicos
    document.body.addEventListener('click', async (e) => {

        // --- MANEJAR REGISTRO ---
        if (e.target.closest('#btn-register-submit')) {
            e.preventDefault();
            await handleAuth('register');
        }

        // --- MANEJAR LOGIN ---
        if (e.target.closest('#btn-login-submit')) {
            e.preventDefault();
            await handleAuth('login');
        }

        // --- MANEJAR LOGOUT (CON SPINNER DINÁMICO) ---
        const logoutBtn = e.target.closest('.menu-link-logout');
        if (logoutBtn) {
            e.preventDefault(); 

            // Evitamos que se pulse varias veces
            if (logoutBtn.dataset.processing === "true") return;
            logoutBtn.dataset.processing = "true";

            // 1. Crear el nuevo contenedor 'menu-link-icon'
            const iconContainer = document.createElement('div');
            iconContainer.className = 'menu-link-icon'; // Misma clase para mantener consistencia de tamaño
            
            // 2. Crear el spinner y meterlo dentro
            const spinner = document.createElement('div');
            spinner.className = 'small-spinner';
            iconContainer.appendChild(spinner);

            // 3. Agregar el nuevo icono a la derecha del texto
            logoutBtn.appendChild(iconContainer);

            // 4. Redirigir a logout.php (Pequeño delay para asegurar que el render se vea)
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 50);
        }
    });
});

// Definimos el path base para evitar problemas de rutas relativas
const API_BASE_PATH = '/ProjectAurora/';

async function handleAuth(action) {
    const emailId = action === 'register' ? 'register-email' : 'login-email';
    const passId = action === 'register' ? 'register-password' : 'login-password';

    // Verificación de existencia de elementos
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

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Respuesta no válida:', text);
            throw new Error('Error del servidor.');
        }

        if (result.success) {
            if (typeof navigateTo === 'function') {
                navigateTo('main');
            } else {
                window.location.href = API_BASE_PATH;
            }
        } else {
            alert(result.message);
        }

    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'Ocurrió un error inesperado');
    } finally {
        if(btn) {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }
}