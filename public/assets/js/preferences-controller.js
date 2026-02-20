// public/assets/js/preferences-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';

export class PreferencesController {
    constructor() {
        this.prefs = { 
            language: 'en-us', 
            openLinksNewTab: true,
            theme: 'system',
            extendedAlerts: false
        };
        this.isLoggedIn = document.body.classList.contains('is-logged-in');
        this.supportedLangs = ['en-us', 'en-gb', 'fr-fr', 'de-de', 'it-it', 'es-latam', 'es-mx', 'es-es', 'pt-br', 'pt-pt'];
        
        // Listener dinámico para cambios del tema en el SO
        this.systemThemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.systemThemeQuery.addEventListener('change', () => this.applyThemeMode());
        
        this.init();
    }

    async init() {
        await this.loadPreferences();
        this.applyPreferencesToDOM();
        window.addEventListener('viewLoaded', () => { this.syncUI(); this.applyPreferencesToDOM(); });
        this.syncUI();
    }

    getBrowserLanguage() {
        let browserLang = navigator.language.toLowerCase();
        if (this.supportedLangs.includes(browserLang)) return browserLang;
        if (browserLang.startsWith('es')) return 'es-latam';
        if (browserLang.startsWith('en')) return 'en-us';
        if (browserLang.startsWith('pt')) return 'pt-br';
        return 'en-us'; 
    }

    async loadPreferences() {
        if (this.isLoggedIn) {
            try {
                const res = await ApiService.get(API_ROUTES.SETTINGS.GET_PREFERENCES);
                if (res.success && res.preferences) {
                    this.prefs.language = res.preferences.language;
                    this.prefs.openLinksNewTab = res.preferences.open_links_new_tab == 1;
                    this.prefs.theme = res.preferences.theme || 'system';
                    this.prefs.extendedAlerts = res.preferences.extended_alerts == 1;
                    this.saveLocally(); 
                    return;
                }
            } catch(e) { console.error(e); }
        }
        
        const local = localStorage.getItem('aurora_prefs');
        if (local) {
            this.prefs = { ...this.prefs, ...JSON.parse(local) }; // Combina evitando perder las nuevas props
        } else {
            this.prefs.language = this.getBrowserLanguage();
            this.prefs.openLinksNewTab = true;
            this.prefs.theme = 'system';
            this.prefs.extendedAlerts = false;
            this.saveLocally();
            if (this.isLoggedIn) {
                this.updateBackend('language', this.prefs.language);
                this.updateBackend('open_links_new_tab', this.prefs.openLinksNewTab);
                this.updateBackend('theme', this.prefs.theme);
                this.updateBackend('extended_alerts', this.prefs.extendedAlerts);
            }
        }
    }

    saveLocally() {
        localStorage.setItem('aurora_prefs', JSON.stringify(this.prefs));
        document.cookie = `aurora_lang=${this.prefs.language}; path=/; max-age=31536000; SameSite=Strict`;
    }

    async updatePreference(key, value) {
        let jsKey = key;
        if (key === 'open_links_new_tab') jsKey = 'openLinksNewTab';
        if (key === 'extended_alerts') jsKey = 'extendedAlerts';
        
        const originalValue = this.prefs[jsKey]; 
        const changed = this.prefs[jsKey] !== value;
        
        if (!changed) return;

        // Actualización optimista
        this.prefs[jsKey] = value;
        this.saveLocally(); 

        let successBackend = true;
        if (this.isLoggedIn) {
            successBackend = await this.updateBackend(key, value);
        }
        
        // Si el backend lo rechaza (ej. Rate Limit), revertimos JS y UI
        if (!successBackend) {
            this.prefs[jsKey] = originalValue;
            this.saveLocally();
            this.syncUI(); 
            return;
        }
        
        this.applyPreferencesToDOM();

        // Recarga completa solo si el idioma cambia para reflejar la traducción
        if (key === 'language' && changed) {
            window.location.reload();
        }
    }

    async updateBackend(key, value) {
        const csrfToken = document.getElementById('csrf_token_settings') 
            ? document.getElementById('csrf_token_settings').value 
            : (document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '');
        if (!csrfToken) return false;
        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.UPDATE_PREFERENCE, { field: key, value: value, csrf_token: csrfToken });
            if (!res.success) {
                alert(window.t(res.message));
                return false;
            }
            return true;
        } catch (error) { 
            console.error(error); 
            return false;
        }
    }

    syncUI() {
        // --- Sincronizar interruptores ---
        document.querySelectorAll('#pref-open-links, #pref-open-links-guest').forEach(t => t.checked = this.prefs.openLinksNewTab);
        
        const alertToggle = document.getElementById('pref-extended-alerts');
        if (alertToggle) alertToggle.checked = this.prefs.extendedAlerts;

        // --- Sincronizar todos los Dropdowns dinámicamente ---
        document.querySelectorAll('.component-dropdown').forEach(dropdown => {
            const prefKey = dropdown.dataset.prefKey || 'language';
            let currentValue = this.prefs[prefKey === 'theme' ? 'theme' : 'language'];

            dropdown.querySelectorAll('[data-action="select-option"]').forEach(opt => {
                opt.classList.remove('active');
                if (opt.dataset.value === currentValue) {
                    opt.classList.add('active');
                    const textDisplay = dropdown.querySelector('.component-dropdown-text');
                    if (textDisplay) textDisplay.textContent = opt.dataset.label;
                }
            });
        });
    }

    applyPreferencesToDOM() {
        document.documentElement.lang = this.prefs.language;
        
        const links = document.querySelectorAll('a');
        if (this.prefs.openLinksNewTab) {
            links.forEach(a => {
                if (a.hostname !== window.location.hostname && a.href && !a.href.startsWith('javascript')) {
                    a.target = '_blank'; a.rel = 'noopener noreferrer';
                }
            });
        } else {
            links.forEach(a => {
                if (a.hostname !== window.location.hostname && a.href && !a.href.startsWith('javascript')) {
                    a.removeAttribute('target'); a.removeAttribute('rel');
                }
            });
        }

        // Aplicar el tema (inyectar clase en el HTML)
        this.applyThemeMode();
    }

    applyThemeMode() {
        const html = document.documentElement;
        html.classList.remove('dark-theme', 'light-theme');

        if (this.prefs.theme === 'dark') {
            html.classList.add('dark-theme');
        } else if (this.prefs.theme === 'light') {
            html.classList.add('light-theme');
        } else {
            // "system" o fallback
            if (this.systemThemeQuery.matches) {
                html.classList.add('dark-theme');
            } else {
                html.classList.add('light-theme');
            }
        }
    }
}