/**
 * public/assets/js/modules/admin/server-config-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

export const ServerConfigController = {
    init: () => {
        console.log("ServerConfigController: Inicializado");
        
        const btnSave = document.getElementById('btn-save-server-config');
        if (btnSave) {
            btnSave.addEventListener('click', saveConfig);
        }

        _initSteppers();
        _initDomainManager();
    }
};

// --- GESTIÓN DE DOMINIOS (CHIPS) ---
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

    // === ACTIVAR MODO EDICIÓN ===
    btnAdd.addEventListener('click', () => {
        btnAdd.style.display = 'none';
        listContainer.classList.add('d-none');
        
        inputGroup.style.display = 'flex';
        inputNew.value = '';
        inputNew.focus();
    });

    // === SALIR MODO EDICIÓN ===
    const hideInput = () => {
        inputGroup.style.display = 'none';
        btnAdd.style.display = 'inline-flex';
        listContainer.classList.remove('d-none');
    };

    btnCancel.addEventListener('click', hideInput);

    const addDomain = () => {
        const val = inputNew.value.trim().toLowerCase();
        if (val) {
            if (val === '*') {
                domains = ['*'];
            } else {
                if (domains.includes('*')) {
                    domains = domains.filter(d => d !== '*');
                }
                if (!domains.includes(val)) {
                    domains.push(val);
                } else {
                    Toast.show(I18n.t('admin.server.domain_exists') || 'El dominio ya existe', 'warning');
                    return;
                }
            }
            updateHiddenInput();
            renderChips();
            hideInput();
        }
    };

    btnConfirm.addEventListener('click', addDomain);
    inputNew.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') addDomain();
    });

    listContainer.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.domain-chip-remove');
        if (removeBtn) {
            const valToRemove = removeBtn.dataset.value;
            domains = domains.filter(d => d !== valToRemove);
            
            if(domains.length === 0) domains = ['*'];

            updateHiddenInput();
            renderChips();
        }
    });

    function updateHiddenInput() {
        hiddenInput.value = domains.join(',');
    }

    function renderChips() {
        listContainer.innerHTML = '';
        domains.forEach(domain => {
            const chip = document.createElement('div');
            chip.className = 'domain-chip';
            chip.innerHTML = `
                <span>${domain}</span>
                <span class="material-symbols-rounded domain-chip-remove" data-value="${domain}">close</span>
            `;
            listContainer.appendChild(chip);
        });
    }
}

// --- STEPPERS (Actualizado para .component-stepper) ---
function _initSteppers() {
    const container = document.querySelector('[data-section="admin-server-config"]');
    if (!container) return;

    container.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        // [MODIFICADO] Selector actualizado
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

// --- SAVE ---
async function saveConfig() {
    const btn = document.getElementById('btn-save-server-config');
    const container = document.querySelector('[data-section="admin-server-config"]');
    
    if (!container || !btn) return;

    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="spinner-sm"></div> ${I18n.t('js.core.saving') || 'Guardando...'}`;

    const inputs = container.querySelectorAll('input[name], select[name], textarea[name]');
    const formData = new FormData();

    inputs.forEach(input => {
        let value = input.value;

        if (input.type === 'checkbox') {
            value = input.checked ? '1' : '0';
        }

        if (input.name === 'upload_avatar_max_size') {
            const mbValue = parseInt(value) || 0;
            value = mbValue * 1048576; 
        }

        formData.append(input.name, value);
    });

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.UpdateServerConfig, formData);
        if (res.success) {
            Toast.show(res.message, 'success');
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (error) {
        console.error(error);
        Toast.show(I18n.t('js.core.connection_error') || 'Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}