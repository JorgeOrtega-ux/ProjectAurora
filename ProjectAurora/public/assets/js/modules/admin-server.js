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
            
            // Lógica visual específica: Si activamos mantenimiento, desactivamos registro visualmente
            if (key === 'maintenance_mode' && value === 1) {
                const regToggle = document.getElementById('toggle-allow-registration');
                if (regToggle) regToggle.checked = false;
            }
        } else {
            if (window.alertManager) window.alertManager.showAlert(data.message, 'error');
            // Revertir cambio visual si falló
            if (elementToRevertOnError && elementToRevertOnError.type === 'checkbox') {
                elementToRevertOnError.checked = !elementToRevertOnError.checked;
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

// Variable para controlar el debounce del stepper y no saturar el servidor
let stepperTimeout = null;

export function initAdminServer() {
    // Usamos Delegación de Eventos Global para evitar problemas con la carga dinámica (SPA)
    
    // 1. Listener para Checkboxes (Mantenimiento y Registro)
    document.body.addEventListener('change', (e) => {
        const target = e.target;
        
        // Detectar Toggle de Mantenimiento
        if (target.matches('#toggle-maintenance-mode')) {
            updateConfig('maintenance_mode', target.checked ? 1 : 0, target);
        }

        // Detectar Toggle de Registro
        if (target.matches('#toggle-allow-registration')) {
            updateConfig('allow_registrations', target.checked ? 1 : 0, target);
        }
    });

    // 2. Listener para Steppers (Botones de incremento/decremento)
    document.body.addEventListener('click', (e) => {
        // Buscamos si el click fue dentro de un botón de stepper
        const btn = e.target.closest('.stepper-button');
        if (!btn) return;

        // Buscamos el contenedor padre del stepper
        const container = btn.closest('.component-stepper');
        if (!container) return;

        // Solo actuamos si es el stepper de configuración de servidor
        if (container.dataset.action !== 'update-max-concurrent-users') return;

        e.preventDefault(); // Evitar comportamientos extraños

        const valueDisplay = container.querySelector('.stepper-value');
        const min = parseInt(container.dataset.min) || 0;
        const max = parseInt(container.dataset.max) || 9999;
        let currentVal = parseInt(container.dataset.currentValue) || 0;
        const action = btn.dataset.stepAction;

        // Calcular nuevo valor
        if (action === 'increment-1') currentVal += 1;
        if (action === 'increment-10') currentVal += 10;
        if (action === 'decrement-1') currentVal -= 1;
        if (action === 'decrement-10') currentVal -= 10;

        // Validar límites
        if (currentVal < min) currentVal = min;
        if (currentVal > max) currentVal = max;

        // Actualizar UI inmediatamente
        valueDisplay.textContent = currentVal;
        container.dataset.currentValue = currentVal;

        // Enviar al servidor con Debounce (esperar 500ms a que el usuario deje de clickear)
        if (stepperTimeout) clearTimeout(stepperTimeout);
        
        stepperTimeout = setTimeout(() => {
            updateConfig('max_concurrent_users', currentVal, null);
        }, 500);
    });
}