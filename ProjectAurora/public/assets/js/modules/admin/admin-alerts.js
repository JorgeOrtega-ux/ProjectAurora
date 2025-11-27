// public/assets/js/modules/admin/admin-alerts.js

import { t } from '../../core/i18n-manager.js';
import { postJson, setButtonLoading } from '../../core/utilities.js';
import { closeAllModules } from '../../ui/main-controller.js';

export function initAdminAlerts() {
    checkActiveAlert();
    initListeners();
}

async function checkActiveAlert() {
    const res = await postJson('api/admin_handler.php', { action: 'get_alert_status' });
    if (res.success) {
        updateUI(res.active_alert);
    }
}

function updateUI(activeAlert) {
    const indicator = document.getElementById('active-alert-indicator');
    const indicatorName = document.getElementById('active-alert-name');
    const indicatorMeta = document.getElementById('active-alert-meta');
    
    const mainEmitBtn = document.getElementById('btn-emit-selected-alert');
    const triggerWrapper = document.querySelector('.trigger-select-wrapper');

    if (activeAlert) {
        // Hay alerta activa
        if (indicator) indicator.classList.remove('d-none');
        if (indicatorName) indicatorName.textContent = t(`admin.alerts.templates.${activeAlert.type}.title`);
        
        // Mostrar metadatos si existen
        if (indicatorMeta) {
            let metaText = '';
            if (activeAlert.meta_data) {
                const meta = (typeof activeAlert.meta_data === 'string') ? JSON.parse(activeAlert.meta_data) : activeAlert.meta_data;
                
                if (meta.date) metaText += `📅 ${meta.date} ${meta.time || ''} `;
                if (meta.link) metaText += `🔗 ${meta.link}`;
            }
            indicatorMeta.textContent = metaText;
        }
        
        if (mainEmitBtn) {
            mainEmitBtn.disabled = true;
            mainEmitBtn.textContent = 'Alerta en curso...';
        }
        
        if (triggerWrapper) {
            triggerWrapper.classList.add('disabled-interactive');
            triggerWrapper.style.opacity = '0.5';
        }

    } else {
        if (indicator) indicator.classList.add('d-none');
        
        if (triggerWrapper) {
            triggerWrapper.classList.remove('disabled-interactive');
            triggerWrapper.style.opacity = '1';
        }

        const currentSelection = document.getElementById('input-alert-type').value;
        if (mainEmitBtn) {
            if (currentSelection) {
                mainEmitBtn.disabled = false;
                mainEmitBtn.textContent = t('admin.alerts.emit_btn');
            } else {
                mainEmitBtn.disabled = true;
            }
        }
    }
}

function handleSelection(option) {
    const val = option.dataset.value;
    const label = option.dataset.label;
    const icon = option.dataset.icon;
    const color = option.dataset.color;

    // Inputs
    document.getElementById('input-alert-type').value = val;
    document.getElementById('current-alert-text').textContent = label;
    const iconEl = document.getElementById('current-alert-icon');
    iconEl.textContent = icon;
    iconEl.style.color = color;

    // Preview Description
    const descKey = `admin.alerts.templates.${val}.desc`;
    const previewEl = document.getElementById('alert-preview-desc');
    if (previewEl) previewEl.textContent = t(descKey);

    // Habilitar botón
    const btn = document.getElementById('btn-emit-selected-alert');
    if (btn) {
        btn.disabled = false;
        btn.textContent = t('admin.alerts.emit_btn');
    }

    // === LOGICA DE CAMPOS ADICIONALES ===
    const configContainer = document.getElementById('alert-config-container');
    const dateWrapper = document.getElementById('wrapper-date-picker');
    const linkContainer = document.getElementById('wrapper-link-container');

    // 1. Resetear todo a oculto
    configContainer.classList.add('d-none');
    dateWrapper.classList.add('d-none');
    linkContainer.classList.add('d-none');

    // 2. Determinar requisitos
    // Tipos con Fecha: maintenance, terms, privacy, cookie
    const needsDate = ['maintenance_warning', 'terms_update', 'privacy_update', 'cookie_update'].includes(val);
    // Tipos con Link: update_info, terms, privacy, cookie
    const needsLink = ['update_info', 'terms_update', 'privacy_update', 'cookie_update'].includes(val);

    if (needsDate || needsLink) {
        configContainer.classList.remove('d-none');
    }

    // 3. Lógica Secuencial
    if (needsDate) {
        // Si requiere fecha, mostramos fecha primero. 
        // El enlace se mostrará DESPUÉS de confirmar la fecha (ver handleDateTimeConfirm).
        dateWrapper.classList.remove('d-none');
        
        // Limpiamos texto del selector de fecha por si había uno previo
        document.getElementById('selected-datetime-text').textContent = t('admin.alerts.select_date_placeholder');
        document.getElementById('input-alert-date').value = '';
    } 
    else if (needsLink) {
        // Si NO requiere fecha pero SI enlace (ej: update_info), mostramos el enlace directamente
        linkContainer.classList.remove('d-none');
        setTimeout(() => document.getElementById('input-alert-link').focus(), 100);
    }
}

function handleDateTimeConfirm() {
    const dateIn = document.getElementById('picker-date-input').value;
    const timeIn = document.getElementById('picker-time-input').value;
    
    if (!dateIn) {
        alert('Selecciona al menos una fecha.');
        return;
    }

    // Guardar en hiddens
    document.getElementById('input-alert-date').value = dateIn;
    document.getElementById('input-alert-time').value = timeIn;

    // Formato legible para el trigger
    const displayDate = new Date(dateIn + 'T00:00:00').toLocaleDateString(); // Ajuste zona horaria simple
    const displayText = `${displayDate} ${timeIn ? 'a las ' + timeIn : ''}`;
    
    document.getElementById('selected-datetime-text').textContent = displayText;
    
    // Cerrar el popover
    closeAllModules(); 

    // === SECUENCIA: AHORA MOSTRAR ENLACE SI ES NECESARIO ===
    const currentType = document.getElementById('input-alert-type').value;
    const needsLink = ['terms_update', 'privacy_update', 'cookie_update'].includes(currentType);
    
    if (needsLink) {
        const linkContainer = document.getElementById('wrapper-link-container');
        linkContainer.classList.remove('d-none');
        linkContainer.classList.add('animate-fade-in'); // Pequeña animación visual
        setTimeout(() => document.getElementById('input-alert-link').focus(), 100);
    }
}

function initListeners() {
    document.body.addEventListener('click', async (e) => {
        
        // 1. Selección del Tipo de Alerta
        const option = e.target.closest('[data-action="select-alert-option"]');
        if (option) {
            handleSelection(option);
            return;
        }

        // 2. Confirmar Fecha en el Popover
        const confirmDateBtn = e.target.closest('[data-action="confirm-datetime"]');
        if (confirmDateBtn) {
            e.preventDefault();
            e.stopPropagation(); // Evitar que se cierre inmediatamente por click outside
            handleDateTimeConfirm();
            return;
        }

        // 3. Emitir Alerta
        const emitBtn = e.target.closest('#btn-emit-selected-alert');
        if (emitBtn && !emitBtn.disabled) {
            const type = document.getElementById('input-alert-type').value;
            if (!type) return;

            // Validaciones
            const needsDate = !document.getElementById('wrapper-date-picker').classList.contains('d-none');
            // Check si el container de link está visible
            const needsLink = !document.getElementById('wrapper-link-container').classList.contains('d-none');

            const dateVal = document.getElementById('input-alert-date').value;
            const timeVal = document.getElementById('input-alert-time').value;
            const linkVal = document.getElementById('input-alert-link').value;

            if (needsDate && !dateVal) {
                alert(t('admin.error.reason_required') + ' (Fecha faltante)');
                return;
            }
            if (needsLink && !linkVal) {
                alert(t('admin.error.reason_required') + ' (Enlace faltante)');
                return;
            }

            if (!confirm(t('admin.alerts.confirm_emit'))) return;

            setButtonLoading(emitBtn, true);
            
            const payload = { 
                action: 'activate_alert', 
                type,
                meta_data: {
                    date: dateVal,
                    time: timeVal,
                    link: linkVal
                }
            };

            const res = await postJson('api/admin_handler.php', payload);
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                checkActiveAlert();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(emitBtn, false, t('admin.alerts.emit_btn'));
            }
        }

        // 4. Detener Alerta
        const stopBtn = e.target.closest('[data-action="stop-alert"]');
        if (stopBtn) {
            if (!confirm(t('admin.alerts.confirm_stop'))) return;

            setButtonLoading(stopBtn, true);
            const res = await postJson('api/admin_handler.php', { action: 'stop_alert' });
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
                // Limpiar UI
                document.getElementById('selected-datetime-text').textContent = t('admin.alerts.select_date_placeholder');
                document.getElementById('input-alert-date').value = '';
                document.getElementById('input-alert-time').value = '';
                document.getElementById('input-alert-link').value = '';
                
                checkActiveAlert();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            setButtonLoading(stopBtn, false);
        }
    });
}