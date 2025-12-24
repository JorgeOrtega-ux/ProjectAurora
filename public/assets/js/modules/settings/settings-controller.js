/**
 * public/assets/js/modules/settings/settings-controller.js
 * Maneja preferencias globales (Tema, Idioma, Toggles).
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

export const SettingsController = (function() {
    
    const CONFIG = {
        activeClass: 'active',
        popoverSelector: '.popover-module',
        triggerSelector: '.trigger-selector',
        wrapperSelector: '.trigger-select-wrapper'
    };

    function init() {
        // Cerrar dropdowns al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!e.target.closest(CONFIG.wrapperSelector)) {
                closeAllDropdowns();
            }
        });
        
        _initToggles();
        console.log("SettingsController (Global Prefs) inicializado.");
    }

    // --- LÓGICA DE PREFERENCIAS ---

    async function savePreference(key, value) {
        if (window.USER_PREFS) {
            window.USER_PREFS[key] = value;
        }

        if (key === 'theme') {
            applyTheme(value);
        }

        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('key', key);
        formData.append('value', value);

        try {
            const res = await ApiService.post('settings-handler.php', formData);
            if (!res.success) {
                Toast.show(res.message || I18n.t('js.settings.pref_error'), 'error');
            }
        } catch (error) {
            console.error(error);
            Toast.show(I18n.t('js.settings.pref_error'), 'error');
        }
    }

    function applyTheme(theme) {
        const root = document.documentElement;
        if (theme === 'dark') {
            root.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            root.setAttribute('data-theme', 'light');
        } else {
            root.removeAttribute('data-theme');
        }
    }

    function _initToggles() {
        // Toggle: Abrir enlaces en nueva pestaña
        const toggleLinks = document.getElementById('pref-open-links');
        if (toggleLinks) {
            toggleLinks.addEventListener('change', (e) => {
                savePreference('open_links_new_tab', e.target.checked);
            });
        }
        // Toggle: Notificaciones extendidas
        const toggleToast = document.getElementById('pref-extended-toast');
        if (toggleToast) {
            toggleToast.addEventListener('change', (e) => {
                savePreference('extended_toast', e.target.checked);
            });
        }
    }

    // --- UTILIDADES UI (Dropdowns) ---

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
        const isSameValue = itemElement.classList.contains(CONFIG.activeClass);

        const triggerText = wrapper.querySelector('.trigger-select-text');
        if (triggerText) triggerText.innerText = textValue;
        
        const newIcon = itemElement.querySelector('.material-symbols-rounded')?.innerText;
        const triggerIcon = wrapper.querySelector('.trigger-select-icon');
        if(newIcon && triggerIcon) triggerIcon.innerText = newIcon;

        wrapper.querySelectorAll('.menu-link').forEach(link => link.classList.remove(CONFIG.activeClass));
        itemElement.classList.add(CONFIG.activeClass);
        closeAllDropdowns();

        // Si es Tema o Idioma, guardar preferencia
        if (dataValue) {
            const isTheme = ['sync', 'light', 'dark'].includes(dataValue);
            savePreference(isTheme ? 'theme' : 'language', dataValue);
            
            // Si cambiamos idioma, recargar para aplicar textos
            if (!isTheme && !isSameValue) {
                setTimeout(() => window.location.reload(), 300);
            }
        }

        if (event) event.stopPropagation();
    }

    function closeAllDropdowns() {
        document.querySelectorAll(CONFIG.popoverSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.triggerSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.wrapperSelector).forEach(el => el.classList.remove('dropdown-active'));
    }

    return {
        init,
        toggleDropdown,
        selectOption,
        savePreference,
        applyTheme
    };
})();

// Exponemos helpers de UI global
window.toggleDropdown = SettingsController.toggleDropdown;
window.selectOption = SettingsController.selectOption;