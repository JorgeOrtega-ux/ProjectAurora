/**
 * public/assets/js/modules/admin/system-alerts-controller.js
 */
import { ApiService } from '../../core/api-service.js';
import { ToastManager } from '../../core/toast-manager.js';
import { DialogManager } from '../../core/dialog-manager.js';
import { DateTimePicker } from '../../core/date-time-picker.js';
import { I18nManager } from '../../core/i18n-manager.js';

let _container = null;

export const SystemAlertsController = {
    init: () => {
        _container = document.querySelector('[data-section="admin-system-alerts"]');
        if (!_container) return;

        const btnEmit = _container.querySelector('[data-action="emit-alert"]');
        const btnDeactivateMini = _container.querySelector('[data-action="deactivate-alert-mini"]');
        const btnRefresh = _container.querySelector('[data-action="refresh-status"]');

        let isAlertActive = false;
        let selectedMainType = 'performance';
        let selectedPerfMsgType = 'degradation';
        let selectedMaintType = 'scheduled';
        let selectedPolicyDoc = 'terms';
        let selectedPolicyStatus = 'future';

        // --- INICIALIZAR CALENDARIOS ---
        const wrapperStart = _container.querySelector('[data-element="wrapper-maint-start"]');
        const inputStart = _container.querySelector('[data-input="maint-start-time"]');
        new DateTimePicker(wrapperStart, inputStart, { minDate: new Date(), enableTime: true });

        const wrapperEmerg = _container.querySelector('[data-element="wrapper-maint-emergency"]');
        const inputEmerg = _container.querySelector('[data-input="maint-emergency-time"]');
        new DateTimePicker(wrapperEmerg, inputEmerg, { minDate: new Date(), enableTime: true });

        const wrapperPolicy = _container.querySelector('[data-element="wrapper-policy-date"]');
        const inputPolicy = _container.querySelector('[data-input="policy-effective-date"]');
        new DateTimePicker(wrapperPolicy, inputPolicy, { minDate: new Date(), enableTime: false, format: 'YYYY-MM-DD' });

        // --- CONFIGURACIÓN UI (Textos estáticos) ---
        const configMainType = {
            'performance': { icon: 'speed', text: I18nManager.t('admin.alerts.type_perf') },
            'maintenance': { icon: 'build', text: I18nManager.t('admin.alerts.type_maint') },
            'policy':      { icon: 'policy', text: I18nManager.t('admin.alerts.type_policy') }
        };

        const configPerfMsg = {
            'degradation': { icon: 'troubleshoot', text: I18nManager.t('admin.alerts.perf_deg'), message: I18nManager.t('system_alerts.performance.degradation') },
            'latency':     { icon: 'network_check', text: I18nManager.t('admin.alerts.perf_lat'), message: I18nManager.t('system_alerts.performance.latency') },
            'overload':    { icon: 'memory', text: I18nManager.t('admin.alerts.perf_over'),     message: I18nManager.t('system_alerts.performance.overload') }
        };

        const configPolicyDoc = {
            'terms':   { text: I18nManager.t('system_alerts.policy.names.terms') },
            'privacy': { text: I18nManager.t('system_alerts.policy.names.privacy') },
            'cookies': { text: I18nManager.t('system_alerts.policy.names.cookies') }
        };

        // --- PREVIEW ---
        const updatePreview = () => {
            const iconEl = _container.querySelector('[data-preview="card-icon"]');
            const titleEl = _container.querySelector('[data-preview="card-title"]');
            const msgEl = _container.querySelector('[data-preview="card-message"]');
            const textContainer = _container.querySelector('[data-preview="text-container"]');
            
            const existingMeta = textContainer.querySelector('.preview-meta-tag');
            if (existingMeta) existingMeta.remove();

            iconEl.textContent = configMainType[selectedMainType].icon;
            let metaHtml = '';

            if (selectedMainType === 'performance') {
                const conf = configPerfMsg[selectedPerfMsgType];
                titleEl.textContent = conf.text;
                msgEl.textContent = conf.message;
                iconEl.textContent = conf.icon;
            
            } else if (selectedMainType === 'maintenance') {
                if (selectedMaintType === 'scheduled') {
                    titleEl.textContent = I18nManager.t('admin.alerts.mode_sched'); 
                    const startVal = _container.querySelector('[data-input="maint-start-time"]').value;
                    const duration = _container.querySelector('[data-input="maint-duration"]').value || '60';
                    const dateStr = startVal 
                        ? new Date(startVal).toLocaleString([], {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'}) 
                        : '--/-- --:--';
                    msgEl.textContent = I18nManager.t('system_alerts.maintenance.scheduled', [dateStr, duration]);
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">timer</span> ${duration} min`;
                } else {
                    titleEl.textContent = I18nManager.t('admin.alerts.mode_emerg');
                    const timeVal = _container.querySelector('[data-input="maint-emergency-time"]').value;
                    let timeStr = '--:--';
                    if (timeVal) {
                        const d = new Date(timeVal);
                        timeStr = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    }
                    msgEl.textContent = I18nManager.t('system_alerts.maintenance.emergency', [timeStr]);
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px; color:var(--color-error)">warning</span> ${I18nManager.t('admin.alerts.forced_disconnect') || 'Desconexión Forzosa'}`;
                    iconEl.textContent = 'warning';
                }

            } else if (selectedMainType === 'policy') {
                const docName = configPolicyDoc[selectedPolicyDoc].text;
                const link = _container.querySelector('[data-input="policy-link"]').value;
                
                titleEl.textContent = I18nManager.t('admin.alerts.legal_update') || "Actualización Legal"; 
                iconEl.textContent = 'gavel';

                if (selectedPolicyStatus === 'future') {
                    const dateVal = _container.querySelector('[data-input="policy-effective-date"]').value;
                    const dateStr = dateVal ? new Date(dateVal).toLocaleDateString() : '--/--/----';
                    msgEl.innerHTML = I18nManager.t('system_alerts.policy.future', [`<b>${dateStr}</b>`, `<b>${docName}</b>`]);
                } else {
                    msgEl.innerHTML = I18nManager.t('system_alerts.policy.immediate', [`<b>${docName}</b>`]);
                }
                
                if (link) {
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">link</span> <span style="text-decoration:underline">${I18nManager.t('js.core.view_more') || 'Ver más'}</span>`;
                }
            }

            if (metaHtml) {
                const metaEl = document.createElement('div');
                metaEl.className = 'preview-meta-tag';
                metaEl.style.cssText = "display: flex; gap: 8px; margin-top: 4px; font-size: 12px; color: var(--primary-color); font-weight: 600; align-items: center;";
                metaEl.innerHTML = metaHtml;
                textContainer.appendChild(metaEl);
            }
        };

        // Listeners UI (Inputs)
        const inputsToWatch = _container.querySelectorAll('[data-input]');
        inputsToWatch.forEach(el => el.addEventListener('input', updatePreview));

        const btnDurDec = _container.querySelector('[data-action="duration-dec"]');
        const btnDurInc = _container.querySelector('[data-action="duration-inc"]');
        const inputDur = _container.querySelector('[data-input="maint-duration"]');

        if (btnDurDec && inputDur) btnDurDec.onclick = () => { inputDur.stepDown(15); updatePreview(); };
        if (btnDurInc && inputDur) btnDurInc.onclick = () => { inputDur.stepUp(15); updatePreview(); };

        // --- TRIGGERS & POPOVERS ---
        const setupTrigger = (selectorKey, actionName, onSelectCallback) => {
            const trigger = _container.querySelector(`[data-selector="${selectorKey}"]`);
            const popover = trigger?.nextElementSibling; 
            
            if (!trigger || !popover || !popover.classList.contains('popover-module')) return;

            trigger.onclick = (e) => {
                e.stopPropagation();
                document.querySelectorAll('.popover-module').forEach(p => { if (p !== popover) p.classList.remove('active'); });
                document.querySelectorAll('.trigger-selector').forEach(t => { if (t !== trigger) t.classList.remove('active'); });
                
                popover.classList.toggle('active');
                trigger.classList.toggle('active');
            };

            const options = popover.querySelectorAll(`[data-action="${actionName}"]`);
            options.forEach(opt => {
                opt.onclick = () => {
                    options.forEach(o => o.classList.remove('active'));
                    opt.classList.add('active');
                    const value = opt.dataset.value;
                    onSelectCallback(value, trigger);
                    popover.classList.remove('active');
                    trigger.classList.remove('active');
                    updatePreview();
                };
            });
        };

        setupTrigger('alert-type', 'select-main-type', (val) => {
            selectedMainType = val;
            _container.querySelector('[data-preview="text-type"]').textContent = configMainType[val].text;
            _container.querySelector('[data-preview="icon-type"]').textContent = configMainType[val].icon;
            
            _container.querySelectorAll('.config-group').forEach(el => el.style.display = 'none');
            const group = _container.querySelector(`[data-group="${val}"]`);
            if (group) group.style.display = 'block';
        });

        setupTrigger('perf-msg', 'select-perf-msg', (val) => {
            selectedPerfMsgType = val;
            _container.querySelector('[data-preview="text-perf"]').textContent = configPerfMsg[val].text;
            _container.querySelector('[data-preview="icon-perf"]').textContent = configPerfMsg[val].icon;
        });

        setupTrigger('maint-type', 'select-maint-type', (val) => {
            selectedMaintType = val;
            const text = (val === 'scheduled' ? I18nManager.t('admin.alerts.mode_sched') : I18nManager.t('admin.alerts.mode_emerg'));
            _container.querySelector('[data-preview="text-maint"]').textContent = text;
            _container.querySelector('[data-subgroup="maint-scheduled"]').style.display = (val === 'scheduled') ? 'block' : 'none';
            _container.querySelector('[data-subgroup="maint-emergency"]').style.display = (val === 'emergency') ? 'block' : 'none';
        });

        setupTrigger('policy-doc', 'select-policy-doc', (val) => {
            selectedPolicyDoc = val;
            _container.querySelector('[data-preview="text-policy-doc"]').textContent = configPolicyDoc[val].text;
        });

        setupTrigger('policy-status', 'select-policy-status', (val) => {
            selectedPolicyStatus = val;
            const text = (val === 'future' ? I18nManager.t('admin.alerts.status_future') : I18nManager.t('admin.alerts.status_immediate'));
            _container.querySelector('[data-preview="text-policy-status"]').textContent = text;
            _container.querySelector('[data-subgroup="policy-date"]').style.display = (val === 'immediate') ? 'none' : 'block';
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.trigger-select-wrapper')) {
                document.querySelectorAll('.popover-module.active').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.trigger-selector.active').forEach(el => el.classList.remove('active'));
            }
        });

        updatePreview();

        // --- VALIDACIÓN DE DATOS ---
        const validateInputs = () => {
            if (selectedMainType === 'maintenance') {
                if (selectedMaintType === 'scheduled') {
                    const start = _container.querySelector('[data-input="maint-start-time"]').value;
                    const duration = _container.querySelector('[data-input="maint-duration"]').value;
                    if (!start) return { valid: false, msg: I18nManager.t('admin.alerts.error_start_date') || 'Debes especificar la fecha y hora de inicio.' };
                    if (!duration || duration <= 0) return { valid: false, msg: I18nManager.t('admin.alerts.error_duration') || 'La duración debe ser mayor a 0 minutos.' };
                } else {
                    const cutoff = _container.querySelector('[data-input="maint-emergency-time"]').value;
                    if (!cutoff) return { valid: false, msg: I18nManager.t('admin.alerts.error_cutoff') || 'Debes especificar la hora de la desconexión inminente.' };
                }
            }
            if (selectedMainType === 'policy') {
                if (selectedPolicyStatus === 'future') {
                    const date = _container.querySelector('[data-input="policy-effective-date"]').value;
                    if (!date) return { valid: false, msg: I18nManager.t('admin.alerts.error_effective_date') || 'Debes indicar la fecha de entrada en vigor.' };
                }
            }
            return { valid: true };
        };

        // --- LÓGICA DE EMISIÓN ---
        const executeEmission = async () => {
            DialogManager.showLoading(I18nManager.t('admin.alerts.btn_emit') + '...');

            let payload = { type: selectedMainType, meta: {} };

            switch (selectedMainType) {
                case 'performance':
                    payload.meta.code = selectedPerfMsgType;
                    break;
                case 'maintenance':
                    payload.meta.subtype = selectedMaintType;
                    if (selectedMaintType === 'scheduled') {
                        payload.meta.start = _container.querySelector('[data-input="maint-start-time"]').value;
                        payload.meta.duration = _container.querySelector('[data-input="maint-duration"]').value;
                    } else {
                        payload.meta.cutoff = _container.querySelector('[data-input="maint-emergency-time"]').value;
                    }
                    break;
                case 'policy':
                    payload.meta.doc = selectedPolicyDoc;
                    payload.meta.update_type = selectedPolicyStatus;
                    payload.meta.link = _container.querySelector('[data-input="policy-link"]').value;
                    if (selectedPolicyStatus === 'future') {
                        payload.meta.date = _container.querySelector('[data-input="policy-effective-date"]').value;
                    } else {
                        payload.meta.date = new Date().toISOString().split('T')[0];
                    }
                    break;
            }

            try {
                const formData = new FormData();
                formData.append('alert_data', JSON.stringify(payload));
                const res = await ApiService.post(ApiService.Routes.Admin.CreateSystemAlert, formData);
                DialogManager.close();
                if (res.success) {
                    ToastManager.show(I18nManager.t('admin.alerts.emit_success') || 'Difusión emitida correctamente', 'success');
                    checkActiveAlertStatus();
                } else {
                    ToastManager.show(res.message || (I18nManager.t('admin.alerts.emit_error') || 'Error al emitir'), 'error');
                }
            } catch (e) { 
                DialogManager.close();
                console.error(e); 
            }
        };

        if (btnEmit) {
            btnEmit.onclick = async () => {
                const validation = validateInputs();
                if (!validation.valid) {
                    DialogManager.alert({ title: I18nManager.t('js.auth.fill_all'), message: validation.msg });
                    return;
                }
                if (isAlertActive) {
                    const confirmed = await DialogManager.confirm({
                        title: I18nManager.t('admin.alerts.active_detected') || 'Alerta en curso detectada',
                        message: I18nManager.t('admin.alerts.replace_confirm') || 'Ya existe una alerta transmitiéndose. ¿Deseas reemplazarla?',
                        confirmText: I18nManager.t('admin.alerts.btn_replace') || 'Reemplazar',
                        cancelText: I18nManager.t('js.core.cancel') || 'Cancelar'
                    });
                    if (confirmed) await executeEmission();
                } else {
                    await executeEmission();
                }
            };
        }

        const handleDeactivate = async () => {
            const confirmed = await DialogManager.confirm({
                title: I18nManager.t('admin.alerts.btn_deactivate'),
                message: I18nManager.t('admin.alerts.stop_confirm') || '¿Estás seguro de que deseas detener la alerta actual?',
                type: 'danger',
                confirmText: I18nManager.t('admin.alerts.btn_stop') || 'Detener',
                cancelText: I18nManager.t('js.core.cancel') || 'Cancelar'
            });

            if (confirmed) {
                try {
                    const res = await ApiService.post(ApiService.Routes.Admin.DeactivateSystemAlert);
                    if (res.success) {
                        ToastManager.show(I18nManager.t('admin.alerts.system_normalized') || 'Sistema normalizado', 'success');
                        checkActiveAlertStatus();
                    }
                } catch (e) { console.error(e); }
            }
        };

        if (btnDeactivateMini) btnDeactivateMini.onclick = handleDeactivate;

        if (btnRefresh) btnRefresh.onclick = () => {
             checkActiveAlertStatus();
             ToastManager.show(I18nManager.t('admin.alerts.btn_refresh'), 'info');
        };

        // --- CHECK STATUS ---
        checkActiveAlertStatus();

        async function checkActiveAlertStatus() {
            const statOnline = _container.querySelector('[data-stat="online-users"]');
            const statToday = _container.querySelector('[data-stat="alerts-today"]');
            const badgeTotal = _container.querySelector('[data-stat="badge-total"]');
            const statIcon = _container.querySelector('[data-stat="active-icon"]');
            const statText = _container.querySelector('[data-stat="active-text"]');
            const cardStatus = _container.querySelector('[data-element="card-status-indicator"]');
            
            const impactIcon = _container.querySelector('[data-stat="impact-icon"]');
            const impactVal = _container.querySelector('[data-stat="last-severity"]');
            const impactTime = _container.querySelector('[data-stat="last-time"]');

            try {
                statText.textContent = I18nManager.t('js.auth.loading');
                const res = await ApiService.post(ApiService.Routes.Admin.GetActiveAlert);
                
                if (res.stats) {
                    statOnline.textContent = res.stats.online_users;
                    statToday.textContent = res.stats.alerts_today;
                    badgeTotal.innerHTML = `<span class="material-symbols-rounded" style="font-size:14px;">history</span> Total: ${res.stats.alerts_total}`;
                }

                if (res.success && res.alert) {
                    isAlertActive = true; 
                    const color = res.alert.severity === 'critical' ? 'var(--color-error)' : 'var(--color-warning)';
                    statIcon.textContent = 'warning';
                    statIcon.style.color = color;
                    statText.textContent = I18nManager.t('admin.alerts.stat_active'); 
                    statText.style.color = color;
                    cardStatus.style.borderLeftColor = color;
                    btnDeactivateMini.style.display = 'flex';
                    
                    const sevCritical = I18nManager.t('admin.alerts.sev_critical') || 'Crítico';
                    const sevModerate = I18nManager.t('admin.alerts.sev_moderate') || 'Moderado';
                    impactVal.textContent = res.alert.severity === 'critical' ? sevCritical : sevModerate;
                    impactIcon.style.color = color;
                    impactTime.textContent = res.alert.type.toUpperCase(); 
                } else {
                    isAlertActive = false;
                    statIcon.textContent = 'check_circle';
                    statIcon.style.color = 'var(--color-success)';
                    statText.textContent = I18nManager.t('admin.alerts.stat_operational'); 
                    statText.style.color = 'var(--text-primary)';
                    cardStatus.style.borderLeftColor = 'var(--color-success)';
                    btnDeactivateMini.style.display = 'none';
                    impactVal.textContent = I18nManager.t('admin.alerts.sev_normal') || "Normal";
                    impactIcon.style.color = 'var(--text-tertiary)';
                    if (res.stats && res.stats.last_alert_time) {
                        impactTime.textContent = (I18nManager.t('admin.alerts.last_alert') || "Última: ") + res.stats.last_alert_time;
                    } else {
                        impactTime.textContent = I18nManager.t('admin.alerts.stat_none'); 
                    }
                }
            } catch (e) { console.error(e); }
        }
    }
};