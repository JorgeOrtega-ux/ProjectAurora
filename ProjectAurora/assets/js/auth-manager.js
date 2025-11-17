const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

export function initAuthManager() {
    document.body.addEventListener('click', async (e) => {
        
        // --- PASO 1 -> 2 ---
        if (e.target.closest('#btn-register-step1')) {
            e.preventDefault();
            await handleRegisterStep('step1', 'register_step_1', 2, 'register/additional-data');
        }

        // --- PASO 2 -> 3 ---
        if (e.target.closest('#btn-register-step2')) {
            e.preventDefault();
            await handleRegisterStep('step2', 'register_step_2', 3, 'register/verification-account');
        }

        // --- PASO 3 -> FINAL ---
        if (e.target.closest('#btn-register-step3')) {
            e.preventDefault();
            await handleRegisterStep('step3', 'register_final', 'main', null);
        }

        // --- BOTÓN VOLVER (Paso 2) ---
        if (e.target.closest('#btn-back-step1')) {
            e.preventDefault();
            switchRegisterStep(1, 'register');
        }

        // --- TOGGLE PASSWORD (OJO/VISIBILIDAD) ---
        // Excluimos el botón mágico para que no intente cambiar type="text/password"
        if (e.target.closest('.password-toggle-btn') && !e.target.closest('.username-magic-btn')) {
            const btn = e.target.closest('.password-toggle-btn');
            const input = btn.previousElementSibling; 
            if (input && input.tagName === 'INPUT') {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.querySelector('span').innerText = 'visibility_off';
                } else {
                    input.type = 'password';
                    btn.querySelector('span').innerText = 'visibility';
                }
            }
        }

        // --- GENERADOR DE USUARIO MÁGICO ---
        if (e.target.closest('.username-magic-btn')) {
            e.preventDefault();
            const input = document.getElementById('reg-username');
            if (input) {
                input.value = generateMagicUsername();
                input.focus();
                input.classList.remove('input-error'); // Limpiar error visual si había
            }
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

// Genera formato: user20251117_133529wl
function generateMagicUsername() {
    const now = new Date();
    const pad = (num) => num.toString().padStart(2, '0');
    
    const year = now.getFullYear();
    const month = pad(now.getMonth() + 1);
    const day = pad(now.getDate());
    const hours = pad(now.getHours());
    const minutes = pad(now.getMinutes());
    const seconds = pad(now.getSeconds());
    
    const timePart = `${year}${month}${day}_${hours}${minutes}${seconds}`;
    
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let randomPart = '';
    for (let i = 0; i < 2; i++) {
        randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    return `user${timePart}${randomPart}`;
}

function switchRegisterStep(stepNumber, urlPath) {
    document.getElementById('step-container-1').style.display = 'none';
    document.getElementById('step-container-2').style.display = 'none';
    document.getElementById('step-container-3').style.display = 'none';
    
    const target = document.getElementById(`step-container-${stepNumber}`);
    if (target) {
        target.style.display = 'block';
        if (urlPath) {
            const newUrl = API_BASE_PATH + urlPath;
            history.pushState({ section: urlPath }, '', newUrl);
        }
    }
}

// --- VALIDACIONES ESTRICTAS (JS) ---

function isValidEmailDomain(email) {
    // Regex Estricta:
    // ^ ... $   -> Inicio y fin de cadena (Evita basura antes o después)
    // @( ... )  -> Solo estos dominios
    // \.[a-z]+  -> Extensión
    // $         -> IMPORTANTE: Obliga a que termine ahí.
    const regex = /^[^@\s]+@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i;
    return regex.test(email);
}

function isValidUsername(username) {
    // Regex: Min 8, Max 32, Solo letras, números y guión bajo
    const regex = /^[a-zA-Z0-9_]{8,32}$/;
    return regex.test(username);
}

async function handleRegisterStep(stepName, apiAction, nextStep, nextUrl) {
    let payload = { action: apiAction };
    let btnId, errorId, inputIds = [];
    let errorMessage = '';

    // --- VALIDACIÓN PASO 1 ---
    if (stepName === 'step1') {
        const emailIn = document.getElementById('reg-email');
        const passIn = document.getElementById('reg-password');
        if (!emailIn || !passIn) return;
        
        const emailVal = emailIn.value.trim();
        const passVal = passIn.value;

        if (!isValidEmailDomain(emailVal)) {
            errorMessage = "Correo inválido. Solo se permite: Gmail, Outlook, iCloud, Yahoo.";
            emailIn.classList.add('input-error');
        } else if (passVal.length < 8) {
            errorMessage = "La contraseña debe tener al menos 8 caracteres.";
            passIn.classList.add('input-error');
        }

        payload.email = emailVal;
        payload.password = passVal;
        btnId = 'btn-register-step1'; errorId = 'register-error-1'; inputIds = ['reg-email', 'reg-password'];
    
    // --- VALIDACIÓN PASO 2 ---
    } else if (stepName === 'step2') {
        const userIn = document.getElementById('reg-username');
        if (!userIn) return;

        const userVal = userIn.value.trim();
        if (!isValidUsername(userVal)) {
            errorMessage = "Usuario inválido: 8-32 caracteres. Solo letras, números y '_'.";
            userIn.classList.add('input-error');
        }

        payload.username = userVal;
        btnId = 'btn-register-step2'; errorId = 'register-error-2'; inputIds = ['reg-username'];
    
    // --- PASO 3 ---
    } else if (stepName === 'step3') {
        const codeIn = document.getElementById('reg-code');
        if (!codeIn) return;
        payload.code = codeIn.value;
        btnId = 'btn-register-step3'; errorId = 'register-error-3'; inputIds = ['reg-code'];
    }

    // Limpieza UI
    inputIds.forEach(id => document.getElementById(id).classList.remove('input-error'));
    const errorDiv = document.getElementById(errorId);
    if(errorDiv) { errorDiv.innerText = ''; errorDiv.classList.remove('active'); }

    // Check vacíos
    let hasEmpty = false;
    inputIds.forEach(id => {
        const el = document.getElementById(id);
        if(!el.value.trim()) { el.classList.add('input-error'); hasEmpty = true; }
    });

    if (hasEmpty) {
        if(errorDiv) { errorDiv.innerText = "Todos los campos son requeridos."; errorDiv.classList.add('active'); }
        return;
    }

    // Check errores de lógica (regex)
    if (errorMessage) {
        if(errorDiv) { errorDiv.innerText = errorMessage; errorDiv.classList.add('active'); }
        return;
    }

    await sendAuthRequest(payload, btnId, errorId, nextStep, nextUrl);
}

async function sendAuthRequest(payload, btnId, errorId, nextStep, nextUrl) {
    const btn = document.getElementById(btnId);
    const errorDiv = document.getElementById(errorId);
    const originalText = btn ? btn.innerText : '';
    if(btn) { btn.innerText = 'Procesando...'; btn.disabled = true; }
    
    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (result.success) {
            if (nextStep === 'main') {
                window.location.href = API_BASE_PATH;
            } else {
                switchRegisterStep(nextStep, nextUrl);
            }
        } else {
            if(errorDiv) { errorDiv.innerText = result.message; errorDiv.classList.add('active'); }
        }
    } catch (error) {
        if(errorDiv) { errorDiv.innerText = "Error de conexión"; errorDiv.classList.add('active'); }
    } finally {
        if(btn) { btn.innerText = originalText; btn.disabled = false; }
    }
}

async function handleLogin() {
    const emailInput = document.getElementById('login-email');
    const passInput = document.getElementById('login-password');
    const errorDiv = document.getElementById('login-error');

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