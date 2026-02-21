// public/assets/js/controllers/admin-manage-status-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class AdminManageStatusController {
    constructor() {
        this.init();
    }

    init() {
        // Escuchar cambios en los inputs/selects (Delegación de eventos)
        document.body.addEventListener('change', (e) => {
            const view = document.getElementById('admin-manage-status-view');
            if (!view) return;

            // 1. Cambio en "Estado de Existencia" (Activa / Eliminada)
            if (e.target.id === 'select-lifecycle-status') {
                this.handleLifecycleChange(e.target.value);
            }

            // 2. Cambio en el Toggle "Suspensión de Acceso"
            if (e.target.id === 'toggle-is-suspended') {
                this.handleSuspensionToggle(e.target.checked);
            }

            // 3. Cambio en el "Tipo de Suspensión" (Temporal / Permanente)
            if (e.target.id === 'select-suspension-type') {
                this.handleSuspensionTypeChange(e.target.value);
            }
        });

        // Escuchar el click en el botón de "Guardar Cambios"
        document.body.addEventListener('click', (e) => {
            const btnSave = e.target.closest('#btn-save-status-changes');
            if (btnSave && document.getElementById('admin-manage-status-view')) {
                e.preventDefault();
                this.saveStatusChanges(btnSave);
            }
        });
    }

    // ==========================================
    // LÓGICA DE UI EN CASCADA
    // ==========================================

    handleLifecycleChange(status) {
        const deletionData = document.getElementById('cascade-deletion-data');
        const suspensionCard = document.getElementById('card-suspension-control');

        if (status === 'deleted') {
            // Si se elimina, mostramos por qué se elimina y bloqueamos la tarjeta de suspensión
            if (deletionData) deletionData.classList.remove('disabled');
            if (suspensionCard) suspensionCard.classList.add('disabled');
        } else {
            // Si está activa, ocultamos los datos de eliminación y habilitamos la suspensión
            if (deletionData) deletionData.classList.add('disabled');
            if (suspensionCard) suspensionCard.classList.remove('disabled');
        }
    }

    handleSuspensionToggle(isSuspended) {
        const suspensionData = document.getElementById('cascade-suspension-data');
        if (isSuspended) {
            // Si se suspende, se despliegan las opciones extra
            if (suspensionData) suspensionData.classList.remove('disabled');
        } else {
            if (suspensionData) suspensionData.classList.add('disabled');
        }
    }

    handleSuspensionTypeChange(type) {
        const dateSection = document.getElementById('cascade-suspension-date');
        if (type === 'temporal') {
            // Si es temporal, mostrar el selector de fecha
            if (dateSection) dateSection.style.display = 'flex';
        } else {
            if (dateSection) dateSection.style.display = 'none';
        }
    }

    // ==========================================
    // ENVÍO DE DATOS AL BACKEND
    // ==========================================

    async saveStatusChanges(btn) {
        const targetUuid = document.getElementById('admin-target-uuid') ? document.getElementById('admin-target-uuid').value : null;
        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : null;

        if (!targetUuid) return;

        // Recolectar el estado actual de los inputs
        const status = document.getElementById('select-lifecycle-status')?.value || 'active';
        const isSuspended = document.getElementById('toggle-is-suspended')?.checked ? 1 : 0;
        
        const suspensionType = document.getElementById('select-suspension-type')?.value;
        const suspensionDate = document.getElementById('input-suspension-date')?.value;
        const suspensionReason = document.getElementById('input-suspension-reason')?.value;
        
        const deletionType = document.getElementById('select-deletion-type')?.value;
        const deletionReason = document.getElementById('input-deletion-reason')?.value;

        // Validación Frontend Simple
        if (status === 'active' && isSuspended === 1 && suspensionType === 'temporal') {
            if (!suspensionDate) {
                Toast.show('Debes especificar la fecha y hora de expiración para la suspensión temporal.', 'error');
                return;
            }
        }

        // Armar el payload a enviar
        const payload = {
            target_uuid: targetUuid,
            csrf_token: csrfToken,
            status: status,
            is_suspended: isSuspended,
            suspension_type: suspensionType,
            suspension_expires_at: suspensionDate,
            suspension_reason: suspensionReason,
            deletion_type: deletionType,
            deletion_reason: deletionReason
        };

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.ADMIN.UPDATE_STATUS, payload);
            
            if (res.success) {
                Toast.show(res.message, 'success');
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error interno de red al actualizar el estado de la cuenta.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }
}