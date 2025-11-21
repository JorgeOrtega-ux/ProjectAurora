// public/assets/js/core/i18n-manager.js

const BASE_PATH = window.BASE_PATH || '/ProjectAurora/';
let translations = {};
let currentLang = 'es-latam'; // Por defecto

// Detectar idioma desde variable global inyectada por PHP o navegador
if (window.USER_LANG) {
    currentLang = window.USER_LANG;
}

export async function initI18n() {
    try {
        const response = await fetch(`${BASE_PATH}assets/translations/${currentLang}.json`);
        if (!response.ok) throw new Error('Translation file not found');
        
        translations = await response.json();
        
        // Traducir todo el documento actual
        translateDocument();
        
        // Exponer función globalmente para uso en otros scripts legacy
        window.t = t;
        
        // Disparar evento de que i18n está listo
        document.dispatchEvent(new Event('i18n-ready'));

    } catch (error) {
        console.error('i18n Error:', error);
    }
}

/**
 * Cambia el idioma dinámicamente sin recargar la página
 * @param {string} newLang Código del nuevo idioma (ej: 'en-us')
 */
export async function changeLanguage(newLang) {
    currentLang = newLang;
    window.USER_LANG = newLang; // Actualizar variable global

    try {
        const response = await fetch(`${BASE_PATH}assets/translations/${newLang}.json`);
        if (!response.ok) throw new Error('Translation file not found');
        
        translations = await response.json();
        
        // Volver a traducir toda la interfaz visible
        translateDocument();
        
        console.log(`Idioma cambiado a: ${newLang}`);
        
    } catch (error) {
        console.error('Error changing language:', error);
    }
}

// Exponer cambio de idioma globalmente por si acaso
window.changeLanguage = changeLanguage;

/**
 * Traduce una clave dada. Soporta anidación (auth.login.title)
 * y reemplazo de variables {name}.
 */
export function t(key, vars = {}) {
    const keys = key.split('.');
    let current = translations;
    
    for (const k of keys) {
        if (current[k] === undefined) {
            return key; // Si falta, devuelve la clave
        }
        current = current[k];
    }
    
    let text = current;
    
    if (typeof text === 'string') {
        Object.keys(vars).forEach(variable => {
            text = text.replace(new RegExp(`{${variable}}`, 'g'), vars[variable]);
        });
    }
    
    return text;
}

/**
 * Busca y traduce elementos en el DOM
 */
export function translateDocument(container = document) {
    // 1. Texto simple (textContent/innerHTML)
    const elements = container.querySelectorAll('[data-i18n]');
    elements.forEach(el => {
        const key = el.getAttribute('data-i18n');
        el.innerHTML = t(key); // Usamos innerHTML para permitir etiquetas como <strong>
    });

    // 2. Placeholders
    const placeholders = container.querySelectorAll('[data-i18n-placeholder]');
    placeholders.forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        el.setAttribute('placeholder', t(key));
    });

    // 3. Tooltips (data-tooltip)
    const tooltips = container.querySelectorAll('[data-i18n-tooltip]');
    tooltips.forEach(el => {
        const key = el.getAttribute('data-i18n-tooltip');
        el.setAttribute('data-tooltip', t(key));
    });
    
    // 4. Títulos (title)
    const titles = container.querySelectorAll('[data-i18n-title]');
    titles.forEach(el => {
        const key = el.getAttribute('data-i18n-title');
        el.setAttribute('title', t(key));
    });
}