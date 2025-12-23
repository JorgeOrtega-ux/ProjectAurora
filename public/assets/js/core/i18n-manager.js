/**
 * public/assets/js/core/i18n-manager.js
 * Sistema de traducción para JavaScript (Cliente)
 */

export const I18n = {
    /**
     * Obtiene una traducción.
     * @param {string} key - La clave de traducción (ej: 'menu.home')
     * @returns {string} - El texto traducido o la clave misma si no existe.
     */
    t: (key) => {
        // window.TRANSLATIONS se inyecta en public/index.php
        if (window.TRANSLATIONS && window.TRANSLATIONS[key]) {
            return window.TRANSLATIONS[key];
        }
        
        // Fallback: Si no existe el diccionario o la clave, devolvemos la clave.
        // Esto cumple con el requisito de mostrar 'menu.home' si estamos en 'es-mx' y no existe el archivo.
        return key;
    }
};