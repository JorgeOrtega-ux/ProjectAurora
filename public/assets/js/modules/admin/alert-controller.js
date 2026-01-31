/**
 * public/assets/js/modules/admin/alert-controller.js
 */
import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js'; // Asegúrate de importar Toast si lo usas

export const AlertController = {
    init: () => {
        const btnOpen = document.getElementById('btn-open-alert-modal');
        const modal = document.getElementById('modal-system-alert');
        const typeSelector = document.getElementById('alert-type-selector');
        const btnEmit = document.getElementById('btn-emit-alert');
        const btnDeactivate = document.getElementById('btn-deactivate-alert');

        if (!btnOpen || !modal) return;

        // Abrir Modal
        btnOpen.onclick = () => {
            modal.style.display = 'flex';
            checkActiveAlertStatus();
        };

        // Cerrar Modal
        modal.onclick = (e) => {
            if (e.target.closest('[data-action="close-modal"]')) {
                modal.style.display = 'none';
            }
        };

        // Tabs
        if (typeSelector) {
            typeSelector.onchange = (e) => {
                document.querySelectorAll('.alert-config-block').forEach(el => el.style.display = 'none');
                const block = document.getElementById(`block-${e.target.value}`);
                if (block) block.style.display = 'block';
            };
        }

        // Radios Mantenimiento
        document.querySelectorAll('input[name="maint_type"]').forEach(radio => {
            radio.onchange = (e) => {
                const isScheduled = e.target.value === 'scheduled';
                document.getElementById('maint-scheduled-options').style.display = isScheduled ? 'block' : 'none';
                document.getElementById('maint-emergency-options').style.display = isScheduled ? 'none' : 'block';
            };
        });

        // Radios Políticas
        document.querySelectorAll('input[name="policy_status"]').forEach(radio => {
            radio.onchange = (e) => {
                document.getElementById('policy-future-options').style.display =
                    (e.target.value === 'future') ? 'block' : 'none';
            };
        });

        // Emitir Alerta
        if (btnEmit) {
            btnEmit.onclick = async () => {
                const type = typeSelector.value;
                let payload = { type: type, message: '', meta: {} };

                if (type === 'performance') {
                    payload.message = document.getElementById('perf-message').value;
                }
                else if (type === 'maintenance') {
                    const subType = document.querySelector('input[name="maint_type"]:checked').value;
                    payload.meta.subtype = subType;
                    if (subType === 'scheduled') {
                        payload.message = "Mantenimiento Programado";
                        payload.meta.start = document.getElementById('maint-start-time').value;
                        payload.meta.duration = document.getElementById('maint-duration').value;
                    } else {
                        payload.message = "Mantenimiento de Emergencia";
                        payload.meta.cutoff = document.getElementById('maint-emergency-time').value;
                    }
                }
                else if (type === 'policy') {
                    const status = document.querySelector('input[name="policy_status"]:checked').value;
                    payload.message = "Actualización de Políticas";
                    payload.meta.status = status;
                    payload.meta.doc = document.getElementById('policy-doc-type').value;
                    payload.meta.link = document.getElementById('policy-link').value;
                    if (status === 'future') {
                        payload.meta.date = document.getElementById('policy-effective-date').value;
                    }
                }

                try {
                    const formData = new FormData();
                    // ApiService inyecta 'csrf_token' y 'route' automáticamente, 
                    // solo necesitamos los datos del payload.
                    formData.append('alert_data', JSON.stringify(payload));
                    // 'action' ya no es necesario aquí, se define en ApiRoutes

                    // CORRECCIÓN: Usar ApiService
                    const res = await ApiService.post(ApiService.Routes.Admin.CreateSystemAlert, formData);

                    if (res.success) {
                        Toast.show('Alerta emitida correctamente', 'success');
                        modal.style.display = 'none';
                    } else {
                        Toast.show(res.message || 'Error al emitir alerta', 'error');
                    }
                } catch (e) { console.error(e); }
            };
        }

        // Desactivar Alerta
        if (btnDeactivate) {
            btnDeactivate.onclick = async () => {
                if (!confirm('¿Seguro que quieres quitar la alerta actual?')) return;
                
                // CORRECCIÓN: Usar ApiService
                const res = await ApiService.post(ApiService.Routes.Admin.DeactivateSystemAlert);
                
                if (res.success) {
                    Toast.show('Alerta desactivada', 'success');
                    modal.style.display = 'none';
                } else {
                    Toast.show('Error al desactivar', 'error');
                }
            };
        }

        // Verificar estado activo
        async function checkActiveAlertStatus() {
            try {
                // CORRECCIÓN: Usar ApiService
                const res = await ApiService.post(ApiService.Routes.Admin.GetActiveAlert);
                
                if (res.success && res.alert) {
                    btnDeactivate.style.display = 'block';
                    btnEmit.textContent = 'Sobrescribir Alerta';
                    btnEmit.classList.add('warning');
                } else {
                    btnDeactivate.style.display = 'none';
                    btnEmit.textContent = 'Emitir Alerta';
                    btnEmit.classList.remove('warning');
                }
            } catch (e) { console.error(e); }
        }
    }
};