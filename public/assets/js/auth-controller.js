import { navigateTo } from './core/url-manager.js';

let resendTimerInterval = null;

export function initAuthController() {
    console.log("Auth Controller: Listo (SPA - Event Delegation)");

    const csrfToken = getCsrfToken();

    // Comprobar si entramos directamente a la pantalla de verificación para iniciar timer
    const resendBtn = document.getElementById('btn-resend-code');
    if (resendBtn) {
        startResendTimer(60);
    }

    // ============================================================
    // DELEGACIÓN DE EVENTOS (Maneja clics en elementos dinámicos)
    // ============================================================
    document.body.addEventListener('click', async (e) => {
        const target = e.target;

        // ------------------------------------------
        // 1. MANEJO DE LOGIN
        // ------------------------------------------
        const btnLogin = target.closest('#btn-login');
        if (btnLogin) {
            e.preventDefault();
            handleLogin(btnLogin, csrfToken);
            return;
        }

        // ------------------------------------------
        // 2. REGISTRO - PASO 1 (Email/Pass) -> PASO 2
        // ------------------------------------------
        const btnNext1 = target.closest('#btn-next-1');
        if (btnNext1) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if(!email || !password) { alert("Completa los campos"); return; }

            const formData = new FormData();
            formData.append('action', 'register_step_1');
            formData.append('email', email);
            formData.append('password', password);
            formData.append('csrf_token', csrfToken);

            setLoading(btnNext1, true);

            try {
                const res = await fetchApi(formData);
                if (res.success) {
                    navigateTo(res.next_url); 
                } else {
                    alert(res.message);
                    setLoading(btnNext1, false);
                }
            } catch (err) {
                console.error(err);
                setLoading(btnNext1, false);
            }
            return;
        }

        // ------------------------------------------
        // 3. REGISTRO - PASO 2 (Username) -> PASO 3
        // ------------------------------------------
        const btnNext2 = target.closest('#btn-next-2');
        if (btnNext2) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            
            if(!username) { alert("Elige un usuario"); return; }

            const formData = new FormData();
            formData.append('action', 'initiate_verification');
            formData.append('username', username);
            formData.append('csrf_token', csrfToken);

            setLoading(btnNext2, true);

            try {
                const res = await fetchApi(formData);
                if (res.success) {
                    if(res.debug_code) console.log("Code:", res.debug_code);
                    navigateTo(res.next_url); 
                    // Iniciar timer después de navegar
                    setTimeout(() => startResendTimer(60), 500);
                } else {
                    alert(res.message);
                    setLoading(btnNext2, false);
                }
            } catch (err) {
                setLoading(btnNext2, false);
            }
            return;
        }

        // ------------------------------------------
        // 4. REGISTRO - PASO 3 (Código) -> FINAL
        // ------------------------------------------
        const btnFinish = target.closest('#btn-finish');
        if (btnFinish) {
            e.preventDefault();
            const code = document.getElementById('verification_code').value;
            
            if(!code) { alert("Ingresa el código"); return; }

            const formData = new FormData();
            formData.append('action', 'complete_register');
            formData.append('code', code);
            formData.append('csrf_token', csrfToken);

            setLoading(btnFinish, true);

            try {
                const res = await fetchApi(formData);
                if (res.success) {
                    window.location.href = res.redirect; // Recarga completa al Home
                } else {
                    alert(res.message);
                    setLoading(btnFinish, false);
                }
            } catch (err) {
                setLoading(btnFinish, false);
            }
            return;
        }

        // ------------------------------------------
        // 5. REENVIAR CÓDIGO
        // ------------------------------------------
        const btnResend = target.closest('#btn-resend-code');
        if (btnResend) {
            e.preventDefault();
            
            // Verificar si está deshabilitado por estilo o clase
            if (btnResend.classList.contains('link-disabled') || btnResend.style.pointerEvents === 'none') {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'resend_code');
            formData.append('csrf_token', csrfToken);

            btnResend.style.opacity = '0.5';
            
            try {
                const res = await fetchApi(formData);
                btnResend.style.opacity = '1';

                if (res.success) {
                    alert("Nuevo código enviado.");
                    if(res.debug_code) console.log("New Code:", res.debug_code);
                    startResendTimer(60);
                } else {
                    alert(res.message);
                }
            } catch (err) {
                console.error(err);
                btnResend.style.opacity = '1';
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
            formData.append('csrf_token', csrfToken);
            await fetchApi(formData);
            window.location.href = window.BASE_PATH + 'login';
            return;
        }

        // ------------------------------------------
        // UI INTERACTIONS (Botones genéricos en inputs)
        // ------------------------------------------
        const inputActionBtn = target.closest('.btn-input-action');
        if (inputActionBtn) {
            e.preventDefault();
            const action = inputActionBtn.dataset.action;
            
            // ACCIÓN 1: Toggle Password
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
            
            // ACCIÓN 2: Generar Username
            else if (action === 'generate-username') {
                const input = document.getElementById('username');
                if (input) {
                    const now = new Date();
                    
                    // Formatear fecha DDMMYYYY
                    const day = String(now.getDate()).padStart(2, '0');
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const year = now.getFullYear();
                    
                    // Obtener timestamp (milisegundos)
                    const timestamp = now.getTime();

                    // Construir string
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
                const btn = document.getElementById('btn-login');
                if(btn) btn.click();
            }
        }
    });
}

// --- UTILIDADES ---

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function fetchApi(formData) {
    try {
        const response = await fetch(window.BASE_PATH + 'api/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        return await response.json();
    } catch (error) {
        console.error("API Error:", error);
        alert("Error de conexión con el servidor");
        throw error;
    }
}

// FUNCIÓN ACTUALIZADA: Usa Spinner HTML en lugar de texto
function setLoading(btn, isLoading) {
    if (isLoading) {
        btn.dataset.originalText = btn.innerText;
        btn.innerHTML = '<div class="spinner-sm"></div>'; // Spinner pequeño
        btn.disabled = true;
        btn.style.opacity = '0.8'; // Opacidad ligera para indicar deshabilitado pero visible
    } else {
        btn.innerText = btn.dataset.originalText || 'Continuar';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function handleLogin(btn, token) {
    const inputs = document.querySelectorAll('.auth-card input');
    const formData = new FormData();
    formData.append('action', 'login');
    formData.append('csrf_token', token);
    
    let hasEmpty = false;
    inputs.forEach(input => {
        if(input.name) formData.append(input.name, input.value);
        if(input.required && !input.value) hasEmpty = true;
    });

    if(hasEmpty) { alert("Llena los campos"); return; }

    setLoading(btn, true);
    try {
        const res = await fetchApi(formData);
        if (res.success) {
            window.location.href = res.redirect;
        } else {
            alert(res.message);
            setLoading(btn, false);
        }
    } catch(e) { setLoading(btn, false); }
}

// --- TIMER DE REENVÍO ---
function startResendTimer(seconds) {
    const btn = document.getElementById('btn-resend-code');
    const timerSpan = document.getElementById('register-timer');
    
    if (!btn || !timerSpan) return;

    let timeLeft = seconds;
    
    // Aplicar estado deshabilitado visualmente
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
            // Habilitar botón
            btn.classList.remove('link-disabled');
            btn.style.pointerEvents = 'auto';
            btn.style.color = ''; // Volver al color original (heredado o CSS)
            timerSpan.textContent = ''; 
        }
    }, 1000);
}