/**
 * public/assets/js/modules/admin/system-alerts-controller.js
 */
import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

export const SystemAlertsController = {
    init: () => {
        const btnEmit = document.getElementById('btn-emit-alert');
        const btnDeactivateMini = document.getElementById('btn-deactivate-alert-mini'); // Botón en la card
        const btnRefresh = document.querySelector('[data-action="refresh-status"]');

        // Referencias de estado
        let selectedMainType = 'performance';
        let selectedPerfMsgType = 'degradation';
        let selectedMaintType = 'scheduled';
        let selectedPolicyDoc = 'terms';
        let selectedPolicyStatus = 'future';

        // --- CONFIGURACIÓN DE UI ---
        const configMainType = {
            'performance': { icon: 'speed', text: 'Rendimiento' },
            'maintenance': { icon: 'build', text: 'Mantenimiento' },
            'policy':      { icon: 'policy', text: 'Políticas y Legal' }
        };

        const configPerfMsg = {
            'degradation': { icon: 'troubleshoot', text: 'Degradación de Servicio', message: 'Estamos experimentando lentitud en algunos servicios. Trabajamos en ello.' },
            'latency':     { icon: 'network_check', text: 'Latencia Alta Detectada', message: 'Se ha detectado una latencia alta en la conexión. Su experiencia podría verse afectada.' },
            'overload':    { icon: 'memory', text: 'Sobrecarga Temporal',     message: 'El sistema presenta una carga inusual. Algunas funciones podrían no responder.' }
        };

        const configMaintType = {
            'scheduled': { icon: 'event', text: 'Programado' },
            'emergency': { icon: 'warning', text: 'Emergencia' }
        };

        const configPolicyDoc = {
            'terms':   { icon: 'description', text: 'Términos y Condiciones' },
            'privacy': { icon: 'lock', text: 'Política de Privacidad' },
            'cookies': { icon: 'cookie', text: 'Política de Cookies' }
        };

        const configPolicyStatus = {
            'future':    { text: 'Actualización Futura', icon: 'calendar_month' },
            'immediate': { text: 'Ya Disponible',        icon: 'publish' }
        };

        // --- FUNCIONES DE PREVIEW DINÁMICO ---
        const updatePreview = () => {
            const iconEl = document.getElementById('preview-icon');
            const titleEl = document.getElementById('preview-title');
            const msgEl = document.getElementById('preview-message');
            const textContainer = document.getElementById('preview-text-container');
            
            // 1. Icono base
            iconEl.textContent = configMainType[selectedMainType].icon;
            
            let metaHtml = '';

            // 2. Lógica por tipo
            if (selectedMainType === 'performance') {
                const conf = configPerfMsg[selectedPerfMsgType];
                titleEl.textContent = conf.text;
                msgEl.textContent = conf.message;
                metaHtml = ''; // Sin meta
                iconEl.textContent = conf.icon;
            
            } else if (selectedMainType === 'maintenance') {
                if (selectedMaintType === 'scheduled') {
                    titleEl.textContent = "Mantenimiento Programado";
                    const startDate = document.getElementById('maint-start-time').value || '--/-- --:--';
                    const duration = document.getElementById('maint-duration').value || '60';
                    
                    let dateStr = startDate;
                    if (startDate !== '--/-- --:--') {
                        const d = new Date(startDate);
                        dateStr = d.toLocaleString();
                    }

                    msgEl.textContent = `Nuestros servicios no estarán disponibles a partir del ${dateStr} por aprox. ${duration} min.`;
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">timer</span> ${duration} min`;
                } else {
                    titleEl.textContent = "Mantenimiento de Emergencia";
                    const time = document.getElementById('maint-emergency-time').value || '--:--';
                    msgEl.textContent = `Atención: Se realizará un corte de servicio inminente a las ${time}. Guarde su trabajo.`;
                    metaHtml = `<span class="material-symbols-rounded" style="font-size:14px; color:var(--color-error)">warning</span> Urgente`;
                    iconEl.textContent = 'warning';
                }

            } else if (selectedMainType === 'policy') {
                const docName = configPolicyDoc[selectedPolicyDoc].text;
                const link = document.getElementById('policy-link').value || '#';
                
                titleEl.textContent = "Actualización Legal";
                iconEl.textContent = 'gavel';

                if (selectedPolicyStatus === 'future') {
                    const date = document.getElementById('policy-effective-date').value || '--/--/----';
                    msgEl.innerHTML = `Nuestros <b>${docName}</b> se actualizarán el día ${date}.`;
                } else {
                    msgEl.innerHTML = `Hemos actualizado nuestros <b>${docName}</b>.`;
                }
                
                metaHtml = `<span class="material-symbols-rounded" style="font-size:14px">link</span> <span style="text-decoration:underline">${link}</span>`;
            }

            // 3. Manejo dinámico del elemento META
            let metaEl = document.getElementById('preview-meta');

            if (metaHtml) {
                // Si necesitamos meta y no existe, lo creamos
                if (!metaEl) {
                    metaEl = document.createElement('div');
                    metaEl.id = 'preview-meta';
                    // Estilos dinámicos solicitados
                    metaEl.style.cssText = "display: flex; gap: 8px; margin-top: 4px; font-size: 12px; color: var(--text-tertiary);";
                    textContainer.appendChild(metaEl);
                }
                metaEl.innerHTML = metaHtml;
            } else {
                // Si no necesitamos meta y existe, lo eliminamos
                if (metaEl) {
                    metaEl.remove();
                }
            }
        };

        // Listeners para inputs de texto/fecha
        ['maint-start-time', 'maint-duration', 'maint-emergency-time', 'policy-link', 'policy-effective-date'].forEach(id => {
            const el = document.getElementById(id);
            if(el) el.addEventListener('input', updatePreview);
        });

        // --- MANEJO SEGURO DE STEPPER ---
        const btnDurDec = document.getElementById('btn-duration-dec');
        const btnDurInc = document.getElementById('btn-duration-inc');
        const inputDur = document.getElementById('maint-duration');

        if (btnDurDec && inputDur) {
            btnDurDec.addEventListener('click', () => {
                inputDur.stepDown(15);
                updatePreview(); 
            });
        }

        if (btnDurInc && inputDur) {
            btnDurInc.addEventListener('click', () => {
                inputDur.stepUp(15);
                updatePreview(); 
            });
        }


        // --- TRIGGERS ---
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
                    const value = opt.dataset.value;
                    onSelectCallback(value, trigger);
                    popover.classList.remove('active');
                    trigger.classList.remove('active');
                    updatePreview();
                };
            });
        };

        // Inicializar Triggers
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
            document.getElementById('text-maint-type').textContent = configMaintType[val].text;
            document.getElementById('icon-maint-type').textContent = configMaintType[val].icon;
            document.getElementById('subgroup-maint-scheduled').style.display = (val === 'scheduled') ? 'block' : 'none';
            document.getElementById('subgroup-maint-emergency').style.display = (val === 'emergency') ? 'block' : 'none';
        });

        setupTrigger('trigger-policy-doc', 'popover-policy-doc', 'select-policy-doc', (val) => {
            selectedPolicyDoc = val;
            document.getElementById('text-policy-doc').textContent = configPolicyDoc[val].text;
            document.getElementById('icon-policy-doc').textContent = configPolicyDoc[val].icon;
        });

        setupTrigger('trigger-policy-status', 'popover-policy-status', 'select-policy-status', (val) => {
            selectedPolicyStatus = val;
            document.getElementById('text-policy-status').textContent = configPolicyStatus[val].text;
            document.getElementById('icon-policy-status').textContent = configPolicyStatus[val].icon;
            const dateGroup = document.getElementById('subgroup-policy-date');
            dateGroup.style.display = (val === 'immediate') ? 'none' : 'block';
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.trigger-select-wrapper')) {
                document.querySelectorAll('.popover-module.active').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.trigger-selector.active').forEach(el => el.classList.remove('active'));
            }
        });

        // Init inicial
        updatePreview();

        // --- LÓGICA DE EMISIÓN ---
        if (btnEmit) {
            btnEmit.onclick = async () => {
                let payload = { type: selectedMainType, message: '', meta: {} };

                switch (selectedMainType) {
                    case 'performance':
                        payload.message = configPerfMsg[selectedPerfMsgType].message;
                        payload.meta.code = selectedPerfMsgType;
                        break;
                    case 'maintenance':
                        payload.meta.subtype = selectedMaintType;
                        if (selectedMaintType === 'scheduled') {
                            payload.message = "Mantenimiento Programado";
                            payload.meta.start = document.getElementById('maint-start-time').value;
                            payload.meta.duration = document.getElementById('maint-duration').value;
                        } else {
                            payload.message = "Mantenimiento de Emergencia";
                            payload.meta.cutoff = document.getElementById('maint-emergency-time').value;
                        }
                        break;
                    case 'policy':
                        payload.message = "Actualización Legal";
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
                    if (res.success) {
                        Toast.show('Difusión emitida correctamente', 'success');
                        checkActiveAlertStatus();
                    } else {
                        Toast.show(res.message || 'Error al emitir', 'error');
                    }
                } catch (e) { console.error(e); }
            };
        }

        // --- DESACTIVACIÓN ---
        const handleDeactivate = async () => {
            if (!confirm('¿Detener alerta/difusión actual?')) return;
            try {
                const res = await ApiService.post(ApiService.Routes.Admin.DeactivateSystemAlert);
                if (res.success) {
                    Toast.show('Sistema normalizado', 'success');
                    checkActiveAlertStatus();
                }
            } catch (e) { console.error(e); }
        };

        if (btnDeactivateMini) btnDeactivateMini.onclick = handleDeactivate;

        if (btnRefresh) btnRefresh.onclick = () => {
             checkActiveAlertStatus();
             Toast.show('Métricas actualizadas', 'info');
        };

        checkActiveAlertStatus();

        async function checkActiveAlertStatus() {
            // Referencias Cards Dashboard
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
                
                // 1. Actualizar Stats (Siempre disponibles)
                if (res.stats) {
                    statOnline.textContent = res.stats.online_users;
                    statToday.textContent = res.stats.alerts_today;
                    badgeTotal.innerHTML = `<span class="material-symbols-rounded" style="font-size:14px;">history</span> Total: ${res.stats.alerts_total}`;
                }

                // 2. Actualizar Estado de Alerta
                if (res.success && res.alert) {
                    // Hay alerta activa
                    const color = res.alert.severity === 'critical' ? 'var(--color-error)' : 'var(--color-warning)';
                    
                    // Card Estado
                    statIcon.textContent = 'warning';
                    statIcon.style.color = color;
                    statText.textContent = "Activa";
                    statText.style.color = color;
                    cardStatus.style.borderLeftColor = color;
                    btnMini.style.display = 'flex';

                    // Card Impacto
                    impactVal.textContent = res.alert.severity === 'critical' ? 'Crítico' : 'Moderado';
                    impactIcon.style.color = color;
                    impactTime.textContent = res.alert.type.toUpperCase(); // Mostrar tipo en vez de tiempo
                    
                } else {
                    // Sistema Normal
                    statIcon.textContent = 'check_circle';
                    statIcon.style.color = 'var(--color-success)';
                    statText.textContent = "Operativo";
                    statText.style.color = 'var(--text-primary)';
                    cardStatus.style.borderLeftColor = 'var(--color-success)';
                    btnMini.style.display = 'none';

                    // Impacto Normal
                    impactVal.textContent = "Normal";
                    impactIcon.style.color = 'var(--text-tertiary)';
                    
                    // Mostrar tiempo desde última alerta si existe
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