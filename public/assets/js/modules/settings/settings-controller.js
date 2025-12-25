/**
 * public/assets/js/modules/settings/settings-controller.js
 */

import { ApiService } from '../../core/api-service.js';
// 1. IMPORTAR TOAST (Faltaba esto)
import { Toast } from '../../core/toast-manager.js';
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
        attrTrigger: '[data-trigger="dropdown"]',
        attrOption: '[data-action="select-option"]',
        attrSearch: '[data-action="filter-languages"]'
    };

    function init() {
        _bindEvents();
        _initToggles();
        _syncStateWithDOM();
    }

    function sync() {
        _syncStateWithDOM();
    }

    function _syncStateWithDOM() {
        const prefs = window.USER_PREFS || {};

        if (prefs.language) _highlightActiveOption('language', prefs.language);
        if (prefs.theme) _highlightActiveOption('theme', prefs.theme);
    }

    function _highlightActiveOption(type, value) {
        const selector = `${SELECTORS.optionClass}[data-type="${type}"]`;
        const options = document.querySelectorAll(selector);

        if (options.length === 0) return;

        options.forEach(opt => {
            const optValue = opt.dataset.value;
            const isMatch = (optValue === value);
            
            if (isMatch) {
                opt.classList.add(SELECTORS.activeClass);
                const wrapper = opt.closest(SELECTORS.wrapper);
                if (wrapper) {
                    const triggerText = wrapper.querySelector(SELECTORS.triggerText);
                    if (triggerText && opt.dataset.label) {
                        triggerText.innerText = opt.dataset.label;
                    }
                }
            } else {
                opt.classList.remove(SELECTORS.activeClass);
            }
        });
    }

    function _bindEvents() {
        // Event Delegation
        document.addEventListener('click', (e) => {
            if (e.target.closest(SELECTORS.attrSearch)) return;

            const optionBtn = e.target.closest(SELECTORS.attrOption);
            if (optionBtn) { _handleOptionSelect(optionBtn, e); return; }

            const triggerWrapper = e.target.closest(SELECTORS.attrTrigger);
            if (triggerWrapper) {
                if (e.target.closest(SELECTORS.triggerSelector)) { _handleDropdownToggle(triggerWrapper, e); }
                return;
            }
            
            if (!e.target.closest(SELECTORS.wrapper)) { _closeAllDropdowns(); }
        });

        // Búsqueda en tiempo real
        document.addEventListener('input', (e) => {
            if (e.target.matches(SELECTORS.attrSearch)) {
                _handleLanguageSearch(e.target);
            }
        });
    }

    function _handleLanguageSearch(input) {
        const term = input.value.toLowerCase().trim();
        const wrapper = input.closest(SELECTORS.popover);
        if (!wrapper) return;

        const links = wrapper.querySelectorAll(`${SELECTORS.optionClass}[data-type="language"]`);
        const noResults = wrapper.querySelector('#no-lang-results');
        let hasVisible = false;

        links.forEach(link => {
            const label = (link.dataset.label || '').toLowerCase();
            if (label.includes(term)) {
                link.style.display = 'flex';
                hasVisible = true;
            } else {
                link.style.display = 'none';
            }
        });

        if (noResults) {
            noResults.style.display = hasVisible ? 'none' : 'block';
        }
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
            
            // Auto-focus al input
            const input = menu.querySelector('input');
            if(input) setTimeout(() => input.focus(), 100);
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

        // UI Optimista (Cambia visualmente antes de confirmar)
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
                // Solo recargamos si es idioma, pero damos un momento para que se guarde
                // OJO: Si falla el guardado, se recargará igual. 
                // Idealmente deberíamos esperar a savePreference, pero para UX rápida lo dejamos así.
                setTimeout(() => window.location.reload(), 150);
            }
        }
    }

    function _closeAllDropdowns() {
        document.querySelectorAll(SELECTORS.popover).forEach(el => {
            el.classList.remove(SELECTORS.activeClass);
            const input = el.querySelector('input');
            if(input) {
                input.value = '';
                _handleLanguageSearch(input); // Resetear lista
            }
        });
        document.querySelectorAll(SELECTORS.triggerSelector).forEach(el => el.classList.remove(SELECTORS.activeClass));
        document.querySelectorAll(SELECTORS.wrapper).forEach(el => el.classList.remove('dropdown-active'));
    }

    // 2. FUNCIÓN SAVEPREFERENCE ACTUALIZADA
    async function savePreference(key, value) {
        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('key', key);
        formData.append('value', value);

        try { 
            const res = await ApiService.post('settings-handler.php', formData); 
            
            if (res.success) {
                // Actualizar caché local solo si tuvo éxito
                if (window.USER_PREFS) window.USER_PREFS[key] = value;
            } else {
                // MOSTRAR ERROR (Aquí es donde sale el Toast del límite)
                Toast.show(res.message, 'error');
                
                // Si era un cambio de tema y falló (por rate limit), revertimos visualmente
                if (key === 'theme' && window.USER_PREFS && window.USER_PREFS.theme) {
                    applyTheme(window.USER_PREFS.theme);
                    _syncStateWithDOM(); // Regresa el selector a su sitio
                }
                
                // Si era un toggle (checkbox), revertimos el check
                if (key === 'open_links_new_tab' || key === 'extended_toast') {
                   _revertToggle(key);
                }
            }
        } catch (error) { 
            console.error(error); 
            Toast.show(I18n.t('js.core.connection_error'), 'error');
        }
    }

    function _revertToggle(key) {
        let selectorId = '';
        if (key === 'open_links_new_tab') selectorId = 'pref-open-links';
        if (key === 'extended_toast') selectorId = 'pref-extended-toast';
        
        const checkbox = document.getElementById(selectorId);
        if (checkbox && window.USER_PREFS) {
            // Regresa al valor que tenía guardado en memoria
            checkbox.checked = !!window.USER_PREFS[key]; 
        }
    }

    function applyTheme(theme) {
        const root = document.documentElement;
        if (theme === 'dark') root.setAttribute('data-theme', 'dark');
        else if (theme === 'light') root.setAttribute('data-theme', 'light');
        else root.removeAttribute('data-theme');
    }

    function _initToggles() {
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
        sync, 
        savePreference,
        applyTheme
    };
})();