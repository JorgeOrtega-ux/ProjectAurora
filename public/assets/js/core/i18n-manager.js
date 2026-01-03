/**
 * public/assets/js/core/i18n-manager.js
 * Sistema de traducción para JavaScript (Cliente)
 */

export const I18n = {
    /**
     * Obtiene una traducción soportando notación de puntos (nested).
     * @param {string} key - La clave de traducción (ej: 'menu.home')
     * @returns {string} - El texto traducido o la clave misma si no existe.
     */
    t: (key) => {
        // window.TRANSLATIONS se inyecta en public/index.php
        if (!window.TRANSLATIONS) {
            return key;
        }
        
        const keys = key.split('.');
        let value = window.TRANSLATIONS;

        for (const k of keys) {
            if (value && value[k]) {
                value = value[k];
            } else {
                return key; // No encontrado
            }
        }
        
        return (typeof value === 'string') ? value : key;
    }
};