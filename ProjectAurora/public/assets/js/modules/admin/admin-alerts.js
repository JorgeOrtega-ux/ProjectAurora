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
    const allEmitBtns = document.querySelectorAll('.btn-emit-alert');

    if (activeAlert) {
        // Hay alerta activa
        if (indicator) indicator.classList.remove('d-none');
        if (indicatorName) indicatorName.textContent = t(`admin.alerts.templates.${activeAlert.type}.title`);
        
        // Deshabilitar botones de emitir
        allEmitBtns.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        });
    } else {
        // No hay alerta
        if (indicator) indicator.classList.add('d-none');
        
        // Habilitar botones
        allEmitBtns.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
        });
    }
}

function initListeners() {
    document.body.addEventListener('click', async (e) => {
        const emitBtn = e.target.closest('[data-action="emit-alert"]');
        if (emitBtn && !emitBtn.disabled) {
            const type = emitBtn.dataset.id;
            if (!confirm(t('admin.alerts.confirm_emit'))) return;

            setButtonLoading(emitBtn, true);
            const res = await postJson('api/admin_handler.php', { action: 'activate_alert', type });
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                checkActiveAlert();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            setButtonLoading(emitBtn, false);
        }

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