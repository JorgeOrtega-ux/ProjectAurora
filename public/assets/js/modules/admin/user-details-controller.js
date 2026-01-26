/**
 * public/assets/js/modules/admin/user-details-controller.js
 * Controlador para la edición detallada de usuarios.
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { navigateTo } from '../../core/url-manager.js';

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

    // [NUEVO] Listener para ver historial
    const btnHistory = _container.querySelector('[data-action="view-history"]');
    if (btnHistory) btnHistory.addEventListener('click', toggleHistorySection);

    const btnCloseHistory = _container.querySelector('[data-action="close-history"]');
    if (btnCloseHistory) btnCloseHistory.addEventListener('click', toggleHistorySection);

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
}

// [NUEVO] Lógica de Historial
async function toggleHistorySection() {
    const section = document.getElementById('user-audit-history-container');
    if (!section) return;

    if (section.classList.contains('d-none')) {
        section.classList.remove('d-none');
        await loadUserAuditLogs();
        // Scroll suave hacia la sección
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        section.classList.add('d-none');
    }
}

async function loadUserAuditLogs() {
    const list = document.getElementById('user-audit-list');
    list.innerHTML = '<div class="state-loading"><div class="spinner-sm"></div></div>';

    const formData = new FormData();
    formData.append('target_id', _targetUserId);
    formData.append('target_type', 'user');
    formData.append('limit', 20); // Traer los últimos 20

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.GetAuditLogs, formData);
        
        if (res.success && res.logs.length > 0) {
            renderHistoryList(list, res.logs);
        } else {
            list.innerHTML = `<div class="state-empty" style="font-size: 13px;">No hay registros de auditoría para este usuario.</div>`;
        }
    } catch (e) {
        list.innerHTML = `<div class="state-error" style="font-size: 13px;">Error cargando historial.</div>`;
    }
}

function renderHistoryList(container, logs) {
    let html = '';
    
    logs.forEach(log => {
        const date = new Date(log.created_at).toLocaleString();
        
        // Formatear cambios
        let changesHtml = '';
        if (log.changes) {
            changesHtml = Object.entries(log.changes)
                .map(([k, v]) => `<div><strong style="color:var(--text-secondary);">${k}:</strong> ${v}</div>`)
                .join('');
        }

        html += `
        <div class="component-group-item" style="padding: 16px; align-items: flex-start; gap: 8px;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <span class="component-badge" style="height: 24px; font-size: 11px;">${log.action}</span>
                    <span style="font-size: 11px; color: var(--text-tertiary);">${date}</span>
                </div>
                <div style="font-size: 13px; color: var(--text-primary); font-family: monospace; background: var(--bg-hover-light); padding: 8px; border-radius: 6px;">
                    ${changesHtml || 'Sin detalles adicionales'}
                </div>
                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">
                    Por: <strong>${log.admin_name || 'Sistema'}</strong>
                </div>
            </div>
        </div>
        <hr class="component-divider" style="margin: 0;">`;
    });

    container.innerHTML = html;
}

// ... (Resto de funciones de edición de perfil: setupFieldEdit, uploadAvatar, etc. permanecen igual) ...

function setupFieldEdit(field, sectionKey) {
    const section = _container.querySelector(`[data-component="${sectionKey}"]`);
    if (!section) return;

    const btnEdit = section.querySelector('[data-action="start-edit-field"]');
    const btnCancel = section.querySelector('[data-action="cancel-edit-field"]');
    const btnSave = section.querySelector('[data-action="save-field"]');
    
    const viewState = section.querySelector('[data-state="view"]');
    const editState = section.querySelector('[data-state="edit"]');
    const viewActions = section.querySelector('[data-state="view-actions"]');
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