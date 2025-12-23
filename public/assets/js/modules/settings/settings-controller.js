/**
 * public/assets/js/modules/settings/settings-controller.js
 * SettingsController
 * Maneja la lógica de interfaz (UI) y comunicación con API para configuraciones.
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

export const SettingsController = (function() {
    
    const CONFIG = {
        activeClass: 'active',
        disabledClass: 'disabled',
        popoverSelector: '.popover-module',
        triggerSelector: '.trigger-selector',
        wrapperSelector: '.trigger-select-wrapper'
    };

    /**
     * Alterna entre modo visualización y edición para inputs de texto (Username, Email)
     */
    function toggleEdit(sectionId, isEditing) {
        const parent = document.querySelector(`[data-component="${sectionId}-section"]`);
        if (!parent) return;

        const viewState = parent.querySelector(`[data-state="${sectionId}-view-state"]`);
        const editState = parent.querySelector(`[data-state="${sectionId}-edit-state"]`);
        const actionsView = parent.querySelector(`[data-state="${sectionId}-actions-view"]`);
        const actionsEdit = parent.querySelector(`[data-state="${sectionId}-actions-edit"]`);
        const input = parent.querySelector('input');

        if (isEditing) {
            _switchState(viewState, editState);
            _switchState(actionsView, actionsEdit);
            if (input) {
                input.dataset.originalValue = input.value;
                input.focus();
            }
        } else {
            _switchState(editState, viewState);
            _switchState(actionsEdit, actionsView);
            // Restaurar valor si se cancela
            if (input && input.dataset.originalValue && input.value !== input.dataset.originalValue) {
                input.value = input.dataset.originalValue;
            }
        }
    }

    /**
     * Guarda los datos de inputs de texto (Username, Email) llamando a la API
     */
    async function saveData(sectionId) {
        const parent = document.querySelector(`[data-component="${sectionId}-section"]`);
        const input = parent.querySelector('input');
        const display = parent.querySelector('.text-display-value');
        const btnSave = parent.querySelector(`[data-state="${sectionId}-actions-edit"] .component-button.primary`);

        if (!input || !display) return;

        const newValue = input.value.trim();
        const originalValue = input.dataset.originalValue;

        if (newValue === originalValue) {
            toggleEdit(sectionId, false);
            return;
        }

        // Feedback visual
        if(btnSave) {
            btnSave.disabled = true;
            btnSave.innerText = 'Guardando...';
        }

        // Preparar datos
        const formData = new FormData();
        formData.append('action', 'update_profile');
        formData.append('field', sectionId); // 'username' o 'email'
        formData.append('value', newValue);

        try {
            const res = await ApiService.post('settings-handler.php', formData);

            if (res.success) {
                display.innerText = newValue;
                input.dataset.originalValue = newValue;
                Toast.show(res.message, 'success');
                toggleEdit(sectionId, false);
            } else {
                Toast.show(res.message, 'error');
                input.focus(); // Mantener foco en error
            }
        } catch (error) {
            Toast.show('Error de conexión.', 'error');
        } finally {
            if(btnSave) {
                btnSave.disabled = false;
                btnSave.innerText = 'Guardar';
            }
        }
    }

    // =======================================================
    // LÓGICA DE DROPDOWNS (UI)
    // =======================================================

    function toggleDropdown(wrapperElement) {
        const menu = wrapperElement.querySelector(CONFIG.popoverSelector);
        const trigger = wrapperElement.querySelector(CONFIG.triggerSelector);
        if (!menu || !trigger) return;

        const isActive = menu.classList.contains(CONFIG.activeClass);
        closeAllDropdowns();

        if (!isActive) {
            menu.classList.add(CONFIG.activeClass);
            trigger.classList.add(CONFIG.activeClass);
            wrapperElement.classList.add('dropdown-active');
        }
        if (event) event.stopPropagation();
    }

    function selectOption(itemElement, textValue) {
        const wrapper = itemElement.closest(CONFIG.wrapperSelector);
        if (!wrapper) return;
        const triggerText = wrapper.querySelector('.trigger-select-text');
        if (triggerText) triggerText.innerText = textValue;
        
        wrapper.querySelectorAll('.menu-link').forEach(link => link.classList.remove(CONFIG.activeClass));
        itemElement.classList.add(CONFIG.activeClass);
        closeAllDropdowns();
        if (event) event.stopPropagation();
    }

    function closeAllDropdowns() {
        document.querySelectorAll(CONFIG.popoverSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.triggerSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.wrapperSelector).forEach(el => el.classList.remove('dropdown-active'));
    }

    // =======================================================
    // LÓGICA DE PASSWORD (LOGIN & SECURITY)
    // =======================================================
    
    function _initPasswordLogic() {
        const container = document.querySelector('[data-component="password-update-section"]');
        if (!container) return; // No estamos en la página de seguridad

        container.addEventListener('click', async (e) => {
            const action = e.target.dataset.action;
            if(!action) return;

            const stage0 = container.querySelector('[data-state="password-stage-0"]'); // Botón inicial
            const stage1 = container.querySelector('[data-state="password-stage-1"]'); // Input Current Pass
            const stage2 = container.querySelector('[data-state="password-stage-2"]'); // Inputs New Pass

            // 1. Iniciar Flujo
            if (action === 'pass-start-flow') {
                _switchState(stage0, stage1);
                const input = document.getElementById('current-password-input');
                if(input) { input.value = ''; input.focus(); }
            }

            // 2. Cancelar Flujo (Reset)
            if (action === 'pass-cancel-flow') {
                // Ocultar 1 y 2, mostrar 0
                if(stage1) { stage1.classList.remove(CONFIG.activeClass); stage1.classList.add(CONFIG.disabledClass); }
                if(stage2) { stage2.classList.remove(CONFIG.activeClass); stage2.classList.add(CONFIG.disabledClass); }
                if(stage0) { stage0.classList.remove(CONFIG.disabledClass); stage0.classList.add(CONFIG.activeClass); }
            }

            // 3. Ir al Paso 2 (Validar que escribió algo en Current)
            if (action === 'pass-go-step-2') {
                const currentPass = document.getElementById('current-password-input').value;
                if (!currentPass) {
                    Toast.show('Ingresa tu contraseña actual.', 'warning');
                    return;
                }
                _switchState(stage1, stage2);
                const inputNew = document.getElementById('new-password-input');
                if(inputNew) { inputNew.value = ''; inputNew.focus(); }
                document.getElementById('repeat-password-input').value = '';
            }

            // 4. Submit Final
            if (action === 'pass-submit-final') {
                const currentPass = document.getElementById('current-password-input').value;
                const newPass = document.getElementById('new-password-input').value;
                const repeatPass = document.getElementById('repeat-password-input').value;

                if (!newPass || !repeatPass) {
                    Toast.show('Completa todos los campos.', 'warning');
                    return;
                }
                if (newPass !== repeatPass) {
                    Toast.show('Las nuevas contraseñas no coinciden.', 'error');
                    return;
                }
                if (newPass.length < 6) {
                    Toast.show('La contraseña debe tener al menos 6 caracteres.', 'warning');
                    return;
                }

                // Enviar a API
                const btn = e.target;
                const originalText = btn.innerText;
                btn.innerText = 'Guardando...';
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'change_password');
                formData.append('current_password', currentPass);
                formData.append('new_password', newPass);

                try {
                    const res = await ApiService.post('settings-handler.php', formData);
                    if (res.success) {
                        Toast.show('Contraseña actualizada correctamente.', 'success');
                        // Resetear UI
                        btn.click(); // Hack: simular click en 'pass-cancel-flow' si fuera el mismo botón, pero no.
                        // Llamamos manualmente a cancelar para resetear
                        if(stage1) { stage1.classList.remove(CONFIG.activeClass); stage1.classList.add(CONFIG.disabledClass); }
                        if(stage2) { stage2.classList.remove(CONFIG.activeClass); stage2.classList.add(CONFIG.disabledClass); }
                        if(stage0) { stage0.classList.remove(CONFIG.disabledClass); stage0.classList.add(CONFIG.activeClass); }
                    } else {
                        Toast.show(res.message, 'error');
                        // Si dice contraseña incorrecta, quizás volver al paso 1?
                        if(res.message.includes('actual')) {
                            _switchState(stage2, stage1);
                            document.getElementById('current-password-input').focus();
                        }
                    }
                } catch(err) {
                    Toast.show('Error al procesar la solicitud.', 'error');
                } finally {
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            }
        });
    }

    // =======================================================
    // UTILIDADES
    // =======================================================

    function _switchState(hideElement, showElement) {
        if (hideElement) {
            hideElement.classList.remove(CONFIG.activeClass);
            hideElement.classList.add(CONFIG.disabledClass);
        }
        if (showElement) {
            showElement.classList.remove(CONFIG.disabledClass);
            showElement.classList.add(CONFIG.activeClass);
        }
    }

    function init() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest(CONFIG.wrapperSelector)) {
                closeAllDropdowns();
            }
        });
        
        // Intentar iniciar lógica de contraseña (si existe el elemento en el DOM)
        _initPasswordLogic();

        console.log("SettingsController inicializado (con lógica API y Password).");
    }

    return {
        init,
        toggleEdit,
        saveData,
        toggleDropdown,
        selectOption,
        closeAllDropdowns
    };
})();

// Mapeo global para compatibilidad con onclicks en HTML
window.toggleEdit = SettingsController.toggleEdit;
window.saveData = SettingsController.saveData;
window.toggleDropdown = SettingsController.toggleDropdown;
window.selectOption = SettingsController.selectOption;