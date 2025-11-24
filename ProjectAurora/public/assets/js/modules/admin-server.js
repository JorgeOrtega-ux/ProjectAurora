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
            
            if (key === 'maintenance_mode' && value === 1) {
                const regToggle = document.getElementById('toggle-allow-registration');
                if (regToggle) regToggle.checked = false;
            }
        } else {
            if (window.alertManager) window.alertManager.showAlert(data.message, 'error');
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

let stepperTimeout = null;

function handleServerStats(e) {
    const { type, stats, log } = e.detail;
    
    // Actualizar Estadísticas
    if (type === 'server_stats_debug' && stats) {
        const elMax = document.getElementById('debug-max-users');
        const elDb = document.getElementById('debug-db-sessions');
        const elQueue = document.getElementById('debug-queue-len');
        const elReal = document.getElementById('debug-real-users');

        if (elMax) elMax.textContent = stats.max_users;
        if (elDb) elDb.textContent = stats.db_total_sessions;
        if (elQueue) elQueue.textContent = stats.queue_length;
        if (elReal) elReal.textContent = stats.real_users_in_app;
    }

    // Actualizar Consola de Logs
    if (type === 'server_log_debug' && log) {
        const consoleDiv = document.getElementById('server-log-console');
        if (consoleDiv) {
            const line = document.createElement('div');
            line.textContent = log;
            consoleDiv.appendChild(line);
            // Auto-scroll al final
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
            
            // Limitar a 100 líneas para no saturar memoria
            if (consoleDiv.children.length > 100) {
                consoleDiv.removeChild(consoleDiv.firstChild);
            }
        }
    }
}

export function initAdminServer() {
    // Limpiar listener anterior si existía
    document.removeEventListener('socket-message', handleServerStats);
    document.addEventListener('socket-message', handleServerStats);

    // 1. Listener para Checkboxes
    document.body.addEventListener('change', (e) => {
        const target = e.target;
        
        if (target.matches('#toggle-maintenance-mode')) {
            updateConfig('maintenance_mode', target.checked ? 1 : 0, target);
        }

        if (target.matches('#toggle-allow-registration')) {
            updateConfig('allow_registrations', target.checked ? 1 : 0, target);
        }
    });

    // 2. Listener para Steppers
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.stepper-button');
        if (!btn) return;

        const container = btn.closest('.component-stepper');
        if (!container) return;

        if (container.dataset.action !== 'update-max-concurrent-users') return;

        e.preventDefault();

        const valueDisplay = container.querySelector('.stepper-value');
        const min = parseInt(container.dataset.min) || 0;
        const max = parseInt(container.dataset.max) || 9999;
        let currentVal = parseInt(container.dataset.currentValue) || 0;
        const action = btn.dataset.stepAction;

        if (action === 'increment-1') currentVal += 1;
        if (action === 'increment-10') currentVal += 10;
        if (action === 'decrement-1') currentVal -= 1;
        if (action === 'decrement-10') currentVal -= 10;

        if (currentVal < min) currentVal = min;
        if (currentVal > max) currentVal = max;

        valueDisplay.textContent = currentVal;
        container.dataset.currentValue = currentVal;

        // Actualizar UI local inmediatamente
        const elMax = document.getElementById('debug-max-users');
        if(elMax) elMax.textContent = currentVal;

        if (stepperTimeout) clearTimeout(stepperTimeout);
        
        stepperTimeout = setTimeout(() => {
            updateConfig('max_concurrent_users', currentVal, null);
        }, 500);
    });
}