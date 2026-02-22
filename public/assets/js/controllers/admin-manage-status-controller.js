// public/assets/js/controllers/admin-manage-status-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class AdminManageStatusController {
    constructor() {
        this.SUSPENSION_RULES = {
            'cat_1': '1d',   // Spam -> 1 día
            'cat_2': '3d',   // Lenguaje inapropiado -> 3 días
            'cat_3': '7d',   // Comportamiento tóxico -> 7 días
            'cat_4': '14d',  // Evasión -> 14 días
            'cat_5': '30d'   // Fraude -> 30 días
        };

        this.DURATION_MAP = {
            '1d': { type: 'days', val: 1 },
            '3d': { type: 'days', val: 3 },
            '7d': { type: 'days', val: 7 },
            '14d': { type: 'days', val: 14 },
            '30d': { type: 'days', val: 30 },
            '3m': { type: 'months', val: 3 },
            '6m': { type: 'months', val: 6 },
            '1y': { type: 'years', val: 1 },
            '3y': { type: 'years', val: 3 }
        };

        this.init();
    }

    init() {
        document.body.addEventListener('click', (e) => {
            const view = document.getElementById('admin-manage-status-view');
            if (!view) return;

            const adminDropdownTrigger = e.target.closest('[data-action="admin-toggle-dropdown"]');
            if (adminDropdownTrigger) {
                e.preventDefault();
                e.stopPropagation(); 
                
                const wrapper = adminDropdownTrigger.closest('.component-dropdown');
                const module = wrapper.querySelector('.component-module');
                
                document.querySelectorAll('.component-dropdown .component-module:not(.disabled)').forEach(m => {
                    if (m !== module) m.classList.add('disabled');
                });
                
                if (module) module.classList.toggle('disabled');
                return;
            }

            const optionSelect = e.target.closest('[data-action="status-select-option"]');
            if (optionSelect) {
                e.preventDefault();
                this.handleOptionSelect(optionSelect);
                return;
            }

            const btnSave = e.target.closest('#btn-save-status-changes');
            if (btnSave) {
                e.preventDefault();
                this.saveStatusChanges(btnSave);
            }
        });

        document.body.addEventListener('change', (e) => {
            const view = document.getElementById('admin-manage-status-view');
            if (!view) return;

            if (e.target.id === 'toggle-is-suspended') {
                this.handleSuspensionToggle(e.target.checked);
            }
        });
    }

    handleOptionSelect(option) {
        const wrapper = option.closest('.component-dropdown');
        const module = wrapper.querySelector('.component-module');
        
        const textDisplay = wrapper.querySelector('.component-dropdown-text');
        textDisplay.textContent = option.dataset.label;
        
        const iconDisplay = wrapper.querySelector('.trigger-select-icon');
        const optionIcon = option.querySelector('.component-menu-link-icon span');
        if (iconDisplay && optionIcon) {
            iconDisplay.textContent = optionIcon.textContent;
        }
        
        module.querySelectorAll('.component-menu-link').forEach(link => link.classList.remove('active'));
        option.classList.add('active');
        
        const value = option.dataset.value;
        wrapper.dataset.value = value;
        
        module.classList.add('disabled');

        const target = option.dataset.target;
        if (target === 'lifecycle') {
            this.handleLifecycleChange(value);
        } else if (target === 'suspension-type') {
            this.handleSuspensionTypeChange(value);
        } else if (target === 'suspension-category') {
            this.handleSuspensionCategoryChange(value);
        } else if (target === 'suspension-duration') {
            this.handleSuspensionDurationChange(value);
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
            
            // Sincronizar UI al encender el switch
            const currentType = document.getElementById('dropdown-suspension-type')?.dataset.value || 'temporal';
            this.handleSuspensionTypeChange(currentType);
        } else {
            if (suspensionData) suspensionData.classList.replace('active', 'disabled');
        }
    }

    handleSuspensionTypeChange(type) {
        const dateSection = document.getElementById('cascade-suspension-date');
        const durationSection = document.getElementById('cascade-suspension-duration');
        const currentCat = document.getElementById('dropdown-suspension-category')?.dataset.value;
        const currentDur = document.getElementById('dropdown-suspension-duration')?.dataset.value;

        if (type === 'temporal') {
            if (currentCat === 'other') {
                if (dateSection) dateSection.classList.replace('disabled', 'active');
                if (durationSection) durationSection.classList.replace('active', 'disabled');
            } else {
                // Mostrar siempre opciones de duración si la categoría está definida
                if (durationSection) durationSection.classList.replace('disabled', 'active');
                
                // Mostrar calendario solo si la duración está marcada como "other"
                if (currentDur === 'other') {
                    if (dateSection) dateSection.classList.replace('disabled', 'active');
                } else {
                    if (dateSection) dateSection.classList.replace('active', 'disabled');
                }
            }
        } else {
            if (dateSection) dateSection.classList.replace('active', 'disabled');
            if (durationSection) durationSection.classList.replace('active', 'disabled');
        }
    }

    handleSuspensionCategoryChange(value) {
        const durationSection = document.getElementById('cascade-suspension-duration');
        const dateSection = document.getElementById('cascade-suspension-date');
        const suspensionType = document.getElementById('dropdown-suspension-type')?.dataset.value;

        document.querySelectorAll('.recommendation-badge').forEach(badge => {
            badge.style.display = 'none';
        });

        if (value === 'other') {
            // Ocultar sección de duraciones predefinidas
            if (durationSection) durationSection.classList.replace('active', 'disabled');
            
            // Mostrar calendario
            if (suspensionType === 'temporal' && dateSection) {
                dateSection.classList.replace('disabled', 'active');
            }

            const inputDate = document.getElementById('input-suspension-date');
            const displayDate = document.getElementById('display-suspension-date');
            if (inputDate) inputDate.value = '';
            if (displayDate) displayDate.textContent = 'Seleccionar fecha y hora';

            // Resetear el valor interno de duración a "other"
            const otherOption = document.querySelector('[data-target="suspension-duration"][data-value="other"]');
            if (otherOption) this.handleOptionSelect(otherOption);

        } else {
            // Mostrar bloque de duraciones si estamos en temporal
            if (suspensionType === 'temporal' && durationSection) {
                durationSection.classList.replace('disabled', 'active');
            }

            const recommendedDurationKey = this.SUSPENSION_RULES[value];
            if (recommendedDurationKey) {
                const recommendedOption = document.querySelector(`[data-target="suspension-duration"][data-value="${recommendedDurationKey}"]`);
                
                if (recommendedOption) {
                    const badge = recommendedOption.querySelector('.recommendation-badge');
                    if (badge) badge.style.display = 'flex';
                    this.handleOptionSelect(recommendedOption);
                }
            }
        }
    }

    handleSuspensionDurationChange(durationKey) {
        const dateSection = document.getElementById('cascade-suspension-date');
        const inputDate = document.getElementById('input-suspension-date');
        const displayDate = document.getElementById('display-suspension-date');

        if (durationKey === 'other') {
            if (dateSection) dateSection.classList.replace('disabled', 'active');
            if (inputDate) inputDate.value = '';
            if (displayDate) displayDate.textContent = 'Seleccionar fecha y hora';
            return;
        }

        if (dateSection) dateSection.classList.replace('active', 'disabled');

        const conf = this.DURATION_MAP[durationKey];
        if (!conf) return;

        const date = new Date();
        
        if (conf.type === 'days') {
            date.setDate(date.getDate() + conf.val);
        } else if (conf.type === 'months') {
            date.setMonth(date.getMonth() + conf.val);
        } else if (conf.type === 'years') {
            date.setFullYear(date.getFullYear() + conf.val);
        }

        const pad = (n) => String(n).padStart(2, '0');
        const formattedValue = `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
        
        if (inputDate) {
            inputDate.value = formattedValue;
        }
    }

    async saveStatusChanges(btn) {
        const targetUuid = document.getElementById('admin-target-uuid') ? document.getElementById('admin-target-uuid').value : null;
        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : null;

        if (!targetUuid) return;

        const status = document.getElementById('dropdown-lifecycle-status')?.dataset.value || 'active';
        const isSuspended = document.getElementById('toggle-is-suspended')?.checked ? 1 : 0;
        
        const suspensionType = document.getElementById('dropdown-suspension-type')?.dataset.value;
        const suspensionCategory = document.getElementById('dropdown-suspension-category')?.dataset.value;
        const suspensionCategoryLabel = document.getElementById('dropdown-suspension-category')?.querySelector('.component-dropdown-text')?.textContent;
        const suspensionDuration = document.getElementById('dropdown-suspension-duration')?.dataset.value;
        const suspensionDate = document.getElementById('input-suspension-date')?.value;
        const suspensionNote = document.getElementById('input-suspension-reason')?.value;
        
        const deletionType = document.getElementById('dropdown-deletion-type')?.dataset.value;
        const deletionReason = document.getElementById('input-deletion-reason')?.value;

        // Validación Frontend
        if (status === 'active' && isSuspended === 1 && suspensionType === 'temporal') {
            if ((suspensionCategory === 'other' || suspensionDuration === 'other') && !suspensionDate) {
                Toast.show('Debes seleccionar una duración o fecha manual de expiración.', 'error');
                return;
            }
        }

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