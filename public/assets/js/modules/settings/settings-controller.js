/**
 * public/assets/js/modules/settings/settings-controller.js
 * Actualizado con Logs de Depuración Profunda
 */

import { ApiService } from '../../core/api-service.js';
import { I18n } from '../../core/i18n-manager.js';

export const SettingsController = (function() {
    
    const SELECTORS = {
        wrapper: '.trigger-select-wrapper',
        popover: '.popover-module',
        triggerSelector: '.trigger-selector',
        triggerText: '.trigger-select-text',
        triggerIcon: '.trigger-select-icon',
        optionClass: '.menu-link',
        activeClass: 'active',
        // Selectores de datos
        attrTrigger: '[data-trigger="dropdown"]',
        attrOption: '[data-action="select-option"]'
    };

    function init() {
        console.group("⚙️ SettingsController: Init");
        _bindEvents();
        _initToggles();
        
        // Intentar sincronizar al inicio
        _syncStateWithDOM();
        
        console.groupEnd();
    }

    /**
     * MÉTODO PÚBLICO NUEVO:
     * Llama a esto cuando cargues la vista de Accesibilidad vía AJAX/Router
     */
    function sync() {
        console.log("🔄 SettingsController: Forzando resincronización manual...");
        _syncStateWithDOM();
    }

    function _syncStateWithDOM() {
        const prefs = window.USER_PREFS || {};
        console.groupCollapsed("🔍 Sincronizando DOM con Preferencias");
        console.log("📦 Preferencias actuales:", JSON.stringify(prefs));

        if (prefs.language) _highlightActiveOption('language', prefs.language);
        if (prefs.theme) _highlightActiveOption('theme', prefs.theme);
        
        console.groupEnd();
    }

    function _highlightActiveOption(type, value) {
        const selector = `${SELECTORS.optionClass}[data-type="${type}"]`;
        const options = document.querySelectorAll(selector);
        
        console.log(`🔎 Buscando elementos para [${type}]:`, selector);
        console.log(`📊 Cantidad encontrada: ${options.length}`);

        if (options.length === 0) {
            console.warn(`⚠️ NO se encontraron elementos en el DOM para [${type}]. Es probable que la vista no esté cargada todavía.`);
            return;
        }

        let matchFound = false;

        options.forEach(opt => {
            const optValue = opt.dataset.value;
            const isMatch = (optValue === value);
            
            // Log de comparación
            // console.log(`   - Comparando elemento [${optValue}] vs preferencia [${value}] -> ${isMatch ? 'MATCH ✅' : 'No match'}`);

            if (isMatch) {
                matchFound = true;
                
                // 1. Activar opción
                opt.classList.add(SELECTORS.activeClass);

                // 2. Actualizar texto del Trigger padre
                const wrapper = opt.closest(SELECTORS.wrapper);
                if (wrapper) {
                    const triggerText = wrapper.querySelector(SELECTORS.triggerText);
                    // Actualizar texto si existe
                    if (triggerText && opt.dataset.label) {
                        triggerText.innerText = opt.dataset.label;
                    }
                }
            } else {
                opt.classList.remove(SELECTORS.activeClass);
            }
        });

        if (matchFound) {
            console.log(`✅ UI Actualizada correctamente para [${type} = ${value}]`);
        } else {
            console.error(`❌ ERROR LÓGICO: Se encontraron elementos para [${type}] pero ninguno tiene el valor [${value}]. Revisa los data-value en el HTML.`);
        }
    }

    // --- (El resto de funciones _bindEvents, _initToggles, savePreference siguen igual) ---
    
    function _bindEvents() {
        document.addEventListener('click', (e) => {
            const optionBtn = e.target.closest(SELECTORS.attrOption);
            if (optionBtn) { _handleOptionSelect(optionBtn, e); return; }

            const triggerWrapper = e.target.closest(SELECTORS.attrTrigger);
            if (triggerWrapper) {
                if (e.target.closest(SELECTORS.triggerSelector)) { _handleDropdownToggle(triggerWrapper, e); }
                return;
            }
            if (!e.target.closest(SELECTORS.wrapper)) { _closeAllDropdowns(); }
        });
    }

    function _handleDropdownToggle(wrapperElement, event) {
        event.stopPropagation();
        const menu = wrapperElement.querySelector(SELECTORS.popover);
        const trigger = wrapperElement.querySelector(SELECTORS.triggerSelector);
        if (!menu || !trigger) return;

        const isAlreadyActive = menu.classList.contains(SELECTORS.activeClass);
        _closeAllDropdowns();
        if (!isAlreadyActive) {
            menu.classList.add(SELECTORS.activeClass);
            trigger.classList.add(SELECTORS.activeClass);
            wrapperElement.classList.add('dropdown-active');
        }
    }

    function _handleOptionSelect(optionElement, event) {
        event.stopPropagation();
        event.preventDefault();
        const wrapper = optionElement.closest(SELECTORS.wrapper);
        if (!wrapper) return;

        const value = optionElement.dataset.value;
        const label = optionElement.dataset.label;
        const type = optionElement.dataset.type;

        // UI Update
        const triggerText = wrapper.querySelector(SELECTORS.triggerText);
        if (triggerText && label) triggerText.innerText = label;
        
        const newIcon = optionElement.querySelector('.material-symbols-rounded')?.innerText;
        const triggerIcon = wrapper.querySelector(SELECTORS.triggerIcon);
        if(newIcon && triggerIcon) triggerIcon.innerText = newIcon;

        const allOptions = wrapper.querySelectorAll(SELECTORS.optionClass);
        allOptions.forEach(opt => opt.classList.remove(SELECTORS.activeClass));
        optionElement.classList.add(SELECTORS.activeClass);

        _closeAllDropdowns();

        if (value && type) {
            savePreference(type, value);
            if (type === 'theme') {
                applyTheme(value);
            } else {
                setTimeout(() => window.location.reload(), 150);
            }
        }
    }

    function _closeAllDropdowns() {
        document.querySelectorAll(SELECTORS.popover).forEach(el => el.classList.remove(SELECTORS.activeClass));
        document.querySelectorAll(SELECTORS.triggerSelector).forEach(el => el.classList.remove(SELECTORS.activeClass));
        document.querySelectorAll(SELECTORS.wrapper).forEach(el => el.classList.remove('dropdown-active'));
    }

    async function savePreference(key, value) {
        if (window.USER_PREFS) window.USER_PREFS[key] = value;
        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('key', key);
        formData.append('value', value);
        try { await ApiService.post('settings-handler.php', formData); } catch (error) { console.error(error); }
    }

    function applyTheme(theme) {
        const root = document.documentElement;
        if (theme === 'dark') root.setAttribute('data-theme', 'dark');
        else if (theme === 'light') root.setAttribute('data-theme', 'light');
        else root.removeAttribute('data-theme');
    }

    function _initToggles() {
        // Toggles logic... (sin cambios)
        const toggleLinks = document.getElementById('pref-open-links');
        if (toggleLinks) {
            toggleLinks.addEventListener('change', (e) => savePreference('open_links_new_tab', e.target.checked));
        }
        const toggleToast = document.getElementById('pref-extended-toast');
        if (toggleToast) {
            toggleToast.addEventListener('change', (e) => savePreference('extended_toast', e.target.checked));
        }
    }

    return {
        init,
        sync, // <--- EXPORTADO PÚBLICAMENTE
        savePreference,
        applyTheme
    };
})();