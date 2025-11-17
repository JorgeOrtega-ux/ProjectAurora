const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

export function initAuthManager() {
    document.body.addEventListener('click', async (e) => {
        
        // --- PASO 1 -> 2 ---
        if (e.target.closest('#btn-register-step1')) {
            e.preventDefault();
            // Al éxito: Cambiar URL a 'register/additional-data' y mostrar Div 2
            await handleRegisterStep('step1', 'register_step_1', 2, 'register/additional-data');
        }

        // --- PASO 2 -> 3 ---
        if (e.target.closest('#btn-register-step2')) {
            e.preventDefault();
            // Al éxito: Cambiar URL a 'register/verification-account' y mostrar Div 3
            await handleRegisterStep('step2', 'register_step_2', 3, 'register/verification-account');
        }

        // --- PASO 3 -> FINAL ---
        if (e.target.closest('#btn-register-step3')) {
            e.preventDefault();
            await handleRegisterStep('step3', 'register_final', 'main', null);
        }

        // --- BOTONES VOLVER ---
        if (e.target.closest('#btn-back-step1')) {
            e.preventDefault();
            switchRegisterStep(1, 'register');
        }
        if (e.target.closest('#btn-back-step2')) {
            e.preventDefault();
            switchRegisterStep(2, 'register/additional-data');
        }

        // ... (Código Login y Logout sin cambios) ...
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

function switchRegisterStep(stepNumber, urlPath) {
    document.getElementById('step-container-1').style.display = 'none';
    document.getElementById('step-container-2').style.display = 'none';
    document.getElementById('step-container-3').style.display = 'none';
    
    const target = document.getElementById(`step-container-${stepNumber}`);
    if (target) {
        target.style.display = 'block';
        // IMPORTANTE: Actualizar URL sin recargar
        if (urlPath) {
            const newUrl = API_BASE_PATH + urlPath;
            history.pushState({ section: urlPath }, '', newUrl);
        }
    }
}

async function handleRegisterStep(stepName, apiAction, nextStep, nextUrl) {
    // ... (Lógica de recolección de datos idéntica a la anterior) ...
    let payload = { action: apiAction };
    let btnId, errorId, inputIds = [];

    if (stepName === 'step1') {
        const emailIn = document.getElementById('reg-email');
        const passIn = document.getElementById('reg-password');
        if (!emailIn || !passIn) return;
        payload.email = emailIn.value;
        payload.password = passIn.value;
        btnId = 'btn-register-step1'; errorId = 'register-error-1'; inputIds = ['reg-email', 'reg-password'];
    } else if (stepName === 'step2') {
        const userIn = document.getElementById('reg-username');
        if (!userIn) return;
        payload.username = userIn.value;
        btnId = 'btn-register-step2'; errorId = 'register-error-2'; inputIds = ['reg-username'];
    } else if (stepName === 'step3') {
        const codeIn = document.getElementById('reg-code');
        if (!codeIn) return;
        payload.code = codeIn.value;
        btnId = 'btn-register-step3'; errorId = 'register-error-3'; inputIds = ['reg-code'];
    }

    // Validar vacíos
    let hasEmpty = false;
    inputIds.forEach(id => {
        const el = document.getElementById(id);
        el.classList.remove('input-error');
        if(!el.value.trim()) { el.classList.add('input-error'); hasEmpty = true; }
    });
    if (hasEmpty) {
        const err = document.getElementById(errorId);
        if(err) { err.innerText = "Campos requeridos."; err.classList.add('active'); }
        return;
    }

    await sendAuthRequest(payload, btnId, errorId, nextStep, nextUrl);
}

async function sendAuthRequest(payload, btnId, errorId, nextStep, nextUrl) {
    const btn = document.getElementById(btnId);
    const errorDiv = document.getElementById(errorId);
    const originalText = btn ? btn.innerText : '';
    if(btn) { btn.innerText = 'Procesando...'; btn.disabled = true; }
    if(errorDiv) { errorDiv.innerText = ''; errorDiv.classList.remove('active'); }

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (result.success) {
            if (nextStep === 'main') {
                window.location.href = API_BASE_PATH;
            } else {
                // Cambio visual + URL
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

// Incluir handleLogin() igual que antes...
async function handleLogin() {
    // ... (Copia tu función handleLogin original aquí) ...
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