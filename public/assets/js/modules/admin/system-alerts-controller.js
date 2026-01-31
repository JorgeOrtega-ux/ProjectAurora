/**
 * public/assets/js/modules/admin/system-alerts-controller.js
 */
import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

export const SystemAlertsController = {
    init: () => {
        const btnEmit = document.getElementById('btn-emit-alert');
        const btnDeactivate = document.getElementById('btn-deactivate-alert');
        const btnRefresh = document.querySelector('[data-action="refresh-status"]');

        // Referencias a los valores seleccionados
        let selectedMainType = 'performance';
        let selectedMaintType = 'scheduled';
        let selectedPolicyDoc = 'terms';

        // Mapas de Configuración Visual para los Triggers
        const configMainType = {
            'performance': { icon: 'speed', text: 'Rendimiento' },
            'maintenance': { icon: 'build', text: 'Mantenimiento' },
            'policy':      { icon: 'policy', text: 'Políticas' }
        };

        const configMaintType = {
            'scheduled': { icon: 'event', text: 'Programado' },
            'emergency': { icon: 'warning', text: 'Emergencia' }
        };

        const configPolicyDoc = {
            'terms':   { text: 'Términos y Condiciones' },
            'privacy': { text: 'Política de Privacidad' },
            'cookies': { text: 'Política de Cookies' }
        };

        // 1. INICIALIZAR TRIGGERS
        // Helper para configurar triggers
        const setupTrigger = (triggerId, popoverId, actionName, onSelectCallback) => {
            const trigger = document.getElementById(triggerId);
            const popover = document.getElementById(popoverId);
            if (!trigger || !popover) return;

            trigger.onclick = (e) => {
                e.stopPropagation();
                // Cerrar otros popovers abiertos
                document.querySelectorAll('.popover-module').forEach(p => {
                    if (p !== popover) p.classList.remove('active');
                });
                document.querySelectorAll('.trigger-selector').forEach(t => {
                    if (t !== trigger) t.classList.remove('active');
                });
                
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
                };
            });
        };

        // Trigger 1: Tipo Principal
        setupTrigger('trigger-alert-type', 'popover-alert-type', 'select-main-type', (val, triggerEl) => {
            selectedMainType = val;
            // Actualizar UI Trigger
            document.getElementById('text-alert-type').textContent = configMainType[val].text;
            document.getElementById('icon-alert-type').textContent = configMainType[val].icon;
            
            // Mostrar/Ocultar Grupos
            document.querySelectorAll('.config-group').forEach(el => el.style.display = 'none');
            const group = document.getElementById(`group-${val}`);
            if (group) group.style.display = 'block';
        });

        // Trigger 2: Tipo Mantenimiento
        setupTrigger('trigger-maint-type', 'popover-maint-type', 'select-maint-type', (val, triggerEl) => {
            selectedMaintType = val;
            // Actualizar UI Trigger
            document.getElementById('text-maint-type').textContent = configMaintType[val].text;
            document.getElementById('icon-maint-type').textContent = configMaintType[val].icon;

            // Mostrar/Ocultar Subgrupos
            document.getElementById('subgroup-maint-scheduled').style.display = (val === 'scheduled') ? 'block' : 'none';
            document.getElementById('subgroup-maint-emergency').style.display = (val === 'emergency') ? 'block' : 'none';
        });

        // Trigger 3: Documento de Política
        setupTrigger('trigger-policy-doc', 'popover-policy-doc', 'select-policy-doc', (val, triggerEl) => {
            selectedPolicyDoc = val;
            document.getElementById('text-policy-doc').textContent = configPolicyDoc[val].text;
        });

        // Clic global para cerrar popovers
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.trigger-select-wrapper')) {
                document.querySelectorAll('.popover-module.active').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.trigger-selector.active').forEach(el => el.classList.remove('active'));
            }
        });

        // 2. LOGICA DE ACCIONES
        if (btnEmit) {
            btnEmit.onclick = async () => {
                let payload = { type: selectedMainType, message: '', meta: {} };

                if (selectedMainType === 'performance') {
                    payload.message = document.getElementById('perf-message').value;
                }
                else if (selectedMainType === 'maintenance') {
                    payload.meta.subtype = selectedMaintType;
                    if (selectedMaintType === 'scheduled') {
                        payload.message = "Mantenimiento Programado";
                        payload.meta.start = document.getElementById('maint-start-time').value;
                        payload.meta.duration = document.getElementById('maint-duration').value;
                    } else {
                        payload.message = "Mantenimiento de Emergencia";
                        payload.meta.cutoff = document.getElementById('maint-emergency-time').value;
                    }
                }
                else if (selectedMainType === 'policy') {
                    payload.message = "Actualización de Políticas";
                    payload.meta.status = 'future';
                    payload.meta.doc = selectedPolicyDoc;
                    payload.meta.link = document.getElementById('policy-link').value;
                    payload.meta.date = document.getElementById('policy-effective-date').value;
                }

                try {
                    const formData = new FormData();
                    formData.append('alert_data', JSON.stringify(payload));

                    const res = await ApiService.post(ApiService.Routes.Admin.CreateSystemAlert, formData);

                    if (res.success) {
                        Toast.show('Alerta emitida globalmente', 'success');
                        checkActiveAlertStatus();
                    } else {
                        Toast.show(res.message || 'Error', 'error');
                    }
                } catch (e) { console.error(e); }
            };
        }

        if (btnDeactivate) {
            btnDeactivate.onclick = async () => {
                if (!confirm('¿Detener alerta actual?')) return;
                try {
                    const res = await ApiService.post(ApiService.Routes.Admin.DeactivateSystemAlert);
                    if (res.success) {
                        Toast.show('Alerta desactivada', 'success');
                        checkActiveAlertStatus();
                    }
                } catch (e) { console.error(e); }
            };
        }

        if (btnRefresh) btnRefresh.onclick = checkActiveAlertStatus;

        // Init Check
        checkActiveAlertStatus();

        // 3. ESTADO DEL SISTEMA
        async function checkActiveAlertStatus() {
            const iconEl = document.getElementById('status-icon');
            const textEl = document.getElementById('status-text');
            const iconContainer = iconEl.parentElement;

            try {
                textEl.textContent = "Verificando...";
                const res = await ApiService.post(ApiService.Routes.Admin.GetActiveAlert);
                
                if (res.success && res.alert) {
                    btnDeactivate.style.display = 'flex';
                    const color = res.alert.severity === 'critical' ? 'var(--color-error)' : 'var(--color-warning)';
                    
                    iconEl.textContent = 'warning';
                    iconEl.style.color = color;
                    iconContainer.style.borderColor = color;
                    textEl.innerHTML = `<strong style="color:${color}">Activa:</strong> ${res.alert.type}`;
                } else {
                    btnDeactivate.style.display = 'none';
                    iconEl.textContent = 'check_circle';
                    iconEl.style.color = 'var(--color-success)';
                    iconContainer.style.borderColor = 'var(--border-transparent-20)';
                    textEl.textContent = "Sistema Normal";
                }
            } catch (e) { console.error(e); }
        }
    }
};