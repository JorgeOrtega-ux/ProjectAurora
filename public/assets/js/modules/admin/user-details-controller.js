/**
 * public/assets/js/modules/admin/user-details-controller.js
 * Controlador para la edición detallada de usuarios.
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { navigateTo } from '../../core/url-manager.js';
import { Dialog } from '../../core/dialog-manager.js'; // Importante para confirmación

let _container = null;
let _targetUserId = null;
let _currentUserData = null;

export const UserDetailsController = {
    init: async () => {
        _container = document.querySelector('[data-section="admin-user-details"]');
        if (!_container) return;

        _targetUserId = _container.dataset.userId;

        const dataScript = document.getElementById('server-user-data');
        if (dataScript) {
            try {
                _currentUserData = JSON.parse(dataScript.textContent);
                dataScript.remove();
            } catch (e) {
                console.error("Error al parsear datos de usuario:", e);
                Toast.show('Error de datos del servidor', 'error');
                return;
            }
        }

        if (!_targetUserId) {
            Toast.show('Error: ID de usuario no encontrado', 'error');
            goBack();
            return;
        }

        initEvents();
        document.addEventListener('ui:dropdown-selected', _handleGlobalDropdown);
    },
    
    // Método público auxiliar para recargar la vista si es necesario
    refresh: () => {
        // ...
    }
};

function goBack() {
    document.removeEventListener('ui:dropdown-selected', _handleGlobalDropdown);
    navigateTo('admin/users');
}

function _handleGlobalDropdown(e) {
    if (!_container || _container.style.display === 'none') return;
    const { type, value } = e.detail;
    if (type === 'language' || type === 'theme') {
        updatePreference(type, value);
    }
}

function initEvents() {
    const btnBack = _container.querySelector('[data-action="back-to-list"]');
    if (btnBack) btnBack.addEventListener('click', goBack);

    // === AVATAR ===
    const btnTriggerUpload = _container.querySelector('#admin-btn-trigger-upload');
    const inputUpload = _container.querySelector('#admin-upload-avatar');
    const btnUploadInit = _container.querySelector('#admin-btn-upload-init'); 
    const btnCancelUpload = _container.querySelector('[data-action="cancel-upload"]');
    const btnSaveUpload = _container.querySelector('[data-action="save-upload"]');
    const btnDeleteAvatar = _container.querySelector('[data-action="delete-avatar"]');
    const btnChangeAvatar = _container.querySelector('[data-action="change-avatar"]');

    if (btnTriggerUpload && inputUpload) {
        btnTriggerUpload.addEventListener('click', () => inputUpload.click());
        if(btnUploadInit) btnUploadInit.addEventListener('click', () => inputUpload.click());
        inputUpload.addEventListener('change', handleFileSelect);
    }

    if (btnCancelUpload) btnCancelUpload.addEventListener('click', () => resetAvatarState());
    if (btnSaveUpload) btnSaveUpload.addEventListener('click', uploadAvatar);
    if (btnDeleteAvatar) btnDeleteAvatar.addEventListener('click', deleteAvatar);
    if (btnChangeAvatar) btnChangeAvatar.addEventListener('click', () => inputUpload.click());

    // === CAMPOS DE TEXTO ===
    setupFieldEdit('username', 'admin-username-section');
    setupFieldEdit('email', 'admin-email-section');

    // === PREFERENCIAS ===
    const toggles = _container.querySelectorAll('[data-action="toggle-pref"]');
    toggles.forEach(toggle => {
        toggle.addEventListener('change', (e) => {
            const type = toggle.dataset.type;
            const value = toggle.checked;
            updatePreference(type, value);
        });
    });

    // [NUEVO] DESACTIVAR 2FA
    const btnDisable2FA = _container.querySelector('[data-action="disable-2fa"]');
    if (btnDisable2FA) {
        btnDisable2FA.addEventListener('click', disable2FA);
    }
}

// [NUEVO] Lógica Desactivar 2FA
async function disable2FA(e) {
    const btn = e.target;
    
    // Diálogo de confirmación
    const confirmed = await Dialog.confirm({
        title: '¿Desactivar 2FA?',
        message: 'Esto reducirá la seguridad de la cuenta del usuario.',
        type: 'danger',
        confirmText: 'Desactivar',
        cancelText: 'Cancelar'
    });

    if (!confirmed) return;

    btn.disabled = true;
    const originalText = btn.innerText;
    btn.innerText = 'Procesando...';

    const formData = new FormData();
    formData.append('target_id', _targetUserId);

    try {
        // Usar la ruta definida en api-routes.js
        const res = await ApiService.post(ApiService.Routes.Admin.Disable2FA, formData);

        if (res.success) {
            Toast.show(res.message, 'success');
            // Recargar para actualizar la vista
            setTimeout(() => window.location.reload(), 1000);
        } else {
            Toast.show(res.message, 'error');
            btn.disabled = false;
            btn.innerText = originalText;
        }
    } catch (err) {
        console.error(err);
        Toast.show('Error de conexión', 'error');
        btn.disabled = false;
        btn.innerText = originalText;
    }
}

function setupFieldEdit(field, sectionKey) {
    const section = _container.querySelector(`[data-component="${sectionKey}"]`);
    if (!section) return;

    const btnEdit = section.querySelector('[data-action="start-edit-field"]');
    const btnCancel = section.querySelector('[data-action="cancel-edit-field"]');
    const btnSave = section.querySelector('[data-action="save-field"]');
    
    const viewState = section.querySelector('[data-state="view"]');
    const editState = section.querySelector('[data-state="edit"]');
    const viewActions = section.querySelector('[data-state="view-actions"]');
    const actionsEdit = section.querySelector('[data-state="actions-edit"]'); // Possible typo in HTML or here, checking HTML
    // Correct selector from HTML logic used previously:
    const actionsEditWrapper = section.querySelector('.component-card__actions.m-0'); // Wrapper inside edit state
    const input = section.querySelector('input');

    const toggleState = (isEditing) => {
        if (isEditing) {
            viewState?.classList.replace('active', 'disabled');
            viewActions?.classList.replace('active', 'disabled');
            editState?.classList.replace('disabled', 'active');
            if(input) input.focus();
        } else {
            editState?.classList.replace('active', 'disabled');
            viewState?.classList.replace('disabled', 'active');
            viewActions?.classList.replace('disabled', 'active');
        }
    };

    if (btnEdit) btnEdit.addEventListener('click', () => toggleState(true));

    const closeEdit = () => {
        toggleState(false);
        if (input && _currentUserData) input.value = _currentUserData[field];
    };

    if (btnCancel) btnCancel.addEventListener('click', closeEdit);

    if (btnSave) {
        btnSave.addEventListener('click', async () => {
            const newValue = input.value.trim();
            if (newValue === _currentUserData[field]) {
                closeEdit();
                return;
            }

            const formData = new FormData();
            formData.append('target_id', _targetUserId);
            formData.append('field', field);
            formData.append('value', newValue);

            const res = await ApiService.post(ApiService.Routes.Admin.UpdateProfile, formData);
            
            if (res.success) {
                Toast.show(res.message, 'success');
                _currentUserData[field] = newValue;
                const display = section.querySelector('.text-display-value');
                if(display) display.textContent = newValue;
                closeEdit();
            } else {
                Toast.show(res.message, 'error');
            }
        });
    }
}

let _tempAvatarFile = null;

function handleFileSelect(e) {
    if (e.target.files && e.target.files[0]) {
        _tempAvatarFile = e.target.files[0];
        const reader = new FileReader();
        reader.onload = function(evt) {
            const img = _container.querySelector('#admin-preview-avatar');
            if(img) img.src = evt.target.result;
            showAvatarActions('preview');
        };
        reader.readAsDataURL(_tempAvatarFile);
    }
}

function resetAvatarState(isCustom = null) {
    _tempAvatarFile = null;
    const input = _container.querySelector('#admin-upload-avatar');
    if(input) input.value = '';
    
    if (isCustom === null && _currentUserData) isCustom = _currentUserData.is_custom_avatar;
    
    if (_currentUserData) {
        const img = _container.querySelector('#admin-preview-avatar');
        if(img) img.src = _currentUserData.avatar_src;
    }

    showAvatarActions(isCustom ? 'custom' : 'default');
}

function showAvatarActions(state) {
    const actionsDefault = _container.querySelector('[data-state="default"]'); 
    const actionsPreview = _container.querySelector('[data-state="preview"]'); 
    const actionsCustom  = _container.querySelector('[data-state="custom"]'); 

    [actionsDefault, actionsPreview, actionsCustom].forEach(el => {
        if(el) { el.classList.remove('active'); el.classList.add('disabled'); el.style.display = ''; }
    });

    if (state === 'default' && actionsDefault) {
        actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
    } else if (state === 'preview' && actionsPreview) {
        actionsPreview.classList.remove('disabled'); actionsPreview.classList.add('active');
    } else if (state === 'custom' && actionsCustom) {
        actionsCustom.classList.remove('disabled'); actionsCustom.classList.add('active');
    }
}

async function uploadAvatar() {
    if (!_tempAvatarFile) return;
    
    const btnSave = _container.querySelector('[data-action="save-upload"]');
    const originalText = btnSave.dataset.originalText || btnSave.textContent;
    if (!btnSave.dataset.originalText) btnSave.dataset.originalText = originalText;
    
    btnSave.textContent = 'Guardando...';
    btnSave.disabled = true; 

    const formData = new FormData();
    formData.append('target_id', _targetUserId);
    formData.append('avatar', _tempAvatarFile);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.UploadAvatar, formData);
        
        if (res.success) {
            Toast.show(res.message, 'success');
            _currentUserData.avatar_src = res.new_src;
            _currentUserData.is_custom_avatar = true;
            resetAvatarState(true);
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        console.error(e);
        Toast.show('Error de conexión', 'error');
    } finally {
        btnSave.textContent = originalText;
        btnSave.disabled = false;
    }
}

async function deleteAvatar() {
    if (!confirm('¿Eliminar avatar personalizado de este usuario?')) return;

    const formData = new FormData();
    formData.append('target_id', _targetUserId);

    const res = await ApiService.post(ApiService.Routes.Admin.DeleteAvatar, formData);
    if (res.success) {
        Toast.show(res.message, 'success');
        _currentUserData.avatar_src = res.new_src;
        _currentUserData.is_custom_avatar = false;
        resetAvatarState(false);
    } else {
        Toast.show(res.message, 'error');
    }
}

async function updatePreference(key, value) {
    const formData = new FormData();
    formData.append('target_id', _targetUserId);
    formData.append('key', key);
    formData.append('value', value);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.UpdatePreference, formData);
        if (res.success) {
            Toast.show(res.message, 'success');
            if(_currentUserData && _currentUserData.preferences) {
                _currentUserData.preferences[key] = value;
            }
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (error) {
        console.error(error);
        Toast.show('Error de conexión', 'error');
    }
}