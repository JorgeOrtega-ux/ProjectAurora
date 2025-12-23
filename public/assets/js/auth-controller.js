import { navigateTo } from './core/url-manager.js';
import { Toast } from './core/toast-manager.js';
import { ApiService } from './core/api-service.js'; // Importar el nuevo servicio

let resendTimerInterval = null;

export function initAuthController() {
    console.log("Auth Controller: Listo (SPA - Event Delegation)");

    // Comprobar si entramos directamente a la pantalla de verificación para iniciar timer
    const resendBtn = document.getElementById('btn-resend-code');
    if (resendBtn) {
        startResendTimer(60);
    }

    // ============================================================
    // DELEGACIÓN DE EVENTOS
    // ============================================================
    document.body.addEventListener('click', async (e) => {
        const target = e.target;

        // ------------------------------------------
        // 1. LOGIN (PASO 1)
        // ------------------------------------------
        const btnLogin = target.closest('#btn-login');
        if (btnLogin) {
            e.preventDefault();
            hideError('login-error'); 
            handleLoginStep1(btnLogin); // Función renombrada para claridad
            return;
        }

        // ------------------------------------------
        // 2. LOGIN (PASO 2 - 2FA)
        // ------------------------------------------
        const btnVerify2FA = target.closest('#btn-verify-2fa');
        if (btnVerify2FA) {
            e.preventDefault();
            handleLoginStep2(btnVerify2FA);
            return;
        }

        // ------------------------------------------
        // 3. REGISTRO - PASO 1 -> PASO 2
        // ------------------------------------------
        const btnNext1 = target.closest('#btn-next-1');
        if (btnNext1) {
            e.preventDefault();
            hideError('register-step1-error');
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if(!email || !password) { 
                showError(btnNext1, 'register-step1-error', "Completa todos los campos"); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'register_step_1');
            formData.append('email', email);
            formData.append('password', password);
            // Nota: CSRF se inyecta automáticamente en ApiService

            setLoading(btnNext1, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    navigateTo(res.next_url); 
                } else {
                    showError(btnNext1, 'register-step1-error', res.message);
                    setLoading(btnNext1, false);
                }
            } catch (err) {
                console.error(err);
                showError(btnNext1, 'register-step1-error', "Ocurrió un error inesperado.");
                setLoading(btnNext1, false);
            }
            return;
        }

        // ------------------------------------------
        // 4. REGISTRO - PASO 2 -> PASO 3
        // ------------------------------------------
        const btnNext2 = target.closest('#btn-next-2');
        if (btnNext2) {
            e.preventDefault();
            hideError('register-step2-error');

            const username = document.getElementById('username').value;
            
            if(!username) { 
                showError(btnNext2.parentElement, 'register-step2-error', "Elige un nombre de usuario"); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'initiate_verification');
            formData.append('username', username);

            setLoading(btnNext2, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    if(res.debug_code) console.log("Code:", res.debug_code);
                    
                    Toast.show('Código de verificación enviado a tu correo', 'info'); 
                    
                    navigateTo(res.next_url); 
                    setTimeout(() => startResendTimer(60), 500);
                } else {
                    showError(btnNext2.parentElement, 'register-step2-error', res.message);
                    setLoading(btnNext2, false);
                }
            } catch (err) {
                showError(btnNext2.parentElement, 'register-step2-error', "Error de conexión.");
                setLoading(btnNext2, false);
            }
            return;
        }

        // ------------------------------------------
        // 5. REGISTRO - PASO 3 -> FINAL
        // ------------------------------------------
        const btnFinish = target.closest('#btn-finish');
        if (btnFinish) {
            e.preventDefault();
            hideError('register-step3-error');

            const code = document.getElementById('verification_code').value;
            
            if(!code) { 
                showError(btnFinish, 'register-step3-error', "Ingresa el código de verificación"); 
                return; 
            }

            const formData = new FormData();
            formData.append('action', 'complete_register');
            formData.append('code', code);

            setLoading(btnFinish, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    window.location.href = res.redirect;
                } else {
                    showError(btnFinish, 'register-step3-error', res.message);
                    setLoading(btnFinish, false);
                }
            } catch (err) {
                showError(btnFinish, 'register-step3-error', "Error al verificar.");
                setLoading(btnFinish, false);
            }
            return;
        }

        // ------------------------------------------
        // 6. REENVIAR CÓDIGO
        // ------------------------------------------
        const btnResend = target.closest('#btn-resend-code');
        if (btnResend) {
            e.preventDefault();
            hideError('register-step3-error');
            
            if (btnResend.classList.contains('link-disabled') || btnResend.style.pointerEvents === 'none') {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'resend_code');

            btnResend.style.opacity = '0.5';
            
            try {
                const res = await ApiService.post('auth-handler.php', formData);
                btnResend.style.opacity = '1';

                if (res.success) {
                    Toast.show('Nuevo código de verificación enviado', 'success');
                    
                    if(res.debug_code) console.log("New Code:", res.debug_code);
                    startResendTimer(60);
                } else {
                    showError(btnResend, 'register-step3-error', res.message);
                }
            } catch (err) {
                console.error(err);
                btnResend.style.opacity = '1';
                showError(btnResend, 'register-step3-error', "Error al reenviar código.");
            }
            return;
        }

        // ------------------------------------------
        // 7. RECUPERAR PASSWORD - SOLICITUD (PASO 1)
        // ------------------------------------------
        const btnRequestReset = target.closest('#btn-request-reset');
        if (btnRequestReset) {
            e.preventDefault();
            hideError('recovery-error');
            
            const email = document.getElementById('email_recovery').value;
            if(!email) {
                showError(btnRequestReset, 'recovery-error', "Ingresa tu correo.");
                return;
            }

            const formData = new FormData();
            formData.append('action', 'request_reset');
            formData.append('email', email);

            setLoading(btnRequestReset, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                setLoading(btnRequestReset, false);

                if (res.success) {
                    Toast.show('Correo enviado. Revisa la consola para el link (MODO DEV)', 'success');
                    console.log("=== LINK DE RECUPERACIÓN ===");
                    console.log(res.debug_link);
                    console.log("============================");
                    
                    const area = document.getElementById('recovery-message-area');
                    if(area) {
                        area.innerHTML = `<div class="alert success mt-16" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:8px;">
                            Link generado (Copia y pega): <br> 
                            <a href="${res.debug_link}">${res.debug_link}</a>
                        </div>`;
                    }
                } else {
                    showError(btnRequestReset, 'recovery-error', res.message);
                }
            } catch (err) {
                setLoading(btnRequestReset, false);
                showError(btnRequestReset, 'recovery-error', "Error de conexión.");
            }
            return;
        }

        // ------------------------------------------
        // 8. RESET PASSWORD - NUEVA CLAVE (PASO 2)
        // ------------------------------------------
        const btnSubmitNewPass = target.closest('#btn-submit-new-password');
        if (btnSubmitNewPass) {
            e.preventDefault();
            hideError('reset-pass-error');

            const token = document.getElementById('reset_token').value;
            const pass1 = document.getElementById('new_password').value;
            const pass2 = document.getElementById('confirm_password').value;

            if (!pass1 || !pass2) {
                showError(btnSubmitNewPass, 'reset-pass-error', "Completa ambos campos.");
                return;
            }
            if (pass1 !== pass2) {
                showError(btnSubmitNewPass, 'reset-pass-error', "Las contraseñas no coinciden.");
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('token', token);
            formData.append('new_password', pass1);

            setLoading(btnSubmitNewPass, true);

            try {
                const res = await ApiService.post('auth-handler.php', formData);
                if (res.success) {
                    Toast.show('Contraseña actualizada. Inicia sesión.', 'success');
                    setTimeout(() => {
                        window.location.href = window.BASE_PATH + 'login';
                    }, 1500);
                } else {
                    setLoading(btnSubmitNewPass, false);
                    showError(btnSubmitNewPass, 'reset-pass-error', res.message);
                }
            } catch (err) {
                setLoading(btnSubmitNewPass, false);
                showError(btnSubmitNewPass, 'reset-pass-error', "Error inesperado.");
            }
            return;
        }

        // ------------------------------------------
        // LOGOUT
        // ------------------------------------------
        const logoutBtn = target.closest('[data-action="logout"]');
        if (logoutBtn) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'logout');
            
            await ApiService.post('auth-handler.php', formData);
            window.location.href = window.BASE_PATH + 'login';
            return;
        }

        // ------------------------------------------
        // UI INTERACTIONS (Mostrar Password, Generar User)
        // ------------------------------------------
        const inputActionBtn = target.closest('.btn-input-action');
        if (inputActionBtn) {
            e.preventDefault();
            const action = inputActionBtn.dataset.action;
            
            if (action === 'toggle-password') {
                const input = inputActionBtn.parentElement.querySelector('input');
                const icon = inputActionBtn.querySelector('.material-symbols-rounded');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'visibility_off';
                } else {
                    input.type = 'password';
                    icon.textContent = 'visibility';
                }
            }
            else if (action === 'generate-username') {
                const input = document.getElementById('username');
                if (input) {
                    const now = new Date();
                    const day = String(now.getDate()).padStart(2, '0');
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const year = now.getFullYear();
                    const timestamp = now.getTime();
                    input.value = `User${day}${month}${year}${timestamp}`;
                }
            }
        }
    });

    // Enter en inputs
    document.body.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const activeInput = document.activeElement;
            if (activeInput && activeInput.closest('#loginContainer')) {
                // Si estamos en etapa 2, trigger botón 2fa
                const stage2 = document.getElementById('login-stage-2');
                if(stage2 && stage2.style.display !== 'none') {
                    const btn2 = document.getElementById('btn-verify-2fa');
                    if(btn2) btn2.click();
                } else {
                    const btn1 = document.getElementById('btn-login');
                    if(btn1) btn1.click();
                }
            }
        }
    });
}

// --- UTILIDADES ---

function setLoading(btn, isLoading) {
    if (isLoading) {
        btn.dataset.originalText = btn.innerText;
        btn.innerHTML = '<div class="spinner-sm"></div>'; 
        btn.disabled = true;
        btn.style.opacity = '0.8'; 
    } else {
        btn.innerText = btn.dataset.originalText || 'Continuar';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// NUEVA FUNCIÓN: LOGIN PASO 1 (EMAIL/PASS)
async function handleLoginStep1(btn) {
    const inputs = document.querySelectorAll('#login-stage-1 input');
    const formData = new FormData();
    formData.append('action', 'login');
    
    let hasEmpty = false;
    inputs.forEach(input => {
        if(input.name) formData.append(input.name, input.value);
        if(input.required && !input.value) hasEmpty = true;
    });

    if(hasEmpty) { 
        showError(btn, 'login-error', "Por favor llena todos los campos"); 
        return; 
    }

    setLoading(btn, true);
    try {
        const res = await ApiService.post('auth-handler.php', formData);
        
        if (res.success) {
            // VERIFICAR SI REQUIERE 2FA
            if (res.require_2fa) {
                // Cambiar UI a Etapa 2
                document.getElementById('login-stage-1').style.display = 'none';
                
                const stage2 = document.getElementById('login-stage-2');
                stage2.style.display = 'block';
                stage2.classList.remove('disabled');

                document.getElementById('auth-title').innerText = "Verificación 2FA";
                document.getElementById('auth-subtitle').innerText = "Protección adicional";
                
                // Enfocar input
                const inputCode = document.getElementById('2fa-code');
                if(inputCode) inputCode.focus();
            } else {
                // Login directo
                window.location.href = res.redirect;
            }
        } else {
            showError(btn, 'login-error', res.message);
            setLoading(btn, false);
        }
    } catch(e) { 
        showError(btn, 'login-error', "Error de conexión");
        setLoading(btn, false); 
    }
}

// NUEVA FUNCIÓN: LOGIN PASO 2 (CÓDIGO 2FA)
async function handleLoginStep2(btn) {
    const code = document.getElementById('2fa-code').value;
    if(!code) return;

    const formData = new FormData();
    formData.append('action', 'verify_2fa_login');
    formData.append('code', code);

    setLoading(btn, true);

    try {
        const res = await ApiService.post('auth-handler.php', formData);
        if (res.success) {
            window.location.href = res.redirect;
        } else {
            Toast.show(res.message, 'error'); // Mostrar error flotante o inline
            setLoading(btn, false);
            document.getElementById('2fa-code').value = '';
        }
    } catch (e) {
        Toast.show("Error de conexión", 'error');
        setLoading(btn, false);
    }
}

function showError(referenceNode, errorId, message) {
    let errorDiv = document.getElementById(errorId);
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = errorId;
        errorDiv.className = 'auth-inline-error';
        // Ajuste para insertar error correctamente
        if(referenceNode.nextSibling) {
            referenceNode.parentNode.insertBefore(errorDiv, referenceNode.nextSibling);
        } else {
            referenceNode.parentNode.appendChild(errorDiv);
        }
    }
    errorDiv.innerText = message;
}

function hideError(errorId) {
    const el = document.getElementById(errorId);
    if (el) {
        el.remove(); 
    }
}

function startResendTimer(seconds) {
    const btn = document.getElementById('btn-resend-code');
    const timerSpan = document.getElementById('register-timer');
    
    if (!btn || !timerSpan) return;

    let timeLeft = seconds;
    
    btn.classList.add('link-disabled');
    btn.style.pointerEvents = 'none';
    btn.style.color = 'rgb(153, 153, 153)';
    timerSpan.textContent = `(${timeLeft})`;

    if (resendTimerInterval) clearInterval(resendTimerInterval);

    resendTimerInterval = setInterval(() => {
        timeLeft--;
        timerSpan.textContent = `(${timeLeft})`;

        if (timeLeft <= 0) {
            clearInterval(resendTimerInterval);
            btn.classList.remove('link-disabled');
            btn.style.pointerEvents = 'auto';
            btn.style.color = ''; 
            timerSpan.textContent = ''; 
        }
    }, 1000);
}