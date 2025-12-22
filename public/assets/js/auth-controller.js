export function initAuthController() {
    console.log("Auth Controller: Iniciado (Diseño Project Test)");

    setupInteractions();

    const btnLogin = document.getElementById('btn-login');
    const btnRegister = document.getElementById('btn-register');
    const logoutBtn = document.querySelector('[data-action="logout"]');

    // Manejo de Login
    if (btnLogin) {
        btnLogin.addEventListener('click', (e) => {
            e.preventDefault();
            handleAuthClick('login', 'loginContainer', btnLogin);
        });
        
        // Enter para login
        document.querySelectorAll('#loginContainer input').forEach(input => {
            input.addEventListener('keypress', (e) => {
                if(e.key === 'Enter') btnLogin.click();
            });
        });
    }

    // Manejo de Registro
    if (btnRegister) {
        btnRegister.addEventListener('click', (e) => {
            e.preventDefault();
            handleAuthClick('register', 'registerContainer', btnRegister);
        });

        // Enter para registro
        document.querySelectorAll('#registerContainer input').forEach(input => {
            input.addEventListener('keypress', (e) => {
                if(e.key === 'Enter') btnRegister.click();
            });
        });
    }

    // Logout
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            handleLogout();
        });
    }
}

/**
 * Configura las interacciones visuales copiadas de Project Test
 * (Mostrar contraseña y Generar usuario)
 */
function setupInteractions() {
    document.addEventListener('click', (e) => {
        
        // 1. Lógica Toggle Password
        const toggleBtn = e.target.closest('.btn-toggle-password');
        if (toggleBtn) {
            e.preventDefault();
            const container = toggleBtn.closest('.form-group');
            if (container) {
                const input = container.querySelector('input');
                const icon = toggleBtn.querySelector('.material-symbols-rounded');
                
                if (input && icon) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.textContent = 'visibility_off';
                        input.classList.add('password-visible');
                    } else {
                        input.type = 'password';
                        icon.textContent = 'visibility';
                        input.classList.remove('password-visible');
                    }
                }
            }
            return;
        }

        // 2. Lógica Generar Usuario
        const genUserBtn = e.target.closest('.btn-generate-username');
        if (genUserBtn) {
            e.preventDefault();
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                const now = new Date();
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const year = now.getFullYear();
                const timestamp = Date.now().toString().slice(-4);
                
                // Genera: User + Fecha + 4 últimos dígitos de timestamp
                const genName = `User${day}${month}${timestamp}`;
                
                usernameInput.value = genName;
                // Efecto visual de focus
                usernameInput.focus();
            }
            return;
        }
    });
}

async function handleAuthClick(action, containerId, btnElement) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Recolectar datos
    const inputs = container.querySelectorAll('input');
    const formData = new FormData();
    formData.append('action', action);
    
    let hasEmpty = false;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            hasEmpty = true;
            input.style.borderColor = '#d32f2f'; // Rojo error
        } else {
            input.style.borderColor = '#00000020'; // Restaurar borde
        }
        formData.append(input.name, input.value);
    });

    if (hasEmpty) {
        alert('Por favor completa todos los campos.');
        return;
    }

    // Feedback visual en botón
    const originalText = btnElement.innerText;
    btnElement.innerText = 'Procesando...';
    btnElement.disabled = true;
    btnElement.style.opacity = '0.7';

    try {
        const response = await fetch(window.BASE_PATH + 'public/auth-handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = data.redirect;
        } else {
            alert(data.message);
            btnElement.innerText = originalText;
            btnElement.disabled = false;
            btnElement.style.opacity = '1';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Ocurrió un error en la conexión.');
        btnElement.innerText = originalText;
        btnElement.disabled = false;
        btnElement.style.opacity = '1';
    }
}

async function handleLogout() {
    const formData = new FormData();
    formData.append('action', 'logout');

    try {
        const response = await fetch(window.BASE_PATH + 'public/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.location.href = data.redirect;
        }
    } catch (error) {
        console.error('Logout error:', error);
    }
}