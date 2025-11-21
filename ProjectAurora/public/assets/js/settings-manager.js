// public/assets/js/settings-manager.js

const API_SETTINGS = (window.BASE_PATH || '/ProjectAurora/') + 'api/settings_handler.php';

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

export function initSettingsManager() {
    // Solo inicializar si estamos en la sección de perfil
    if (!document.getElementById('settings/your-profile') && !document.getElementById('avatar-section')) return;

    console.log('Settings Manager: Inicializando...');

    initAvatarLogic();
    initUsernameLogic();
}

// ========================================================
// LÓGICA DE AVATAR (Ya existente, optimizada)
// ========================================================
function initAvatarLogic() {
    const elements = {
        fileInput: document.getElementById('avatar-upload-input'),
        previewImg: document.getElementById('avatar-preview-image'),
        uploadBtn: document.getElementById('avatar-upload-trigger'),
        changeBtn: document.getElementById('avatar-change-trigger'),
        removeBtn: document.getElementById('avatar-remove-trigger'),
        cancelBtn: document.getElementById('avatar-cancel-trigger'),
        saveBtn: document.getElementById('avatar-save-trigger-btn'),
        actionsDefault: document.getElementById('avatar-actions-default'),
        actionsCustom: document.getElementById('avatar-actions-custom'),
        actionsPreview: document.getElementById('avatar-actions-preview')
    };

    if (!elements.fileInput) return;

    let originalImageSrc = elements.previewImg.src;
    
    // Trigger file input
    const triggerUpload = (e) => { e.preventDefault(); elements.fileInput.click(); };
    if (elements.uploadBtn) elements.uploadBtn.addEventListener('click', triggerUpload);
    if (elements.changeBtn) elements.changeBtn.addEventListener('click', triggerUpload);

    // Change input
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

    // Cancel
    elements.cancelBtn.addEventListener('click', () => {
        elements.previewImg.src = originalImageSrc;
        elements.fileInput.value = '';
        // Determinar estado previo (si la src original estaba vacía o era default, vamos a default)
        const mode = (originalImageSrc.includes('data:image') || originalImageSrc === '' || originalImageSrc.endsWith('/')) ? 'default' : 'custom';
        toggleAvatarActions(mode === 'custom' ? 'custom' : 'default'); // Simplificación segura
    });

    // Save
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

    // Remove
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
                toggleAvatarActions('default'); // Asumimos que vuelve a estado default visualmente
            }
        } catch (e) { console.error(e); }
        setLoading(elements.removeBtn, false, 'Eliminar');
    });

    function toggleAvatarActions(mode) {
        elements.actionsDefault.className = (mode === 'default') ? 'active' : 'disabled';
        elements.actionsCustom.className = (mode === 'custom') ? 'active' : 'disabled';
        elements.actionsPreview.className = (mode === 'preview') ? 'active' : 'disabled';
    }
}

// ========================================================
// LÓGICA DE NOMBRE DE USUARIO (NUEVA)
// ========================================================
function initUsernameLogic() {
    const els = {
        viewState: document.getElementById('username-view-state'),
        editState: document.getElementById('username-edit-state'),
        actionsView: document.getElementById('username-actions-view'),
        actionsEdit: document.getElementById('username-actions-edit'),
        
        display: document.getElementById('username-display-text'),
        input: document.getElementById('username-input'),
        
        editBtn: document.getElementById('username-edit-trigger'),
        cancelBtn: document.getElementById('username-cancel-trigger'),
        saveBtn: document.getElementById('username-save-trigger-btn')
    };

    if (!els.input) return;

    let originalUsername = els.input.value;

    // Switch to Edit Mode
    els.editBtn.addEventListener('click', () => {
        toggleUsernameMode(true);
        els.input.focus();
    });

    // Cancel Edit
    els.cancelBtn.addEventListener('click', () => {
        els.input.value = originalUsername;
        toggleUsernameMode(false);
    });

    // Save Username
    els.saveBtn.addEventListener('click', async () => {
        const newVal = els.input.value.trim();
        if (newVal === originalUsername) {
            toggleUsernameMode(false);
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
                toggleUsernameMode(false);
            } else {
                if (window.alertManager) window.alertManager.showAlert(data.message || 'Error al actualizar.', 'error');
            }
        } catch (error) {
            console.error(error);
            if (window.alertManager) window.alertManager.showAlert('Error de conexión.', 'error');
        }
        setLoading(els.saveBtn, false, 'Guardar');
    });

    function toggleUsernameMode(isEditing) {
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
}

// Helper global
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