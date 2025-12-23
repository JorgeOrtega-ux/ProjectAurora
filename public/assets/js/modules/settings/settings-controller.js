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
     * Guarda una preferencia general (Idioma, Toggles) en la BD
     */
    async function savePreference(key, value) {
        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('key', key);
        formData.append('value', value);

        try {
            const res = await ApiService.post('settings-handler.php', formData);
            if (!res.success) {
                Toast.show(res.message || 'Error al guardar preferencia', 'error');
            } else {
                Toast.show(res.message || 'Preferencia guardada', 'success');
            }
        } catch (error) {
            console.error(error);
            Toast.show('Error de conexión al guardar preferencia', 'error');
        }
    }

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

        if(btnSave) {
            btnSave.disabled = true;
            btnSave.innerText = 'Guardando...';
        }

        const formData = new FormData();
        formData.append('action', 'update_profile');
        formData.append('field', sectionId); 
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
                input.focus(); 
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
    // LÓGICA DE PASSWORD (LOGIN & SECURITY)
    // =======================================================
    
    function _initPasswordLogic() {
        const container = document.querySelector('[data-component="password-update-section"]');
        if (!container) return; 

        container.addEventListener('click', async (e) => {
            const action = e.target.dataset.action;
            if(!action) return;

            const stage0 = container.querySelector('[data-state="password-stage-0"]'); // Botón inicial
            const stage1 = container.querySelector('[data-state="password-stage-1"]'); // Input Contraseña Actual
            const stage2 = container.querySelector('[data-state="password-stage-2"]'); // Inputs Nueva Contraseña
            const parentGroup = container.closest('.component-group-item'); // El contenedor padre para añadir 'stacked'

            // 1. Iniciar Flujo
            if (action === 'pass-start-flow') {
                // Añadimos clase stacked para permitir diseño vertical ancho completo
                if(parentGroup) parentGroup.classList.add('component-group-item--stacked');
                
                _switchState(stage0, stage1);
                const input = document.getElementById('current-password-input');
                if(input) { input.value = ''; input.focus(); }
            }

            // 2. Cancelar Flujo
            if (action === 'pass-cancel-flow') {
                // Quitamos clase stacked para volver a diseño horizontal
                if(parentGroup) parentGroup.classList.remove('component-group-item--stacked');

                if(stage1) { stage1.classList.remove(CONFIG.activeClass); stage1.classList.add(CONFIG.disabledClass); }
                if(stage2) { stage2.classList.remove(CONFIG.activeClass); stage2.classList.add(CONFIG.disabledClass); }
                if(stage0) { stage0.classList.remove(CONFIG.disabledClass); stage0.classList.add(CONFIG.activeClass); }
                
                // Limpiar inputs
                container.querySelectorAll('input').forEach(i => i.value = '');
            }

            // 3. Ir al Paso 2 (CON VALIDACIÓN PREVIA)
            if (action === 'pass-go-step-2') {
                const currentPassInput = document.getElementById('current-password-input');
                const currentPass = currentPassInput.value;
                
                if (!currentPass) {
                    Toast.show('Ingresa tu contraseña actual.', 'warning');
                    return;
                }

                // === NUEVO: Validar contra backend antes de avanzar ===
                const btn = e.target;
                const originalText = btn.innerText;
                btn.innerText = 'Verificando...';
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'validate_current_password');
                formData.append('current_password', currentPass);

                try {
                    const res = await ApiService.post('settings-handler.php', formData);
                    
                    if (res.success) {
                        // Correcto: Avanzamos UI
                        _switchState(stage1, stage2);
                        const inputNew = document.getElementById('new-password-input');
                        if(inputNew) { inputNew.value = ''; inputNew.focus(); }
                        document.getElementById('repeat-password-input').value = '';
                    } else {
                        // Incorrecto: Mostramos error y nos quedamos aquí
                        Toast.show('La contraseña actual es incorrecta.', 'error');
                        currentPassInput.focus();
                    }
                } catch (err) {
                    console.error(err);
                    Toast.show('Error de conexión al validar.', 'error');
                } finally {
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
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
                        
                        // Resetear todo
                        if(parentGroup) parentGroup.classList.remove('component-group-item--stacked');
                        if(stage1) { stage1.classList.remove(CONFIG.activeClass); stage1.classList.add(CONFIG.disabledClass); }
                        if(stage2) { stage2.classList.remove(CONFIG.activeClass); stage2.classList.add(CONFIG.disabledClass); }
                        if(stage0) { stage0.classList.remove(CONFIG.disabledClass); stage0.classList.add(CONFIG.activeClass); }
                        
                        container.querySelectorAll('input').forEach(i => i.value = '');
                    } else {
                        Toast.show(res.message, 'error');
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

    function selectOption(itemElement, textValue, dataValue = null) {
        const wrapper = itemElement.closest(CONFIG.wrapperSelector);
        if (!wrapper) return;

        // 1. Detectamos si ya estaba activo para evitar API Call
        const isSameValue = itemElement.classList.contains(CONFIG.activeClass);

        // 2. Realizamos TODA la lógica visual (Feedback UI inmediato)
        const triggerText = wrapper.querySelector('.trigger-select-text');
        if (triggerText) triggerText.innerText = textValue;
        
        wrapper.querySelectorAll('.menu-link').forEach(link => link.classList.remove(CONFIG.activeClass));
        itemElement.classList.add(CONFIG.activeClass);
        
        // Cerramos el dropdown para que no se sienta "trabado"
        closeAllDropdowns();

        // 3. Solo si cambió el valor, llamamos al servidor
        if (dataValue && !isSameValue) {
            savePreference('language', dataValue);
        } else if (isSameValue) {
            console.log("Preferencia sin cambios. UI actualizada, API omitida.");
        }

        if (event) event.stopPropagation();
    }

    function closeAllDropdowns() {
        document.querySelectorAll(CONFIG.popoverSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.triggerSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.wrapperSelector).forEach(el => el.classList.remove('dropdown-active'));
    }

    function _initToggles() {
        const toggleLinks = document.getElementById('pref-open-links');
        if (toggleLinks) {
            toggleLinks.addEventListener('change', (e) => {
                savePreference('open_links_new_tab', e.target.checked);
            });
        }
    }

    function init() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest(CONFIG.wrapperSelector)) {
                closeAllDropdowns();
            }
        });
        
        _initToggles();
        _initPasswordLogic();

        console.log("SettingsController inicializado.");
    }

    return {
        init,
        toggleEdit,
        saveData,
        toggleDropdown,
        selectOption,
        closeAllDropdowns,
        savePreference
    };
})();

// Mapeo global
window.toggleEdit = SettingsController.toggleEdit;
window.saveData = SettingsController.saveData;
window.toggleDropdown = SettingsController.toggleDropdown;
window.selectOption = SettingsController.selectOption;