/**
 * MainController.js
 */

// ==========================================
// CONFIGURACIÓN
// ==========================================
let allowMultipleModules = false; 
let closeOnEsc = true;            

// Función auxiliar para enviar datos como si fuera un formulario (Form Submission)
const submitAuthData = (data) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href; // Se envía a la URL actual
    form.style.display = 'none';

    for (const [key, value] of Object.entries(data)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
};

const toggleModuleState = (moduleElement) => {
    if (!moduleElement) return;
    if (moduleElement.classList.contains('disabled')) {
        moduleElement.classList.remove('disabled');
        moduleElement.classList.add('active');
    } else {
        moduleElement.classList.remove('active');
        moduleElement.classList.add('disabled');
    }
};

const closeAllActiveModules = (exceptModule = null) => {
    const activeModules = document.querySelectorAll('.module-content.active');
    activeModules.forEach(mod => {
        if (mod !== exceptModule) {
            mod.classList.remove('active');
            mod.classList.add('disabled');
        }
    });
};

const setupEventListeners = () => {
    // 1. Configuración de Módulos (Surface y Profile)
    const moduleTriggers = [
        { action: 'toggleModuleSurface', target: 'moduleSurface' },
        { action: 'toggleModuleProfile', target: 'moduleProfile' }
    ];

    moduleTriggers.forEach(({ action, target }) => {
        const btn = document.querySelector(`[data-action="${action}"]`);
        const moduleEl = document.querySelector(`[data-module="${target}"]`);

        if (btn && moduleEl) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); 
                if (!allowMultipleModules && moduleEl.classList.contains('disabled')) {
                    closeAllActiveModules(moduleEl);
                }
                toggleModuleState(moduleEl);
            });
        }
    });

    // 2. Configuración del Buscador
    const searchBtn = document.getElementById('searchToggleBtn');
    const headerCenter = document.getElementById('headerCenter');
    
    if (searchBtn && headerCenter) {
        const searchInput = headerCenter.querySelector('input');
        searchBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            headerCenter.classList.toggle('active');
            if (headerCenter.classList.contains('active') && searchInput) {
                searchInput.focus();
            }
        });
    }

    // 3. Cerrar módulos al hacer clic fuera
    document.addEventListener('click', (e) => {
        const modules = document.querySelectorAll('.module-content.active');
        modules.forEach(mod => {
            if (!mod.contains(e.target)) {
                 mod.classList.remove('active');
                 mod.classList.add('disabled');
            }
        });

        // ============================================================
        //  LÓGICA DE AUTH MULTI-STEP
        // ============================================================
        
        // A) LOGIN
        if (e.target && e.target.id === 'btn-login') {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const action = document.getElementById('login-action').value;

            if(email && password) submitAuthData({ action, email, password });
            else alert("Por favor completa todos los campos.");
        }

        // B) REGISTRO PASO 1 (Email + Pass)
        if (e.target && e.target.id === 'btn-register-step-1') {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const action = document.getElementById('register-action-1').value; // 'register_step_1'

            if(email && password) submitAuthData({ action, email, password });
            else alert("Por favor completa todos los campos.");
        }

        // C) REGISTRO PASO 2 (Username)
        if (e.target && e.target.id === 'btn-register-step-2') {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const action = document.getElementById('register-action-2').value; // 'register_step_2'

            if(username) submitAuthData({ action, username });
            else alert("Por favor escribe un nombre de usuario.");
        }

        // D) VERIFICACIÓN (Code)
        if (e.target && e.target.id === 'btn-verify') {
            e.preventDefault();
            const code = document.getElementById('code').value;
            const action = document.getElementById('verify-action').value; // 'verify_code'

            if(code) submitAuthData({ action, code });
            else alert("Por favor ingresa el código.");
        }
    });

    // 4. Cerrar con tecla Escape y Enter support
    if (closeOnEsc) {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllActiveModules();
                if (headerCenter && headerCenter.classList.contains('active')) {
                    headerCenter.classList.remove('active');
                }
            }
            if (e.key === 'Enter') {
                // Trigger simple para el botón principal presente
                const btns = ['btn-login', 'btn-register-step-1', 'btn-register-step-2', 'btn-verify'];
                for(let id of btns){
                    const btn = document.getElementById(id);
                    if(btn) { btn.click(); break; }
                }
            }
        });
    }
};

export const initMainController = () => {
    console.log('MainController: Inicializando...');
    setupEventListeners();
};