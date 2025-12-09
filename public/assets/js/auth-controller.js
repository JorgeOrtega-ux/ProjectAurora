/**
 * AuthController.js
 * Lógica asíncrona para autenticación (Login, Registro, Verify) sin recargar la página en errores.
 */

// Función para mostrar errores en la interfaz
const showError = (message) => {
    // Busca si ya existe una alerta
    let alertBox = document.querySelector('.alert.error');
    
    // Si no existe, crearla dinámicamente dentro de .auth-card
    if (!alertBox) {
        const authCard = document.querySelector('.auth-card');
        if (authCard) {
            alertBox = document.createElement('div');
            alertBox.className = 'alert error';
            alertBox.style.marginTop = '16px';
            alertBox.style.marginBottom = '0';
            // Insertar antes del footer o al final del form
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
        
        // Efecto visual de "sacudida" o resalte opcional
        alertBox.animate([
            { transform: 'translateX(0)' },
            { transform: 'translateX(-5px)' },
            { transform: 'translateX(5px)' },
            { transform: 'translateX(0)' }
        ], { duration: 300 });
    } else {
        alert(message); // Fallback
    }
};

// Función asíncrona para enviar datos
const submitAuthData = async (data) => {
    // Deshabilitar botones para evitar doble envío
    const buttons = document.querySelectorAll('.btn-primary');
    buttons.forEach(btn => {
        btn.disabled = true; 
        btn.style.opacity = '0.7';
        btn.textContent = 'Procesando...';
    });

    try {
        const formData = new FormData();
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }

        const response = await fetch(window.BASE_PATH + 'api/auth_handler.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            // ÉXITO: Redirigir a donde diga el backend
            window.location.href = result.redirect;
        } else {
            // ERROR: Mostrar mensaje y reactivar botones
            showError(result.message || 'Ocurrió un error desconocido.');
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.textContent = 'Continuar'; // O el texto original si lo guardas antes
            });
        }

    } catch (error) {
        console.error("Error de red:", error);
        showError("Error de conexión con el servidor.");
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
            const action = document.getElementById('login-action');

            if(email && password && action && email.value && password.value) {
                submitAuthData({ action: action.value, email: email.value, password: password.value });
            } else {
                showError("Por favor completa todos los campos.");
            }
        }

        // B) REGISTRO PASO 1
        if (e.target && e.target.id === 'btn-register-step-1') {
            e.preventDefault();
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const action = document.getElementById('register-action-1'); 

            if(email && password && action && email.value && password.value) {
                submitAuthData({ action: action.value, email: email.value, password: password.value });
            } else {
                showError("Por favor completa todos los campos.");
            }
        }

        // C) REGISTRO PASO 2
        if (e.target && e.target.id === 'btn-register-step-2') {
            e.preventDefault();
            const username = document.getElementById('username');
            const action = document.getElementById('register-action-2'); 

            if(username && action && username.value) {
                submitAuthData({ action: action.value, username: username.value });
            } else {
                showError("Por favor escribe un nombre de usuario.");
            }
        }

        // D) VERIFICACIÓN
        if (e.target && e.target.id === 'btn-verify') {
            e.preventDefault();
            const code = document.getElementById('code');
            const action = document.getElementById('verify-action');

            if(code && action && code.value) {
                submitAuthData({ action: action.value, code: code.value });
            } else {
                showError("Por favor ingresa el código.");
            }
        }
    });

    // Soporte ENTER
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const btns = ['btn-login', 'btn-register-step-1', 'btn-register-step-2', 'btn-verify'];
            for(let id of btns){
                const btn = document.getElementById(id);
                if(btn && !btn.disabled) { 
                    btn.click(); 
                    break; 
                }
            }
        }
    });
};

export const initAuthController = () => {
    console.log('AuthController: Inicializado (Modo Asíncrono).');
    setupAuthListeners();
};