// public/assets/js/modules/admin-server.js

import { t } from '../core/i18n-manager.js';

const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
let debounceTimer = null;

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

// Mapa de acciones a claves de BD
const actionKeyMap = {
    'update-min-password-length': 'min_password_length',
    'update-max-password-length': 'max_password_length',
    'update-min-username-length': 'min_username_length',
    'update-max-username-length': 'max_username_length',
    'update-max-email-length': 'max_email_length',
    'update-max-login-attempts': 'max_login_attempts',
    'update-lockout-time-minutes': 'lockout_time_minutes',
    'update-code-resend-cooldown': 'code_resend_cooldown',
    'update-username-cooldown': 'username_cooldown',
    'update-email-cooldown': 'email_cooldown',
    'update-avatar-max-size': 'avatar_max_size'
};

async function updateConfig(key, value, elementToRevertOnError, silent = false) {
    try {
        const res = await fetch(API_ADMIN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ action: 'update_server_config', key, value })
        });
        const data = await res.json();

        if (data.success) {
            if (!silent && window.alertManager) window.alertManager.showAlert(data.message, 'success');
            
            // [CORRECCIÓN CRÍTICA] Actualizar configuración global en caliente
            // Esto asegura que las validaciones (auth, settings) usen el nuevo valor inmediatamente
            if (!window.SERVER_CONFIG) window.SERVER_CONFIG = {};
            window.SERVER_CONFIG[key] = value;
            console.log(`[Config] Updated ${key} to ${value}`);

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

function handleStepperClick(btn) {
    const stepper = btn.closest('.component-stepper');
    if (!stepper) return;

    const action = stepper.dataset.action;
    const min = parseInt(stepper.dataset.min);
    const max = parseInt(stepper.dataset.max);
    let currentVal = parseInt(stepper.dataset.currentValue);
    
    const stepAction = btn.dataset.stepAction;
    const step1 = parseInt(stepper.dataset.step1 || 1);
    const step10 = parseInt(stepper.dataset.step10 || 10);

    let newVal = currentVal;

    if (stepAction === 'increment-1') newVal += step1;
    if (stepAction === 'decrement-1') newVal -= step1;
    if (stepAction === 'increment-10') newVal += step10;
    if (stepAction === 'decrement-10') newVal -= step10;

    if (newVal < min) newVal = min;
    if (newVal > max) newVal = max;

    // Update UI
    stepper.dataset.currentValue = newVal;
    const valueDisplay = stepper.querySelector('.stepper-value');
    if (valueDisplay) valueDisplay.textContent = newVal;

    // Update button states
    const btns = stepper.querySelectorAll('.stepper-button');
    btns.forEach(b => {
        const type = b.dataset.stepAction;
        if (type.includes('decrement')) b.disabled = (newVal <= min);
        if (type.includes('increment')) b.disabled = (newVal >= max);
    });

    // Send to server (Debounced)
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const key = actionKeyMap[action];
        if (key) {
            updateConfig(key, newVal, null, true); // Silent update
        }
    }, 500);
}

export function initAdminServer() {
    // Listener para Checkboxes
    document.body.addEventListener('change', (e) => {
        const target = e.target;
        
        if (target.matches('#toggle-maintenance-mode')) {
            updateConfig('maintenance_mode', target.checked ? 1 : 0, target);
        }

        if (target.matches('#toggle-allow-registration')) {
            updateConfig('allow_registrations', target.checked ? 1 : 0, target);
        }
    });

    // Listener para Steppers
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.stepper-button');
        if (btn) {
            handleStepperClick(btn);
        }
    });
    
    // Init state buttons
    document.querySelectorAll('.component-stepper').forEach(stepper => {
        const min = parseInt(stepper.dataset.min);
        const max = parseInt(stepper.dataset.max);
        const current = parseInt(stepper.dataset.currentValue);
        const btns = stepper.querySelectorAll('.stepper-button');
        btns.forEach(b => {
            const type = b.dataset.stepAction;
            if (type.includes('decrement')) b.disabled = (current <= min);
            if (type.includes('increment')) b.disabled = (current >= max);
        });
    });
}