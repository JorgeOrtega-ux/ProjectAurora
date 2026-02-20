// public/assets/js/preferences-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';

export class PreferencesController {
    constructor() {
        this.prefs = { language: 'en-us', openLinksNewTab: true };
        this.isLoggedIn = document.body.classList.contains('is-logged-in');
        this.supportedLangs = ['en-us', 'en-gb', 'fr-fr', 'de-de', 'it-it', 'es-latam', 'es-mx', 'es-es', 'pt-br', 'pt-pt'];
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
                    this.saveLocally(); 
                    return;
                }
            } catch(e) { console.error(e); }
        }
        
        const local = localStorage.getItem('aurora_prefs');
        if (local) {
            this.prefs = JSON.parse(local);
        } else {
            this.prefs.language = this.getBrowserLanguage();
            this.prefs.openLinksNewTab = true;
            this.saveLocally();
            if (this.isLoggedIn) {
                this.updateBackend('language', this.prefs.language);
                this.updateBackend('open_links_new_tab', this.prefs.openLinksNewTab);
            }
        }
    }

    saveLocally() {
        localStorage.setItem('aurora_prefs', JSON.stringify(this.prefs));
        document.cookie = `aurora_lang=${this.prefs.language}; path=/; max-age=31536000; SameSite=Strict`;
    }

    async updatePreference(key, value) {
        const jsKey = key === 'open_links_new_tab' ? 'openLinksNewTab' : key;
        const originalValue = this.prefs[jsKey]; // Guardamos el original por si falla
        const changed = this.prefs[jsKey] !== value;
        
        if (!changed) return;

        // Actualizamos de forma optimista
        this.prefs[jsKey] = value;
        this.saveLocally(); 

        let successBackend = true;
        if (this.isLoggedIn) {
            successBackend = await this.updateBackend(key, value);
        }
        
        // Si el backend lo rechaza (ej. Por Rate Limit), revertimos los cambios en JS y en UI
        if (!successBackend) {
            this.prefs[jsKey] = originalValue;
            this.saveLocally();
            this.syncUI(); // Forzamos a que los botones vuelvan a como estaban
            return;
        }
        
        this.applyPreferencesToDOM();

        // RECARGA COMPLETA SI CAMBIÓ EL IDIOMA PARA REFLEJAR TODO
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
        document.querySelectorAll('#pref-open-links, #pref-open-links-guest').forEach(t => t.checked = this.prefs.openLinksNewTab);
        document.querySelectorAll('[data-action="select-option"]').forEach(opt => {
            opt.classList.remove('active');
            if (opt.dataset.value === this.prefs.language) {
                opt.classList.add('active');
                const dropdown = opt.closest('.component-dropdown');
                if (dropdown) {
                    const textDisplay = dropdown.querySelector('.component-dropdown-text');
                    if (textDisplay) textDisplay.textContent = opt.dataset.label;
                }
            }
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
    }
}