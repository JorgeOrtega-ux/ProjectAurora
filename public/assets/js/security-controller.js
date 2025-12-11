/**
 * SecurityController.js
 * Maneja la lógica de la sección "Login & Security" y el asistente 2FA.
 * AHORA TAMBIÉN MANEJA LA SECCIÓN DE DISPOSITIVOS.
 */

import { SettingsService } from './api-services.js';
import { Toast } from './toast-service.js';

let currentPasswordBuffer = ''; 

/* --- LÓGICA DE CONTRASEÑA --- */
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

/* --- LÓGICA DE 2FA --- */
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

const handle2faSetupStart = async () => {
    const passwordInput = document.getElementById('setup-2fa-password');
    const btn = document.getElementById('btn-start-2fa-setup');
    if(!passwordInput.value) { Toast.error(window.t('js.error.complete_fields')); return; }
    btn.disabled = true;
    try {
        const result = await TwoFactorService.initSetup(passwordInput.value);
        if(result.status === 'success') {
            document.getElementById('step-1-auth').classList.remove('active');
            document.getElementById('step-1-auth').classList.add('disabled');
            const step2 = document.getElementById('step-2-qr');
            step2.classList.remove('disabled');
            step2.classList.add('active');
            document.getElementById('qr-image').src = result.data.qr_url;
            document.getElementById('secret-text').innerText = result.data.secret;
        } else {
            Toast.error(result.message);
        }
    } catch(e) {
        console.error(e); Toast.error(window.t('js.error.connection'));
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
            document.getElementById('step-2-qr').classList.remove('active');
            document.getElementById('step-2-qr').classList.add('disabled');
            const step3 = document.getElementById('step-3-backup');
            step3.classList.remove('disabled');
            step3.classList.add('active');
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
    } catch(e) { Toast.error(window.t('js.error.connection')); } finally { btn.disabled = false; }
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
            setTimeout(() => window.location.reload(), 1000);
        } else { Toast.error(result.message); }
    } catch(e) { Toast.error(window.t('js.error.connection')); } finally { btn.disabled = false; }
};

/* ==================================================
   NUEVO: LÓGICA DE DISPOSITIVOS
   ================================================== */
   
const loadDevices = async () => {
    const container = document.getElementById('devices-list-container');
    if (!container) return; // No estamos en la página correcta
    
    container.innerHTML = '<div class="loader-container"><div class="spinner"></div></div>';
    
    try {
        const result = await SettingsService.getActiveSessions();
        if (result.status === 'success') {
            renderDevicesList(container, result.data);
        } else {
            container.innerHTML = `<div class="alert error">${result.message}</div>`;
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = '<div class="alert error">Error cargando dispositivos.</div>';
    }
};

const renderDevicesList = (container, sessions) => {
    if (sessions.length === 0) {
        container.innerHTML = '<p style="text-align:center; color:#666;">No hay sesiones activas.</p>';
        return;
    }
    
    let html = '';
    sessions.forEach(session => {
        // Marcado especial para la sesión actual
        const highlightClass = session.is_current ? 'style="background-color: #f0f9ff; border-color: #b3e5fc;"' : '';
        const badge = session.is_current ? '<span class="component-badge" style="color: #0277bd; background:#e1f5fe; padding:2px 8px; border-radius:4px;">Este dispositivo</span>' : '';
        
        // Botón de eliminar (desactivado para la sesión actual)
        const deleteBtn = !session.is_current 
            ? `<button class="component-button danger" data-action="revoke-single" data-id="${session.id}">
                 <span class="material-symbols-rounded">logout</span>
               </button>`
            : '';

        html += `
        <div class="component-group-item" ${highlightClass}>
            <div class="component-card__content">
                <div class="component-card__profile-picture component-card__profile-picture--bordered">
                    <span class="material-symbols-rounded" style="font-size: 28px; color: #555;">${session.icon}</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title">
                        ${session.os} - ${session.browser} ${badge}
                    </h2>
                    <p class="component-card__description">
                        IP: ${session.ip} • Última vez: ${session.last_activity}
                    </p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                ${deleteBtn}
            </div>
        </div>
        <hr class="component-divider" style="margin:0;">
        `;
    });
    
    // Quitar el último HR
    if (html.endsWith('<hr class="component-divider" style="margin:0;">')) {
        html = html.substring(0, html.length - 46);
    }
    
    container.innerHTML = html;
};

const handleRevokeSingle = async (sessionId, btn) => {
    if(!confirm("¿Cerrar sesión en este dispositivo?")) return;
    
    btn.disabled = true;
    try {
        const result = await SettingsService.revokeSession(sessionId);
        if (result.status === 'success') {
            Toast.success(result.message);
            // Recargar la lista
            loadDevices();
        } else {
            Toast.error(result.message);
            btn.disabled = false;
        }
    } catch(e) {
        Toast.error(window.t('js.error.connection'));
        btn.disabled = false;
    }
};

const handleRevokeAll = async () => {
    const password = prompt("Para confirmar, ingresa tu contraseña actual:");
    if (!password) return;
    
    try {
        const result = await SettingsService.revokeAllSessions(password);
        if (result.status === 'success') {
            Toast.success(result.message);
            loadDevices();
        } else {
            Toast.error(result.message);
        }
    } catch(e) {
        Toast.error(window.t('js.error.connection'));
    }
};

const setupSecurityListeners = () => {
    document.addEventListener('click', (e) => {
        // Password Flow (Existente)
        const container = e.target.closest('[data-component="password-update-section"]');
        if (container) {
            if (e.target.closest('[data-action="pass-start-flow"]')) goToStage1(container);
            if (e.target.closest('[data-action="pass-cancel-flow"]')) resetFlow(container);
            if (e.target.closest('[data-action="pass-go-step-2"]')) goToStage2(container);
            if (e.target.closest('[data-action="pass-submit-final"]')) submitPasswordChange(container);
        }

        // 2FA Flow (Existente)
        if (e.target.id === 'btn-start-2fa-setup') handle2faSetupStart();
        if (e.target.id === 'btn-verify-2fa-setup') handle2faVerify();
        if (e.target.id === 'btn-disable-2fa') handle2faDisable();
        
        // Devices Flow (NUEVO)
        const revokeBtn = e.target.closest('[data-action="revoke-single"]');
        if (revokeBtn) {
            handleRevokeSingle(revokeBtn.dataset.id, revokeBtn);
        }
        
        if (e.target.closest('[data-action="revoke-all"]')) {
            handleRevokeAll();
        }
    });
    
    // Si entramos a la sección de dispositivos, cargar la lista
    if (document.getElementById('devices-list-container')) {
        loadDevices();
    }
};

export const initSecurityController = () => {
    console.log('SecurityController: Inicializado.');
    setupSecurityListeners();
};