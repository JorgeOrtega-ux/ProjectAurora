/**
 * public/assets/js/modules/admin/system-alerts-controller.js
 */
import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { DateTimePicker } from '../../core/date-time-picker.js';

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

        // 2. Mantenimiento Emergencia (Fecha + Hora) - NUEVO
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

        // --- CONFIGURACIÓN UI ---
        const configMainType = {
            'performance': { icon: 'speed', text: 'Rendimiento' },
            'maintenance': { icon: 'build', text: 'Mantenimiento' },
            'policy':      { icon: 'policy', text: 'Políticas y Legal' }
        };

        const configPerfMsg = {
            'degradation': { icon: 'troubleshoot', text: 'Degradación de Servicio', message: 'Estamos experimentando lentitud en algunos servicios.' },
            'latency':     { icon: 'network_check', text: 'Latencia Alta Detectada', message: 'Se ha detectado una latencia alta en la conexión.' },
            'overload':    { icon: 'memory', text: 'Sobrecarga Temporal',     message: 'El sistema presenta una carga inusual.' }
        };

        const configPolicyDoc = {
            'terms':   { text: 'Términos y Condiciones' },
            'privacy': { text: 'Política de Privacidad' },
            'cookies': { text: 'Política de Cookies' }
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
                    titleEl.textContent = "Mantenimiento Programado";
                    const startVal = document.getElementById('maint-start-time').value;
                    const duration = document.getElementById('maint-duration').value || '60';
                    
                    const dateStr = startVal 
                        ? new Date(startVal).toLocaleString([], {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'}) 
                        : '--/-- --:--';

                    msgEl.textContent = `Los servicios no estarán disponibles desde el ${dateStr} por aprox. ${duration} min.`;
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">timer</span> ${duration} min`;
                } else {
                    titleEl.textContent = "Mantenimiento de Emergencia";
                    const timeVal = document.getElementById('maint-emergency-time').value;
                    
                    // Formato amigable para el preview (solo hora si es hoy, o fecha+hora)
                    let timeStr = '--:--';
                    if (timeVal) {
                        const d = new Date(timeVal);
                        timeStr = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    }

                    msgEl.textContent = `Atención: Se realizará un corte de servicio inminente a las ${timeStr}.`;
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px; color:var(--color-error)">warning</span> Urgente`;
                    iconEl.textContent = 'warning';
                }

            } else if (selectedMainType === 'policy') {
                const docName = configPolicyDoc[selectedPolicyDoc].text;
                const link = document.getElementById('policy-link').value;
                
                titleEl.textContent = "Actualización Legal";
                iconEl.textContent = 'gavel';

                if (selectedPolicyStatus === 'future') {
                    const dateVal = document.getElementById('policy-effective-date').value;
                    const dateStr = dateVal ? new Date(dateVal + 'T00:00:00').toLocaleDateString() : '--/--/----';
                    msgEl.innerHTML = `A partir del <b>${dateStr}</b> entrarán en vigor los nuevos <b>${docName}</b>.`;
                } else {
                    msgEl.innerHTML = `Hemos actualizado nuestros <b>${docName}</b>. Revisa los cambios.`;
                }
                
                if (link) {
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">link</span> <span style="text-decoration:underline">Ver más</span>`;
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
            document.getElementById('text-maint-type').textContent = (val === 'scheduled' ? 'Programado' : 'Emergencia');
            document.getElementById('subgroup-maint-scheduled').style.display = (val === 'scheduled') ? 'block' : 'none';
            document.getElementById('subgroup-maint-emergency').style.display = (val === 'emergency') ? 'block' : 'none';
        });

        setupTrigger('trigger-policy-doc', 'popover-policy-doc', 'select-policy-doc', (val) => {
            selectedPolicyDoc = val;
            document.getElementById('text-policy-doc').textContent = configPolicyDoc[val].text;
        });

        setupTrigger('trigger-policy-status', 'popover-policy-status', 'select-policy-status', (val) => {
            selectedPolicyStatus = val;
            document.getElementById('text-policy-status').textContent = (val === 'future' ? 'Actualización Futura' : 'Ya Disponible');
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
                    if (!start) return { valid: false, msg: 'Debes especificar la fecha y hora de inicio.' };
                    if (!duration || duration <= 0) return { valid: false, msg: 'La duración debe ser mayor a 0 minutos.' };
                } else {
                    const cutoff = document.getElementById('maint-emergency-time').value;
                    if (!cutoff) return { valid: false, msg: 'Debes especificar la hora del corte inminente.' };
                }
            }
            if (selectedMainType === 'policy') {
                if (selectedPolicyStatus === 'future') {
                    const date = document.getElementById('policy-effective-date').value;
                    if (!date) return { valid: false, msg: 'Debes indicar la fecha de entrada en vigor.' };
                }
            }
            return { valid: true };
        };

        // --- LÓGICA DE EMISIÓN ---
        const executeEmission = async () => {
            Dialog.showLoading('Emitiendo alerta...');

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
                    Toast.show('Difusión emitida correctamente', 'success');
                    checkActiveAlertStatus();
                } else {
                    Toast.show(res.message || 'Error al emitir', 'error');
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
                    Dialog.alert({ title: 'Datos incompletos', message: validation.msg });
                    return;
                }

                if (isAlertActive) {
                    const confirmed = await Dialog.confirm({
                        title: 'Alerta en curso detectada',
                        message: 'Ya existe una alerta transmitiéndose. ¿Deseas reemplazarla?',
                        confirmText: 'Reemplazar',
                        cancelText: 'Cancelar'
                    });
                    if (confirmed) await executeEmission();
                } else {
                    await executeEmission();
                }
            };
        }

        const handleDeactivate = async () => {
            const confirmed = await Dialog.confirm({
                title: 'Desactivar Difusión',
                message: '¿Estás seguro de que deseas detener la alerta actual?',
                type: 'danger',
                confirmText: 'Detener',
                cancelText: 'Cancelar'
            });

            if (confirmed) {
                try {
                    const res = await ApiService.post(ApiService.Routes.Admin.DeactivateSystemAlert);
                    if (res.success) {
                        Toast.show('Sistema normalizado', 'success');
                        checkActiveAlertStatus();
                    }
                } catch (e) { console.error(e); }
            }
        };

        if (btnDeactivateMini) btnDeactivateMini.onclick = handleDeactivate;

        if (btnRefresh) btnRefresh.onclick = () => {
             checkActiveAlertStatus();
             Toast.show('Métricas actualizadas', 'info');
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
                statText.textContent = "Sincronizando...";
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
                    statText.textContent = "Activa";
                    statText.style.color = color;
                    cardStatus.style.borderLeftColor = color;
                    btnMini.style.display = 'flex';
                    impactVal.textContent = res.alert.severity === 'critical' ? 'Crítico' : 'Moderado';
                    impactIcon.style.color = color;
                    impactTime.textContent = res.alert.type.toUpperCase(); 
                } else {
                    isAlertActive = false;
                    statIcon.textContent = 'check_circle';
                    statIcon.style.color = 'var(--color-success)';
                    statText.textContent = "Operativo";
                    statText.style.color = 'var(--text-primary)';
                    cardStatus.style.borderLeftColor = 'var(--color-success)';
                    btnMini.style.display = 'none';
                    impactVal.textContent = "Normal";
                    impactIcon.style.color = 'var(--text-tertiary)';
                    if (res.stats && res.stats.last_alert_time) {
                        impactTime.textContent = "Última: " + res.stats.last_alert_time;
                    } else {
                        impactTime.textContent = "Sin incidentes recientes";
                    }
                }
            } catch (e) { console.error(e); }
        }
    }
};