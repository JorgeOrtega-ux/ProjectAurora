export function initAuthController() {
    console.log("Auth Controller: Iniciado (Multi-step)");

    setupInteractions();

    const btnLogin = document.getElementById('btn-login');
    
    // Botones del flujo de registro
    const btnNext1 = document.getElementById('btn-next-1');
    const btnNext2 = document.getElementById('btn-next-2');
    const btnFinish = document.getElementById('btn-finish');
    const btnsBack = document.querySelectorAll('.btn-back');

    const logoutBtn = document.querySelector('[data-action="logout"]');

    // Manejo de Login (Sin cambios)
    if (btnLogin) {
        btnLogin.addEventListener('click', (e) => {
            e.preventDefault();
            handleLogin();
        });
        document.querySelectorAll('#loginContainer input').forEach(input => {
            input.addEventListener('keypress', (e) => { if(e.key === 'Enter') btnLogin.click(); });
        });
    }

    // === FLUJO DE REGISTRO ===
    // Inicializar el paso correcto basado en la URL
    if (document.getElementById('registerContainer')) {
        initRegisterFlow();
    }

    // Paso 1 -> Paso 2
    if (btnNext1) {
        btnNext1.addEventListener('click', () => {
            const email = document.getElementById('email').value;
            const pass = document.getElementById('password').value;
            if(!email || !pass) { alert("Completa los campos."); return; }
            
            // Navegación virtual
            updateRegisterStep(2);
        });
    }

    // Paso 2 -> Paso 3 (Llamada a API initiate_verification)
    if (btnNext2) {
        btnNext2.addEventListener('click', async () => {
            await handleInitiateVerification(btnNext2);
        });
    }

    // Paso 3 -> Final (Llamada a API complete_register)
    if (btnFinish) {
        btnFinish.addEventListener('click', async () => {
            await handleCompleteRegistration(btnFinish);
        });
    }

    // Botones Volver
    btnsBack.forEach(btn => {
        btn.addEventListener('click', () => {
            const step = btn.dataset.go;
            updateRegisterStep(parseInt(step));
        });
    });

    // Logout
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            handleLogout();
        });
    }
}

// Variables temporales para el registro (memoria volátil)
let tempRegisterData = {
    email: '',
    password: '',
    username: ''
};

function initRegisterFlow() {
    const path = window.location.pathname;
    
    // Detectar en qué URL estamos y mostrar el paso correspondiente
    if (path.includes('/register/verification-account')) {
        // Si el usuario recarga aquí sin datos, devolverlo al 1
        if (!tempRegisterData.email) {
            updateRegisterStep(1); // Redirigir si no hay datos en memoria
        } else {
            showStep(3);
        }
    } else if (path.includes('/register/aditional-data')) {
        if (!tempRegisterData.email) {
            updateRegisterStep(1);
        } else {
            showStep(2);
        }
    } else {
        showStep(1);
    }
}

function updateRegisterStep(stepNumber) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    let newUrl = basePath + 'register';
    
    if (stepNumber === 1) {
        newUrl = basePath + 'register';
    } else if (stepNumber === 2) {
        // Guardar datos del paso 1 antes de cambiar
        tempRegisterData.email = document.getElementById('email').value;
        tempRegisterData.password = document.getElementById('password').value;
        newUrl = basePath + 'register/aditional-data';
    } else if (stepNumber === 3) {
        newUrl = basePath + 'register/verification-account';
    }

    // Actualizar URL sin recargar
    window.history.pushState({ step: stepNumber }, '', newUrl);
    showStep(stepNumber);
}

function showStep(step) {
    // Ocultar todos
    document.querySelectorAll('.reg-step').forEach(el => {
        el.classList.remove('active');
    });
    
    // Mostrar actual
    const current = document.getElementById(`step-${step}`);
    if(current) current.classList.add('active');

    // Actualizar Textos Header
    const title = document.getElementById('step-title');
    const desc = document.getElementById('step-desc');
    
    if(step === 1) {
        title.innerText = "Crear Cuenta";
        desc.innerText = "Ingresa tus datos de acceso";
    } else if(step === 2) {
        title.innerText = "Te damos la bienvenida";
        desc.innerText = "Elige un nombre de usuario";
    } else if(step === 3) {
        title.innerText = "Verifica tu cuenta";
        desc.innerText = "Introduce el código enviado a " + tempRegisterData.email;
    }
}

/**
 * API: Iniciar verificación (Paso 2 -> 3)
 */
async function handleInitiateVerification(btnElement) {
    const username = document.getElementById('username').value;
    if(!username) { alert("Elige un nombre de usuario."); return; }

    tempRegisterData.username = username;

    const formData = new FormData();
    formData.append('action', 'initiate_verification');
    formData.append('email', tempRegisterData.email);
    formData.append('password', tempRegisterData.password);
    formData.append('username', tempRegisterData.username);

    setLoading(btnElement, true);

    try {
        const response = await fetch(window.BASE_PATH + 'api/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        setLoading(btnElement, false);

        if (data.success) {
            // Ir al paso 3
            if(data.debug_code) console.log("Code:", data.debug_code); // Solo para dev
            updateRegisterStep(3);
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error(error);
        setLoading(btnElement, false);
        alert("Error de conexión");
    }
}

/**
 * API: Finalizar registro (Paso 3 -> Home)
 */
async function handleCompleteRegistration(btnElement) {
    const code = document.getElementById('verification_code').value;
    if(!code || code.length < 6) { alert("Código incompleto"); return; }

    const formData = new FormData();
    formData.append('action', 'complete_register');
    formData.append('email', tempRegisterData.email);
    formData.append('code', code);

    setLoading(btnElement, true);

    try {
        const response = await fetch(window.BASE_PATH + 'api/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            window.location.href = data.redirect;
        } else {
            setLoading(btnElement, false);
            alert(data.message);
        }
    } catch (error) {
        console.error(error);
        setLoading(btnElement, false);
        alert("Error de conexión");
    }
}

// === LOGIN NORMAL ===
async function handleLogin() {
    const btnElement = document.getElementById('btn-login');
    const inputs = document.querySelectorAll('#loginContainer input');
    
    const formData = new FormData();
    formData.append('action', 'login');
    inputs.forEach(input => formData.append(input.name, input.value));

    setLoading(btnElement, true);

    try {
        const response = await fetch(window.BASE_PATH + 'api/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.location.href = data.redirect;
        } else {
            setLoading(btnElement, false);
            alert(data.message);
        }
    } catch (e) {
        setLoading(btnElement, false);
    }
}

async function handleLogout() {
    const formData = new FormData();
    formData.append('action', 'logout');
    try {
        const response = await fetch(window.BASE_PATH + 'api/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) window.location.href = data.redirect;
    } catch (e) { console.error(e); }
}

function setLoading(btn, isLoading) {
    if (isLoading) {
        btn.dataset.originalText = btn.innerText;
        btn.innerText = 'Procesando...';
        btn.disabled = true;
        btn.style.opacity = '0.7';
    } else {
        btn.innerText = btn.dataset.originalText || 'Continuar';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

/**
 * Configura las interacciones visuales (Toggle Password, Generate User)
 */
function setupInteractions() {
    document.addEventListener('click', (e) => {
        // Toggle Password
        const toggleBtn = e.target.closest('.btn-toggle-password');
        if (toggleBtn) {
            e.preventDefault();
            const container = toggleBtn.closest('.form-group');
            const input = container.querySelector('input');
            const icon = toggleBtn.querySelector('.material-symbols-rounded');
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

        // Generate Username
        const genUserBtn = e.target.closest('.btn-generate-username');
        if (genUserBtn) {
            e.preventDefault();
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                const now = new Date();
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const timestamp = Date.now().toString().slice(-4);
                usernameInput.value = `User${day}${month}${timestamp}`;
                usernameInput.focus();
            }
        }
    });
}