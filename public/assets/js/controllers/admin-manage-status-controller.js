// public/assets/js/controllers/admin-manage-status-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class AdminManageStatusController {
    constructor() {
        // Reglas de tiempo para sanciones automáticas (en días)
        this.SUSPENSION_RULES = {
            'cat_1': 1,  // Spam
            'cat_2': 3,  // Lenguaje inapropiado
            'cat_3': 7,  // Comportamiento tóxico
            'cat_4': 14, // Evasión
            'cat_5': 30  // Fraude
        };

        this.init();
    }

    init() {
        // Escuchar clics (Delegación de eventos global)
        document.body.addEventListener('click', (e) => {
            const view = document.getElementById('admin-manage-status-view');
            if (!view) return;

            // --- MANEJO DE DROPDOWNS (TOGGLE) ---
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
        } else if (target === 'suspension-category') {
            this.handleSuspensionCategoryChange(value, option.dataset.label);
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
        const durationSection = document.getElementById('cascade-suspension-duration');
        
        if (type === 'temporal') {
            const currentCat = document.getElementById('dropdown-suspension-category')?.dataset.value;
            // Evaluamos qué mostrar basándonos en la categoría actual
            if (currentCat === 'other') {
                if (dateSection) dateSection.classList.replace('disabled', 'active');
                if (durationSection) durationSection.classList.replace('active', 'disabled');
            } else {
                if (dateSection) dateSection.classList.replace('active', 'disabled');
                if (durationSection) durationSection.classList.replace('disabled', 'active');
            }
        } else {
            // Si es Permanente, escondemos tanto el picker manual como la duración automática
            if (dateSection) dateSection.classList.replace('active', 'disabled');
            if (durationSection) durationSection.classList.replace('active', 'disabled');
        }
    }

    handleSuspensionCategoryChange(value, label) {
        const dateSection = document.getElementById('cascade-suspension-date');
        const durationSection = document.getElementById('cascade-suspension-duration');
        const displayDays = document.getElementById('display-suspension-days');
        
        const inputDate = document.getElementById('input-suspension-date');
        const displayDate = document.getElementById('display-suspension-date');
        const suspensionType = document.getElementById('dropdown-suspension-type')?.dataset.value;
        
        if (value === 'other') {
            // Ocultar duración automática
            if (durationSection) durationSection.classList.replace('active', 'disabled');
            
            // Si elige "Otro" y estamos en modo Temporal, mostramos el picker
            if (suspensionType === 'temporal' && dateSection) {
                dateSection.classList.replace('disabled', 'active');
            }
            
            // Forzamos limpiar la fecha para que el admin la asigne manualmente
            if (inputDate) inputDate.value = '';
            if (displayDate) displayDate.textContent = 'Seleccionar fecha y hora';
            
        } else if (this.SUSPENSION_RULES[value]) {
            // Ocultar calendario manual
            if (dateSection) dateSection.classList.replace('active', 'disabled');
            
            // Mostrar y actualizar la duración en el componente visual
            const days = this.SUSPENSION_RULES[value];
            if (suspensionType === 'temporal') {
                if (durationSection) durationSection.classList.replace('disabled', 'active');
                if (displayDays) displayDays.textContent = days;
            }
            
            // Calculamos la fecha objetivo
            const date = new Date();
            date.setDate(date.getDate() + days);
            
            const pad = (n) => String(n).padStart(2, '0');
            const formattedValue = `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
            
            // Inyectamos silenciosamente la fecha para que se envíe al backend
            if (inputDate) inputDate.value = formattedValue;
        }
    }

    // ==========================================
    // ENVÍO DE DATOS AL BACKEND
    // ==========================================

    async saveStatusChanges(btn) {
        const targetUuid = document.getElementById('admin-target-uuid') ? document.getElementById('admin-target-uuid').value : null;
        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : null;

        if (!targetUuid) return;

        const status = document.getElementById('dropdown-lifecycle-status')?.dataset.value || 'active';
        const isSuspended = document.getElementById('toggle-is-suspended')?.checked ? 1 : 0;
        
        const suspensionType = document.getElementById('dropdown-suspension-type')?.dataset.value;
        const suspensionCategory = document.getElementById('dropdown-suspension-category')?.dataset.value;
        const suspensionCategoryLabel = document.getElementById('dropdown-suspension-category')?.querySelector('.component-dropdown-text')?.textContent;
        const suspensionDate = document.getElementById('input-suspension-date')?.value;
        const suspensionNote = document.getElementById('input-suspension-reason')?.value;
        
        const deletionType = document.getElementById('dropdown-deletion-type')?.dataset.value;
        const deletionReason = document.getElementById('input-deletion-reason')?.value;

        // Validación Frontend: Si es temporal Y manual ("Otro"), debe llevar fecha
        if (status === 'active' && isSuspended === 1 && suspensionType === 'temporal') {
            if (suspensionCategory === 'other' && !suspensionDate) {
                Toast.show('Debes especificar la fecha y hora de expiración para la suspensión temporal manual.', 'error');
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
            suspension_category: suspensionCategory,
            suspension_category_label: suspensionCategoryLabel,
            suspension_expires_at: suspensionDate,
            suspension_note: suspensionNote,
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