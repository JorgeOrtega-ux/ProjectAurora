/**
 * public/assets/js/core/i18n-manager.js
 * Sistema de traducción para JavaScript (Cliente)
 */

export const I18n = {
    /**
     * Obtiene una traducción soportando notación de puntos (nested) y parámetros.
     * @param {string} key - La clave de traducción (ej: 'menu.home')
     * @param {Array} params - (Opcional) Array de valores para reemplazar los '%s'
     * @returns {string} - El texto traducido e interpolado.
     */
    t: (key, params = []) => {
        // window.TRANSLATIONS se inyecta en public/index.php
        if (!window.TRANSLATIONS) {
            return key;
        }
        
        const keys = key.split('.');
        let value = window.TRANSLATIONS;

        // 1. Buscar la cadena en el objeto JSON
        for (const k of keys) {
            if (value && value[k]) {
                value = value[k];
            } else {
                return key; // No encontrado
            }
        }
        
        let text = (typeof value === 'string') ? value : key;

        // 2. Reemplazar los marcadores %s si hay parámetros
        if (params && params.length > 0) {
            params.forEach(param => {
                // Reemplaza solo la primera ocurrencia de %s cada vez
                text = text.replace('%s', param);
            });
        }

        return text;
    }
};