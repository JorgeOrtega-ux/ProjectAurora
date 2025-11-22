// public/assets/js/modules/settings-manager.js

import { changeLanguage } from '../core/i18n-manager.js';
import { updateTheme } from '../core/theme-manager.js'; 

const API_SETTINGS = (window.BASE_PATH || '/ProjectAurora/') + 'api/settings_handler.php';

// Bandera de control para evitar duplicidad de eventos globales
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
    // 1. Detectar en qué sección estamos
    const isProfile = qs('[data-section="settings/your-profile"]');
    const isChangePass = qs('[data-section="settings/change-password"]');
    
    // Lógica LOCAL para cada sección
    if (isProfile) {
        initAvatarLogic();
        initUsernameLogic();
        initEmailLogic();
    }

    if (isChangePass) {
        initChangePasswordLogic();
    }
    
    // 3. Lógica GLOBAL (Se ejecuta UNA SOLA VEZ por sesión)
    if (!areGlobalsInitialized) {
        initPreferencesLogic();        // Selects (Tema, Idioma, Uso)
        initBooleanPreferencesLogic(); // Checkboxes (Toggles)
        
        areGlobalsInitialized = true;
        console.log('[SettingsManager] Listeners globales inicializados (Única vez).');
    }
}

/**
 * [CORREGIDO] Muestra/Oculta errores.
 * Busca la tarjeta principal (.component-card) para renderizar el error FUERA de ella,
 * evitando que quede atrapado dentro de un .component-card--grouped.
 */
function updateCardError(element, message = '', show = true) {
    if (!element) return;
    
    // Buscamos el contenedor padre 'component-card'. 
    // Si el elemento pasado YA es la tarjeta (como en change-password), se usa a sí mismo.
    const cardContainer = element.closest('.component-card') || element;

    let nextElement = cardContainer.nextElementSibling;
    let errorDiv = null;

    // Verificar si ya existe un div de error después de la tarjeta
    if (nextElement && nextElement.classList.contains('component-card__error')) {
        errorDiv = nextElement;
    }

    // Crear div si no existe y debemos mostrar error
    if (!errorDiv && show) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'component-card__error';
        cardContainer.after(errorDiv); // Se inserta DESPUÉS de la tarjeta principal
    }

    if (show && errorDiv) {
        errorDiv.textContent = message;
        // Pequeño delay para permitir transición CSS si aplica
        requestAnimationFrame(() => errorDiv.classList.add('active'));
    } else if (!show && errorDiv) {
        errorDiv.classList.remove('active');
        // Esperar a que termine transición (opcional) o remover directo
        setTimeout(() => {
            if (errorDiv.parentNode) errorDiv.parentNode.removeChild(errorDiv);
        }, 200); 
    }
}

// ========================================================
// LÓGICA CAMBIO DE CONTRASEÑA
// ========================================================
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

    // PASO 1: Verificar contraseña actual
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
                // Transición UI: Ocultar input actual, mostrar nuevos inputs
                currentPassInput.disabled = true; // Bloquear input
                verifyBtn.style.display = 'none'; 
                
                // Mostrar Paso 2
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

    // PASO 2: Guardar nueva contraseña
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
                // Redirigir a seguridad después de un breve delay
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

// ========================================================
// TOGGLES (Booleanos) - Lógica Global
// ========================================================
function initBooleanPreferencesLogic() {
    document.body.addEventListener('change', async (e) => {
        const target = e.target;
        // Delegación de eventos para capturar cualquier toggle booleano
        if (target.matches('input[type="checkbox"][data-preference-type="boolean"]')) {
            const fieldName = target.dataset.fieldName;
            const isChecked = target.checked;
            const card = target.closest('.component-card');
            const toggleWrapper = target.closest('.component-toggle-switch');

            if (!fieldName) return;

            // UI Optimista / Bloqueo
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
                    // Revertir si hubo error lógico en servidor
                    target.checked = !isChecked; 
                    updateCardError(card, data.message);
                }
            } catch (err) {
                // Revertir si hubo error de red
                target.checked = !isChecked;
                console.error(err);
                updateCardError(card, 'Error de conexión');
            } finally {
                if (toggleWrapper) toggleWrapper.classList.remove('disabled-interactive');
            }
        }
    });
}

// ========================================================
// SELECTORES (Theme, Lang, Usage) - Lógica Global
// ========================================================
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
                
                if (prefType === 'language') {
                    await changeLanguage(value);
                }
                if (prefType === 'theme') {
                    updateTheme(value);
                }

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

// ========================================================
// LÓGICA DE AVATAR (Local)
// ========================================================
function initAvatarLogic() {
    // Buscamos el elemento local, pero updateCardError usará su closest('.component-card')
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
        
        if (file.size > 2097152) {
            updateCardError(cardItem, 'El archivo es demasiado grande (Máx. 2MB).');
            this.value = ''; return;
        }
        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
            updateCardError(cardItem, 'Formato no válido. Usa JPG, PNG o WEBP.');
            this.value = ''; return;
        }
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
            } else {
                updateCardError(cardItem, data.message);
            }
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
            } else {
                updateCardError(cardItem, data.message);
            }
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

    els.editBtn.onclick = () => {
        toggleMode(els, true);
        updateCardError(itemSection, '', false);
        els.input.value = ''; els.input.value = originalUsername; els.input.focus();
    };
    els.cancelBtn.onclick = () => {
        els.input.value = originalUsername;
        updateCardError(itemSection, '', false);
        toggleMode(els, false);
    };
    els.saveBtn.onclick = async () => {
        const newVal = els.input.value.trim();
        updateCardError(itemSection, '', false);
        if (newVal === originalUsername) { toggleMode(els, false); return; }
        if (newVal.length < 8 || newVal.length > 32) {
            updateCardError(itemSection, 'El nombre de usuario debe tener entre 8 y 32 caracteres.'); return;
        }
        setLoading(els.saveBtn, true);
        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ action: 'update_username', username: newVal })
            });
            const data = await res.json();
            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                originalUsername = data.new_username;
                els.display.textContent = data.new_username;
                els.input.value = data.new_username;
                toggleMode(els, false);
            } else { updateCardError(itemSection, data.message || 'Error al actualizar.'); }
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

    els.editBtn.onclick = () => {
        toggleMode(els, true);
        updateCardError(itemSection, '', false);
        els.input.value = ''; els.input.value = originalEmail; els.input.focus();
    };
    els.cancelBtn.onclick = () => {
        els.input.value = originalEmail;
        updateCardError(itemSection, '', false);
        toggleMode(els, false);
    };
    els.saveBtn.onclick = async () => {
        const newVal = els.input.value.trim().toLowerCase();
        updateCardError(itemSection, '', false);
        if (newVal === originalEmail) { toggleMode(els, false); return; }
        const regex = /^[^@\s]+@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i;
        if (!regex.test(newVal)) { updateCardError(itemSection, 'Dominio no permitido.'); return; }
        setLoading(els.saveBtn, true);
        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ action: 'update_email', email: newVal })
            });
            const data = await res.json();
            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                originalEmail = data.new_email;
                els.display.textContent = data.new_email;
                els.input.value = data.new_email;
                toggleMode(els, false);
            } else { updateCardError(itemSection, data.message || 'Error al actualizar.'); }
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