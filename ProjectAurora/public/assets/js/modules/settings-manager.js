// public/assets/js/modules/settings-manager.js

import { changeLanguage } from '../core/i18n-manager.js';
import { updateTheme } from '../core/theme-manager.js'; 

const API_SETTINGS = (window.BASE_PATH || '/ProjectAurora/') + 'api/settings_handler.php';

let areGlobalsInitialized = false;

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

function qs(selector) {
    return document.querySelector(selector);
}

export function initSettingsManager() {
    const isProfile = qs('[data-section="settings/your-profile"]');
    const isChangePass = qs('[data-section="settings/change-password"]');
    const isSessions = qs('[data-section="settings/sessions"]');
    const isDeleteAccount = qs('[data-section="settings/delete-account"]'); // Nueva sección
    
    if (isProfile) {
        initAvatarLogic();
        initUsernameLogic();
        initEmailLogic();
    }

    if (isChangePass) {
        initChangePasswordLogic();
    }

    if (isSessions) {
        initSessionsLogic();
    }

    if (isDeleteAccount) {
        initDeleteAccountLogic();
    }
    
    if (!areGlobalsInitialized) {
        initPreferencesLogic();        
        initBooleanPreferencesLogic(); 
        initAccountDeleteNavigation(); // Cambiado nombre para ser más específico
        initSessionsNavLogic();
        
        areGlobalsInitialized = true;
        console.log('[SettingsManager] Listeners globales inicializados (Única vez).');
    }
}

// ... (Funciones auxiliares updateCardError, getCsrfToken, qs se mantienen igual) ...
function updateCardError(element, message = '', show = true) {
    if (!element) return;
    const cardContainer = element.closest('.component-card') || element;
    let nextElement = cardContainer.nextElementSibling;
    let errorDiv = null;

    if (nextElement && nextElement.classList.contains('component-card__error')) {
        errorDiv = nextElement;
    }

    if (!errorDiv && show) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'component-card__error';
        cardContainer.after(errorDiv);
    }

    if (show && errorDiv) {
        errorDiv.textContent = message;
        requestAnimationFrame(() => errorDiv.classList.add('active'));
    } else if (!show && errorDiv) {
        errorDiv.classList.remove('active');
        setTimeout(() => {
            if (errorDiv.parentNode) errorDiv.parentNode.removeChild(errorDiv);
        }, 200); 
    }
}

// ========================================================
// LÓGICA ELIMINACIÓN DE CUENTA (NUEVO)
// ========================================================

// 1. Navegación (Listener Global)
function initAccountDeleteNavigation() {
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="trigger-account-delete"]');
        if (btn) {
            e.preventDefault();
            // Ahora redirige a la nueva sección en lugar de mostrar un alert
            if (window.navigateTo) window.navigateTo('settings/delete-account');
        }
    });
}

// 2. Lógica de la página de confirmación
function initDeleteAccountLogic() {
    const confirmBtn = qs('[data-action="confirm-account-deletion"]');
    const passInput = qs('[data-element="delete-confirm-password"]');
    const card = qs('.component-card--danger');

    if (!confirmBtn || !passInput) return;

    confirmBtn.onclick = async () => {
        const password = passInput.value;
        
        updateCardError(card, '', false);

        if (!password) {
            updateCardError(card, 'Por favor, ingresa tu contraseña para confirmar.');
            return;
        }

        if (!confirm('ADVERTENCIA FINAL: Esta acción no se puede deshacer. ¿Borrar cuenta permanentemente?')) {
            return;
        }

        setLoading(confirmBtn, true);

        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ 
                    action: 'delete_account', 
                    password: password 
                })
            });
            const data = await res.json();

            if (data.success) {
                // Redirigir a página de estado "Eliminado"
                window.location.href = (window.BASE_PATH || '/ProjectAurora/') + 'status-page?status=deleted';
            } else {
                updateCardError(card, data.message);
                setLoading(confirmBtn, false, 'Eliminar mi cuenta permanentemente');
            }
        } catch (e) {
            updateCardError(card, 'Error de conexión.');
            setLoading(confirmBtn, false, 'Eliminar mi cuenta permanentemente');
        }
    };
}

// ... (RESTO DE FUNCIONES: initSessionsLogic, initChangePasswordLogic, initPreferencesLogic, etc. SE MANTIENEN IGUAL) ...
// Solo asegúrate de no borrar las funciones existentes (initSessionsNavLogic, initAvatarLogic, etc.) 
// que ya estaban en el archivo original.

// (Copiar funciones helpers: setLoading, toggleMode, updateHeaderAvatar del archivo original)
function initSessionsNavLogic() {
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="trigger-sessions-manage"]');
        if (btn) {
            e.preventDefault();
            if (window.navigateTo) window.navigateTo('settings/sessions');
        }
    });
}

async function initSessionsLogic() {
    const container = qs('#sessions-list-container');
    const revokeAllBtn = qs('[data-action="revoke-all-sessions"]');
    if (!container) return;

    try {
        const res = await fetch(API_SETTINGS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            body: JSON.stringify({ action: 'get_sessions' })
        });
        const data = await res.json();

        if (data.success) {
            renderSessionsList(data.sessions, container);
        } else {
            container.innerHTML = `<p style="text-align:center; color:#d32f2f;">${data.message}</p>`;
        }
    } catch (e) {
        container.innerHTML = `<p style="text-align:center; color:#666;">Error de conexión.</p>`;
    }

    container.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="revoke-single"]');
        if (btn) {
            const sessionIdDb = btn.dataset.id;
            if (!confirm('¿Cerrar sesión en este dispositivo?')) return;

            btn.disabled = true; 
            btn.innerHTML = '<div class="small-spinner"></div>';

            try {
                const res = await fetch(API_SETTINGS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify({ action: 'revoke_session', session_id_db: sessionIdDb })
                });
                const data = await res.json();
                if (data.success) {
                    const card = btn.closest('.component-card');
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                    if (window.alertManager) window.alertManager.showAlert('Sesión cerrada.', 'success');
                } else {
                    if (window.alertManager) window.alertManager.showAlert(data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Revocar';
                }
            } catch (e) {
                console.error(e);
            }
        }
    });

    if (revokeAllBtn) {
        revokeAllBtn.onclick = async () => {
            if (!confirm('¿Estás seguro de cerrar todas las demás sesiones?')) return;
            setLoading(revokeAllBtn, true);
            try {
                const res = await fetch(API_SETTINGS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                    body: JSON.stringify({ action: 'revoke_all_sessions' })
                });
                const data = await res.json();
                if (data.success) {
                    if (window.alertManager) window.alertManager.showAlert('Sesiones cerradas.', 'success');
                    initSessionsLogic();
                } else {
                    if (window.alertManager) window.alertManager.showAlert(data.message, 'error');
                }
            } catch (e) {}
            setLoading(revokeAllBtn, false, 'Cerrar todas las demás sesiones');
        };
    }
}

function renderSessionsList(sessions, container) {
    if (sessions.length === 0) {
        container.innerHTML = '<p style="text-align:center; color:#666;">No se encontraron sesiones.</p>';
        return;
    }

    let html = '';
    sessions.forEach(sess => {
        const statusBadge = sess.is_current 
            ? `<span style="background:#e8f5e9; color:#2e7d32; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; margin-left:8px;">ACTUAL</span>` 
            : '';
        
        const revokeBtn = sess.is_current 
            ? '' 
            : `<button class="component-button" data-action="revoke-single" data-id="${sess.id}" style="color:#d32f2f; border-color:#ffcdd2;">Cerrar sesión</button>`;

        html += `
        <div class="component-card component-card--grouped" style="margin-bottom:16px;">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">${sess.icon}</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="display:flex; align-items:center;">
                            ${sess.os} - ${sess.browser} ${statusBadge}
                        </h2>
                        <p class="component-card__description">
                            ${sess.ip} • Última vez: ${new Date(sess.last_active).toLocaleString()}
                        </p>
                    </div>
                </div>
                <div class="component-card__actions">
                    ${revokeBtn}
                </div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function initChangePasswordLogic() {
    const step1Card = qs('[data-step="password-step-1"]');
    const step2Card = qs('[data-step="password-step-2"]');
    const step2Sessions = qs('[data-step="password-step-2-sessions"]');
    const step2Actions = qs('[data-step="password-step-2-actions"]');
    
    const currentPassInput = qs('[data-element="current-password"]');
    const newPassInput = qs('[data-element="new-password"]');
    const confirmPassInput = qs('[data-element="confirm-password"]');
    const logoutCheck = qs('[data-element="logout-others-check"]');

    const verifyBtn = qs('[data-action="verify-current-password"]');
    const saveBtn = qs('[data-action="save-new-password"]');

    if (!step1Card || !verifyBtn) return;

    verifyBtn.onclick = async () => {
        const pass = currentPassInput.value;
        if (!pass) {
            updateCardError(step1Card, 'Ingresa tu contraseña actual.');
            return;
        }

        setLoading(verifyBtn, true);
        updateCardError(step1Card, '', false);

        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ action: 'verify_current_password', password: pass })
            });
            const data = await res.json();

            if (data.success) {
                currentPassInput.disabled = true;
                verifyBtn.style.display = 'none'; 
                
                step2Card.classList.remove('disabled');
                step2Card.classList.add('active');
                step2Sessions.classList.remove('disabled');
                step2Sessions.classList.add('active');
                step2Actions.classList.remove('disabled');
                step2Actions.classList.add('active');

                newPassInput.focus();
            } else {
                updateCardError(step1Card, data.message);
            }
        } catch (e) {
            updateCardError(step1Card, 'Error de conexión.');
            console.error(e);
        }
        setLoading(verifyBtn, false);
    };

    saveBtn.onclick = async () => {
        const newPass = newPassInput.value;
        const confirmPass = confirmPassInput.value;
        const logout = logoutCheck.checked;

        updateCardError(step2Card, '', false);

        if (newPass.length < 8) {
            updateCardError(step2Card, 'La contraseña debe tener al menos 8 caracteres.');
            return;
        }

        if (newPass !== confirmPass) {
            updateCardError(step2Card, 'Las contraseñas no coinciden.');
            return;
        }

        setLoading(saveBtn, true);

        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ 
                    action: 'update_password', 
                    new_password: newPass,
                    logout_others: logout
                })
            });
            const data = await res.json();

            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                setTimeout(() => {
                    if (window.navigateTo) window.navigateTo('settings/login-security');
                    else window.location.reload();
                }, 1500);
            } else {
                updateCardError(step2Card, data.message);
            }
        } catch (e) {
            updateCardError(step2Card, 'Error de conexión.');
        }
        setLoading(saveBtn, false);
    };
}

function initBooleanPreferencesLogic() {
    document.body.addEventListener('change', async (e) => {
        const target = e.target;
        if (target.matches('input[type="checkbox"][data-preference-type="boolean"]')) {
            const fieldName = target.dataset.fieldName;
            const isChecked = target.checked;
            const card = target.closest('.component-card');
            const toggleWrapper = target.closest('.component-toggle-switch');

            if (!fieldName) return;

            updateCardError(card, '', false);
            if (toggleWrapper) toggleWrapper.classList.add('disabled-interactive');

            const payload = {
                action: 'update_boolean_preference',
                field: fieldName,
                value: isChecked, 
                csrf_token: getCsrfToken()
            };

            try {
                const res = await fetch(API_SETTINGS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if (data.success) {
                    if (window.alertManager) window.alertManager.showAlert('Preferencia actualizada.', 'success');
                } else {
                    target.checked = !isChecked; 
                    updateCardError(card, data.message);
                }
            } catch (err) {
                target.checked = !isChecked;
                console.error(err);
                updateCardError(card, 'Error de conexión');
            } finally {
                if (toggleWrapper) toggleWrapper.classList.remove('disabled-interactive');
            }
        }
    });
}

function initPreferencesLogic() {
    document.body.addEventListener('click', async (e) => {
        const option = e.target.closest('.menu-link[data-value]');
        if (!option) return;

        if (option.classList.contains('active')) return;

        const module = option.closest('.popover-module');
        if (!module) return;

        const wrapper = module.closest('.trigger-select-wrapper');
        const card = option.closest('.component-card');
        const prefType = module.dataset.preferenceType; 
        const value = option.dataset.value;

        if (!prefType || !value) return;

        updateCardError(card, '', false);
        if (wrapper) wrapper.classList.add('disabled-interactive');
        else module.classList.add('disabled-interactive');

        let payload = { action: '', csrf_token: getCsrfToken() };
        
        if (prefType === 'usage') {
            payload.action = 'update_usage';
            payload.usage = value;
        } else if (prefType === 'language') {
            payload.action = 'update_language';
            payload.language = value;
        } else if (prefType === 'theme') {
            payload.action = 'update_theme';
            payload.theme = value;
        }

        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                if (prefType === 'language') await changeLanguage(value);
                if (prefType === 'theme') updateTheme(value);
            } else {
                updateCardError(card, data.message);
            }
        } catch (err) {
            console.error(err);
            updateCardError(card, 'Error de conexión');
        } finally {
            if (wrapper) wrapper.classList.remove('disabled-interactive');
            else module.classList.remove('disabled-interactive');
        }
    });
}

function initAvatarLogic() {
    const cardItem = qs('[data-component="avatar-section"]');
    if (!cardItem) return;
    const elements = {
        fileInput: qs('[data-element="avatar-upload-input"]'),
        previewImg: qs('[data-element="avatar-preview-image"]'),
        overlayTrigger: qs('[data-action="trigger-avatar-upload"]'), 
        uploadBtn: qs('[data-action="avatar-upload-trigger"]'),
        changeBtn: qs('[data-action="avatar-change-trigger"]'),
        removeBtn: qs('[data-action="avatar-remove-trigger"]'),
        cancelBtn: qs('[data-action="avatar-cancel-trigger"]'),
        saveBtn: qs('[data-action="avatar-save-trigger-btn"]'),
        actionsDefault: qs('[data-state="avatar-actions-default"]'),
        actionsCustom: qs('[data-state="avatar-actions-custom"]'),
        actionsPreview: qs('[data-state="avatar-actions-preview"]')
    };
    if (!elements.fileInput) return;
    let originalImageSrc = elements.previewImg.src;
    const triggerUpload = (e) => { 
        if(e) e.preventDefault(); 
        updateCardError(cardItem, '', false);
        elements.fileInput.click(); 
    };
    if (elements.uploadBtn) elements.uploadBtn.onclick = triggerUpload;
    if (elements.changeBtn) elements.changeBtn.onclick = triggerUpload;
    if (elements.overlayTrigger) elements.overlayTrigger.onclick = triggerUpload;
    elements.fileInput.onchange = function(e) {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2097152) { updateCardError(cardItem, 'El archivo es demasiado grande (Máx. 2MB).'); this.value = ''; return; }
        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) { updateCardError(cardItem, 'Formato no válido.'); this.value = ''; return; }
        updateCardError(cardItem, '', false);
        const reader = new FileReader();
        reader.onload = function(evt) {
            elements.previewImg.src = evt.target.result;
            elements.previewImg.style.display = 'block';
            toggleAvatarActions('preview');
        };
        reader.readAsDataURL(file);
    };
    elements.cancelBtn.onclick = () => {
        updateCardError(cardItem, '', false);
        elements.previewImg.src = originalImageSrc;
        elements.fileInput.value = '';
        const isDefault = originalImageSrc.includes('data:image') || originalImageSrc === '' || originalImageSrc.endsWith('/') || originalImageSrc.includes('/default/') || originalImageSrc.includes('avatars_default') || originalImageSrc.includes('ui-avatars.com');       
        toggleAvatarActions(isDefault ? 'default' : 'custom');
    };
    elements.saveBtn.onclick = async () => {
        const file = elements.fileInput.files[0];
        if (!file) return;
        setLoading(elements.saveBtn, true);
        updateCardError(cardItem, '', false);
        const formData = new FormData();
        formData.append('action', 'update_avatar');
        formData.append('avatar', file);
        formData.append('csrf_token', getCsrfToken());
        try {
            const res = await fetch(API_SETTINGS, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                const newSrc = data.avatar_url + '?t=' + new Date().getTime();
                elements.previewImg.src = newSrc;
                originalImageSrc = newSrc;
                updateHeaderAvatar(newSrc);
                toggleAvatarActions('custom');
            } else { updateCardError(cardItem, data.message); }
        } catch (e) { updateCardError(cardItem, 'Error de conexión.'); }
        setLoading(elements.saveBtn, false, 'Guardar');
    };
    elements.removeBtn.onclick = async () => {
        if (!confirm('¿Restablecer avatar por defecto?')) return;
        setLoading(elements.removeBtn, true);
        updateCardError(cardItem, '', false);
        try {
            const formData = new FormData();
            formData.append('action', 'remove_avatar');
            formData.append('csrf_token', getCsrfToken());
            const res = await fetch(API_SETTINGS, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'info');
                const newSrc = data.avatar_url + '?t=' + new Date().getTime();
                elements.previewImg.src = newSrc;
                originalImageSrc = newSrc;
                updateHeaderAvatar(newSrc);
                toggleAvatarActions('default'); 
            } else { updateCardError(cardItem, data.message); }
        } catch (e) { updateCardError(cardItem, 'Error de conexión.'); }
        setLoading(elements.removeBtn, false, 'Eliminar');
    };
    function toggleAvatarActions(mode) {
        if(elements.actionsDefault) elements.actionsDefault.className = (mode === 'default') ? 'active' : 'disabled';
        if(elements.actionsCustom) elements.actionsCustom.className = (mode === 'custom') ? 'active' : 'disabled';
        if(elements.actionsPreview) elements.actionsPreview.className = (mode === 'preview') ? 'active' : 'disabled';
    }
}

function initUsernameLogic() {
    const itemSection = qs('[data-component="username-section"]');
    if (!itemSection) return;
    const els = {
        viewState: qs('[data-state="username-view-state"]'),
        editState: qs('[data-state="username-edit-state"]'),
        actionsView: qs('[data-state="username-actions-view"]'),
        actionsEdit: qs('[data-state="username-actions-edit"]'),
        display: qs('[data-element="username-display-text"]'),
        input: qs('[data-element="username-input"]'),
        editBtn: qs('[data-action="username-edit-trigger"]'),
        cancelBtn: qs('[data-action="username-cancel-trigger"]'),
        saveBtn: qs('[data-action="username-save-trigger-btn"]')
    };
    if (!els.input) return;
    let originalUsername = els.input.value;
    els.editBtn.onclick = () => { toggleMode(els, true); updateCardError(itemSection, '', false); els.input.value = ''; els.input.value = originalUsername; els.input.focus(); };
    els.cancelBtn.onclick = () => { els.input.value = originalUsername; updateCardError(itemSection, '', false); toggleMode(els, false); };
    els.saveBtn.onclick = async () => {
        const newVal = els.input.value.trim();
        updateCardError(itemSection, '', false);
        if (newVal === originalUsername) { toggleMode(els, false); return; }
        if (newVal.length < 8 || newVal.length > 32) { updateCardError(itemSection, 'El nombre de usuario debe tener entre 8 y 32 caracteres.'); return; }
        setLoading(els.saveBtn, true);
        try {
            const res = await fetch(API_SETTINGS, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }, body: JSON.stringify({ action: 'update_username', username: newVal }) });
            const data = await res.json();
            if (data.success) { if (window.alertManager) window.alertManager.showAlert(data.message, 'success'); originalUsername = data.new_username; els.display.textContent = data.new_username; els.input.value = data.new_username; toggleMode(els, false); } else { updateCardError(itemSection, data.message || 'Error al actualizar.'); }
        } catch (error) { updateCardError(itemSection, 'Error de conexión con el servidor.'); }
        setLoading(els.saveBtn, false, 'Guardar');
    };
}

function initEmailLogic() {
    const itemSection = qs('[data-component="email-section"]');
    if (!itemSection) return;
    const els = {
        viewState: qs('[data-state="email-view-state"]'),
        editState: qs('[data-state="email-edit-state"]'),
        actionsView: qs('[data-state="email-actions-view"]'),
        actionsEdit: qs('[data-state="email-actions-edit"]'),
        display: qs('[data-element="email-display-text"]'),
        input: qs('[data-element="email-input"]'),
        editBtn: qs('[data-action="email-edit-trigger"]'),
        cancelBtn: qs('[data-action="email-cancel-trigger"]'),
        saveBtn: qs('[data-action="email-save-trigger-btn"]')
    };
    if (!els.input) return;
    let originalEmail = els.input.value;
    els.editBtn.onclick = () => { toggleMode(els, true); updateCardError(itemSection, '', false); els.input.value = ''; els.input.value = originalEmail; els.input.focus(); };
    els.cancelBtn.onclick = () => { els.input.value = originalEmail; updateCardError(itemSection, '', false); toggleMode(els, false); };
    els.saveBtn.onclick = async () => {
        const newVal = els.input.value.trim().toLowerCase();
        updateCardError(itemSection, '', false);
        if (newVal === originalEmail) { toggleMode(els, false); return; }
        const regex = /^[^@\s]+@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i;
        if (!regex.test(newVal)) { updateCardError(itemSection, 'Dominio no permitido.'); return; }
        setLoading(els.saveBtn, true);
        try {
            const res = await fetch(API_SETTINGS, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }, body: JSON.stringify({ action: 'update_email', email: newVal }) });
            const data = await res.json();
            if (data.success) { if (window.alertManager) window.alertManager.showAlert(data.message, 'success'); originalEmail = data.new_email; els.display.textContent = data.new_email; els.input.value = data.new_email; toggleMode(els, false); } else { updateCardError(itemSection, data.message || 'Error al actualizar.'); }
        } catch (error) { updateCardError(itemSection, 'Error de conexión con el servidor.'); }
        setLoading(els.saveBtn, false, 'Guardar');
    };
}

function toggleMode(els, isEditing) {
    if (isEditing) {
        els.viewState.classList.remove('active'); els.viewState.classList.add('disabled');
        els.actionsView.classList.remove('active'); els.actionsView.classList.add('disabled');
        els.editState.classList.remove('disabled'); els.editState.classList.add('active');
        els.actionsEdit.classList.remove('disabled'); els.actionsEdit.classList.add('active');
    } else {
        els.editState.classList.remove('active'); els.editState.classList.add('disabled');
        els.actionsEdit.classList.remove('active'); els.actionsEdit.classList.add('disabled');
        els.viewState.classList.remove('disabled'); els.viewState.classList.add('active');
        els.actionsView.classList.remove('disabled'); els.actionsView.classList.add('active');
    }
}

function updateHeaderAvatar(src) {
    const headerImg = document.querySelector('.header-button.profile-button .profile-img');
    if (headerImg) headerImg.src = src;
}

function setLoading(btn, isLoading, originalText) {
    if (isLoading) {
        btn.dataset.original = btn.textContent;
        btn.innerHTML = '<div class="small-spinner"></div>';
        btn.disabled = true;
    } else {
        btn.innerHTML = originalText || btn.dataset.original;
        btn.disabled = false;
    }
}