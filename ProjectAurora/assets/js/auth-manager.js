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

        // --- MANEJAR LOGOUT ---
        // Buscamos la clase o ID del botón de cerrar sesión en el menú
        if (e.target.closest('.menu-link-logout')) {
            // No prevenimos default inmediatamente si es un link, pero aquí actuamos como botón
            await handleLogout();
        }
    });
});

async function handleAuth(action) {
    const emailId = action === 'register' ? 'register-email' : 'login-email';
    const passId = action === 'register' ? 'register-password' : 'login-password';
    
    const email = document.getElementById(emailId).value;
    const password = document.getElementById(passId).value;

    if (!email || !password) {
        alert('Por favor completa todos los campos');
        return;
    }

    // Mostrar estado de carga (opcional: cambiar texto del botón)
    const btnId = action === 'register' ? 'btn-register-submit' : 'btn-login-submit';
    const btn = document.getElementById(btnId);
    const originalText = btn.innerText;
    btn.innerText = 'Procesando...';
    btn.disabled = true;

    try {
        const response = await fetch('includes/auth_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, email, password })
        });

        const result = await response.json();

        if (result.success) {
            // Redirigir a Main
            // Usamos la función global navigateTo de url-manager.js
            if (typeof navigateTo === 'function') {
                navigateTo('main'); 
            } else {
                window.location.reload();
            }
        } else {
            alert(result.message);
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Ocurrió un error inesperado');
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

async function handleLogout() {
    try {
        const response = await fetch('includes/auth_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });

        const result = await response.json();
        
        if (result.success) {
            // Redirigir al login
            if (typeof navigateTo === 'function') {
                navigateTo('login');
            } else {
                window.location.href = 'login';
            }
        }
    } catch (error) {
        console.error('Error logout:', error);
    }
}