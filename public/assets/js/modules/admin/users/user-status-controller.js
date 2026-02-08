/**
 * public/assets/js/modules/admin/users/user-status-controller.js
 */

import { ApiService } from '../../../core/api-service.js';
import { ToastManager } from '../../../core/toast-manager.js';
import { navigateTo } from '../../../core/url-manager.js';
import { DateTimePicker } from '../../../core/date-time-picker.js';
import { I18nManager } from '../../../core/i18n-manager.js';

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
        
        _state = {};
        _dateTimePicker = null;
        
        // 1. Primero inicializamos eventos y componentes (Calendario)
        initEvents();

        // 2. Luego cargamos y rellenamos la información
        loadServerData();
    }
};

function initEvents() {
    const btnBack = _container.querySelector('[data-action="back-to-list"]');
    if (btnBack) btnBack.addEventListener('click', () => navigateTo('admin/users'));

    const btnSave = _container.querySelector('#btn-save-status');
    if (btnSave) btnSave.addEventListener('click', saveStatus);

    document.removeEventListener('ui:dropdown-selected', handleDropdownSelection);
    document.addEventListener('ui:dropdown-selected', handleDropdownSelection);
    
    // Inicializar el calendario
    if (document.getElementById('suspension-picker-wrapper')) {
        _dateTimePicker = new DateTimePicker('#suspension-picker-wrapper', '#suspension-date-input', {
            enableTime: false,
            minDate: new Date(),
            dateFormat: "Y-m-d",
            onChange: (selectedDates, dateStr, instance) => {
                if (selectedDates.length > 0) {
                    handleDateSelection(selectedDates[0], dateStr);
                }
            }
        });

        // Escuchar cambios manuales en el input hidden (por si se setea vía JS)
        const hiddenInput = document.getElementById('suspension-date-input');
        if (hiddenInput) {
            hiddenInput.addEventListener('input', (e) => {
                const val = e.target.value;
                if (val) {
                    const dateVal = new Date(val);
                    if (!isNaN(dateVal.getTime())) {
                        handleDateSelection(dateVal, val.split('T')[0]);
                    }
                }
            });
        }
    }
}

function loadServerData() {
    const dataScript = document.getElementById('server-status-data');
    if (!dataScript) return;

    try {
        const data = JSON.parse(dataScript.textContent);
        
        // 1. Restaurar Estado Principal
        if (data.status) {
            _state.status = data.status;
            
            // Texto del label principal
            const key = `admin.user_status.status.${data.status}`;
            const label = I18nManager.t(key) || data.status;
            updateLabel('current-status-label', label);
            
            // Desplegar secciones correspondientes
            processMainStatusChange(data.status);
            
            // 2. Lógica Específica para SUSPENDIDO
            if (data.status === 'suspended') {
                populateReasons('suspension');

                if (data.suspension_ends_at) {
                    // Es Temporal
                    _state.suspensionType = 'temp';
                    updateLabel('suspension-type-label', I18nManager.t('admin.user_status.suspension_type.temp'));
                    processSuspensionTypeChange('temp');

                    // Setear fecha en el calendario y calcular días
                    if (_dateTimePicker) {
                        const endDate = new Date(data.suspension_ends_at);
                        // Ajustamos el input, esto disparará la lógica visual
                        const isoDate = endDate.toISOString().split('T')[0];
                        
                        // Forzamos actualización visual del componente
                        const input = document.getElementById('suspension-date-input');
                        if (input) input.value = isoDate;
                        
                        handleDateSelection(endDate, isoDate);
                    }
                } else {
                    // Es Permanente
                    _state.suspensionType = 'perm';
                    updateLabel('suspension-type-label', I18nManager.t('admin.user_status.suspension_type.perm'));
                    processSuspensionTypeChange('perm');
                }
            } 
            // 3. Lógica Específica para ELIMINADO
            else if (data.status === 'deleted') {
                populateReasons('deletion');
                // Asumimos 'Admin' como fuente para visualización si ya está eliminado
                _state.deletionSource = 'admin'; 
                updateLabel('deletion-source-label', I18nManager.t('admin.user_status.decision.admin'));
                showSection('group-reason');
            }

            // 4. Restaurar Razón
            if (data.reason) {
                _state.reason = data.reason;
                updateLabel('reason-label', data.reason);
                // Si todo está cargado, habilitamos guardar
                toggleSaveButton(true);
            }
        }
        
        dataScript.remove();

    } catch (e) {
        console.error("Error parseando datos del servidor", e);
    }
}

function handleDateSelection(date, dateStr) {
    const today = new Date();
    today.setHours(0,0,0,0); 
    date.setHours(0,0,0,0);

    const diffTime = date.getTime() - today.getTime();
    let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    // Si es una fecha pasada o hoy, mínimo 1 día para lógica visual
    if (diffDays < 1) diffDays = 0; 

    _state.durationDays = diffDays;
    
    setTimeout(() => {
        const label = document.getElementById('suspension-date-label');
        if (label) {
            const daysText = diffDays === 1 ? 'día' : 'días';
            label.textContent = `${dateStr} (${diffDays} ${daysText})`;
            label.style.color = 'var(--text-primary)';
        }
    }, 50);

    showSection('group-reason');
    populateReasons('suspension');
    
    // Si ya hay razón seleccionada (al cargar datos), habilitar guardar
    if (_state.reason) toggleSaveButton(true);
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
    // Reset visual
    hideSection('group-suspension-type');
    hideSection('group-suspension-days');
    hideSection('group-deletion-source');
    hideSection('group-reason');
    
    toggleSaveButton(false); 

    // Reset labels internos solo si el usuario está cambiando manualmente
    // (Si estamos en loadServerData, los valores se sobreescriben después)
    if (!_state.reason) { // Check simple para saber si es carga inicial o interacción
         updateLabel('suspension-type-label', I18nManager.t('admin.user_status.select_type') || 'Seleccionar tipo...');
         updateLabel('deletion-source-label', I18nManager.t('admin.user_status.select_source') || 'Seleccionar origen...');
         updateLabel('reason-label', I18nManager.t('admin.user_status.select_reason') || 'Seleccionar razón...');
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
    
    // Si estamos cambiando tipo manualmente, deshabilitar guardar hasta completar flujo
    if (!_state.reason) toggleSaveButton(false);
    
    if (type === 'temp') {
        showSection('group-suspension-days');
        // Si ya había fecha cargada, mostrar razón también
        if (_state.durationDays !== null && _state.durationDays !== undefined) {
             showSection('group-reason');
        }
    } else {
        // Permanente
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
    btn.textContent = (I18nManager.t('js.core.saving') || 'Guardando') + '...';

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
            ToastManager.show(res.message, 'success');
            setTimeout(() => {
                navigateTo('admin/users');
            }, 1000);
        } else {
            ToastManager.show(res.message, 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (e) {
        console.error(e);
        ToastManager.show(I18nManager.t('js.core.connection_error') || 'Error de conexión', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}