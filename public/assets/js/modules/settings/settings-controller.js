import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';

// Variable para guardar la referencia del listener y poder limpiarlo si cambian de modo
let _systemThemeListener = null;

const SettingsController = {
    init: () => {
        _initToggles();
        
        document.removeEventListener('ui:dropdown-selected', _onDropdownSelection);
        document.addEventListener('ui:dropdown-selected', _onDropdownSelection);
        
        if (!window.IS_LOGGED_IN) {
            _syncGuestUI();
        }
    },

    sync: () => {
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
        const res = await ApiService.post(
            ApiService.Routes.Settings.UpdatePreference, 
            formData, 
            { signal: window.PAGE_SIGNAL }
        ); 

        if (!res.success) {
            ToastManager.show(res.message, 'error');
            
            if (key === 'open_links_new_tab') {
                const el = document.getElementById('pref-open-links');
                if (el) el.checked = !el.checked;
            }
             if (key === 'extended_toast') {
                const el = document.getElementById('pref-extended-toast');
                if (el) el.checked = !el.checked;
            }
        }
    } catch (error) { 
        if (error.isAborted) return;
        
        ToastManager.show('Error de conexión', 'error');
    }
}

/**
 * MODIFICADO: Ahora maneja "sync" aplicando explícitamente data-theme
 * y escuchando cambios del sistema operativo.
 */
function applyTheme(theme) {
    const root = document.documentElement;
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    // 1. Limpiar listener previo si existía (para evitar duplicados o conflictos)
    if (_systemThemeListener) {
        mediaQuery.removeEventListener('change', _systemThemeListener);
        _systemThemeListener = null;
    }

    if (theme === 'dark') {
        // Caso: Fijo Oscuro
        root.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
        // Caso: Fijo Claro
        root.setAttribute('data-theme', 'light');
    } else {
        // Caso: Sincronizar (o null)
        
        // Función interna para actualizar según lo que diga el sistema
        const updateSystemTheme = (e) => {
            const isDark = e.matches;
            root.setAttribute('data-theme', isDark ? 'dark' : 'light');
        };

        // A. Aplicar estado actual INMEDIATAMENTE
        updateSystemTheme(mediaQuery);

        // B. Guardar referencia y escuchar cambios futuros (ej: usuario cambia Windows de día a noche)
        _systemThemeListener = updateSystemTheme;
        mediaQuery.addEventListener('change', _systemThemeListener);
    }
}

function _initToggles() {
    const toggleLinks = document.getElementById('pref-open-links');
    if (toggleLinks && !toggleLinks.dataset.hasListener) {
        toggleLinks.addEventListener('change', (e) => savePreference('open_links_new_tab', e.target.checked));
        toggleLinks.dataset.hasListener = "true";
    }

    const toggleToast = document.getElementById('pref-extended-toast');
    if (toggleToast && !toggleToast.dataset.hasListener) {
        toggleToast.addEventListener('change', (e) => savePreference('extended_toast', e.target.checked));
        toggleToast.dataset.hasListener = "true";
    }
}

export { SettingsController };