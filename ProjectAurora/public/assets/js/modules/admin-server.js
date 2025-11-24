// public/assets/js/modules/admin-server.js

import { t } from '../core/i18n-manager.js';

const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

async function updateConfig(key, value, elementToRevertOnError) {
    try {
        const res = await fetch(API_ADMIN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ action: 'update_server_config', key, value })
        });
        const data = await res.json();

        if (data.success) {
            if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
            
            // Lógica especial de UI: Si activamos mantenimiento, desactivamos visualmente registro
            if (key === 'maintenance_mode' && value === 1) {
                const regToggle = document.getElementById('toggle-allow-registration');
                if (regToggle) regToggle.checked = false;
            }
        } else {
            if (window.alertManager) window.alertManager.showAlert(data.message, 'error');
            if (elementToRevertOnError) {
                // Revertir UI
                if (elementToRevertOnError.type === 'checkbox') {
                    elementToRevertOnError.checked = !elementToRevertOnError.checked;
                }
            }
        }
    } catch (e) {
        console.error(e);
        if (window.alertManager) window.alertManager.showAlert(t('global.error_connection'), 'error');
        if (elementToRevertOnError && elementToRevertOnError.type === 'checkbox') {
            elementToRevertOnError.checked = !elementToRevertOnError.checked;
        }
    }
}

export function initAdminServer() {
    // Toggle Mantenimiento
    const maintToggle = document.getElementById('toggle-maintenance-mode');
    if (maintToggle) {
        maintToggle.addEventListener('change', (e) => {
            updateConfig('maintenance_mode', e.target.checked ? 1 : 0, e.target);
        });
    }

    // Toggle Registro
    const regToggle = document.getElementById('toggle-allow-registration');
    if (regToggle) {
        regToggle.addEventListener('change', (e) => {
            updateConfig('allow_registrations', e.target.checked ? 1 : 0, e.target);
        });
    }

    // Stepper
    const steppers = document.querySelectorAll('.component-stepper');
    steppers.forEach(container => {
        const valueDisplay = container.querySelector('.stepper-value');
        const min = parseInt(container.dataset.min) || 0;
        const max = parseInt(container.dataset.max) || 9999;
        let currentVal = parseInt(container.dataset.currentValue) || 0;

        // Función local para actualizar valor visual y backend
        const updateStepper = (newVal) => {
            if (newVal < min) newVal = min;
            if (newVal > max) newVal = max;
            currentVal = newVal;
            valueDisplay.textContent = currentVal;
            container.dataset.currentValue = currentVal;
            
            // Debounce para no saturar API
            clearTimeout(container.debounceTimer);
            container.debounceTimer = setTimeout(() => {
                updateConfig('max_concurrent_users', currentVal, null);
            }, 500);
        };

        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.stepper-button');
            if (!btn) return;
            
            const action = btn.dataset.stepAction;
            if (action === 'increment-1') updateStepper(currentVal + 1);
            if (action === 'increment-10') updateStepper(currentVal + 10);
            if (action === 'decrement-1') updateStepper(currentVal - 1);
            if (action === 'decrement-10') updateStepper(currentVal - 10);
        });
    });
}