/**
 * ServerController.js
 * Lógica para la configuración del servidor (Toggles, Contadores, Chips de Dominios).
 */

let state = {
    allowedDomainsList: []
};

let dom = {};

const init = () => {
    // 1. Resetear DOM refs
    dom = {
        // Toggles
        maintToggle: document.getElementById('admin-maintenance-toggle'),
        regToggle: document.getElementById('admin-registration-toggle'),
        
        // Inputs Config
        minPass: document.getElementById('min-pass-len'),
        maxPass: document.getElementById('max-pass-len'),
        minUser: document.getElementById('min-user-len'),
        maxUser: document.getElementById('max-user-len'),
        maxEmail: document.getElementById('max-email-len'),
        
        // Dominios
        allowedDomainsInput: document.getElementById('allowed-domains'),
        chipsContainer: document.getElementById('domain-chips-container'),
        newDomainInput: document.getElementById('new-domain-input'),
        btnAddDomain: document.getElementById('btn-add-domain'),
        
        // Security Inputs
        maxLogin: document.getElementById('max-login-attempts'),
        lockoutTime: document.getElementById('lockout-time'),
        codeResend: document.getElementById('code-resend'),
        userCooldown: document.getElementById('username-cooldown'),
        emailCooldown: document.getElementById('email-cooldown'),
        profileSize: document.getElementById('profile-size'),
        
        // Save Button
        btnSaveLimits: document.getElementById('btn-save-limits'),
        
        // Contenedor principal para delegación de eventos
        wrapper: document.querySelector('.section-content[data-section="admin/server"]')
    };

    if (!dom.wrapper) return;

    setupEventListeners();
    loadConfig();
};

const destroy = () => {
    // Limpiar estado y referencias
    state.allowedDomainsList = [];
    dom = {};
};

// --- LÓGICA INTERNA ---

const setupEventListeners = () => {
    // 1. Delegación para Acordeones y Contadores (botones + -)
    dom.wrapper.addEventListener('click', (e) => {
        
        // A) Acordeones
        const header = e.target.closest('.accordion-header');
        if (header) {
            const content = header.nextElementSibling;
            if (content && content.classList.contains('accordion-content')) {
                toggleSection(header, content);
            }
        }

        // B) Contadores
        const btnCounter = e.target.closest('.counter-btn');
        if (btnCounter) {
            const controlGroup = btnCounter.closest('.component-counter-control');
            if (controlGroup) {
                const input = controlGroup.querySelector('input.counter-input');
                if (input) {
                    const allBtns = controlGroup.querySelectorAll('.counter-btn');
                    const index = Array.from(allBtns).indexOf(btnCounter);
                    
                    let amount = 0;
                    // Orden visual: << (-big) | < (-1) | INPUT | > (+1) | >> (+big)
                    const id = input.id;
                    const bigStep = (id === 'code-resend') ? 60 : (id.includes('email') || id.includes('lockout') || id.includes('max-pass')) ? 10 : 5;
                    
                    if (index === 0) amount = -bigStep;
                    if (index === 1) amount = -1;
                    if (index === 2) amount = 1;
                    if (index === 3) amount = bigStep;
                    
                    adjustCounterValue(input, amount);
                }
            }
        }
    });

    // 2. Toggles (Lógica visual y de dependencia, SIN autoguardado)
    if (dom.maintToggle) {
        dom.maintToggle.addEventListener('change', (e) => {
            // Regla: Si activas Mantenimiento, desactivar registros
            if (e.target.checked && dom.regToggle) {
                dom.regToggle.checked = false;
            }
            // Si desactivas Mantenimiento, registros queda libre (como estaba)
        });
    }
    // Nota: Eliminamos los listeners que llamaban a updateConfig en 'change'

    // 3. Botón Guardar (Único punto de guardado)
    if (dom.btnSaveLimits) dom.btnSaveLimits.addEventListener('click', updateConfig);

    // 4. Dominios
    if (dom.btnAddDomain) dom.btnAddDomain.addEventListener('click', addDomain);
    if (dom.newDomainInput) {
        dom.newDomainInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addDomain();
            }
        });
    }
};

// --- HELPERS LÓGICOS ---

const toggleSection = (header, content) => {
    const isOpen = content.classList.contains('open');
    if (isOpen) {
        content.classList.remove('open');
        header.classList.remove('active');
    } else {
        content.classList.add('open');
        header.classList.add('active');
    }
};

const adjustCounterValue = (input, amount) => {
    let val = parseInt(input.value) || 0;
    let min = parseInt(input.getAttribute('min')) || 0;
    let max = parseInt(input.getAttribute('max')) || 9999;
    
    val += amount;
    
    if (val < min) val = min;
    if (val > max) val = max;
    
    input.value = val;
};

// --- DOMINIOS LOGIC ---

const renderDomains = () => {
    if (!dom.chipsContainer) return;
    dom.chipsContainer.innerHTML = '';
    
    if (state.allowedDomainsList.length === 0) {
        dom.chipsContainer.innerHTML = '<span class="empty-domains-text">Todos los dominios permitidos (Sin restricciones)</span>';
        if (dom.allowedDomainsInput) dom.allowedDomainsInput.value = '';
        return;
    }

    state.allowedDomainsList.forEach((domain, index) => {
        const chip = document.createElement('div');
        chip.className = 'domain-chip';
        chip.innerHTML = `
            <span>${domain}</span>
            <div class="domain-chip-remove">
                <span class="material-symbols-rounded">close</span>
            </div>
        `;
        
        // Listener para borrar individual
        const btnRemove = chip.querySelector('.domain-chip-remove');
        btnRemove.addEventListener('click', () => {
            state.allowedDomainsList.splice(index, 1);
            renderDomains();
        });
        
        dom.chipsContainer.appendChild(chip);
    });

    if (dom.allowedDomainsInput) {
        dom.allowedDomainsInput.value = state.allowedDomainsList.join(',');
    }
};

const addDomain = () => {
    if (!dom.newDomainInput) return;
    const val = dom.newDomainInput.value.trim().toLowerCase();
    if (!val) return;

    if (!state.allowedDomainsList.includes(val)) {
        state.allowedDomainsList.push(val);
        renderDomains();
    }
    dom.newDomainInput.value = '';
    dom.newDomainInput.focus();
};

// --- API OPS ---

const loadConfig = async () => {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const formData = new FormData();
    formData.append('action', 'get_server_config');
    formData.append('csrf_token', csrf);

    try {
        const req = await fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData });
        const res = await req.json();

        if (res.status === 'success') {
            const d = res.data;
            
            if (dom.maintToggle) dom.maintToggle.checked = (d.maintenance_mode == 1);
            if (dom.regToggle) dom.regToggle.checked = (d.allow_registrations == 1);

            if (dom.minPass) dom.minPass.value = d.min_password_length;
            if (dom.maxPass) dom.maxPass.value = d.max_password_length;
            if (dom.minUser) dom.minUser.value = d.min_username_length;
            if (dom.maxUser) dom.maxUser.value = d.max_username_length;
            if (dom.maxEmail) dom.maxEmail.value = d.max_email_length;
            
            // Dominios
            const domainsStr = d.allowed_email_domains || ''; 
            if (domainsStr) {
                state.allowedDomainsList = domainsStr.split(',').map(s => s.trim()).filter(s => s !== '');
            } else {
                state.allowedDomainsList = [];
            }
            renderDomains();

            if (dom.maxLogin) dom.maxLogin.value = d.max_login_attempts;
            if (dom.lockoutTime) dom.lockoutTime.value = d.lockout_time_minutes;
            if (dom.codeResend) dom.codeResend.value = d.code_resend_cooldown;
            if (dom.userCooldown) dom.userCooldown.value = d.username_cooldown;
            if (dom.emailCooldown) dom.emailCooldown.value = d.email_cooldown;
            if (dom.profileSize) dom.profileSize.value = d.profile_picture_max_size;
        }
    } catch (e) {
        console.error('Error cargando config servidor', e);
    }
};

const updateConfig = async (e) => {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    // Bloquear UI
    if (dom.btnSaveLimits) dom.btnSaveLimits.disabled = true;
    if (dom.maintToggle) dom.maintToggle.disabled = true;
    if (dom.regToggle) dom.regToggle.disabled = true;

    const formData = new FormData();
    formData.append('action', 'update_server_config');
    formData.append('csrf_token', csrf);

    // Recoger valores
    if (dom.maintToggle) formData.append('maintenance_mode', dom.maintToggle.checked ? 1 : 0);
    if (dom.regToggle) formData.append('allow_registrations', dom.regToggle.checked ? 1 : 0);
    
    if (dom.minPass) formData.append('min_password_length', dom.minPass.value);
    if (dom.maxPass) formData.append('max_password_length', dom.maxPass.value);
    if (dom.minUser) formData.append('min_username_length', dom.minUser.value);
    if (dom.maxUser) formData.append('max_username_length', dom.maxUser.value);
    if (dom.maxEmail) formData.append('max_email_length', dom.maxEmail.value);
    
    // Usamos el input oculto o la lista del estado directamente
    formData.append('allowed_email_domains', state.allowedDomainsList.join(','));

    if (dom.maxLogin) formData.append('max_login_attempts', dom.maxLogin.value);
    if (dom.lockoutTime) formData.append('lockout_time_minutes', dom.lockoutTime.value);
    if (dom.codeResend) formData.append('code_resend_cooldown', dom.codeResend.value);
    if (dom.userCooldown) formData.append('username_cooldown', dom.userCooldown.value);
    if (dom.emailCooldown) formData.append('email_cooldown', dom.emailCooldown.value);
    if (dom.profileSize) formData.append('profile_picture_max_size', dom.profileSize.value);

    try {
        const req = await fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData });
        const res = await req.json();

        if (res.status !== 'success') {
            alert(res.message || 'Error guardando configuración');
            loadConfig(); 
        } else {
            console.log('Configuración guardada.');
            // Opcional: Mostrar Toast o feedback visual aquí
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión al guardar.');
    } finally {
        if (dom.btnSaveLimits) dom.btnSaveLimits.disabled = false;
        if (dom.maintToggle) dom.maintToggle.disabled = false;
        if (dom.regToggle) dom.regToggle.disabled = false;
    }
};

export default { init, destroy };