/**
 * public/assets/js/modules/settings/settings-controller.js
 * Maneja preferencias globales (Tema, Idioma, Toggles) usando Event Delegation.
 */

import { ApiService } from '../../core/api-service.js';
import { I18n } from '../../core/i18n-manager.js';

export const SettingsController = (function() {
    
    // Selectores centralizados para fácil mantenimiento
    const SELECTORS = {
        wrapper: '.trigger-select-wrapper',
        popover: '.popover-module',
        triggerSelector: '.trigger-selector', // El botón visual
        triggerText: '.trigger-select-text',
        triggerIcon: '.trigger-select-icon',
        optionClass: '.menu-link',
        
        // Data Attributes
        attrTrigger: '[data-trigger="dropdown"]', // El contenedor padre
        attrOption: '[data-action="select-option"]', // La opción individual
        
        activeClass: 'active'
    };

    function init() {
        _bindEvents();
        _initToggles();
        console.log("SettingsController: Inicializado (Delegación corregida).");
    }

    function _bindEvents() {
        document.addEventListener('click', (e) => {
            
            // 1. PRIORIDAD ALTA: Verificar si es una Opción (Elemento hijo más específico)
            const optionBtn = e.target.closest(SELECTORS.attrOption);
            if (optionBtn) {
                _handleOptionSelect(optionBtn, e);
                return; // Importante: Detener aquí para que no se ejecute el trigger del padre
            }

            // 2. PRIORIDAD MEDIA: Verificar si es el Trigger (Contenedor padre)
            const triggerWrapper = e.target.closest(SELECTORS.attrTrigger);
            if (triggerWrapper) {
                // Verificar que el click NO fue dentro del contenido del popover (espacios vacíos)
                // Solo queremos togglear si se clickea la cabecera (trigger-selector)
                if (e.target.closest(SELECTORS.triggerSelector)) {
                    _handleDropdownToggle(triggerWrapper, e);
                }
                return;
            }

            // 3. PRIORIDAD BAJA: Click fuera de cualquier dropdown (Cerrar todo)
            if (!e.target.closest(SELECTORS.wrapper)) {
                _closeAllDropdowns();
            }
        });
    }

    function _handleDropdownToggle(wrapperElement, event) {
        event.stopPropagation();

        const menu = wrapperElement.querySelector(SELECTORS.popover);
        const trigger = wrapperElement.querySelector(SELECTORS.triggerSelector);
        
        if (!menu || !trigger) return;

        const isAlreadyActive = menu.classList.contains(SELECTORS.activeClass);
        
        // Primero cerramos otros dropdowns abiertos para evitar colisiones
        _closeAllDropdowns();

        // Si no estaba activo, lo abrimos
        if (!isAlreadyActive) {
            menu.classList.add(SELECTORS.activeClass);
            trigger.classList.add(SELECTORS.activeClass);
            wrapperElement.classList.add('dropdown-active');
        }
    }

    function _handleOptionSelect(optionElement, event) {
        event.stopPropagation(); // Detener propagación
        event.preventDefault(); // Prevenir comportamientos por defecto
        
        const wrapper = optionElement.closest(SELECTORS.wrapper);
        if (!wrapper) return;

        // Extraer datos del dataset
        const value = optionElement.dataset.value;
        const label = optionElement.dataset.label;
        const type = optionElement.dataset.type; // 'theme' o 'language'
        
        // 1. Actualizar UI del Trigger (Texto e Icono) inmediatamente
        const triggerText = wrapper.querySelector(SELECTORS.triggerText);
        if (triggerText && label) triggerText.innerText = label;
        
        const newIcon = optionElement.querySelector('.material-symbols-rounded')?.innerText;
        const triggerIcon = wrapper.querySelector(SELECTORS.triggerIcon);
        if(newIcon && triggerIcon) triggerIcon.innerText = newIcon;

        // 2. Actualizar estado visual de las opciones (Clase Active)
        const allOptions = wrapper.querySelectorAll(SELECTORS.optionClass);
        allOptions.forEach(opt => opt.classList.remove(SELECTORS.activeClass));
        optionElement.classList.add(SELECTORS.activeClass);

        // 3. Cerrar dropdown
        _closeAllDropdowns();

        // 4. Guardar preferencia y aplicar cambios
        if (value && type) {
            const isTheme = type === 'theme';
            
            // Guardar en backend
            savePreference(type, value);

            // Efectos
            if (isTheme) {
                applyTheme(value);
            } else {
                // Si es idioma, recargar para aplicar textos con pequeño delay para feedback visual
                setTimeout(() => window.location.reload(), 150);
            }
        }
    }

    function _closeAllDropdowns() {
        document.querySelectorAll(SELECTORS.popover).forEach(el => el.classList.remove(SELECTORS.activeClass));
        document.querySelectorAll(SELECTORS.triggerSelector).forEach(el => el.classList.remove(SELECTORS.activeClass));
        document.querySelectorAll(SELECTORS.wrapper).forEach(el => el.classList.remove('dropdown-active'));
    }

    // --- LÓGICA DE PREFERENCIAS (Backend) ---

    async function savePreference(key, value) {
        if (window.USER_PREFS) {
            window.USER_PREFS[key] = value;
        }

        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('key', key);
        formData.append('value', value);

        try {
            await ApiService.post('settings-handler.php', formData);
            // Fallo silencioso intencional para no interrumpir UX si es error menor de red
        } catch (error) {
            console.error("Error guardando preferencia:", error);
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

    return {
        init,
        savePreference,
        applyTheme
    };
})();