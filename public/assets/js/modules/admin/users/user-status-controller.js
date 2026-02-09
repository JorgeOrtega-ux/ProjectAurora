/**
 * public/assets/js/modules/admin/users/user-status-controller.js
 */

import { ApiService } from '../../../core/services/api-service.js';
import { ToastManager } from '../../../core/components/toast-manager.js';
import { navigateTo } from '../../../core/utils/url-manager.js';
import { DateTimePicker } from '../../../core/components/date-time-picker.js';
import { I18nManager } from '../../../core/utils/i18n-manager.js';

let _container = null;
let _targetUserId = null;
let _dateTimePicker = null; 

let _state = {
    status: null,         
    suspensionType: null, 
    durationDays: null,   
    deletionSource: null, 
    reasonValue: null, // Guarda el ID (ej: 'spam', 'other')
    reasonLabel: null  // Guarda el texto visible (ej: 'Comportamiento sospechoso')
};

export const UserStatusController = {
    init: async () => {
        _container = document.querySelector('[data-section="admin-user-status"]');
        if (!_container) return;

        _targetUserId = _container.dataset.userId;
        
        _state = {};
        _dateTimePicker = null;
        
        initEvents();
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
    
    // Listener para el Text Area manual (activar botón guardar al escribir)
    const manualInput = document.getElementById('manual-reason-input');
    if (manualInput) {
        manualInput.addEventListener('input', () => {
            if (_state.reasonValue === 'other') toggleSaveButton(true);
        });
    }

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
        
        if (data.status) {
            _state.status = data.status;
            
            const key = `admin.user_status.status.${data.status}`;
            const label = I18nManager.t(key) || data.status;
            updateLabel('current-status-label', label);
            
            syncDropdownVisuals('status', data.status);
            processMainStatusChange(data.status);
            
            if (data.status === 'suspended') {
                populateReasons('suspension');

                if (data.suspension_ends_at) {
                    _state.suspensionType = 'temp';
                    updateLabel('suspension-type-label', I18nManager.t('admin.user_status.suspension_type.temp'));
                    processSuspensionTypeChange('temp');
                    syncDropdownVisuals('suspension_type', 'temp'); 

                    if (_dateTimePicker) {
                        const endDate = new Date(data.suspension_ends_at);
                        const isoDate = endDate.toISOString().split('T')[0];
                        
                        const input = document.getElementById('suspension-date-input');
                        if (input) input.value = isoDate;
                        
                        handleDateSelection(endDate, isoDate);
                    }
                } else {
                    _state.suspensionType = 'perm';
                    updateLabel('suspension-type-label', I18nManager.t('admin.user_status.suspension_type.perm'));
                    processSuspensionTypeChange('perm');
                    syncDropdownVisuals('suspension_type', 'perm'); 
                }
            } 
            else if (data.status === 'deleted') {
                populateReasons('deletion');
                _state.deletionSource = 'admin'; 
                updateLabel('deletion-source-label', I18nManager.t('admin.user_status.decision.admin'));
                syncDropdownVisuals('deletion_source', 'admin'); 
                showSection('group-reason');
            }

            if (data.reason) {
                // Al cargar datos existentes, no podemos saber el código original si no se guardó.
                // Asumimos que es texto plano. Lo mostramos como label y no marcamos 'other' 
                // a menos que quieras lógica compleja de matching inverso.
                // Por simplicidad, solo mostramos el texto en el selector.
                _state.reasonLabel = data.reason;
                updateLabel('reason-label', data.reason);
                toggleSaveButton(true);
            }
        }
        
        dataScript.remove();

    } catch (e) {
        console.error("Error parseando datos del servidor", e);
    }
}

function syncDropdownVisuals(type, value) {
    if (!_container) return;
    const options = _container.querySelectorAll(`.menu-link[data-type="${type}"]`);
    options.forEach(opt => {
        const optValue = opt.dataset.value;
        const optLabel = opt.dataset.label;
        if (optValue === value || optLabel === value) {
            opt.classList.add('active');
        } else {
            opt.classList.remove('active');
        }
    });
}

function handleDateSelection(date, dateStr) {
    const today = new Date();
    today.setHours(0,0,0,0); 
    date.setHours(0,0,0,0);

    const diffTime = date.getTime() - today.getTime();
    let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
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
    
    if (_state.reasonLabel) {
        toggleSaveButton(true);
    }
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
        _state.reasonValue = value; // Código (ej: 'other')
        _state.reasonLabel = label; // Texto (ej: 'Otro')
        
        updateLabel('reason-label', label);
        
        // [MODIFICADO] Lógica para mostrar/ocultar Text Area
        if (value === 'other') {
            showSection('group-reason-manual');
            const manualInput = document.getElementById('manual-reason-input');
            if(manualInput) setTimeout(() => manualInput.focus(), 100);
            
            // Validar si el textarea está vacío para deshabilitar botón
            const manualText = manualInput ? manualInput.value.trim() : '';
            toggleSaveButton(manualText.length > 0);
        } else {
            hideSection('group-reason-manual');
            toggleSaveButton(true);
        }
    }
}

function processMainStatusChange(status) {
    hideSection('group-suspension-type');
    hideSection('group-suspension-days');
    hideSection('group-deletion-source');
    hideSection('group-reason');
    hideSection('group-reason-manual'); // Resetear manual también
    
    toggleSaveButton(false); 

    // Resetear estados de razón
    _state.reasonValue = null;
    _state.reasonLabel = null;
    updateLabel('reason-label', I18nManager.t('admin.user_status.select_reason') || 'Seleccionar razón...');

    // Labels iniciales para otros dropdowns
    updateLabel('suspension-type-label', I18nManager.t('admin.user_status.select_type') || 'Seleccionar tipo...');
    updateLabel('deletion-source-label', I18nManager.t('admin.user_status.select_source') || 'Seleccionar origen...');

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
    hideSection('group-reason-manual');
    
    // Resetear razón si cambia el tipo
    _state.reasonValue = null;
    _state.reasonLabel = null;
    updateLabel('reason-label', I18nManager.t('admin.user_status.select_reason') || 'Seleccionar razón...');
    
    toggleSaveButton(false);
    
    if (type === 'temp') {
        showSection('group-suspension-days');
        if (_state.durationDays !== null && _state.durationDays !== undefined) {
             showSection('group-reason');
        }
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

    // [MODIFICADO] Uso de claves de traducción
    let reasons = [];
    
    if (context === 'suspension') {
        reasons = [
            { id: 'terms', label: I18nManager.t('admin.user_status.reasons.terms') || 'Violación de Términos' },
            { id: 'spam', label: I18nManager.t('admin.user_status.reasons.spam') || 'Comportamiento sospechoso (Spam)' },
            { id: 'reports', label: I18nManager.t('admin.user_status.reasons.reports') || 'Reportes de usuarios' },
            { id: 'other', label: I18nManager.t('admin.user_status.reasons.other') || 'Otro (Especificar)' }
        ];
    } else if (context === 'deletion') {
        reasons = [
            { id: 'support_req', label: I18nManager.t('admin.user_status.reasons.support_req') || 'Solicitud via Soporte' },
            { id: 'inactive', label: I18nManager.t('admin.user_status.reasons.inactive') || 'Cuenta inactiva' },
            { id: 'duplicate', label: I18nManager.t('admin.user_status.reasons.duplicate') || 'Usuario duplicado' },
            { id: 'rules', label: I18nManager.t('admin.user_status.reasons.rules') || 'Incumplimiento grave' },
            { id: 'other', label: I18nManager.t('admin.user_status.reasons.other') || 'Otro (Especificar)' }
        ];
    }

    let html = '';
    reasons.forEach(item => {
        const isActive = (_state.reasonValue === item.id) ? 'active' : '';
        html += `
            <div class="menu-link ${isActive}" data-action="select-option" data-type="reason" data-value="${item.id}" data-label="${item.label}">
                <div class="menu-link-icon"><span class="material-symbols-rounded">flag</span></div>
                <div class="menu-link-text">${item.label}</div>
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
    
    // [MODIFICADO] Lógica de envío de Razón
    let finalReason = _state.reasonLabel;
    
    if (_state.reasonValue === 'other') {
        const manualInput = document.getElementById('manual-reason-input');
        finalReason = manualInput ? manualInput.value.trim() : '';
        
        if (!finalReason) {
            ToastManager.show(I18nManager.t('admin.user_status.error_reason_empty') || 'Debes especificar una razón.', 'warning');
            btn.disabled = false;
            btn.textContent = originalText;
            if(manualInput) manualInput.focus();
            return;
        }
    }
    
    if (finalReason) formData.append('reason', finalReason);
    
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