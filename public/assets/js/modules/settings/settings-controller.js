/**
 * public/assets/js/modules/settings/settings-controller.js
 */

import { ApiService } from '../../core/api-service.js';

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
                _handleLanguageSearch(input); // Resetear lista
            }
        });
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