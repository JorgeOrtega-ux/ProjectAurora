/**
 * public/assets/js/modules/settings/settings-controller.js
 */

import { ApiService } from '../../core/api-service.js';
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
        bindScrollListeners(); // Intentar vincular al inicio por si acaso
    }

    function sync() {
        _syncStateWithDOM();
    }

    // === NUEVA FUNCIÓN PÚBLICA ===
    // Se debe llamar cada vez que se carga la vista 'settings/your-profile'
    function bindScrollListeners() {
        const scrollableLists = document.querySelectorAll('.menu-list--scrollable');
        
        scrollableLists.forEach(list => {
            // Evitar doble binding verificando si ya tiene un flag
            if (list.dataset.scrollBound === 'true') return;
            
            list.dataset.scrollBound = 'true'; // Marcar como vinculado
            
            list.addEventListener('scroll', (e) => {
                const container = e.target;
                // Buscamos el contenedor padre .popover-module para encontrar el header dentro de él
                const wrapper = container.closest('.popover-module');
                if (!wrapper) return;

                const header = wrapper.querySelector('.menu-search-header');
                if (!header) return;

                if (container.scrollTop > 5) {
                    header.classList.add('shadow');
                } else {
                    header.classList.remove('shadow');
                }
            });
        });
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
        // Event Delegation (Click en Dropdowns, Opciones, etc.)
        // Estos sí funcionan siempre porque se atan al document
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

        // Búsqueda en tiempo real (Input Delegation)
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

        const listContainer = wrapper.querySelector('.menu-list');
        const links = wrapper.querySelectorAll(`${SELECTORS.optionClass}[data-type="language"]`);
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

        // Lógica Dinámica para "No results"
        let noResults = wrapper.querySelector('#no-lang-results');

        if (!hasVisible) {
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.id = 'no-lang-results';
                noResults.className = 'menu-empty-state';
                noResults.innerText = 'No se encontraron resultados.';
                // Forzamos display block porque la clase CSS tiene display:none por defecto
                noResults.style.display = 'block'; 
                listContainer.appendChild(noResults);
            }
        } else {
            if (noResults) {
                noResults.remove();
            }
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

        // UI Optimista
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
        document.querySelectorAll(SELECTORS.popover).forEach(el => {
            el.classList.remove(SELECTORS.activeClass);
            const input = el.querySelector('input');
            if(input) {
                input.value = '';
                _handleLanguageSearch(input);
            }
        });
        document.querySelectorAll(SELECTORS.triggerSelector).forEach(el => el.classList.remove(SELECTORS.activeClass));
        document.querySelectorAll(SELECTORS.wrapper).forEach(el => el.classList.remove('dropdown-active'));
    }

    async function savePreference(key, value) {
        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('key', key);
        formData.append('value', value);

        try { 
            const res = await ApiService.post('settings-handler.php', formData); 
            if (res.success) {
                if (window.USER_PREFS) window.USER_PREFS[key] = value;
            } else {
                Toast.show(res.message, 'error');
                if (key === 'theme' && window.USER_PREFS && window.USER_PREFS.theme) {
                    applyTheme(window.USER_PREFS.theme);
                    _syncStateWithDOM(); 
                }
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
        applyTheme,
        bindScrollListeners // <--- EXPORTAMOS LA FUNCIÓN
    };
})();