/**
 * public/assets/js/modules/admin/user-role-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { navigateTo } from '../../core/url-manager.js';

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
        
        // Escuchar evento global del dropdown UI
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
        
        // Actualizar UI del selector (Icono y Texto)
        const labelEl = _container.querySelector('[data-element="current-role-label"]');
        const iconEl = _container.querySelector('[data-element="current-role-icon"]');
        
        // Map de iconos simple basado en el valor
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

    // Habilitar solo si el rol cambió y no es el original
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
    btnSave.textContent = 'Guardando...';

    const formData = new FormData();
    // [CORREGIDO] Eliminado append 'action' manual. El router lo inyecta automáticamente.
    formData.append('target_id', _targetUserId);
    formData.append('new_role', _selectedRole);

    try {
        // [CORREGIDO] Usamos ApiService.post con la ruta correcta definida en api-routes.js
        // Antes apuntaba a .Base (que era undefined), ahora apunta a .UpdateRole
        const res = await ApiService.post(ApiService.Routes.Admin.UpdateRole, formData);
        
        if (res.success) {
            Toast.show(res.message, 'success');
            _originalRole = _selectedRole; // Actualizar estado
            updateSaveButtonState();
            
            // Opcional: Volver a la lista automáticamente
            // setTimeout(() => navigateTo('admin/users'), 1000);
        } else {
            Toast.show(res.message, 'error');
            updateSaveButtonState(); // Reactivar si falló
        }
    } catch (error) {
        console.error(error);
        Toast.show('Error de conexión', 'error');
        updateSaveButtonState();
    } finally {
        btnSave.textContent = originalText;
    }
}