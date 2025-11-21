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
    // Ahora verificamos usando el data-section o el data-component del avatar
    if (!qs('[data-section="settings/your-profile"]') && !qs('[data-component="avatar-section"]')) return;

    console.log('Settings Manager: Inicializando (Modo Data Attributes)...');

    initAvatarLogic();
    initUsernameLogic();
    initEmailLogic();
}

// ========================================================
// LÓGICA DE AVATAR
// ========================================================
function initAvatarLogic() {
    // Mapeo usando data-attributes
    const elements = {
        fileInput: qs('[data-element="avatar-upload-input"]'),
        previewImg: qs('[data-element="avatar-preview-image"]'),
        overlayTrigger: qs('[data-action="trigger-avatar-upload"]'), // Nuevo trigger overlay
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
    
    // Función para abrir el selector de archivos
    const triggerUpload = (e) => { 
        if(e) e.preventDefault(); 
        elements.fileInput.click(); 
    };

    // Asignar listeners
    if (elements.uploadBtn) elements.uploadBtn.addEventListener('click', triggerUpload);
    if (elements.changeBtn) elements.changeBtn.addEventListener('click', triggerUpload);
    if (elements.overlayTrigger) elements.overlayTrigger.addEventListener('click', triggerUpload);

    elements.fileInput.addEventListener('change', function(e) {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2097152) {
            if (window.alertManager) window.alertManager.showAlert('El archivo pesa más de 2MB.', 'warning');
            this.value = ''; return;
        }
        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
            if (window.alertManager) window.alertManager.showAlert('Formato no válido.', 'error');
            this.value = ''; return;
        }
        const reader = new FileReader();
        reader.onload = function(evt) {
            elements.previewImg.src = evt.target.result;
            elements.previewImg.style.display = 'block';
            toggleAvatarActions('preview');
        };
        reader.readAsDataURL(file);
    });

    elements.cancelBtn.addEventListener('click', () => {
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
                if (window.alertManager) window.alertManager.showAlert(data.message, 'error');
            }
        } catch (e) { console.error(e); }
        setLoading(elements.saveBtn, false, 'Guardar');
    });

    elements.removeBtn.addEventListener('click', async () => {
        if (!confirm('¿Restablecer avatar por defecto?')) return;
        setLoading(elements.removeBtn, true);
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
            }
        } catch (e) { console.error(e); }
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
        els.input.focus();
    });

    els.cancelBtn.addEventListener('click', () => {
        els.input.value = originalUsername;
        toggleMode(els, false);
    });

    els.saveBtn.addEventListener('click', async () => {
        const newVal = els.input.value.trim();
        if (newVal === originalUsername) {
            toggleMode(els, false);
            return;
        }

        if (newVal.length < 8 || newVal.length > 32) {
            if (window.alertManager) window.alertManager.showAlert('Debe tener entre 8 y 32 caracteres.', 'warning');
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
                if (window.alertManager) window.alertManager.showAlert(data.message || 'Error al actualizar.', 'error');
            }
        } catch (error) {
            console.error(error);
            if (window.alertManager) window.alertManager.showAlert('Error de conexión.', 'error');
        }
        setLoading(els.saveBtn, false, 'Guardar');
    });
}

// ========================================================
// LÓGICA DE CORREO ELECTRÓNICO
// ========================================================
function initEmailLogic() {
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
        els.input.focus();
    });

    els.cancelBtn.addEventListener('click', () => {
        els.input.value = originalEmail;
        toggleMode(els, false);
    });

    els.saveBtn.addEventListener('click', async () => {
        const newVal = els.input.value.trim().toLowerCase();
        if (newVal === originalEmail) {
            toggleMode(els, false);
            return;
        }

        const regex = /^[^@\s]+@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i;
        if (!regex.test(newVal)) {
            if (window.alertManager) window.alertManager.showAlert('Dominio no permitido (Solo Gmail, Outlook, iCloud, Yahoo).', 'warning');
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
                if (window.alertManager) window.alertManager.showAlert(data.message || 'Error al actualizar.', 'error');
            }
        } catch (error) {
            console.error(error);
            if (window.alertManager) window.alertManager.showAlert('Error de conexión.', 'error');
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
    // Este selector se puede mantener genérico por clase, o cambiar a data si modificas header.php
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