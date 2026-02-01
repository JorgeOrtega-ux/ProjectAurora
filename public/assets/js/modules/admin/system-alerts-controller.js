/**
 * public/assets/js/modules/admin/system-alerts-controller.js
 */
import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { DateTimePicker } from '../../core/date-time-picker.js';
import { I18n } from '../../core/i18n-manager.js';

export const SystemAlertsController = {
    init: () => {
        const btnEmit = document.getElementById('btn-emit-alert');
        const btnDeactivateMini = document.getElementById('btn-deactivate-alert-mini');
        const btnRefresh = document.querySelector('[data-action="refresh-status"]');

        // Estado interno
        let isAlertActive = false;

        // Variables de selección
        let selectedMainType = 'performance';
        let selectedPerfMsgType = 'degradation';
        let selectedMaintType = 'scheduled';
        let selectedPolicyDoc = 'terms';
        let selectedPolicyStatus = 'future';

        // --- INICIALIZAR CALENDARIOS ---
        
        // 1. Mantenimiento Programado (Fecha + Hora)
        const maintPicker = new DateTimePicker('wrapper-maint-start', 'maint-start-time', {
            minDate: new Date(),
            enableTime: true
        });

        // 2. Mantenimiento Emergencia (Fecha + Hora)
        const emergencyPicker = new DateTimePicker('wrapper-maint-emergency', 'maint-emergency-time', {
            minDate: new Date(),
            enableTime: true
        });

        // 3. Políticas (Solo Fecha)
        const policyPicker = new DateTimePicker('wrapper-policy-date', 'policy-effective-date', {
            minDate: new Date(),
            enableTime: false, 
            format: 'YYYY-MM-DD'
        });

        // --- CONFIGURACIÓN UI (TRANSLATED) ---
        const configMainType = {
            'performance': { icon: 'speed', text: I18n.t('admin.alerts.type_perf') },
            'maintenance': { icon: 'build', text: I18n.t('admin.alerts.type_maint') },
            'policy':      { icon: 'policy', text: I18n.t('admin.alerts.type_policy') }
        };

        const configPerfMsg = {
            'degradation': { icon: 'troubleshoot', text: I18n.t('admin.alerts.perf_deg'), message: I18n.t('system_alerts.performance.degradation') },
            'latency':     { icon: 'network_check', text: I18n.t('admin.alerts.perf_lat'), message: I18n.t('system_alerts.performance.latency') },
            'overload':    { icon: 'memory', text: I18n.t('admin.alerts.perf_over'),     message: I18n.t('system_alerts.performance.overload') }
        };

        const configPolicyDoc = {
            'terms':   { text: I18n.t('system_alerts.policy.names.terms') },
            'privacy': { text: I18n.t('system_alerts.policy.names.privacy') },
            'cookies': { text: I18n.t('system_alerts.policy.names.cookies') }
        };

        // --- PREVIEW ---
        const updatePreview = () => {
            const iconEl = document.getElementById('preview-icon');
            const titleEl = document.getElementById('preview-title');
            const msgEl = document.getElementById('preview-message');
            const textContainer = document.getElementById('preview-text-container');
            
            const existingMeta = document.getElementById('preview-meta');
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
                    // "Intervención Planificada" matches admin.alerts.mode_sched
                    titleEl.textContent = I18n.t('admin.alerts.mode_sched'); 
                    const startVal = document.getElementById('maint-start-time').value;
                    const duration = document.getElementById('maint-duration').value || '60';
                    
                    const dateStr = startVal 
                        ? new Date(startVal).toLocaleString([], {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'}) 
                        : '--/-- --:--';

                    // "Mantenimiento Programado: Acceso interrumpido desde %s (Duración aprox: %s min)."
                    msgEl.textContent = I18n.t('system_alerts.maintenance.scheduled', [dateStr, duration]);
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">timer</span> ${duration} min`;
                } else {
                    // "Incidente Crítico / Urgente" matches admin.alerts.mode_emerg
                    titleEl.textContent = I18n.t('admin.alerts.mode_emerg');
                    const timeVal = document.getElementById('maint-emergency-time').value;
                    
                    // Formato amigable para el preview
                    let timeStr = '--:--';
                    if (timeVal) {
                        const d = new Date(timeVal);
                        timeStr = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    }

                    // "ALERTA CRÍTICA: Desconexión forzosa programada para las %s. Guarda tu trabajo inmediatamente."
                    msgEl.textContent = I18n.t('system_alerts.maintenance.emergency', [timeStr]);
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px; color:var(--color-error)">warning</span> ${I18n.t('admin.alerts.forced_disconnect') || 'Desconexión Forzosa'}`;
                    iconEl.textContent = 'warning';
                }

            } else if (selectedMainType === 'policy') {
                const docName = configPolicyDoc[selectedPolicyDoc].text;
                const link = document.getElementById('policy-link').value;
                
                titleEl.textContent = I18n.t('admin.alerts.legal_update') || "Actualización Legal"; 
                iconEl.textContent = 'gavel';

                if (selectedPolicyStatus === 'future') {
                    const dateVal = document.getElementById('policy-effective-date').value;
                    const dateStr = dateVal ? new Date(dateVal).toLocaleDateString() : '--/--/----';
                    
                    // "Aviso Legal: A partir del %s entrará en vigor la nueva versión de %s."
                    // Note: We inject HTML tags into the params since translation engine does simple replace
                    msgEl.innerHTML = I18n.t('system_alerts.policy.future', [`<b>${dateStr}</b>`, `<b>${docName}</b>`]);
                } else {
                    // "Actualización Legal: Hemos modificado nuestros %s. Te recomendamos revisarlos."
                    msgEl.innerHTML = I18n.t('system_alerts.policy.immediate', [`<b>${docName}</b>`]);
                }
                
                if (link) {
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">link</span> <span style="text-decoration:underline">${I18n.t('js.core.view_more') || 'Ver más'}</span>`;
                }
            }

            if (metaHtml) {
                const metaEl = document.createElement('div');
                metaEl.id = 'preview-meta';
                metaEl.style.cssText = "display: flex; gap: 8px; margin-top: 4px; font-size: 12px; color: var(--primary-color); font-weight: 600; align-items: center;";
                metaEl.innerHTML = metaHtml;
                textContainer.appendChild(metaEl);
            }
        };

        // Listeners UI
        ['maint-start-time', 'maint-duration', 'maint-emergency-time', 'policy-link', 'policy-effective-date'].forEach(id => {
            const el = document.getElementById(id);
            if(el) el.addEventListener('input', updatePreview);
        });

        const btnDurDec = document.getElementById('btn-duration-dec');
        const btnDurInc = document.getElementById('btn-duration-inc');
        const inputDur = document.getElementById('maint-duration');

        if (btnDurDec && inputDur) btnDurDec.onclick = () => { inputDur.stepDown(15); updatePreview(); };
        if (btnDurInc && inputDur) btnDurInc.onclick = () => { inputDur.stepUp(15); updatePreview(); };

        // --- TRIGGERS & POPOVERS ---
        const setupTrigger = (triggerId, popoverId, actionName, onSelectCallback) => {
            const trigger = document.getElementById(triggerId);
            const popover = document.getElementById(popoverId);
            if (!trigger || !popover) return;

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

        // Inicializar Selectores
        setupTrigger('trigger-alert-type', 'popover-alert-type', 'select-main-type', (val) => {
            selectedMainType = val;
            document.getElementById('text-alert-type').textContent = configMainType[val].text;
            document.getElementById('icon-alert-type').textContent = configMainType[val].icon;
            
            document.querySelectorAll('.config-group').forEach(el => el.style.display = 'none');
            const group = document.getElementById(`group-${val}`);
            if (group) group.style.display = 'block';
        });

        setupTrigger('trigger-perf-msg', 'popover-perf-msg', 'select-perf-msg', (val) => {
            selectedPerfMsgType = val;
            document.getElementById('text-perf-msg').textContent = configPerfMsg[val].text;
            document.getElementById('icon-perf-msg').textContent = configPerfMsg[val].icon;
        });

        setupTrigger('trigger-maint-type', 'popover-maint-type', 'select-maint-type', (val) => {
            selectedMaintType = val;
            const text = (val === 'scheduled' ? I18n.t('admin.alerts.mode_sched') : I18n.t('admin.alerts.mode_emerg'));
            document.getElementById('text-maint-type').textContent = text;
            document.getElementById('subgroup-maint-scheduled').style.display = (val === 'scheduled') ? 'block' : 'none';
            document.getElementById('subgroup-maint-emergency').style.display = (val === 'emergency') ? 'block' : 'none';
        });

        setupTrigger('trigger-policy-doc', 'popover-policy-doc', 'select-policy-doc', (val) => {
            selectedPolicyDoc = val;
            document.getElementById('text-policy-doc').textContent = configPolicyDoc[val].text;
        });

        setupTrigger('trigger-policy-status', 'popover-policy-status', 'select-policy-status', (val) => {
            selectedPolicyStatus = val;
            const text = (val === 'future' ? I18n.t('admin.alerts.status_future') : I18n.t('admin.alerts.status_immediate'));
            document.getElementById('text-policy-status').textContent = text;
            document.getElementById('subgroup-policy-date').style.display = (val === 'immediate') ? 'none' : 'block';
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
                    const start = document.getElementById('maint-start-time').value;
                    const duration = document.getElementById('maint-duration').value;
                    if (!start) return { valid: false, msg: I18n.t('admin.alerts.error_start_date') || 'Debes especificar la fecha y hora de inicio.' };
                    if (!duration || duration <= 0) return { valid: false, msg: I18n.t('admin.alerts.error_duration') || 'La duración debe ser mayor a 0 minutos.' };
                } else {
                    const cutoff = document.getElementById('maint-emergency-time').value;
                    if (!cutoff) return { valid: false, msg: I18n.t('admin.alerts.error_cutoff') || 'Debes especificar la hora de la desconexión inminente.' };
                }
            }
            if (selectedMainType === 'policy') {
                if (selectedPolicyStatus === 'future') {
                    const date = document.getElementById('policy-effective-date').value;
                    if (!date) return { valid: false, msg: I18n.t('admin.alerts.error_effective_date') || 'Debes indicar la fecha de entrada en vigor.' };
                }
            }
            return { valid: true };
        };

        // --- LÓGICA DE EMISIÓN ---
        const executeEmission = async () => {
            Dialog.showLoading(I18n.t('admin.alerts.btn_emit') + '...');

            let payload = { type: selectedMainType, meta: {} };

            switch (selectedMainType) {
                case 'performance':
                    payload.meta.code = selectedPerfMsgType;
                    break;
                case 'maintenance':
                    payload.meta.subtype = selectedMaintType;
                    if (selectedMaintType === 'scheduled') {
                        payload.meta.start = document.getElementById('maint-start-time').value;
                        payload.meta.duration = document.getElementById('maint-duration').value;
                    } else {
                        payload.meta.cutoff = document.getElementById('maint-emergency-time').value;
                    }
                    break;
                case 'policy':
                    payload.meta.doc = selectedPolicyDoc;
                    payload.meta.update_type = selectedPolicyStatus;
                    payload.meta.link = document.getElementById('policy-link').value;
                    if (selectedPolicyStatus === 'future') {
                        payload.meta.date = document.getElementById('policy-effective-date').value;
                    } else {
                        payload.meta.date = new Date().toISOString().split('T')[0];
                    }
                    break;
            }

            try {
                const formData = new FormData();
                formData.append('alert_data', JSON.stringify(payload));
                const res = await ApiService.post(ApiService.Routes.Admin.CreateSystemAlert, formData);
                
                Dialog.close();

                if (res.success) {
                    Toast.show(I18n.t('admin.alerts.emit_success') || 'Difusión emitida correctamente', 'success');
                    checkActiveAlertStatus();
                } else {
                    Toast.show(res.message || (I18n.t('admin.alerts.emit_error') || 'Error al emitir'), 'error');
                }
            } catch (e) { 
                Dialog.close();
                console.error(e); 
            }
        };

        if (btnEmit) {
            btnEmit.onclick = async () => {
                const validation = validateInputs();
                if (!validation.valid) {
                    Dialog.alert({ title: I18n.t('js.auth.fill_all'), message: validation.msg });
                    return;
                }

                if (isAlertActive) {
                    const confirmed = await Dialog.confirm({
                        title: I18n.t('admin.alerts.active_detected') || 'Alerta en curso detectada',
                        message: I18n.t('admin.alerts.replace_confirm') || 'Ya existe una alerta transmitiéndose. ¿Deseas reemplazarla?',
                        confirmText: I18n.t('admin.alerts.btn_replace') || 'Reemplazar',
                        cancelText: I18n.t('js.core.cancel') || 'Cancelar'
                    });
                    if (confirmed) await executeEmission();
                } else {
                    await executeEmission();
                }
            };
        }

        const handleDeactivate = async () => {
            const confirmed = await Dialog.confirm({
                title: I18n.t('admin.alerts.btn_deactivate'),
                message: I18n.t('admin.alerts.stop_confirm') || '¿Estás seguro de que deseas detener la alerta actual?',
                type: 'danger',
                confirmText: I18n.t('admin.alerts.btn_stop') || 'Detener',
                cancelText: I18n.t('js.core.cancel') || 'Cancelar'
            });

            if (confirmed) {
                try {
                    const res = await ApiService.post(ApiService.Routes.Admin.DeactivateSystemAlert);
                    if (res.success) {
                        Toast.show(I18n.t('admin.alerts.system_normalized') || 'Sistema normalizado', 'success');
                        checkActiveAlertStatus();
                    }
                } catch (e) { console.error(e); }
            }
        };

        if (btnDeactivateMini) btnDeactivateMini.onclick = handleDeactivate;

        if (btnRefresh) btnRefresh.onclick = () => {
             checkActiveAlertStatus();
             Toast.show(I18n.t('admin.alerts.btn_refresh'), 'info');
        };

        checkActiveAlertStatus();

        async function checkActiveAlertStatus() {
            const statOnline = document.getElementById('stat-online-users');
            const statToday = document.getElementById('stat-alerts-today');
            const badgeTotal = document.getElementById('badge-alerts-total');
            const statIcon = document.getElementById('stat-active-icon');
            const statText = document.getElementById('stat-active-text');
            const cardStatus = document.getElementById('card-status-indicator');
            const btnMini = document.getElementById('btn-deactivate-alert-mini');
            const impactIcon = document.getElementById('stat-impact-icon');
            const impactVal = document.getElementById('stat-last-severity');
            const impactTime = document.getElementById('stat-last-time');

            try {
                statText.textContent = I18n.t('js.auth.loading');
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
                    statText.textContent = I18n.t('admin.alerts.stat_active'); // "Alerta Activa"
                    statText.style.color = color;
                    cardStatus.style.borderLeftColor = color;
                    btnMini.style.display = 'flex';
                    
                    const sevCritical = I18n.t('admin.alerts.sev_critical') || 'Crítico';
                    const sevModerate = I18n.t('admin.alerts.sev_moderate') || 'Moderado';
                    impactVal.textContent = res.alert.severity === 'critical' ? sevCritical : sevModerate;
                    
                    impactIcon.style.color = color;
                    impactTime.textContent = res.alert.type.toUpperCase(); 
                } else {
                    isAlertActive = false;
                    statIcon.textContent = 'check_circle';
                    statIcon.style.color = 'var(--color-success)';
                    statText.textContent = I18n.t('admin.alerts.stat_operational'); // "Operativo"
                    statText.style.color = 'var(--text-primary)';
                    cardStatus.style.borderLeftColor = 'var(--color-success)';
                    btnMini.style.display = 'none';
                    impactVal.textContent = I18n.t('admin.alerts.sev_normal') || "Normal";
                    impactIcon.style.color = 'var(--text-tertiary)';
                    if (res.stats && res.stats.last_alert_time) {
                        impactTime.textContent = (I18n.t('admin.alerts.last_alert') || "Última: ") + res.stats.last_alert_time;
                    } else {
                        impactTime.textContent = I18n.t('admin.alerts.stat_none'); // "Sin incidentes recientes"
                    }
                }
            } catch (e) { console.error(e); }
        }
    }
};