/**
 * SecurityController.js
 * Maneja la lógica de la sección "Login & Security" y el asistente 2FA.
 */

import { SettingsService } from './api-services.js';
import { Toast } from './toast-service.js';

let currentPasswordBuffer = ''; 

/* --- LÓGICA DE CONTRASEÑA (Existente) --- */
const resetFlow = (container) => {
    currentPasswordBuffer = '';
    const inputs = container.querySelectorAll('input');
    inputs.forEach(input => input.value = '');
    const stage0 = container.querySelector('[data-state="password-stage-0"]');
    const stage1 = container.querySelector('[data-state="password-stage-1"]');
    const stage2 = container.querySelector('[data-state="password-stage-2"]');
    const itemContainer = container.closest('.component-group-item');
    if (itemContainer) { itemContainer.classList.remove('component-group-item--stacked'); }
    if(stage0) { stage0.classList.remove('disabled'); stage0.classList.add('active'); }
    if(stage1) { stage1.classList.remove('active'); stage1.classList.add('disabled'); }
    if(stage2) { stage2.classList.remove('active'); stage2.classList.add('disabled'); }
};

const goToStage1 = (container) => {
    const stage0 = container.querySelector('[data-state="password-stage-0"]');
    const stage1 = container.querySelector('[data-state="password-stage-1"]');
    const itemContainer = container.closest('.component-group-item');
    if (itemContainer) { itemContainer.classList.add('component-group-item--stacked'); }
    if(stage0) { stage0.classList.remove('active'); stage0.classList.add('disabled'); }
    if(stage1) { stage1.classList.remove('disabled'); stage1.classList.add('active'); }
    setTimeout(() => {
        const input = container.querySelector('#current-password-input');
        if(input) input.focus();
    }, 100);
};

const goToStage2 = (container) => {
    const inputCurrent = container.querySelector('#current-password-input');
    if (!inputCurrent || !inputCurrent.value) {
        Toast.error(window.t('js.error.complete_fields'));
        return;
    }
    currentPasswordBuffer = inputCurrent.value;
    const stage1 = container.querySelector('[data-state="password-stage-1"]');
    const stage2 = container.querySelector('[data-state="password-stage-2"]');
    if(stage1) { stage1.classList.remove('active'); stage1.classList.add('disabled'); }
    if(stage2) { stage2.classList.remove('disabled'); stage2.classList.add('active'); }
    setTimeout(() => {
        const inputNew = container.querySelector('#new-password-input');
        if(inputNew) inputNew.focus();
    }, 100);
};

const submitPasswordChange = async (container) => {
    const inputNew = container.querySelector('#new-password-input');
    const inputRepeat = container.querySelector('#repeat-password-input');
    if (!inputNew || !inputRepeat || !inputNew.value || !inputRepeat.value) {
        Toast.error(window.t('js.error.complete_fields'));
        return;
    }
    if (inputNew.value !== inputRepeat.value) {
        Toast.error(window.t('js.error.pass_mismatch'));
        return;
    }
    if (inputNew.value.length < 8) {
        Toast.error(window.t('api.error.password_short'));
        return;
    }
    const btn = container.querySelector('[data-action="pass-submit-final"]');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = window.t('global.processing');
    try {
        const result = await SettingsService.updatePassword(currentPasswordBuffer, inputNew.value);
        if (result.status === 'success') {
            Toast.success(result.message);
            resetFlow(container);
        } else {
            Toast.error(result.message);
        }
    } catch (error) {
        console.error(error);
        Toast.error(window.t('js.error.connection'));
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
};

/* --- NUEVA LÓGICA DE 2FA (Services) --- */
const TwoFactorService = {
    initSetup: async (currentPassword) => {
        const formData = new FormData();
        formData.append('action', 'init_2fa');
        formData.append('current_password', currentPassword);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        
        const res = await fetch(window.BASE_PATH + 'api/settings_handler.php', { method: 'POST', body: formData });
        return await res.json();
    },
    enable: async (code) => {
        const formData = new FormData();
        formData.append('action', 'enable_2fa');
        formData.append('code', code);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        
        const res = await fetch(window.BASE_PATH + 'api/settings_handler.php', { method: 'POST', body: formData });
        return await res.json();
    },
    disable: async (currentPassword) => {
        const formData = new FormData();
        formData.append('action', 'disable_2fa');
        formData.append('current_password', currentPassword);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        
        const res = await fetch(window.BASE_PATH + 'api/settings_handler.php', { method: 'POST', body: formData });
        return await res.json();
    }
};

/* --- MANEJADORES DE UI PARA 2FA --- */
const handle2faSetupStart = async () => {
    const passwordInput = document.getElementById('setup-2fa-password');
    const btn = document.getElementById('btn-start-2fa-setup');

    if(!passwordInput.value) { Toast.error(window.t('js.error.complete_fields')); return; }

    btn.disabled = true;
    try {
        const result = await TwoFactorService.initSetup(passwordInput.value);
        if(result.status === 'success') {
            // Mostrar Paso 2 (QR)
            document.getElementById('step-1-auth').classList.remove('active');
            document.getElementById('step-1-auth').classList.add('disabled');
            
            const step2 = document.getElementById('step-2-qr');
            step2.classList.remove('disabled');
            step2.classList.add('active');

            // Llenar datos
            document.getElementById('qr-image').src = result.data.qr_url;
            document.getElementById('secret-text').innerText = result.data.secret;
        } else {
            Toast.error(result.message);
        }
    } catch(e) {
        console.error(e);
        Toast.error(window.t('js.error.connection'));
    } finally {
        btn.disabled = false;
    }
};

const handle2faVerify = async () => {
    const codeInput = document.getElementById('verify-2fa-code');
    const btn = document.getElementById('btn-verify-2fa-setup');

    if(!codeInput.value) { Toast.error(window.t('js.error.complete_fields')); return; }

    btn.disabled = true;
    try {
        const result = await TwoFactorService.enable(codeInput.value);
        if(result.status === 'success') {
            // Mostrar Paso 3 (Backup)
            document.getElementById('step-2-qr').classList.remove('active');
            document.getElementById('step-2-qr').classList.add('disabled');
            
            const step3 = document.getElementById('step-3-backup');
            step3.classList.remove('disabled');
            step3.classList.add('active');

            // Renderizar códigos
            const list = document.getElementById('backup-codes-list');
            list.innerHTML = '';
            result.data.recovery_codes.forEach(code => {
                const span = document.createElement('div');
                span.innerText = code;
                list.appendChild(span);
            });
            Toast.success(result.message);
        } else {
            Toast.error(result.message);
        }
    } catch(e) {
        Toast.error(window.t('js.error.connection'));
    } finally {
        btn.disabled = false;
    }
};

const handle2faDisable = async () => {
    const passwordInput = document.getElementById('disable-2fa-password');
    const btn = document.getElementById('btn-disable-2fa');

    if(!passwordInput.value) { Toast.error(window.t('js.error.complete_fields')); return; }
    
    if(!confirm("¿Seguro que quieres desactivar la protección 2FA?")) return;

    btn.disabled = true;
    try {
        const result = await TwoFactorService.disable(passwordInput.value);
        if(result.status === 'success') {
            Toast.success(result.message);
            // Recargar para actualizar el estado UI
            setTimeout(() => window.location.reload(), 1000);
        } else {
            Toast.error(result.message);
        }
    } catch(e) {
        Toast.error(window.t('js.error.connection'));
    } finally {
        btn.disabled = false;
    }
};

const setupSecurityListeners = () => {
    document.addEventListener('click', (e) => {
        // Password Flow
        const container = e.target.closest('[data-component="password-update-section"]');
        if (container) {
            if (e.target.closest('[data-action="pass-start-flow"]')) goToStage1(container);
            if (e.target.closest('[data-action="pass-cancel-flow"]')) resetFlow(container);
            if (e.target.closest('[data-action="pass-go-step-2"]')) goToStage2(container);
            if (e.target.closest('[data-action="pass-submit-final"]')) submitPasswordChange(container);
        }

        // 2FA Flow
        if (e.target.id === 'btn-start-2fa-setup') handle2faSetupStart();
        if (e.target.id === 'btn-verify-2fa-setup') handle2faVerify();
        if (e.target.id === 'btn-disable-2fa') handle2faDisable();
    });
};

export const initSecurityController = () => {
    console.log('SecurityController: Inicializado.');
    setupSecurityListeners();
};