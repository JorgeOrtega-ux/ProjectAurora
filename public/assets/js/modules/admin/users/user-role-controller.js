/**
 * public/assets/js/modules/admin/users/user-role-controller.js
 */

import { ApiService } from '../../../core/services/api-service.js';
import { ToastManager } from '../../../core/components/toast-manager.js';
import { navigateTo } from '../../../core/utils/url-manager.js';
import { I18nManager } from '../../../core/utils/i18n-manager.js';

let _container = null;
let _targetUserId = null;
let _selectedRole = null;
let _originalRole = null;

export const UserRoleController = {
    init: () => {
        _container = document.querySelector('[data-section="admin-user-role"]');
        if (!_container) return;

        _targetUserId = _container.dataset.userId;
        
        // Obtener rol original del script incrustado
        const dataScript = document.getElementById('server-role-data');
        if (dataScript) {
            try {
                const data = JSON.parse(dataScript.textContent);
                _originalRole = data.current_role;
                _selectedRole = _originalRole;
                dataScript.remove();
            } catch (e) {
                console.error("Error parseando data", e);
            }
        }

        initEvents();
        document.addEventListener('ui:dropdown-selected', handleDropdownSelection);
    }
};

function initEvents() {
    const btnBack = _container.querySelector('[data-action="back-to-list"]');
    if (btnBack) {
        btnBack.addEventListener('click', () => {
            document.removeEventListener('ui:dropdown-selected', handleDropdownSelection);
            navigateTo('admin/users');
        });
    }

    const btnSave = _container.querySelector('[data-action="save-role"]');
    if (btnSave) {
        btnSave.addEventListener('click', saveRole);
    }
}

function handleDropdownSelection(e) {
    if (!_container || _container.style.display === 'none') return;
    
    const { type, value, label } = e.detail;
    
    if (type === 'role') {
        _selectedRole = value;
        updateSaveButtonState();
        
        const labelEl = _container.querySelector('[data-element="current-role-label"]');
        const iconEl = _container.querySelector('[data-element="current-role-icon"]');
        
        const iconMap = {
            'user': 'person',
            'moderator': 'gpp_maybe',
            'administrator': 'security'
        };

        if (labelEl) labelEl.textContent = label;
        if (iconEl) iconEl.textContent = iconMap[value] || 'badge';
    }
}

function updateSaveButtonState() {
    const btnSave = _container.querySelector('[data-action="save-role"]');
    if (!btnSave) return;

    if (_selectedRole !== _originalRole) {
        btnSave.disabled = false;
    } else {
        btnSave.disabled = true;
    }
}

async function saveRole() {
    if (!_targetUserId || !_selectedRole) return;

    const btnSave = _container.querySelector('[data-action="save-role"]');
    btnSave.disabled = true;
    const originalText = btnSave.textContent;
    
    // "Guardando..."
    btnSave.textContent = (I18nManager.t('js.core.saving') || 'Guardando') + '...';

    const formData = new FormData();
    formData.append('target_id', _targetUserId);
    formData.append('new_role', _selectedRole);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.UpdateRole, formData);
        
        if (res.success) {
            ToastManager.show(res.message, 'success');
            _originalRole = _selectedRole;
            updateSaveButtonState();
        } else {
            ToastManager.show(res.message, 'error');
            updateSaveButtonState();
        }
    } catch (error) {
        console.error(error);
        ToastManager.show(I18nManager.t('js.core.connection_error') || 'Error de conexión', 'error');
        updateSaveButtonState();
    } finally {
        btnSave.textContent = originalText;
    }
}