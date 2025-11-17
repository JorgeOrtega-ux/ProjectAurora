const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

export function initAuthManager() {
    document.body.addEventListener('click', async (e) => {
        
        // --- PASO 1 -> 2 (REGISTRO) ---
        if (e.target.closest('[data-action="register-step1"]')) {
            e.preventDefault();
            await handleRegisterStep('step1', 'register_step_1', 2, 'register/additional-data');
        }

        // --- PASO 2 -> 3 (REGISTRO) ---
        if (e.target.closest('[data-action="register-step2"]')) {
            e.preventDefault();
            await handleRegisterStep('step2', 'register_step_2', 3, 'register/verification-account');
        }

        // --- PASO 3 -> FINAL (REGISTRO) ---
        if (e.target.closest('[data-action="register-step3"]')) {
            e.preventDefault();
            await handleRegisterStep('step3', 'register_final', 'main', null);
        }

        // --- BOTÓN VOLVER (Paso 2 Registro) ---
        if (e.target.closest('[data-action="register-back-step1"]')) {
            e.preventDefault();
            switchRegisterStep(1, 'register');
        }

        // =================================================
        // LÓGICA RECUPERACIÓN (Forgot Password)
        // =================================================
        
        // Paso 1: Enviar Email
        if (e.target.closest('[data-action="rec-step1"]')) {
            e.preventDefault();
            await handleRecoveryStep('step1');
        }
        
        // Paso 2: Verificar Código
        if (e.target.closest('[data-action="rec-step2"]')) {
            e.preventDefault();
            await handleRecoveryStep('step2');
        }
        
        // Paso 3: Cambiar Contraseña
        if (e.target.closest('[data-action="rec-step3"]')) {
            e.preventDefault();
            await handleRecoveryStep('step3');
        }

        // Botón "Reenviar / Cambiar correo"
        if (e.target.closest('[data-action="rec-resend"]')) {
            e.preventDefault();
            document.querySelector('[data-step="rec-1"]').style.display = 'block';
            document.querySelector('[data-step="rec-2"]').style.display = 'none';
            document.querySelector('[data-step="rec-3"]').style.display = 'none';
        }
        // =================================================

        // --- TOGGLE PASSWORD (OJO/VISIBILIDAD) ---
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
            const input = document.querySelector('[data-input="reg-username"]');
            if (input) {
                input.value = generateMagicUsername();
                input.focus();
                input.classList.remove('input-error'); 
            }
        }

        // --- LOGIN (STEP 1) ---
        if (e.target.closest('[data-action="login-submit"]')) {
            e.preventDefault();
            await handleLogin();
        }

        // --- LOGIN (STEP 2 - 2FA SUBMIT) ---
        if (e.target.closest('[data-action="login-2fa-submit"]')) {
            e.preventDefault();
            await handleLogin2FA();
        }

        // --- LOGIN (STEP 2 - BOTÓN ATRÁS) ---
        if (e.target.closest('[data-action="login-2fa-back"]')) {
            e.preventDefault();
            // Restaurar vista al paso 1
            document.querySelector('[data-step="login-1"]').style.display = 'block';
            document.querySelector('[data-step="login-2"]').style.display = 'none';
            
            // Restaurar URL a /login
            const loginUrl = API_BASE_PATH + 'login';
            history.pushState({ section: 'login' }, '', loginUrl);
            
            // Limpiar errores previos del paso 2
            const err = document.querySelector('[data-error="login-2fa"]');
            if(err) { err.innerText = ''; err.classList.remove('active'); }
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

// Helper para obtener el token CSRF
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Helper para selectores (simplifica el código)
function qs(selector) {
    return document.querySelector(selector);
}

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
    qs('[data-step="register-1"]').style.display = 'none';
    qs('[data-step="register-2"]').style.display = 'none';
    qs('[data-step="register-3"]').style.display = 'none';
    
    const target = qs(`[data-step="register-${stepNumber}"]`);
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
    const regex = /^[^@\s]+@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i;
    return regex.test(email);
}

function isValidUsername(username) {
    const regex = /^[a-zA-Z0-9_]{8,32}$/;
    return regex.test(username);
}

async function handleRegisterStep(stepName, apiAction, nextStep, nextUrl) {
    let payload = { action: apiAction };
    let btnSelector, errorSelector, inputSelectors = [];
    let errorMessage = '';

    // --- VALIDACIÓN PASO 1 ---
    if (stepName === 'step1') {
        const emailIn = qs('[data-input="reg-email"]');
        const passIn = qs('[data-input="reg-password"]');
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
        btnSelector = '[data-action="register-step1"]'; 
        errorSelector = '[data-error="register-1"]'; 
        inputSelectors = ['[data-input="reg-email"]', '[data-input="reg-password"]'];
    
    // --- VALIDACIÓN PASO 2 ---
    } else if (stepName === 'step2') {
        const userIn = qs('[data-input="reg-username"]');
        if (!userIn) return;

        const userVal = userIn.value.trim();
        if (!isValidUsername(userVal)) {
            errorMessage = "Usuario inválido: 8-32 caracteres. Solo letras, números y '_'.";
            userIn.classList.add('input-error');
        }

        payload.username = userVal;
        btnSelector = '[data-action="register-step2"]'; 
        errorSelector = '[data-error="register-2"]'; 
        inputSelectors = ['[data-input="reg-username"]'];
    
    // --- PASO 3 ---
    } else if (stepName === 'step3') {
        const codeIn = qs('[data-input="reg-code"]');
        if (!codeIn) return;
        payload.code = codeIn.value;
        btnSelector = '[data-action="register-step3"]'; 
        errorSelector = '[data-error="register-3"]'; 
        inputSelectors = ['[data-input="reg-code"]'];
    }

    // Limpieza UI
    inputSelectors.forEach(sel => qs(sel).classList.remove('input-error'));
    const errorDiv = qs(errorSelector);
    if(errorDiv) { errorDiv.innerText = ''; errorDiv.classList.remove('active'); }

    // Check vacíos
    let hasEmpty = false;
    inputSelectors.forEach(sel => {
        const el = qs(sel);
        if(!el.value.trim()) { el.classList.add('input-error'); hasEmpty = true; }
    });

    if (hasEmpty) {
        if(errorDiv) { errorDiv.innerText = "Todos los campos son requeridos."; errorDiv.classList.add('active'); }
        return;
    }

    if (errorMessage) {
        if(errorDiv) { errorDiv.innerText = errorMessage; errorDiv.classList.add('active'); }
        return;
    }

    await sendAuthRequest(payload, btnSelector, errorSelector, nextStep, nextUrl);
}

// --- LÓGICA DE RECUPERACIÓN (FORGOT PASSWORD) ---
async function handleRecoveryStep(stepName) {
    let payload = { action: '' };
    let btnSelector, errorSelector, inputSelectors = [];
    let nextStepDataAttr = '';

    // Step 1: Enviar Email
    if (stepName === 'step1') {
        const emailIn = qs('[data-input="rec-email"]');
        if(!emailIn) return;
        if(!emailIn.value.trim()) { 
            emailIn.classList.add('input-error'); 
            return; 
        }
        
        payload = { action: 'recovery_step_1', email: emailIn.value.trim() };
        btnSelector = '[data-action="rec-step1"]'; 
        errorSelector = '[data-error="rec-1"]'; 
        inputSelectors = ['[data-input="rec-email"]'];
        nextStepDataAttr = 'rec-2';

    // Step 2: Enviar Código
    } else if (stepName === 'step2') {
        const codeIn = qs('[data-input="rec-code"]');
        if(!codeIn) return;
        if(!codeIn.value.trim()) { 
            codeIn.classList.add('input-error'); 
            return; 
        }
        
        payload = { action: 'recovery_step_2', code: codeIn.value.trim() };
        btnSelector = '[data-action="rec-step2"]'; 
        errorSelector = '[data-error="rec-2"]'; 
        inputSelectors = ['[data-input="rec-code"]'];
        nextStepDataAttr = 'rec-3';

    // Step 3: Nueva Contraseña
    } else if (stepName === 'step3') {
        const passIn = qs('[data-input="rec-pass"]');
        if(!passIn) return;
        
        if(passIn.value.length < 8) { 
            passIn.classList.add('input-error'); 
            const err = qs('[data-error="rec-3"]');
            if(err) {
                err.innerText = 'Mínimo 8 caracteres';
                err.classList.add('active');
            }
            return; 
        }

        payload = { action: 'recovery_final', password: passIn.value };
        btnSelector = '[data-action="rec-step3"]'; 
        errorSelector = '[data-error="rec-3"]'; 
        inputSelectors = ['[data-input="rec-pass"]'];
    }

    // UI Loading
    const btn = qs(btnSelector);
    const errorDiv = qs(errorSelector);
    let originalContent = '';
    
    if(btn) { 
        originalContent = btn.innerHTML;
        btn.innerHTML = '<div class="btn-spinner"></div>'; 
        btn.disabled = true; 
    }
    
    inputSelectors.forEach(sel => qs(sel).classList.remove('input-error'));
    if(errorDiv) { errorDiv.innerText = ''; errorDiv.classList.remove('active'); }

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            }, 
            body: JSON.stringify(payload)
        });
        const res = await response.json();

        if (res.success) {
            if (stepName === 'step3') {
                window.location.href = API_BASE_PATH + 'login';
            } else {
                qs('[data-step="rec-1"]').style.display = 'none';
                qs('[data-step="rec-2"]').style.display = 'none';
                qs('[data-step="rec-3"]').style.display = 'none';
                
                const next = qs(`[data-step="${nextStepDataAttr}"]`);
                if(next) next.style.display = 'block';
                
                if(stepName === 'step1') {
                    const display = qs('[data-display="rec-email"]');
                    if(display) display.innerText = payload.email;
                }
                
                if(btn) { btn.innerHTML = originalContent; btn.disabled = false; }
            }
        } else {
            if(errorDiv) { errorDiv.innerText = res.message; errorDiv.classList.add('active'); }
            if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
        }
    } catch (e) {
        if(errorDiv) { errorDiv.innerText = "Error de conexión"; errorDiv.classList.add('active'); }
        if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
    }
}

async function sendAuthRequest(payload, btnSelector, errorSelector, nextStep, nextUrl) {
    const btn = qs(btnSelector);
    const errorDiv = qs(errorSelector);
    let originalContent = '';

    // UI Loading
    if(btn) { 
        originalContent = btn.innerHTML;
        btn.innerHTML = '<div class="btn-spinner"></div>'; 
        btn.disabled = true; 
    }
    
    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            }, 
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (result.success) {
            if (nextStep === 'main') {
                window.location.href = API_BASE_PATH;
            } else {
                switchRegisterStep(nextStep, nextUrl);
                if(btn) { btn.innerHTML = originalContent; btn.disabled = false; }
            }
        } else {
            if(errorDiv) { errorDiv.innerText = result.message; errorDiv.classList.add('active'); }
            if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
        }
    } catch (error) {
        if(errorDiv) { errorDiv.innerText = "Error de conexión"; errorDiv.classList.add('active'); }
        if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
    }
}

// --- LÓGICA DE LOGIN ---
async function handleLogin() {
    const emailInput = qs('[data-input="login-email"]');
    const passInput = qs('[data-input="login-password"]');
    const errorDiv = qs('[data-error="login-error"]');

    if(errorDiv) errorDiv.classList.remove('active');
    emailInput.classList.remove('input-error');
    passInput.classList.remove('input-error');

    if (!emailInput.value.trim() || !passInput.value.trim()) {
        if(!emailInput.value.trim()) emailInput.classList.add('input-error');
        if(!passInput.value.trim()) passInput.classList.add('input-error');
        return;
    }

    const btn = qs('[data-action="login-submit"]');
    const originalContent = btn.innerHTML; 

    btn.innerHTML = '<div class="btn-spinner"></div>';
    btn.disabled = true;

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ 
                action: 'login', 
                email: emailInput.value, 
                password: passInput.value 
            })
        });

        const res = await response.json();
        
        if (res.success) {
            if (res.require_2fa) {
                // History API
                const nextUrl = API_BASE_PATH + 'login/verification-additional';
                history.pushState({ section: 'login/verification-additional' }, '', nextUrl);

                // Cambio UI
                qs('[data-step="login-1"]').style.display = 'none';
                qs('[data-step="login-2"]').style.display = 'block';
                
                const displayEmail = qs('[data-display="login-2fa-email"]');
                if(displayEmail && res.masked_email) {
                    displayEmail.innerText = res.masked_email;
                }

                btn.innerHTML = originalContent;
                btn.disabled = false;
                
                setTimeout(() => {
                    const codeField = qs('[data-input="login-2fa-code"]');
                    if(codeField) codeField.focus();
                }, 100);

            } else {
                window.location.href = API_BASE_PATH;
            }
        } else {
            if(errorDiv) {
                errorDiv.innerText = res.message;
                errorDiv.classList.add('active');
            }
            emailInput.classList.add('input-error');
            passInput.classList.add('input-error');
            
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }
    } catch (e) {
        if(errorDiv) {
            errorDiv.innerText = "Error de conexión";
            errorDiv.classList.add('active');
        }
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}

// --- LÓGICA LOGIN 2FA VERIFY ---
async function handleLogin2FA() {
    const codeInput = qs('[data-input="login-2fa-code"]');
    const errorDiv = qs('[data-error="login-2fa"]');
    
    if (!codeInput) return;
    
    if (!codeInput.value.trim()) {
        codeInput.classList.add('input-error');
        return;
    }
    
    const btn = qs('[data-action="login-2fa-submit"]');
    const originalContent = btn.innerHTML;

    btn.innerHTML = '<div class="btn-spinner"></div>';
    btn.disabled = true;
    if(errorDiv) errorDiv.classList.remove('active');

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ 
                action: 'login_2fa_verify', 
                code: codeInput.value.trim()
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
            codeInput.classList.add('input-error');
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }
    } catch (e) {
        if(errorDiv) {
            errorDiv.innerText = "Error de conexión";
            errorDiv.classList.add('active');
        }
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}