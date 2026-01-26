/**
 * public/assets/js/modules/admin/backup-config-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { navigateTo } from '../../core/url-manager.js';

let _container = null;

export const BackupConfigController = {
    init: async () => {
        console.log("BackupConfigController: Inicializado");
        
        _container = document.querySelector('[data-section="admin-backup-config"]');
        if (!_container) return;

        initEvents();
        await loadConfig();
    }
};

function initEvents() {
    const btnBack = _container.querySelector('[data-action="back-to-backups"]');
    if (btnBack) btnBack.addEventListener('click', () => navigateTo('admin/backups'));

    // CORRECCIÓN: Usar querySelector con el selector de ID (#)
    const btnSave = _container.querySelector('#btn-save-backup-config');
    if (btnSave) btnSave.addEventListener('click', saveConfig);

    // Lógica de Steppers (Reutilizable)
    _container.addEventListener('click', (e) => {
        const btn = e.target.closest('.stepper-btn');
        if (!btn) return;

        const wrapper = btn.closest('.stepper-control');
        if (!wrapper) return;

        const input = wrapper.querySelector('input');
        if (!input) return;

        const action = btn.dataset.action;
        const stepSmall = parseInt(wrapper.dataset.stepSmall) || 1;
        const stepLarge = parseInt(wrapper.dataset.stepLarge) || 5;
        
        let currentValue = parseInt(input.value) || 0;

        switch (action) {
            case 'dec-large': currentValue -= stepLarge; break;
            case 'dec-small': currentValue -= stepSmall; break;
            case 'inc-small': currentValue += stepSmall; break;
            case 'inc-large': currentValue += stepLarge; break;
        }

        if (currentValue < 1) currentValue = 1; // Mínimo 1 para estos campos
        input.value = currentValue;
    });
}

async function loadConfig() {
    // Aquí podemos usar querySelector sobre el contenedor para mayor seguridad
    const loading = _container.querySelector('#config-loading-state');
    const content = _container.querySelector('#config-content-area');

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.GetConfig);
        
        if (res.success) {
            const checkEnabled = _container.querySelector('#input-auto-enabled');
            const inputFreq = _container.querySelector('#input-frequency');
            const inputRet = _container.querySelector('#input-retention');

            if (checkEnabled) checkEnabled.checked = res.enabled;
            if (inputFreq) inputFreq.value = res.frequency;
            if (inputRet) inputRet.value = res.retention;

            if(loading) loading.classList.add('d-none');
            if(content) content.classList.remove('d-none');
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        console.error(e);
        Toast.show('Error de conexión', 'error');
    }
}

async function saveConfig() {
    const btn = _container.querySelector('#btn-save-backup-config');
    const originalText = btn.innerHTML;
    
    const enabled = _container.querySelector('#input-auto-enabled').checked;
    const frequency = _container.querySelector('#input-frequency').value;
    const retention = _container.querySelector('#input-retention').value;

    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-sm"></div> Guardando...';

    const formData = new FormData();
    formData.append('enabled', enabled ? '1' : '0');
    formData.append('frequency', frequency);
    formData.append('retention', retention);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.UpdateConfig, formData);
        if (res.success) {
            Toast.show('Configuración guardada correctamente', 'success');
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        Toast.show('Error al guardar', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}