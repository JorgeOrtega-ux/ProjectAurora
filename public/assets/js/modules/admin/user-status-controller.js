/**
 * public/assets/js/modules/admin/user-status-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { navigateTo } from '../../core/url-manager.js';

let _container = null;
let _targetUserId = null;

let _state = {
    status: null,         
    suspensionType: null, 
    durationDays: null,   
    deletionSource: null, 
    reason: null          
};

export const UserStatusController = {
    init: async () => {
        _container = document.querySelector('[data-section="admin-user-status"]');
        if (!_container) return;

        _targetUserId = _container.dataset.userId;
        
        // 1. LIMPIEZA INICIAL
        _state = {};
        
        // 2. LEER DATOS DEL SERVIDOR
        loadServerData();

        // 3. INICIALIZAR EVENTOS
        initEvents();
    }
};

function loadServerData() {
    const dataScript = document.getElementById('server-status-data');
    if (!dataScript) return;

    try {
        const data = JSON.parse(dataScript.textContent);
        
        if (data.status) {
            _state.status = data.status;
            
            const statusLabels = {
                'active': 'Activo',
                'suspended': 'Suspendido',
                'deleted': 'Eliminado'
            };
            
            updateLabel('current-status-label', statusLabels[data.status] || data.status);
            processMainStatusChange(data.status);
        }
        
        dataScript.remove();

    } catch (e) {
        console.error("Error parseando datos del servidor", e);
    }
}

function initEvents() {
    const btnBack = _container.querySelector('[data-action="back-to-list"]');
    if (btnBack) btnBack.addEventListener('click', () => navigateTo('admin/users'));

    const btnSave = _container.querySelector('#btn-save-status');
    if (btnSave) btnSave.addEventListener('click', saveStatus);

    document.removeEventListener('ui:dropdown-selected', handleDropdownSelection);
    document.addEventListener('ui:dropdown-selected', handleDropdownSelection);
    
    // Inicializar lista si ya está en un estado que la requiera
    if (_state.status === 'suspended') populateReasons('suspension');
    if (_state.status === 'deleted') populateReasons('deletion');
}

function handleDropdownSelection(e) {
    if (!_container || _container.style.display === 'none') return;
    
    const { type, value, label } = e.detail;

    if (type === 'status') {
        _state.status = value;
        updateLabel('current-status-label', label);
        processMainStatusChange(value);
        showSection('footer-actions');
    }

    if (type === 'suspension_type') {
        _state.suspensionType = value;
        updateLabel('suspension-type-label', label);
        processSuspensionTypeChange(value);
    }

    if (type === 'duration') {
        _state.durationDays = value;
        updateLabel('days-label', label);
        showSection('group-reason');
        populateReasons('suspension');
    }

    if (type === 'deletion_source') {
        _state.deletionSource = value;
        updateLabel('deletion-source-label', label);
        showSection('group-reason');
        populateReasons('deletion');
    }

    if (type === 'reason') {
        _state.reason = label;
        updateLabel('reason-label', label);
        showSection('footer-actions');
    }
}

function processMainStatusChange(status) {
    hideSection('group-suspension-type');
    hideSection('group-suspension-days');
    hideSection('group-deletion-source');
    hideSection('group-reason');
    hideSection('footer-actions');

    updateLabel('suspension-type-label', 'Seleccionar tipo...');
    updateLabel('days-label', 'Seleccionar días...');
    updateLabel('deletion-source-label', 'Seleccionar origen...');
    updateLabel('reason-label', 'Seleccionar razón...');

    if (status === 'active') {
        showSection('footer-actions');
    } 
    else if (status === 'suspended') {
        showSection('group-suspension-type');
    } 
    else if (status === 'deleted') {
        showSection('group-deletion-source');
    }
}

function processSuspensionTypeChange(type) {
    hideSection('group-suspension-days');
    hideSection('group-reason');
    hideSection('footer-actions');
    
    updateLabel('days-label', 'Seleccionar días...');
    updateLabel('reason-label', 'Seleccionar razón...');

    if (type === 'temp') {
        showSection('group-suspension-days');
    } else {
        showSection('group-reason');
        populateReasons('suspension');
    }
}

function showSection(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('d-none');
}

function hideSection(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('d-none');
}

function updateLabel(elementName, text) {
    const el = _container.querySelector(`[data-element="${elementName}"]`);
    if (el) el.textContent = text;
}

function populateReasons(context) {
    const list = document.getElementById('reason-list-container');
    if (!list) return;

    let reasons = [];
    if (context === 'suspension') {
        reasons = [
            'Violación de Términos de Servicio',
            'Comportamiento sospechoso (Spam)',
            'Reportes de otros usuarios',
            'Falta de verificación de identidad',
            'Otro (Especificar en notas)'
        ];
    } else if (context === 'deletion') {
        reasons = [
            'Solicitud via Soporte',
            'Cuenta inactiva por largo periodo',
            'Usuario duplicado',
            'Incumplimiento grave de normas',
            'Otro'
        ];
    }

    let html = '';
    reasons.forEach(r => {
        html += `
            <div class="menu-link" data-action="select-option" data-type="reason" data-value="${r}" data-label="${r}">
                <div class="menu-link-text">${r}</div>
            </div>
        `;
    });
    list.innerHTML = html;
}

async function saveStatus() {
    const btn = _container.querySelector('#btn-save-status');
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Guardando...';

    const formData = new FormData();
    // [MODIFICADO] Ya no añadimos 'action' manualmente.
    // El router (api/index.php) lo inferirá de la ruta Admin.UpdateStatus
    formData.append('target_id', _targetUserId);
    formData.append('status', _state.status);
    
    if (_state.reason) formData.append('reason', _state.reason);
    if (_state.suspensionType) formData.append('suspension_type', _state.suspensionType);
    if (_state.durationDays) formData.append('duration_days', _state.durationDays);
    if (_state.deletionSource) formData.append('deletion_source', _state.deletionSource);

    try {
        // [MODIFICADO] Usamos post() con la ruta definida en ApiRoutes.Admin.UpdateStatus
        const res = await ApiService.post(ApiService.Routes.Admin.UpdateStatus, formData);
        
        if (res.success) {
            Toast.show(res.message, 'success');
            setTimeout(() => {
                navigateTo('admin/users');
            }, 1000);
        } else {
            Toast.show(res.message, 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (e) {
        console.error(e);
        Toast.show('Error de conexión', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}