// assets/js/auth-manager.js

const API_BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

// Helper para selectores
function qs(selector) {
    return document.querySelector(selector);
}

// Helper para cambiar visibilidad usando clases
function toggleStepVisibility(hideSelector, showSelector) {
    const toHide = qs(hideSelector);
    const toShow = qs(showSelector);

    if (toHide) {
        toHide.classList.remove('active');
        // Limpiar errores al cambiar de paso
        const err = toHide.querySelector('.form-error-message');
        if (err) { err.textContent = ''; err.classList.remove('active'); }
    }
    if (toShow) {
        toShow.classList.add('active');
    }
}

// Helper para obtener el token CSRF
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/* =============================================================
   SISTEMA DE REENVÍO DE CÓDIGO (TIMER)
   ============================================================= */
let resendTimerInterval = null;

function initResendTimer(linkSelector, startSeconds = 60) {
    const link = qs(linkSelector);
    if (!link) return;

    if (resendTimerInterval) clearInterval(resendTimerInterval);
    
    let seconds = startSeconds;
    
    link.classList.add('disabled-link');
    link.textContent = `Reenviar código de verificación (${seconds})`;

    resendTimerInterval = setInterval(() => {
        seconds--;
        if (seconds > 0) {
            link.textContent = `Reenviar código de verificación (${seconds})`;
        } else {
            clearInterval(resendTimerInterval);
            link.textContent = "Reenviar código de verificación";
            link.classList.remove('disabled-link');
        }
    }, 1000);
}

async function handleResendCode(type, linkSelector) {
    const link = qs(linkSelector);
    if (!link || link.classList.contains('disabled-link')) return;

    link.classList.add('disabled-link');

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ 
                action: 'resend_code',
                type: type
            })
        });

        const res = await response.json();
        
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
            initResendTimer(linkSelector, 60);
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            
            if (res.remaining_time) {
                initResendTimer(linkSelector, res.remaining_time);
            } else {
                link.classList.remove('disabled-link');
            }
        }
    } catch (error) {
        console.error(error);
        if (link) link.classList.remove('disabled-link');
    }
}

export function initAuthManager() {
    document.body.addEventListener('input', (e) => {
        if (e.target.matches('[data-input="reg-code"], [data-input="login-2fa-code"], [data-input="rec-code"]')) {
            const input = e.target;
            let rawValue = input.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            
            if (rawValue.length > 12) rawValue = rawValue.slice(0, 12);

            const parts = [];
            if (rawValue.length > 0) parts.push(rawValue.slice(0, 4));
            if (rawValue.length > 4) parts.push(rawValue.slice(4, 8));
            if (rawValue.length > 8) parts.push(rawValue.slice(8, 12));

            input.value = parts.join('-');
        }
    });

    document.body.addEventListener('click', async (e) => {
        
        // --- REGISTRO ---
        if (e.target.closest('[data-action="register-step1"]')) {
            e.preventDefault();
            await handleRegisterStep('step1', 'register_step_1', 2, 'register/additional-data');
        }
        if (e.target.closest('[data-action="register-step2"]')) {
            e.preventDefault();
            const success = await handleRegisterStep('step2', 'register_step_2', 3, 'register/verification-account');
            if (success) initResendTimer('[data-action="resend-register"]');
        }
        if (e.target.closest('[data-action="register-step3"]')) {
            e.preventDefault();
            await handleRegisterStep('step3', 'register_final', 'main', null);
        }
        if (e.target.closest('[data-action="resend-register"]')) {
            e.preventDefault();
            await handleResendCode('register', '[data-action="resend-register"]');
        }
        if (e.target.closest('[data-action="register-back-step1"]')) {
            e.preventDefault();
            switchRegisterStep(1, 'register');
        }

        // --- RECUPERACIÓN ---
        if (e.target.closest('[data-action="rec-step1"]')) {
            e.preventDefault();
            const success = await handleRecoveryStep('step1');
            if (success) initResendTimer('[data-action="resend-recovery"]');
        }
        if (e.target.closest('[data-action="rec-step2"]')) {
            e.preventDefault();
            await handleRecoveryStep('step2');
        }
        if (e.target.closest('[data-action="rec-step3"]')) {
            e.preventDefault();
            await handleRecoveryStep('step3');
        }
        if (e.target.closest('[data-action="resend-recovery"]')) {
            e.preventDefault();
            await handleResendCode('recovery', '[data-action="resend-recovery"]');
        }

        // --- UTILIDADES ---
        if (e.target.closest('.floating-input-btn') && !e.target.closest('.username-magic-btn')) {
            const btn = e.target.closest('.floating-input-btn');
            const parent = btn.closest('.floating-label-group');
            if (!parent) return; 
            const input = parent.querySelector('input');

            if (input && input.tagName === 'INPUT') {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.querySelector('span').textContent = 'visibility_off';
                } else {
                    input.type = 'password';
                    btn.querySelector('span').textContent = 'visibility';
                }
            }
        }
        if (e.target.closest('.username-magic-btn')) {
            e.preventDefault();
            const input = document.querySelector('[data-input="reg-username"]');
            if (input) {
                input.value = generateMagicUsername();
                input.focus();
                input.classList.remove('input-error'); 
            }
        }

        // --- LOGIN ---
        if (e.target.closest('[data-action="login-submit"]')) {
            e.preventDefault();
            await handleLogin();
        }
        if (e.target.closest('[data-action="resend-login"]')) {
            e.preventDefault();
            await handleResendCode('login', '[data-action="resend-login"]');
        }
        if (e.target.closest('[data-action="login-2fa-submit"]')) {
            e.preventDefault();
            await handleLogin2FA();
        }
        if (e.target.closest('[data-action="login-2fa-back"]')) {
            e.preventDefault();
            toggleStepVisibility('[data-step="login-2"]', '[data-step="login-1"]');
            const loginUrl = API_BASE_PATH + 'login';
            history.pushState({ section: 'login' }, '', loginUrl);
        }
        
        // --- LOGOUT ---
        const logoutBtn = e.target.closest('.menu-link-logout');
        if (logoutBtn) {
            e.preventDefault(); 
            if (logoutBtn.dataset.processing === "true") return;
            logoutBtn.dataset.processing = "true";
            
            const iconContainer = logoutBtn.querySelector('.menu-link-icon');
            let originalIconHTML = '';

            if (iconContainer) {
                originalIconHTML = iconContainer.innerHTML;
                iconContainer.innerHTML = '<div class="small-spinner"></div>'; 
            }
            
            try {
                const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ 
                        action: 'logout',
                        csrf_token: getCsrfToken()
                    })
                });

                const res = await response.json();

                if (res.success) {
                    if (window.alertManager) window.alertManager.showAlert('Cerrando sesión...', 'info');
                    window.location.href = API_BASE_PATH + 'login';
                } else {
                    if (iconContainer) iconContainer.innerHTML = originalIconHTML;
                    logoutBtn.dataset.processing = "false";
                }

            } catch (error) {
                if (iconContainer) iconContainer.innerHTML = originalIconHTML;
                logoutBtn.dataset.processing = "false";
            }
        }
    });
    
    if (qs('[data-step="register-3"].active')) initResendTimer('[data-action="resend-register"]');
    if (qs('[data-step="rec-2"].active')) initResendTimer('[data-action="resend-recovery"]');
    if (qs('[data-step="login-2"].active')) initResendTimer('[data-action="resend-login"]');
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
    for (let i = 0; i < 2; i++) randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
    return `user${timePart}${randomPart}`;
}

function switchRegisterStep(stepNumber, urlPath) {
    qs('[data-step="register-1"]').classList.remove('active');
    qs('[data-step="register-2"]').classList.remove('active');
    qs('[data-step="register-3"]').classList.remove('active');
    
    const target = qs(`[data-step="register-${stepNumber}"]`);
    if (target) {
        target.classList.add('active');
        if (urlPath) {
            const newUrl = API_BASE_PATH + urlPath;
            history.pushState({ section: urlPath }, '', newUrl);
        }
    }
}

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

    if (stepName === 'step1') {
        const emailIn = qs('[data-input="reg-email"]');
        const passIn = qs('[data-input="reg-password"]');
        if (!emailIn || !passIn) return false;
        
        const emailVal = emailIn.value.trim().toLowerCase();
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
        
        const emailDisplay = qs('[data-display="email-verify"]');
        if (emailDisplay) emailDisplay.textContent = emailVal;

        btnSelector = '[data-action="register-step1"]'; 
        errorSelector = '[data-error="register-1"]'; 
        inputSelectors = ['[data-input="reg-email"]', '[data-input="reg-password"]'];
    
    } else if (stepName === 'step2') {
        const userIn = qs('[data-input="reg-username"]');
        if (!userIn) return false;

        const userVal = userIn.value.trim();
        if (!isValidUsername(userVal)) {
            errorMessage = "Usuario inválido: 8-32 caracteres. Solo letras, números y '_'.";
            userIn.classList.add('input-error');
        }

        payload.username = userVal;
        btnSelector = '[data-action="register-step2"]'; 
        errorSelector = '[data-error="register-2"]'; 
        inputSelectors = ['[data-input="reg-username"]'];
    
    } else if (stepName === 'step3') {
        const codeIn = qs('[data-input="reg-code"]');
        if (!codeIn) return false;
        payload.code = codeIn.value.replace(/-/g, '');
        btnSelector = '[data-action="register-step3"]'; 
        errorSelector = '[data-error="register-3"]'; 
        inputSelectors = ['[data-input="reg-code"]'];
    }

    inputSelectors.forEach(sel => qs(sel).classList.remove('input-error'));
    const errorDiv = qs(errorSelector);
    if(errorDiv) { errorDiv.textContent = ''; errorDiv.classList.remove('active'); }

    let hasEmpty = false;
    inputSelectors.forEach(sel => {
        const el = qs(sel);
        if(!el.value.trim()) { el.classList.add('input-error'); hasEmpty = true; }
    });

    if (hasEmpty) {
        if(errorDiv) { errorDiv.textContent = "Todos los campos son requeridos."; errorDiv.classList.add('active'); }
        return false;
    }

    if (errorMessage) {
        if(errorDiv) { errorDiv.textContent = errorMessage; errorDiv.classList.add('active'); }
        return false;
    }

    return await sendAuthRequest(payload, btnSelector, errorSelector, nextStep, nextUrl);
}

async function handleRecoveryStep(stepName) {
    let payload = { action: '' };
    let btnSelector, errorSelector, inputSelectors = [];
    let currentStepSelector = '';
    let nextStepSelector = '';

    if (stepName === 'step1') {
        const emailIn = qs('[data-input="rec-email"]');
        if(!emailIn) return false;

        const emailVal = emailIn.value.trim().toLowerCase();
        const errorDiv = qs('[data-error="rec-1"]');
        
        if(errorDiv) { errorDiv.textContent = ''; errorDiv.classList.remove('active'); }
        emailIn.classList.remove('input-error');

        if(!emailVal) { 
            emailIn.classList.add('input-error'); 
            if(errorDiv) { errorDiv.textContent = "El correo es requerido."; errorDiv.classList.add('active'); }
            return false; 
        }

        if (!isValidEmailDomain(emailVal)) {
            emailIn.classList.add('input-error');
            if(errorDiv) { errorDiv.textContent = "Correo inválido. Solo se permite: Gmail, Outlook, iCloud, Yahoo."; errorDiv.classList.add('active'); }
            return false;
        }
        
        payload = { action: 'recovery_step_1', email: emailVal };
        
        btnSelector = '[data-action="rec-step1"]'; 
        errorSelector = '[data-error="rec-1"]'; 
        inputSelectors = ['[data-input="rec-email"]'];
        currentStepSelector = '[data-step="rec-1"]';
        nextStepSelector = '[data-step="rec-2"]';

    } else if (stepName === 'step2') {
        const codeIn = qs('[data-input="rec-code"]');
        if(!codeIn) return false;
        
        const rawCode = codeIn.value.trim();
        if(!rawCode) { 
            codeIn.classList.add('input-error'); 
            return false; 
        }
        
        payload = { action: 'recovery_step_2', code: rawCode.replace(/-/g, '') };
        
        btnSelector = '[data-action="rec-step2"]'; 
        errorSelector = '[data-error="rec-2"]'; 
        inputSelectors = ['[data-input="rec-code"]'];
        currentStepSelector = '[data-step="rec-2"]';
        nextStepSelector = '[data-step="rec-3"]';

    } else if (stepName === 'step3') {
        const passIn = qs('[data-input="rec-pass"]');
        if(!passIn) return false;
        
        if(passIn.value.length < 8) { 
            passIn.classList.add('input-error'); 
            const err = qs('[data-error="rec-3"]');
            if(err) { err.textContent = 'Mínimo 8 caracteres'; err.classList.add('active'); }
            return false; 
        }

        payload = { action: 'recovery_final', password: passIn.value };
        btnSelector = '[data-action="rec-step3"]'; 
        errorSelector = '[data-error="rec-3"]'; 
        inputSelectors = ['[data-input="rec-pass"]'];
    }

    const btn = qs(btnSelector);
    let originalContent = '';
    
    if(btn) { 
        originalContent = btn.innerHTML;
        btn.innerHTML = '<div class="btn-spinner"></div>'; 
        btn.disabled = true; 
    }
    
    if (stepName !== 'step1') {
        const errorDiv = qs(errorSelector);
        if(errorDiv) { errorDiv.textContent = ''; errorDiv.classList.remove('active'); }
    }
    inputSelectors.forEach(sel => qs(sel).classList.remove('input-error'));
    
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
                if (window.alertManager) window.alertManager.showAlert('Contraseña actualizada con éxito.', 'success');
                window.location.href = API_BASE_PATH + 'login';
            } else {
                toggleStepVisibility(currentStepSelector, nextStepSelector);
                
                if(stepName === 'step1') {
                    const display = qs('[data-display="rec-email"]');
                    if(display) display.textContent = payload.email;
                    // MODIFICADO: Mensaje más directo
                    if (window.alertManager) window.alertManager.showAlert('Código enviado. Revisa tu correo.', 'success');
                }
                
                if(btn) { btn.innerHTML = originalContent; btn.disabled = false; }
            }
            return true; 
        } else {
            const errorDiv = qs(errorSelector);
            if(errorDiv) { errorDiv.textContent = res.message; errorDiv.classList.add('active'); }
            if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
            return false;
        }
    } catch (e) {
        const errorDiv = qs(errorSelector);
        if(errorDiv) { errorDiv.textContent = "Error de conexión"; errorDiv.classList.add('active'); }
        if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
        return false;
    }
}

async function sendAuthRequest(payload, btnSelector, errorSelector, nextStep, nextUrl) {
    const btn = qs(btnSelector);
    const errorDiv = qs(errorSelector);
    let originalContent = '';

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
                if (payload.action === 'register_step_2' && window.alertManager) {
                    window.alertManager.showAlert('Código de verificación enviado.', 'success');
                }
                switchRegisterStep(nextStep, nextUrl);
                if(btn) { btn.innerHTML = originalContent; btn.disabled = false; }
            }
            return true;
        } else {
            if(errorDiv) { errorDiv.textContent = result.message; errorDiv.classList.add('active'); }
            if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
            return false;
        }
    } catch (error) {
        if(errorDiv) { errorDiv.textContent = "Error de conexión"; errorDiv.classList.add('active'); }
        if(btn) { btn.innerHTML = originalContent; btn.disabled = false; } 
        return false;
    }
}

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
                email: emailInput.value.toLowerCase(), 
                password: passInput.value 
            })
        });

        const res = await response.json();
        
        if (res.success) {
            if (res.require_2fa) {
                const nextUrl = API_BASE_PATH + 'login/verification-additional';
                history.pushState({ section: 'login/verification-additional' }, '', nextUrl);

                toggleStepVisibility('[data-step="login-1"]', '[data-step="login-2"]');
                
                const displayEmail = qs('[data-display="login-2fa-email"]');
                if(displayEmail && res.masked_email) {
                    displayEmail.textContent = res.masked_email;
                }
                
                if (window.alertManager) window.alertManager.showAlert('Código de seguridad 2FA enviado.', 'info');
                initResendTimer('[data-action="resend-login"]');

                btn.innerHTML = originalContent;
                btn.disabled = false;
                
                setTimeout(() => {
                    const codeField = qs('[data-input="login-2fa-code"]');
                    if(codeField) codeField.focus();
                }, 100);

            } else {
                if (window.alertManager) window.alertManager.showAlert('Inicio de sesión exitoso.', 'info');
                window.location.href = API_BASE_PATH;
            }
        } else {
            if(errorDiv) {
                errorDiv.textContent = res.message;
                errorDiv.classList.add('active');
            }
            emailInput.classList.add('input-error');
            passInput.classList.add('input-error');
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }
    } catch (e) {
        if(errorDiv) {
            errorDiv.textContent = "Error de conexión";
            errorDiv.classList.add('active');
        }
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}

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

    const cleanCode = codeInput.value.trim().replace(/-/g, '');

    try {
        const response = await fetch(`${API_BASE_PATH}api/auth_handler.php`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ 
                action: 'login_2fa_verify', 
                code: cleanCode
            })
        });

        const res = await response.json();
        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert('Acceso verificado. ¡Bienvenido!', 'success');
            window.location.href = API_BASE_PATH;
        } else {
            if(errorDiv) {
                errorDiv.textContent = res.message;
                errorDiv.classList.add('active');
            }
            codeInput.classList.add('input-error');
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }
    } catch (e) {
        if(errorDiv) {
            errorDiv.textContent = "Error de conexión";
            errorDiv.classList.add('active');
        }
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}