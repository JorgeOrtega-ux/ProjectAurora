/**
 * public/assets/js/modules/admin/users/user-status-controller.js
 */

import { ApiService } from '../../../core/api-service.js';
import { Toast } from '../../../core/toast-manager.js';
import { navigateTo } from '../../../core/url-manager.js';
import { DateTimePicker } from '../../../core/date-time-picker.js';

let _container = null;
let _targetUserId = null;
let _dateTimePicker = null; 

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
        _dateTimePicker = null;
        
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
    
    // [CORREGIDO] Inicializar el Calendario usando los IDs del wrapper y el input
    // Verificamos primero que exista el wrapper para no dar error en consola
    if (document.getElementById('suspension-picker-wrapper')) {
        _dateTimePicker = new DateTimePicker('suspension-picker-wrapper', 'suspension-date-input', {
            enableTime: false,
            minDate: new Date(),
            dateFormat: "Y-m-d",
            onChange: (selectedDates, dateStr, instance) => {
                // DateTimePicker dispara 'input' en el elemento oculto, pero también soporta callback
                if (selectedDates.length > 0) {
                    handleDateSelection(selectedDates[0], dateStr);
                }
            }
        });

        // Escuchar también el evento nativo del input por si el componente lo dispara
        const hiddenInput = document.getElementById('suspension-date-input');
        if (hiddenInput) {
            hiddenInput.addEventListener('input', (e) => {
                const dateVal = new Date(e.target.value);
                if (!isNaN(dateVal)) {
                    handleDateSelection(dateVal, e.target.value.split('T')[0]);
                }
            });
        }
    }

    if (_state.status === 'suspended') populateReasons('suspension');
    if (_state.status === 'deleted') populateReasons('deletion');
}

function handleDateSelection(date, dateStr) {
    const today = new Date();
    today.setHours(0,0,0,0); 
    date.setHours(0,0,0,0);

    const diffTime = date.getTime() - today.getTime();
    let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays < 1) diffDays = 1;

    _state.durationDays = diffDays;
    
    // El texto del trigger lo actualiza el DateTimePicker internamente, 
    // pero podemos forzar el texto adicional de días aquí si quisiéramos.
    // DateTimePicker pone la fecha formateada. Vamos a agregarle los días.
    setTimeout(() => {
        const label = document.getElementById('suspension-date-label');
        if (label && !label.textContent.includes('días')) {
            label.textContent += ` (${diffDays} días)`;
        }
    }, 50); // Pequeño delay para que corra después del updateValue interno

    showSection('group-reason');
    populateReasons('suspension');
}

function toggleSaveButton(enable) {
    const btn = document.getElementById('btn-save-status');
    if (btn) btn.disabled = !enable;
}

function handleDropdownSelection(e) {
    if (!_container || _container.style.display === 'none') return;
    
    const { type, value, label } = e.detail;

    if (type === 'status') {
        _state.status = value;
        updateLabel('current-status-label', label);
        processMainStatusChange(value);
    }

    if (type === 'suspension_type') {
        _state.suspensionType = value;
        updateLabel('suspension-type-label', label);
        processSuspensionTypeChange(value);
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
        toggleSaveButton(true);
    }
}

function processMainStatusChange(status) {
    hideSection('group-suspension-type');
    hideSection('group-suspension-days');
    hideSection('group-deletion-source');
    hideSection('group-reason');
    
    toggleSaveButton(false); 

    updateLabel('suspension-type-label', 'Seleccionar tipo...');
    updateLabel('deletion-source-label', 'Seleccionar origen...');
    updateLabel('reason-label', 'Seleccionar razón...');
    
    if (document.getElementById('suspension-date-label')) {
        document.getElementById('suspension-date-label').textContent = 'Seleccionar fecha...';
    }

    if (status === 'active') {
        toggleSaveButton(true);
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
    
    toggleSaveButton(false);
    
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
    formData.append('target_id', _targetUserId);
    formData.append('status', _state.status);
    
    if (_state.reason) formData.append('reason', _state.reason);
    if (_state.suspensionType) formData.append('suspension_type', _state.suspensionType);
    if (_state.durationDays) formData.append('duration_days', _state.durationDays);
    if (_state.deletionSource) formData.append('deletion_source', _state.deletionSource);

    try {
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