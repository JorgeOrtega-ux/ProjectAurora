// public/assets/js/modules/admin/admin-alerts.js

import { t } from '../../core/i18n-manager.js';
import { postJson, setButtonLoading } from '../../core/utilities.js';

export function initAdminAlerts() {
    checkActiveAlert();
    initListeners();
}

async function checkActiveAlert() {
    const res = await postJson('api/admin_handler.php', { action: 'get_alert_status' });
    if (res.success) {
        updateUI(res.active_alert);
    }
}

function updateUI(activeAlert) {
    const indicator = document.getElementById('active-alert-indicator');
    const indicatorName = document.getElementById('active-alert-name');
    
    // El botón principal de emitir
    const mainEmitBtn = document.getElementById('btn-emit-selected-alert');
    // El wrapper del selector para deshabilitarlo si hay alerta
    const triggerWrapper = document.querySelector('.trigger-select-wrapper');

    if (activeAlert) {
        // Hay alerta activa
        if (indicator) indicator.classList.remove('d-none');
        if (indicatorName) indicatorName.textContent = t(`admin.alerts.templates.${activeAlert.type}.title`);
        
        if (mainEmitBtn) {
            mainEmitBtn.disabled = true;
            mainEmitBtn.textContent = 'Alerta en curso...';
        }
        
        if (triggerWrapper) {
            triggerWrapper.classList.add('disabled-interactive');
            triggerWrapper.style.opacity = '0.5';
        }

    } else {
        // No hay alerta activa
        if (indicator) indicator.classList.add('d-none');
        
        // Habilitar controles
        if (triggerWrapper) {
            triggerWrapper.classList.remove('disabled-interactive');
            triggerWrapper.style.opacity = '1';
        }

        // Revisar si hay selección para habilitar el botón
        const currentSelection = document.getElementById('input-alert-type').value;
        if (mainEmitBtn) {
            if (currentSelection) {
                mainEmitBtn.disabled = false;
                mainEmitBtn.textContent = t('admin.alerts.emit_btn');
            } else {
                mainEmitBtn.disabled = true;
            }
        }
    }
}

function handleSelection(option) {
    const val = option.dataset.value;
    const label = option.dataset.label;
    const icon = option.dataset.icon;
    const color = option.dataset.color;

    // Actualizar Input
    document.getElementById('input-alert-type').value = val;

    // Actualizar Trigger Visual
    document.getElementById('current-alert-text').textContent = label;
    const iconEl = document.getElementById('current-alert-icon');
    iconEl.textContent = icon;
    iconEl.style.color = color;

    // Actualizar Preview
    const descKey = `admin.alerts.templates.${val}.desc`;
    const previewEl = document.getElementById('alert-preview-desc');
    if (previewEl) previewEl.textContent = t(descKey);

    // Habilitar botón emitir
    const btn = document.getElementById('btn-emit-selected-alert');
    if (btn) {
        btn.disabled = false;
        btn.textContent = t('admin.alerts.emit_btn');
    }
}

function initListeners() {
    document.body.addEventListener('click', async (e) => {
        
        // Selección del Dropdown
        const option = e.target.closest('[data-action="select-alert-option"]');
        if (option) {
            // El manejo visual de 'active' lo hace main-controller.js
            // Nosotros manejamos la lógica específica
            handleSelection(option);
            return;
        }

        // Botón Emitir (Nuevo)
        const emitBtn = e.target.closest('#btn-emit-selected-alert');
        if (emitBtn && !emitBtn.disabled) {
            const type = document.getElementById('input-alert-type').value;
            if (!type) return;

            if (!confirm(t('admin.alerts.confirm_emit'))) return;

            setButtonLoading(emitBtn, true);
            const res = await postJson('api/admin_handler.php', { action: 'activate_alert', type });
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                checkActiveAlert();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(emitBtn, false, t('admin.alerts.emit_btn'));
            }
        }

        // Botón Detener (Stop)
        const stopBtn = e.target.closest('[data-action="stop-alert"]');
        if (stopBtn) {
            if (!confirm(t('admin.alerts.confirm_stop'))) return;

            setButtonLoading(stopBtn, true);
            const res = await postJson('api/admin_handler.php', { action: 'stop_alert' });
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
                checkActiveAlert();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            setButtonLoading(stopBtn, false);
        }
    });
}