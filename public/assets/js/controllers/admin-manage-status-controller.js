// public/assets/js/controllers/admin-manage-status-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class AdminManageStatusController {
    constructor() {
        this.init();
    }

    init() {
        // Escuchar clics (Delegación de eventos global)
        document.body.addEventListener('click', (e) => {
            const view = document.getElementById('admin-manage-status-view');
            if (!view) return;

            // --- SOLUCIÓN: MANEJO DE DROPDOWNS (TOGGLE) ---
            const adminDropdownTrigger = e.target.closest('[data-action="admin-toggle-dropdown"]');
            if (adminDropdownTrigger) {
                e.preventDefault();
                e.stopPropagation(); 
                
                const wrapper = adminDropdownTrigger.closest('.component-dropdown');
                const module = wrapper.querySelector('.component-module');
                
                // Cerrar otros dropdowns abiertos en la pantalla
                document.querySelectorAll('.component-dropdown .component-module:not(.disabled)').forEach(m => {
                    if (m !== module) m.classList.add('disabled');
                });
                
                // Abrir / Cerrar el que recibió el click
                if (module) module.classList.toggle('disabled');
                return;
            }

            // --- MANEJO DE SELECCIÓN DE OPCIONES ---
            const optionSelect = e.target.closest('[data-action="status-select-option"]');
            if (optionSelect) {
                e.preventDefault();
                this.handleOptionSelect(optionSelect);
                return;
            }

            // --- Click en guardar ---
            const btnSave = e.target.closest('#btn-save-status-changes');
            if (btnSave) {
                e.preventDefault();
                this.saveStatusChanges(btnSave);
            }
        });

        // Escuchar cambios (para el Switch de Activar/Desactivar Suspensión)
        document.body.addEventListener('change', (e) => {
            const view = document.getElementById('admin-manage-status-view');
            if (!view) return;

            if (e.target.id === 'toggle-is-suspended') {
                this.handleSuspensionToggle(e.target.checked);
            }
        });
    }

    // ==========================================
    // LÓGICA DE UI Y DROPDOWNS
    // ==========================================

    handleOptionSelect(option) {
        const wrapper = option.closest('.component-dropdown');
        const module = wrapper.querySelector('.component-module');
        
        // 1. Actualizar el texto del trigger
        const textDisplay = wrapper.querySelector('.component-dropdown-text');
        textDisplay.textContent = option.dataset.label;
        
        // 2. Actualizar el ícono del trigger
        const iconDisplay = wrapper.querySelector('.trigger-select-icon');
        const optionIcon = option.querySelector('.component-menu-link-icon span');
        if (iconDisplay && optionIcon) {
            iconDisplay.textContent = optionIcon.textContent;
        }
        
        // 3. Actualizar estados visuales de la lista
        module.querySelectorAll('.component-menu-link').forEach(link => link.classList.remove('active'));
        option.classList.add('active');
        
        // 4. Asignar valor final al componente padre (wrapper)
        const value = option.dataset.value;
        wrapper.dataset.value = value;
        
        // 5. Cerrar módulo
        module.classList.add('disabled');

        // 6. Disparar lógica en cascada según el tipo de campo
        const target = option.dataset.target;
        if (target === 'lifecycle') {
            this.handleLifecycleChange(value);
        } else if (target === 'suspension-type') {
            this.handleSuspensionTypeChange(value);
        }
    }

    handleLifecycleChange(status) {
        const deletionData = document.getElementById('cascade-deletion-data');
        const suspensionCard = document.getElementById('card-suspension-control');

        if (status === 'deleted') {
            if (deletionData) deletionData.classList.replace('disabled', 'active');
            if (suspensionCard) suspensionCard.classList.replace('active', 'disabled');
        } else {
            if (deletionData) deletionData.classList.replace('active', 'disabled');
            if (suspensionCard) suspensionCard.classList.replace('disabled', 'active');
        }
    }

    handleSuspensionToggle(isSuspended) {
        const suspensionData = document.getElementById('cascade-suspension-data');
        if (isSuspended) {
            if (suspensionData) suspensionData.classList.replace('disabled', 'active');
        } else {
            if (suspensionData) suspensionData.classList.replace('active', 'disabled');
        }
    }

    handleSuspensionTypeChange(type) {
        const dateSection = document.getElementById('cascade-suspension-date');
        if (!dateSection) return;

        if (type === 'temporal') {
            dateSection.classList.replace('disabled', 'active');
        } else {
            dateSection.classList.replace('active', 'disabled');
        }
    }

    // ==========================================
    // ENVÍO DE DATOS AL BACKEND
    // ==========================================

    async saveStatusChanges(btn) {
        const targetUuid = document.getElementById('admin-target-uuid') ? document.getElementById('admin-target-uuid').value : null;
        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : null;

        if (!targetUuid) return;

        // Leer valores directamente desde la estructura de los nuevos Dropdowns
        const status = document.getElementById('dropdown-lifecycle-status')?.dataset.value || 'active';
        const isSuspended = document.getElementById('toggle-is-suspended')?.checked ? 1 : 0;
        
        const suspensionType = document.getElementById('dropdown-suspension-type')?.dataset.value;
        const suspensionDate = document.getElementById('input-suspension-date')?.value;
        const suspensionReason = document.getElementById('input-suspension-reason')?.value;
        
        const deletionType = document.getElementById('dropdown-deletion-type')?.dataset.value;
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