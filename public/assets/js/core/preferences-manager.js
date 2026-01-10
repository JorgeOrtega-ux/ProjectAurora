/**
 * public/assets/js/core/preferences-manager.js
 * Gestor híbrido: Escribe Cookies para PHP y maneja eventos en tiempo real para JS
 */

const COOKIE_NAME = 'project_aurora_prefs';

const defaultPreferences = {
    theme: 'sync',
    language: 'auto', // 'auto' dejará que PHP decida en el primer load
    openLinksNewTab: true
};

class PreferencesManager {
    constructor() {
        // Cargamos lo que haya en cookies o defaults
        this.prefs = this.loadPreferences();
        this.init();
    }

    init() {
        // Aplicar efectos visuales inmediatos (Tema y Links)
        this.applyTheme();
        this.applyExternalLinksBehavior();
        
        // Listener para cambios de tema del sistema (si está en sync)
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.prefs.theme === 'sync') this.applyTheme();
        });

        console.log('Preferences Manager: Sincronizado con Cookies', this.prefs);
    }

    // --- COOKIE HELPERS ---
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        // Importante: path=/ para que PHP lo lea en todo el sitio
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    }

    // --- CARGA Y GUARDADO ---
    loadPreferences() {
        const cookieData = this.getCookie(COOKIE_NAME);
        if (cookieData) {
            try {
                // Decodificamos la cookie URL-encoded
                return { ...defaultPreferences, ...JSON.parse(decodeURIComponent(cookieData)) };
            } catch (e) {
                console.error("Error leyendo preferencias", e);
                return { ...defaultPreferences };
            }
        }
        return { ...defaultPreferences };
    }

    savePreferences() {
        // Guardamos JSON string en Cookie
        const jsonStr = JSON.stringify(this.prefs);
        this.setCookie(COOKIE_NAME, encodeURIComponent(jsonStr), 365); // 1 año de duración
    }

    // --- API PÚBLICA (Usada por main-controller) ---

    setLanguage(langCode) {
        this.prefs.language = langCode;
        this.savePreferences();
        // Opcional: Recargar para aplicar cambios de idioma globales (textos del servidor)
        // location.reload(); 
        console.log(`Idioma guardado en cookie: ${langCode}`);
    }

    setTheme(themeMode) {
        this.prefs.theme = themeMode;
        this.savePreferences();
        this.applyTheme();
    }

    setOpenLinksNewTab(isEnabled) {
        this.prefs.openLinksNewTab = isEnabled;
        this.savePreferences();
    }

    getCurrentSettings() {
        return this.prefs;
    }

    // --- APLICACIÓN VISUAL ---
    applyTheme() {
        let effectiveTheme = this.prefs.theme;
        if (effectiveTheme === 'sync') {
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            effectiveTheme = systemDark ? 'dark' : 'light';
        }

        // Usamos atributo data-theme en HTML para CSS global
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        
        // Fallback básico JS
        if (effectiveTheme === 'dark') {
            document.body.classList.add('theme-dark');
            document.body.classList.remove('theme-light');
        } else {
            document.body.classList.add('theme-light');
            document.body.classList.remove('theme-dark');
        }
    }

    applyExternalLinksBehavior() {
        document.addEventListener('click', (e) => {
            if (!this.prefs.openLinksNewTab) return;

            const link = e.target.closest('a');
            if (!link || !link.href) return;

            const currentDomain = window.location.hostname;
            try {
                const linkUrl = new URL(link.href);
                // Si es dominio distinto y no tiene target definido
                if (linkUrl.hostname !== currentDomain && !link.getAttribute('target')) {
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                }
            } catch (err) {}
        });
    }
}

export const prefsManager = new PreferencesManager();