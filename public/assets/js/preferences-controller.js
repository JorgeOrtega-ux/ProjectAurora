// public/assets/js/preferences-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';

export class PreferencesController {
    constructor() {
        this.prefs = {
            language: 'en-us',
            openLinksNewTab: true
        };
        this.isLoggedIn = document.body.classList.contains('is-logged-in');
        this.supportedLangs = ['en-us', 'en-gb', 'fr-fr', 'de-de', 'it-it', 'es-latam', 'es-mx', 'es-es', 'pt-br', 'pt-pt'];
        
        this.init();
    }

    async init() {
        await this.loadPreferences();
        this.applyPreferencesToDOM();
        
        // Refrescar al navegar usando el router SPA
        window.addEventListener('viewLoaded', () => {
            this.syncUI();
            this.applyPreferencesToDOM();
        });
        
        this.syncUI();
    }

    getBrowserLanguage() {
        let browserLang = navigator.language.toLowerCase();
        
        // Exact match
        if (this.supportedLangs.includes(browserLang)) return browserLang;
        
        // Fallbacks por idioma base
        if (browserLang.startsWith('es')) return 'es-latam';
        if (browserLang.startsWith('en')) return 'en-us';
        if (browserLang.startsWith('pt')) return 'pt-br';
        
        // Fallback global final
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
            } catch(e) { console.error("Error al cargar preferencias:", e); }
        }
        
        // Fallback para invitados o error de red (Local Storage)
        const local = localStorage.getItem('aurora_prefs');
        if (local) {
            this.prefs = JSON.parse(local);
        } else {
            // Primer visitante absoluto
            this.prefs.language = this.getBrowserLanguage();
            this.prefs.openLinksNewTab = true;
            this.saveLocally();
            
            // Si estaba logueado y no tenía BD (recién creado), sincroniza
            if (this.isLoggedIn) {
                this.updateBackend('language', this.prefs.language);
                this.updateBackend('open_links_new_tab', this.prefs.openLinksNewTab);
            }
        }
    }

    saveLocally() {
        localStorage.setItem('aurora_prefs', JSON.stringify(this.prefs));
    }

    async updatePreference(key, value) {
        // Mapeo del JSON key local
        this.prefs[key === 'open_links_new_tab' ? 'openLinksNewTab' : key] = value;
        this.saveLocally(); 

        if (this.isLoggedIn) {
            await this.updateBackend(key, value);
        }
        
        this.applyPreferencesToDOM();
    }

    async updateBackend(key, value) {
        const csrfToken = document.getElementById('csrf_token_settings') 
            ? document.getElementById('csrf_token_settings').value 
            : (document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '');
        
        if (!csrfToken) return;

        try {
            await ApiService.post(API_ROUTES.SETTINGS.UPDATE_PREFERENCE, {
                field: key,
                value: value,
                csrf_token: csrfToken
            });
        } catch (error) { console.error("Error al guardar en backend", error); }
    }

    syncUI() {
        // Actualizar Switches/Toggles en vistas settings y guest
        const toggles = document.querySelectorAll('#pref-open-links, #pref-open-links-guest');
        toggles.forEach(t => {
            if (t) t.checked = this.prefs.openLinksNewTab;
        });

        // Actualizar Dropdowns de Lenguaje visualmente
        const options = document.querySelectorAll('[data-action="select-option"]');
        options.forEach(opt => {
            opt.classList.remove('active');
            if (opt.dataset.value === this.prefs.language) {
                opt.classList.add('active');
                
                // Buscar la etiqueta del dropdown correspondiente y actualizarla
                const dropdown = opt.closest('.component-dropdown');
                if (dropdown) {
                    const textDisplay = dropdown.querySelector('.component-dropdown-text');
                    if (textDisplay) textDisplay.textContent = opt.dataset.label;
                }
            }
        });
    }

    applyPreferencesToDOM() {
        // Aplicar a nivel HTML
        document.documentElement.lang = this.prefs.language;

        // Comportamiento enlaces externos
        const links = document.querySelectorAll('a');
        if (this.prefs.openLinksNewTab) {
            links.forEach(a => {
                if (a.hostname !== window.location.hostname && a.href && !a.href.startsWith('javascript')) {
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                }
            });
        } else {
            links.forEach(a => {
                if (a.hostname !== window.location.hostname && a.href && !a.href.startsWith('javascript')) {
                    a.removeAttribute('target');
                    a.removeAttribute('rel');
                }
            });
        }
    }
}