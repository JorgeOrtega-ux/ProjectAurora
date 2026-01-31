/**
 * public/assets/js/modules/admin/alert-controller.js
 */

export const AlertController = {
    init: () => {
        const btnOpen = document.getElementById('btn-open-alert-modal');
        const modal = document.getElementById('modal-system-alert');
        const typeSelector = document.getElementById('alert-type-selector');
        const btnEmit = document.getElementById('btn-emit-alert');
        const btnDeactivate = document.getElementById('btn-deactivate-alert');

        // Si no existen los elementos (ej. no estamos en dashboard), salir
        if (!btnOpen || !modal) return;

        // Limpiar listeners previos (clonando el nodo) para evitar duplicados al navegar
        // O simplemente asumimos que al cambiar de vista el DOM se destruye.
        // Aquí usaremos la asignación directa onclick para simplificar el manejo de eventos en SPA
        // o addEventListener asegurándonos de que es una inicialización fresca.

        // --- HANDLERS ---

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

        // Emitir
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
                    formData.append('action', 'create_system_alert');
                    formData.append('alert_data', JSON.stringify(payload));
                    formData.append('csrf_token', window.CSRF_TOKEN || '');

                    // Usando ApiService si existe, o fetch nativo
                    const response = await fetch('api/handlers/admin-handler.php', { method: 'POST', body: formData });
                    const res = await response.json();

                    if (res.success) {
                        // Usar tu Toast Manager si está disponible
                        if (window.Toast) window.Toast.show('Alerta emitida correctamente', 'success');
                        else alert('Alerta emitida');
                        modal.style.display = 'none';
                    } else {
                        alert('Error: ' + res.message);
                    }
                } catch (e) { console.error(e); }
            };
        }

        // Desactivar
        if (btnDeactivate) {
            btnDeactivate.onclick = async () => {
                if (!confirm('¿Seguro que quieres quitar la alerta actual?')) return;
                const formData = new FormData();
                formData.append('action', 'deactivate_system_alert');
                formData.append('csrf_token', window.CSRF_TOKEN || '');

                await fetch('api/handlers/admin-handler.php', { method: 'POST', body: formData });
                modal.style.display = 'none';
                if (window.Toast) window.Toast.show('Alerta desactivada', 'success');
            };
        }

        // Helpers
        async function checkActiveAlertStatus() {
            const formData = new FormData();
            formData.append('action', 'get_active_alert');
            formData.append('csrf_token', window.CSRF_TOKEN || '');

            try {
                const res = await (await fetch('api/handlers/admin-handler.php', { method: 'POST', body: formData })).json();
                if (res.success && res.alert) {
                    btnDeactivate.style.display = 'block';
                    btnEmit.textContent = 'Sobrescribir Alerta';
                    btnEmit.classList.add('warning'); // Opcional: cambiar estilo
                } else {
                    btnDeactivate.style.display = 'none';
                    btnEmit.textContent = 'Emitir Alerta';
                    btnEmit.classList.remove('warning');
                }
            } catch (e) { console.error(e); }
        }
    }
};