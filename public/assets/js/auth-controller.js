import { navigateTo } from './core/url-manager.js';

export function initAuthController() {
    console.log("Auth Controller: Listo (SPA)");

    // Configura botones visuales (ver password, generar user)
    setupInteractions();

    const csrfToken = getCsrfToken();

    // ==========================================
    // 1. MANEJO DE LOGIN
    // ==========================================
    const btnLogin = document.getElementById('btn-login');
    if (btnLogin) {
        btnLogin.addEventListener('click', (e) => {
            e.preventDefault();
            handleLogin(btnLogin, csrfToken);
        });
        
        // Enter en inputs
        document.querySelectorAll('#loginContainer input').forEach(input => {
            input.addEventListener('keypress', (e) => { if(e.key === 'Enter') btnLogin.click(); });
        });
    }

    // ==========================================
    // 2. REGISTRO - PASO 1 (Email/Pass) -> PASO 2
    // ==========================================
    const btnNext1 = document.getElementById('btn-next-1');
    if (btnNext1) {
        btnNext1.addEventListener('click', async (e) => {
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
                    // AQUÍ ESTÁ LA MAGIA: Navegamos a la URL del paso 2
                    navigateTo(res.next_url); 
                } else {
                    alert(res.message);
                    setLoading(btnNext1, false);
                }
            } catch (err) {
                console.error(err);
                setLoading(btnNext1, false);
            }
        });
    }

    // ==========================================
    // 3. REGISTRO - PASO 2 (Username) -> PASO 3
    // ==========================================
    const btnNext2 = document.getElementById('btn-next-2');
    if (btnNext2) {
        btnNext2.addEventListener('click', async (e) => {
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
                    navigateTo(res.next_url); // Navegar al paso 3
                } else {
                    alert(res.message);
                    setLoading(btnNext2, false);
                }
            } catch (err) {
                setLoading(btnNext2, false);
            }
        });
    }

    // ==========================================
    // 4. REGISTRO - PASO 3 (Código) -> FINAL
    // ==========================================
    const btnFinish = document.getElementById('btn-finish');
    if (btnFinish) {
        btnFinish.addEventListener('click', async (e) => {
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
        });
    }

    // LOGOUT
    const logoutBtn = document.querySelector('[data-action="logout"]');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'logout');
            formData.append('csrf_token', csrfToken);
            await fetchApi(formData);
            window.location.href = window.BASE_PATH + 'login';
        });
    }
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

function setupInteractions() {
    // Listener delegado para elementos que pueden aparecer dinámicamente
    document.body.addEventListener('click', (e) => {
        // Toggle Password
        const toggleBtn = e.target.closest('.btn-toggle-password');
        if (toggleBtn) {
            e.preventDefault();
            const input = toggleBtn.parentElement.querySelector('input');
            const icon = toggleBtn.querySelector('.material-symbols-rounded');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // Generate Username
        const genUserBtn = e.target.closest('.btn-generate-username');
        if (genUserBtn) {
            e.preventDefault();
            const input = document.getElementById('username');
            if (input) {
                const rand = Math.floor(Math.random() * 10000);
                input.value = `User${rand}`;
            }
        }
    });
}

// Login Helper
async function handleLogin(btn, token) {
    const inputs = document.querySelectorAll('#loginContainer input, .auth-card input');
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