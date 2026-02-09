/**
 * public/assets/js/modules/settings/settings-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { ToastManager } from '../../core/toast-manager.js';

export const SettingsController = {
    init: () => {
        console.log("SettingsController: Escuchando eventos UI");
        _initToggles();
        
        // Aquí usas funciones con nombre (_onDropdownSelection), así que removeEventListener SÍ funciona.
        // Esto estaba bien, no causaba duplicados.
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
            setTimeout(() => window.location.reload(), 150);
        }
    }
}

async function savePreference(key, value) {
    if (window.USER_PREFS) window.USER_PREFS[key] = value;

    if (!window.IS_LOGGED_IN) {
        const currentPrefs = JSON.parse(localStorage.getItem('guest_prefs') || '{}');
        currentPrefs[key] = value;
        localStorage.setItem('guest_prefs', JSON.stringify(currentPrefs));
        
        if (key === 'language') {
            document.cookie = `guest_language=${value}; path=/; max-age=31536000; SameSite=Strict`;
        }
        return;
    }

    const formData = new FormData();
    formData.append('key', key);
    formData.append('value', value);

    try { 
        const res = await ApiService.post(ApiService.Routes.Settings.UpdatePreference, formData); 
        if (!res.success) {
            ToastManager.show(res.message, 'error');
            
            // [OPCIONAL] Si falla, revertir el checkbox visualmente para que coincida con el servidor
            // Esto evita que el usuario piense que se guardó.
            if (key === 'open_links_new_tab') {
                const el = document.getElementById('pref-open-links');
                if (el) {
                    el.checked = !el.checked; // Revertir
                    // Importante: No dispares el evento 'change' aquí o crearás un bucle infinito
                }
            }
             if (key === 'extended_toast') {
                const el = document.getElementById('pref-extended-toast');
                if (el) el.checked = !el.checked;
            }

        }
    } catch (error) { 
        console.error(error); 
        ToastManager.show('Error de conexión', 'error');
    }
}

function applyTheme(theme) {
    const root = document.documentElement;
    if (theme === 'dark') root.setAttribute('data-theme', 'dark');
    else if (theme === 'light') root.setAttribute('data-theme', 'light');
    else root.removeAttribute('data-theme');
}

// [CORREGIDO] Evita añadir listeners duplicados si la función se llama varias veces
function _initToggles() {
    const toggleLinks = document.getElementById('pref-open-links');
    // Verificamos si existe Y si NO tiene ya nuestro listener (usando un flag custom)
    if (toggleLinks && !toggleLinks.dataset.hasListener) {
        toggleLinks.addEventListener('change', (e) => savePreference('open_links_new_tab', e.target.checked));
        toggleLinks.dataset.hasListener = "true"; // Marcamos como "escuchando"
    }

    const toggleToast = document.getElementById('pref-extended-toast');
    if (toggleToast && !toggleToast.dataset.hasListener) {
        toggleToast.addEventListener('change', (e) => savePreference('extended_toast', e.target.checked));
        toggleToast.dataset.hasListener = "true"; // Marcamos como "escuchando"
    }
}