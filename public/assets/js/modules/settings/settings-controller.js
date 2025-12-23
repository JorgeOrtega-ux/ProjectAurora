/**
 * SettingsController
 * Maneja la lógica de interfaz (UI) para secciones de configuración:
 * - Modos de edición (View vs Edit) para Textos
 * - Menús desplegables (Popovers)
 */

export const SettingsController = (function() {
    
    const CONFIG = {
        activeClass: 'active',
        disabledClass: 'disabled',
        popoverSelector: '.popover-module',
        triggerSelector: '.trigger-selector',
        wrapperSelector: '.trigger-select-wrapper'
    };

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
            if (input && input.dataset.originalValue) {
                input.value = input.dataset.originalValue;
            }
        }
    }

    function saveData(sectionId) {
        const parent = document.querySelector(`[data-component="${sectionId}-section"]`);
        const input = parent.querySelector('input');
        const display = parent.querySelector('.text-display-value');

        if (input && display) {
            display.innerText = input.value;
            input.dataset.originalValue = input.value; 
            // Aquí iría la llamada a API para guardar textos (username, email)
            toggleEdit(sectionId, false);
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
        console.log("SettingsController inicializado.");
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