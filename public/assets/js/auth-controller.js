/**
 * AuthController.js
 * Lógica asíncrona para autenticación (Login, Registro, Verify) sin recargar la página en errores.
 * Validaciones estrictas añadidas.
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
        
        // Efecto visual de "sacudida"
        alertBox.animate([
            { transform: 'translateX(0)' },
            { transform: 'translateX(-5px)' },
            { transform: 'translateX(5px)' },
            { transform: 'translateX(0)' }
        ], { duration: 300 });
    } 
    // SE ELIMINÓ EL FALLBACK: else { alert(message); }
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
                btn.textContent = 'Continuar'; 
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

        // B) REGISTRO PASO 1 (Validaciones Estrictas)
        if (e.target && e.target.id === 'btn-register-step-1') {
            e.preventDefault();
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const action = document.getElementById('register-action-1'); 

            if(email && password && action && email.value && password.value) {
                
                const emailVal = email.value.trim();
                const passVal = password.value;
                const allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'yahoo.com'];

                // 1. Validar formato básico y separación
                if (!emailVal.includes('@')) {
                    showError("Formato de correo inválido.");
                    return;
                }

                // Obtener dominio y prefijo
                const lastAtPos = emailVal.lastIndexOf('@');
                const localPart = emailVal.substring(0, lastAtPos);
                const domainPart = emailVal.substring(lastAtPos + 1).toLowerCase();

                // 2. Validar 4 caracteres antes del @
                if (localPart.length < 4) {
                    showError("El correo debe tener al menos 4 caracteres antes del @.");
                    return;
                }

                // 3. Validar Dominios permitidos
                if (!allowedDomains.includes(domainPart)) {
                    showError("Dominio no permitido. Use: gmail, outlook, hotmail, icloud o yahoo.");
                    return;
                }

                // 4. Validar contraseña (mínimo 8 caracteres)
                if (passVal.length < 8) {
                    showError("La contraseña debe tener al menos 8 caracteres.");
                    return;
                }

                // Todo OK -> Enviar
                submitAuthData({ action: action.value, email: emailVal, password: passVal });
            } else {
                showError("Por favor completa todos los campos.");
            }
        }

        // C) REGISTRO PASO 2 (Validación Usuario)
        if (e.target && e.target.id === 'btn-register-step-2') {
            e.preventDefault();
            const username = document.getElementById('username');
            const action = document.getElementById('register-action-2'); 

            if(username && action && username.value) {
                const userVal = username.value.trim();

                // 1. Validar longitud de usuario (mínimo 6)
                if (userVal.length < 6) {
                    showError("El nombre de usuario debe tener al menos 6 caracteres.");
                    return;
                }

                submitAuthData({ action: action.value, username: userVal });
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
    console.log('AuthController: Inicializado (Modo Asíncrono - Validaciones Estrictas).');
    setupAuthListeners();
};