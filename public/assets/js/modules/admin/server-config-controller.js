/**
 * public/assets/js/modules/admin/server-config-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { ToastManager } from '../../core/toast-manager.js';
import { I18nManager } from '../../core/i18n-manager.js';
import { DialogManager } from '../../core/dialog-manager.js';

export const ServerConfigController = {
    init: () => {
        console.log("ServerConfigController: Inicializado");
        
        const btnSave = document.getElementById('btn-save-server-config');
        if (btnSave) {
            btnSave.addEventListener('click', saveConfig);
        }

        const btnPanic = document.getElementById('btn-panic-mode');
        if (btnPanic) {
            btnPanic.addEventListener('click', togglePanicMode);
        }

        _initSteppers();
        _initDomainManager();
    }
};

async function togglePanicMode() {
    // ... (Lógica de pánico intacta) ...
    const btn = document.getElementById('btn-panic-mode');
    const isActive = btn.dataset.active === 'true';
    const title = isActive ? '¿Desactivar MODO PÁNICO?' : '¿ACTIVAR MODO PÁNICO?';
    const msg = isActive 
        ? 'Esto restaurará el acceso normal al servidor.'
        : 'ESTA ES UNA ACCIÓN DESTRUCTIVA. Se cerrarán conexiones.';
    const confirmText = isActive ? 'Desactivar y Restaurar' : 'SÍ, ACTIVAR PÁNICO';
    
    const confirmed = await DialogManager.confirm({
        title: title, message: msg, type: 'danger', confirmText: confirmText, cancelText: I18nManager.t('global.cancel')
    });

    if (!confirmed) return;

    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-sm"></div> Procesando...';

    const formData = new FormData();
    formData.append('activate', !isActive ? '1' : '0');

    try {
        const res = await ApiService.post({ route: 'admin.toggle_panic' }, formData);
        if (res.success) {
            ToastManager.show(res.message, 'success');
            const newState = !isActive;
            btn.dataset.active = newState ? 'true' : 'false';
            if (newState) {
                btn.style.cssText = 'background-color: var(--color-error); color: white; border-color: var(--color-error); animation: pulse-red 2s infinite;';
                btn.innerHTML = '<span class="material-symbols-rounded" style="margin-right: 6px;">report_off</span><span style="font-weight: 700; font-size: 12px;">DESACTIVAR PÁNICO</span>';
            } else {
                btn.style.cssText = 'color: var(--color-error); border-color: rgba(211, 47, 47, 0.3); animation: none;';
                btn.innerHTML = '<span class="material-symbols-rounded" style="margin-right: 6px;">report</span><span style="font-weight: 700; font-size: 12px;">MODO PÁNICO</span>';
            }
            const checkReg = document.querySelector('input[name="allow_registrations"]');
            if (checkReg) checkReg.checked = !newState; 
        } else {
            ToastManager.show(res.message, 'error');
            btn.innerHTML = originalContent; 
        }
    } catch (e) {
        ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
        btn.innerHTML = originalContent;
    } finally {
        btn.disabled = false;
    }
}

// --- GESTIÓN DE DOMINIOS (CHIPS MODULARES) ---
function _initDomainManager() {
    const hiddenInput = document.getElementById('input-allowed-domains');
    const listContainer = document.getElementById('domain-list-container');
    const btnAdd = document.getElementById('btn-add-domain');
    const inputGroup = document.getElementById('domain-input-group');
    const inputNew = document.getElementById('new-domain-input');
    const btnConfirm = document.getElementById('btn-confirm-domain');
    const btnCancel = document.getElementById('btn-cancel-domain');

    if (!hiddenInput || !listContainer) return;

    let domains = hiddenInput.value.split(',').map(d => d.trim()).filter(d => d !== '');
    if (domains.length === 0) domains = ['*']; 

    renderChips();

    btnAdd.addEventListener('click', () => {
        btnAdd.style.display = 'none';
        listContainer.classList.add('d-none');
        inputGroup.style.display = 'flex';
        inputNew.value = '';
        inputNew.focus();
    });

    const hideInput = () => {
        inputGroup.style.display = 'none';
        btnAdd.style.display = 'inline-flex';
        listContainer.classList.remove('d-none');
    };

    btnCancel.addEventListener('click', hideInput);

    const addDomain = () => {
        const val = inputNew.value.trim().toLowerCase();
        if (val) {
            if (val === '*') domains = ['*'];
            else {
                if (domains.includes('*')) domains = domains.filter(d => d !== '*');
                if (!domains.includes(val)) domains.push(val);
                else {
                    ToastManager.show(I18nManager.t('admin.server.domain_exists') || 'El dominio ya existe', 'warning');
                    return;
                }
            }
            updateHiddenInput();
            renderChips();
            hideInput();
        }
    };

    btnConfirm.addEventListener('click', addDomain);
    inputNew.addEventListener('keypress', (e) => { if (e.key === 'Enter') addDomain(); });

    // Listener para eliminar chip (Delegado)
    listContainer.addEventListener('click', (e) => {
        // Buscar el chip o el icono de cerrar
        const chip = e.target.closest('.component-chip');
        if (chip && chip.dataset.value) {
            const valToRemove = chip.dataset.value;
            domains = domains.filter(d => d !== valToRemove);
            
            if(domains.length === 0) domains = ['*'];

            updateHiddenInput();
            renderChips();
        }
    });

    function updateHiddenInput() {
        hiddenInput.value = domains.join(',');
    }

    // Renderizado usando el nuevo componente estándar
    function renderChips() {
        listContainer.innerHTML = '';
        domains.forEach(domain => {
            // Usamos .chip-removable para que se ponga rojo en hover
            const chip = document.createElement('div');
            chip.className = 'component-chip chip-removable';
            chip.dataset.value = domain;
            chip.title = 'Clic para eliminar';
            chip.innerHTML = `
                <span class="chip-text">${domain}</span>
                <span class="material-symbols-rounded chip-icon">close</span>
            `;
            listContainer.appendChild(chip);
        });
    }
}

function _initSteppers() {
    const container = document.querySelector('[data-section="admin-server-config"]');
    if (!container) return;

    container.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const wrapper = btn.closest('.component-stepper');
        if (!wrapper) return;
        const input = wrapper.querySelector('input');
        if (!input) return;

        const action = btn.dataset.action;
        const stepSmall = parseInt(wrapper.dataset.stepSmall) || 1;
        const stepLarge = parseInt(wrapper.dataset.stepLarge) || 10;
        
        let currentValue = parseInt(input.value) || 0;

        switch (action) {
            case 'dec-large': currentValue -= stepLarge; break;
            case 'dec-small': currentValue -= stepSmall; break;
            case 'inc-small': currentValue += stepSmall; break;
            case 'inc-large': currentValue += stepLarge; break;
        }

        if (currentValue < 0) currentValue = 0;
        input.value = currentValue;
        input.dispatchEvent(new Event('change'));
    });
}

async function saveConfig() {
    const btn = document.getElementById('btn-save-server-config');
    const container = document.querySelector('[data-section="admin-server-config"]');
    if (!container || !btn) return;

    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="spinner-sm"></div> ${I18nManager.t('js.core.saving') || 'Guardando...'}`;

    const inputs = container.querySelectorAll('input[name], select[name], textarea[name]');
    const formData = new FormData();

    inputs.forEach(input => {
        let value = input.value;
        if (input.type === 'checkbox') value = input.checked ? '1' : '0';
        if (input.name === 'upload_avatar_max_size') {
            const mbValue = parseInt(value) || 0;
            value = mbValue * 1048576; 
        }
        formData.append(input.name, value);
    });

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.UpdateServerConfig, formData);
        if (res.success) ToastManager.show(res.message, 'success');
        else ToastManager.show(res.message, 'error');
    } catch (error) {
        ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}