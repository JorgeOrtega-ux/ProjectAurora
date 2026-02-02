/**
 * public/assets/js/modules/admin/backup-config-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { navigateTo } from '../../core/url-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { I18n } from '../../core/i18n-manager.js';

let _container = null;
let _countdownInterval = null;
let _secondsRemaining = 0;

export const BackupConfigController = {
    init: async () => {
        console.log("BackupConfigController: Inicializado");
        
        _container = document.querySelector('[data-section="admin-backup-config"]');
        if (!_container) return;

        initEvents();
        await loadConfig();
    }
};

function initEvents() {
    const btnBack = _container.querySelector('[data-action="back-to-backups"]');
    if (btnBack) btnBack.addEventListener('click', () => navigateTo('admin/backups'));

    const btnSave = _container.querySelector('#btn-save-backup-config');
    if (btnSave) btnSave.addEventListener('click', saveConfig);

    const btnTrigger = _container.querySelector('#btn-trigger-now');
    if (btnTrigger) btnTrigger.addEventListener('click', triggerNow);

    // Lógica de Steppers (Actualizada para BEM: .component-stepper)
    _container.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        // [MODIFICADO] Selector actualizado a .component-stepper
        const wrapper = btn.closest('.component-stepper');
        if (!wrapper) return;

        const input = wrapper.querySelector('input');
        if (!input) return;

        const action = btn.dataset.action;
        const stepSmall = parseInt(wrapper.dataset.stepSmall) || 1;
        const stepLarge = parseInt(wrapper.dataset.stepLarge) || 5;
        
        let currentValue = parseInt(input.value) || 0;

        switch (action) {
            case 'dec-large': currentValue -= stepLarge; break;
            case 'dec-small': currentValue -= stepSmall; break;
            case 'inc-small': currentValue += stepSmall; break;
            case 'inc-large': currentValue += stepLarge; break;
        }

        if (currentValue < 1) currentValue = 1;
        input.value = currentValue;
    });
}

async function loadConfig() {
    const loading = _container.querySelector('#config-loading-state');
    const content = _container.querySelector('#config-content-area');

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.GetConfig);
        
        if (res.success) {
            const checkEnabled = _container.querySelector('#input-auto-enabled');
            const inputFreq = _container.querySelector('#input-frequency');
            const inputRet = _container.querySelector('#input-retention');

            // Inputs
            if (checkEnabled) checkEnabled.checked = res.enabled;
            if (inputFreq) inputFreq.value = res.frequency;
            if (inputRet) inputRet.value = res.retention;

            // Stats Vivos
            updateStats(res);

            if(loading) loading.classList.add('d-none');
            if(content) content.classList.remove('d-none');
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        console.error(e);
        Toast.show(I18n.t('js.core.connection_error'), 'error');
    }
}

function updateStats(data) {
    if (!data.meta) return;

    // 1. Ultimo Run
    const elLastRun = _container.querySelector('#stat-last-run');
    if (elLastRun) {
        if (data.meta.last_run) {
            const date = new Date(data.meta.last_run);
            elLastRun.textContent = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            elLastRun.style.fontSize = '1.2rem';
        } else {
            elLastRun.textContent = I18n.t('admin.backups.never') || "Nunca";
        }
    }

    // 2. Status Badge
    const elBadge = _container.querySelector('#stat-status-badge');
    if (elBadge) {
        if (data.enabled) {
            elBadge.className = 'component-trend-badge success';
            elBadge.innerHTML = `<span class="material-symbols-rounded" style="font-size:14px;">check_circle</span> ${I18n.t('admin.backups.status_active') || 'Activo'}`;
        } else {
            elBadge.className = 'component-trend-badge error';
            elBadge.innerHTML = `<span class="material-symbols-rounded" style="font-size:14px;">pause_circle</span> ${I18n.t('admin.backups.status_paused') || 'Pausado'}`;
        }
    }

    // 3. Countdown Timer
    if (data.enabled && data.meta.seconds_remaining !== null) {
        _secondsRemaining = parseInt(data.meta.seconds_remaining);
        
        const elNextDate = _container.querySelector('#stat-next-date');
        if (elNextDate && data.meta.next_run_estimate) {
            elNextDate.textContent = (I18n.t('admin.backups.predicted') || "Previsto") + ": " + data.meta.next_run_estimate;
        }

        startTimer();
    } else {
        stopTimer();
        const elCountdown = _container.querySelector('#stat-countdown');
        const elNextDate = _container.querySelector('#stat-next-date');
        if(elCountdown) elCountdown.textContent = "--:--:--";
        if(elNextDate) elNextDate.textContent = data.enabled ? (I18n.t('admin.backups.calculating') || "Calculando...") : (I18n.t('admin.backups.schedule_disabled') || "Programación desactivada");
    }
}

function startTimer() {
    stopTimer();
    const elCountdown = _container.querySelector('#stat-countdown');
    
    _countdownInterval = setInterval(() => {
        if (_secondsRemaining <= 0) {
            if(elCountdown) elCountdown.textContent = I18n.t('admin.backups.queued') || "En cola...";
            return;
        }

        _secondsRemaining--;

        const h = Math.floor(_secondsRemaining / 3600);
        const m = Math.floor((_secondsRemaining % 3600) / 60);
        const s = _secondsRemaining % 60;

        const hStr = h.toString().padStart(2, '0');
        const mStr = m.toString().padStart(2, '0');
        const sStr = s.toString().padStart(2, '0');

        if(elCountdown) elCountdown.textContent = `${hStr}:${mStr}:${sStr}`;

    }, 1000);
}

function stopTimer() {
    if (_countdownInterval) {
        clearInterval(_countdownInterval);
        _countdownInterval = null;
    }
}

async function saveConfig() {
    const btn = _container.querySelector('#btn-save-backup-config');
    const originalText = btn.innerHTML;
    
    const enabled = _container.querySelector('#input-auto-enabled').checked;
    const frequency = _container.querySelector('#input-frequency').value;
    const retention = _container.querySelector('#input-retention').value;

    btn.disabled = true;
    btn.innerHTML = `<div class="spinner-sm"></div> ${I18n.t('js.core.saving') || 'Guardando...'}`;

    const formData = new FormData();
    formData.append('enabled', enabled ? '1' : '0');
    formData.append('frequency', frequency);
    formData.append('retention', retention);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.UpdateConfig, formData);
        if (res.success) {
            Toast.show(I18n.t('admin.backups.config_saved') || 'Configuración guardada correctamente', 'success');
            await loadConfig();
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        Toast.show(I18n.t('admin.backups.save_error') || 'Error al guardar', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function triggerNow() {
    const btn = _container.querySelector('#btn-trigger-now');
    
    const confirmed = await Dialog.confirm(
        I18n.t('admin.backups.trigger_title') || '¿Adelantar Respaldo?', 
        I18n.t('admin.backups.trigger_message') || 'Esto creará una copia de seguridad inmediatamente y reiniciará el contador del temporizador automático.'
    );

    if (!confirmed) return;

    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="spinner-sm"></div>';

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Create);
        
        if (res.success) {
            Toast.show(I18n.t('admin.backups.trigger_success') || 'Respaldo iniciado correctamente', 'success');
            setTimeout(() => loadConfig(), 2000); 
        } else {
            Toast.show(res.message || (I18n.t('admin.backups.trigger_error') || 'Error al iniciar respaldo'), 'error');
        }
    } catch (e) {
        console.error(e);
        Toast.show(I18n.t('js.core.communication_error') || 'Error de comunicación', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}