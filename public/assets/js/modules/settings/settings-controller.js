/**
 * public/assets/js/modules/settings/settings-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

const SettingsAPI = ApiService.Routes.Settings;

export const SettingsController = {
    init: () => {
        console.log("SettingsController: Escuchando eventos UI");
        _initToggles();
        
        document.removeEventListener('ui:dropdown-selected', _onDropdownSelection);
        document.addEventListener('ui:dropdown-selected', _onDropdownSelection);
        
        if (!window.IS_LOGGED_IN) {
            _syncGuestUI();
        }
    },

    sync: () => {
        console.log("SettingsController: Sincronizando UI y Scroll...");
        _initScrollBehavior();
    },

    savePreference, 
    applyTheme
};

function _syncGuestUI() {
    const localPrefs = JSON.parse(localStorage.getItem('guest_prefs') || '{}');
    
    const toggleLinks = document.getElementById('pref-open-links');
    if (toggleLinks && localPrefs.hasOwnProperty('open_links_new_tab')) {
        toggleLinks.checked = localPrefs.open_links_new_tab;
    }

    if (localPrefs.theme) {
        const themeTrigger = document.querySelector('#guest-theme-selector .trigger-select-text');
        if (themeTrigger) {
            const labels = { 'sync': 'Sincronizar con el sistema', 'light': 'Tema claro', 'dark': 'Tema oscuro' };
            themeTrigger.textContent = labels[localPrefs.theme] || labels['sync'];
        }
    }
    
    // (Opcional) Sincronizar texto de idioma también aquí, aunque el script inline de PHP ya lo hace más rápido
}

function _initScrollBehavior() {
    const scrollContainer = document.querySelector('.general-content-scrolleable');
    const stickyHeader = document.querySelector('.settings-header-wrapper') || document.querySelector('.module-header');

    if (scrollContainer && stickyHeader) {
        scrollContainer.onscroll = null;
        scrollContainer.onscroll = () => {
            if (scrollContainer.scrollTop > 10) {
                stickyHeader.classList.add('shadow-active');
            } else {
                stickyHeader.classList.remove('shadow-active');
            }
        };
    }
}

function _onDropdownSelection(e) {
    const { type, value, element } = e.detail;

    if (element && element.closest('[data-section="admin-user-details"]')) {
        return; 
    }

    if (type === 'theme' || type === 'language') {
        savePreference(type, value);

        if (type === 'theme') {
            applyTheme(value);
        } else if (type === 'language') {
            // Recargamos para aplicar el idioma en PHP
            setTimeout(() => window.location.reload(), 150);
        }
    }
}

async function savePreference(key, value) {
    // 1. Actualizar memoria global
    if (window.USER_PREFS) window.USER_PREFS[key] = value;

    // 2. MODO INVITADO (LocalStorage + COOKIE para idioma)
    if (!window.IS_LOGGED_IN) {
        const currentPrefs = JSON.parse(localStorage.getItem('guest_prefs') || '{}');
        currentPrefs[key] = value;
        localStorage.setItem('guest_prefs', JSON.stringify(currentPrefs));
        
        // [FIX CRÍTICO] Guardar idioma en Cookie para que PHP lo lea al recargar
        if (key === 'language') {
            document.cookie = `guest_language=${value}; path=/; max-age=31536000; SameSite=Strict`;
        }
        
        return;
    }

    // 3. MODO USUARIO (API)
    const formData = new FormData();
    formData.append('key', key);
    formData.append('value', value);

    try { 
        const res = await ApiService.post(SettingsAPI.UpdatePreference, formData); 
        if (!res.success) {
            Toast.show(res.message, 'error');
        }
    } catch (error) { 
        console.error(error); 
        Toast.show('Error de conexión', 'error');
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