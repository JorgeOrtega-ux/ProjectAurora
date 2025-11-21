// public/assets/js/modules/settings-manager.js

const API_SETTINGS = (window.BASE_PATH || '/ProjectAurora/') + 'api/settings_handler.php';

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

// Helper para seleccionar por data-attributes en lugar de ID
function qs(selector) {
    return document.querySelector(selector);
}

export function initSettingsManager() {
    // Inicializar solo si estamos en la sección correcta.
    if (!qs('[data-section="settings/your-profile"]') && !qs('[data-component="avatar-section"]')) return;

    console.log('Settings Manager: Inicializando...');

    initAvatarLogic();
    initUsernameLogic();
    initEmailLogic();
    initPreferencesLogic(); 
    initBooleanPreferencesLogic();
}

// ========================================================
// HELPER PARA ERRORES FUERA DE LA TARJETA
// ========================================================
function updateCardError(cardElement, message = '', show = true) {
    if (!cardElement) return;

    // 1. Buscar si el SIGUIENTE elemento es ya un div de error
    let nextElement = cardElement.nextElementSibling;
    let errorDiv = null;

    if (nextElement && nextElement.classList.contains('component-card__error')) {
        errorDiv = nextElement;
    }

    // 2. Si no existe y queremos mostrarlo, lo creamos e insertamos DESPUÉS
    if (!errorDiv && show) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'component-card__error';
        cardElement.after(errorDiv);
    }

    // 3. Actualizar estado
    if (show && errorDiv) {
        errorDiv.textContent = message;
        requestAnimationFrame(() => {
            errorDiv.classList.add('active');
        });
    } else if (!show && errorDiv) {
        errorDiv.remove();
    }
}

// ========================================================
// LÓGICA DE PREFERENCIAS BOOLEANAS (TOGGLES)
// ========================================================
function initBooleanPreferencesLogic() {
    const profileSection = qs('[data-section="settings/your-profile"]');
    if (!profileSection) return;

    profileSection.addEventListener('change', async (e) => {
        const target = e.target;
        if (target.matches('input[type="checkbox"][data-preference-type="boolean"]')) {
            const fieldName = target.dataset.fieldName;
            const isChecked = target.checked;
            const card = target.closest('.component-card');

            if (!fieldName) return;

            // Limpiamos error previo
            updateCardError(card, '', false);

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
                    // No revertimos visualmente (Optimista), solo mostramos el error
                    updateCardError(card, data.message);
                }
            } catch (err) {
                console.error(err);
                updateCardError(card, 'Error de conexión');
            }
        }
    });
}

// ========================================================
// LÓGICA DE PREFERENCIAS (SELECTORES MENU)
// ========================================================
function initPreferencesLogic() {
    const profileSection = qs('[data-section="settings/your-profile"]');
    if (!profileSection) return;

    profileSection.addEventListener('click', async (e) => {
        const option = e.target.closest('.menu-link[data-value]');
        if (!option) return;

        // [NUEVO] Si la opción ya está activa (seleccionada), no hacemos nada.
        if (option.classList.contains('active')) {
            return;
        }

        const module = option.closest('.popover-module');
        if (!module) return;

        const card = option.closest('.component-card');

        const prefType = module.dataset.preferenceType; 
        const value = option.dataset.value;

        if (!prefType || !value) return;

        // Limpiamos error previo
        updateCardError(card, '', false);

        let payload = { action: '', csrf_token: getCsrfToken() };
        
        if (prefType === 'usage') {
            payload.action = 'update_usage';
            payload.usage = value;
        } else if (prefType === 'language') {
            payload.action = 'update_language';
            payload.language = value;
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
            } else {
                updateCardError(card, data.message);
            }
        } catch (err) {
            console.error(err);
            updateCardError(card, 'Error de conexión');
        }
    });
}

// ========================================================
// LÓGICA DE AVATAR
// ========================================================
function initAvatarLogic() {
    const card = qs('[data-component="avatar-section"]');
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
        updateCardError(card, '', false);
        elements.fileInput.click(); 
    };

    if (elements.uploadBtn) elements.uploadBtn.addEventListener('click', triggerUpload);
    if (elements.changeBtn) elements.changeBtn.addEventListener('click', triggerUpload);
    if (elements.overlayTrigger) elements.overlayTrigger.addEventListener('click', triggerUpload);

    elements.fileInput.addEventListener('change', function(e) {
        const file = this.files[0];
        if (!file) return;
        
        if (file.size > 2097152) {
            updateCardError(card, 'El archivo es demasiado grande (Máx. 2MB).');
            this.value = ''; return;
        }
        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
            updateCardError(card, 'Formato no válido. Usa JPG, PNG o WEBP.');
            this.value = ''; return;
        }
        
        updateCardError(card, '', false);

        const reader = new FileReader();
        reader.onload = function(evt) {
            elements.previewImg.src = evt.target.result;
            elements.previewImg.style.display = 'block';
            toggleAvatarActions('preview');
        };
        reader.readAsDataURL(file);
    });

    elements.cancelBtn.addEventListener('click', () => {
        updateCardError(card, '', false);
        elements.previewImg.src = originalImageSrc;
        elements.fileInput.value = '';
        
        const isDefault = originalImageSrc.includes('data:image') || 
                          originalImageSrc === '' || 
                          originalImageSrc.endsWith('/') ||
                          originalImageSrc.includes('/default/') ||          
                          originalImageSrc.includes('avatars_default') ||    
                          originalImageSrc.includes('ui-avatars.com');       

        const mode = isDefault ? 'default' : 'custom';
        toggleAvatarActions(mode);
    });

    elements.saveBtn.addEventListener('click', async () => {
        const file = elements.fileInput.files[0];
        if (!file) return;
        
        setLoading(elements.saveBtn, true);
        updateCardError(card, '', false);

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
                updateCardError(card, data.message);
            }
        } catch (e) { 
            console.error(e); 
            updateCardError(card, 'Error de conexión.');
        }
        setLoading(elements.saveBtn, false, 'Guardar');
    });

    elements.removeBtn.addEventListener('click', async () => {
        if (!confirm('¿Restablecer avatar por defecto?')) return;
        setLoading(elements.removeBtn, true);
        updateCardError(card, '', false);

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
                updateCardError(card, data.message);
            }
        } catch (e) { 
            console.error(e); 
            updateCardError(card, 'Error de conexión.');
        }
        setLoading(elements.removeBtn, false, 'Eliminar');
    });

    function toggleAvatarActions(mode) {
        if(elements.actionsDefault) elements.actionsDefault.className = (mode === 'default') ? 'active' : 'disabled';
        if(elements.actionsCustom) elements.actionsCustom.className = (mode === 'custom') ? 'active' : 'disabled';
        if(elements.actionsPreview) elements.actionsPreview.className = (mode === 'preview') ? 'active' : 'disabled';
    }
}

// ========================================================
// LÓGICA DE NOMBRE DE USUARIO
// ========================================================
function initUsernameLogic() {
    const card = qs('[data-component="username-section"]');
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

    els.editBtn.addEventListener('click', () => {
        toggleMode(els, true);
        updateCardError(card, '', false);
        els.input.focus();
    });

    els.cancelBtn.addEventListener('click', () => {
        els.input.value = originalUsername;
        updateCardError(card, '', false);
        toggleMode(els, false);
    });

    els.saveBtn.addEventListener('click', async () => {
        const newVal = els.input.value.trim();
        updateCardError(card, '', false);

        if (newVal === originalUsername) {
            toggleMode(els, false);
            return;
        }

        if (newVal.length < 8 || newVal.length > 32) {
            updateCardError(card, 'El nombre de usuario debe tener entre 8 y 32 caracteres.');
            return;
        }

        setLoading(els.saveBtn, true);

        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ 
                    action: 'update_username', 
                    username: newVal 
                })
            });
            const data = await res.json();

            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                originalUsername = data.new_username;
                els.display.textContent = data.new_username;
                els.input.value = data.new_username;
                toggleMode(els, false);
            } else {
                updateCardError(card, data.message || 'Error al actualizar.');
            }
        } catch (error) {
            console.error(error);
            updateCardError(card, 'Error de conexión con el servidor.');
        }
        setLoading(els.saveBtn, false, 'Guardar');
    });
}

// ========================================================
// LÓGICA DE CORREO ELECTRÓNICO
// ========================================================
function initEmailLogic() {
    const card = qs('[data-component="email-section"]');
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

    els.editBtn.addEventListener('click', () => {
        toggleMode(els, true);
        updateCardError(card, '', false);
        els.input.focus();
    });

    els.cancelBtn.addEventListener('click', () => {
        els.input.value = originalEmail;
        updateCardError(card, '', false);
        toggleMode(els, false);
    });

    els.saveBtn.addEventListener('click', async () => {
        const newVal = els.input.value.trim().toLowerCase();
        updateCardError(card, '', false);

        if (newVal === originalEmail) {
            toggleMode(els, false);
            return;
        }

        const regex = /^[^@\s]+@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i;
        if (!regex.test(newVal)) {
            updateCardError(card, 'Dominio no permitido (Solo Gmail, Outlook, iCloud, Yahoo).');
            return;
        }

        setLoading(els.saveBtn, true);

        try {
            const res = await fetch(API_SETTINGS, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ 
                    action: 'update_email', 
                    email: newVal 
                })
            });
            const data = await res.json();

            if (data.success) {
                if (window.alertManager) window.alertManager.showAlert(data.message, 'success');
                originalEmail = data.new_email;
                els.display.textContent = data.new_email;
                els.input.value = data.new_email;
                toggleMode(els, false);
            } else {
                updateCardError(card, data.message || 'Error al actualizar.');
            }
        } catch (error) {
            console.error(error);
            updateCardError(card, 'Error de conexión con el servidor.');
        }
        setLoading(els.saveBtn, false, 'Guardar');
    });
}

// Helper genérico para alternar modo edición/vista
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