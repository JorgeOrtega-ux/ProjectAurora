// public/assets/js/core/timer-manager.js

/**
 * TimerManager: Centraliza la gestión de temporizadores para asegurar
 * su limpieza automática al cambiar de vista en la SPA.
 */

const activeIntervals = new Set();
const activeTimeouts = new Set();

export const TimerManager = {
    /**
     * Reemplazo seguro para window.setInterval
     */
    setInterval: (callback, ms) => {
        const id = window.setInterval(callback, ms);
        activeIntervals.add(id);
        return id;
    },

    /**
     * Reemplazo seguro para window.clearInterval
     */
    clearInterval: (id) => {
        window.clearInterval(id);
        activeIntervals.delete(id);
    },

    /**
     * Reemplazo seguro para window.setTimeout
     */
    setTimeout: (callback, ms) => {
        const id = window.setTimeout(() => {
            activeTimeouts.delete(id); // Limpiar del set al ejecutarse
            callback();
        }, ms);
        activeTimeouts.add(id);
        return id;
    },

    /**
     * Reemplazo seguro para window.clearTimeout
     */
    clearTimeout: (id) => {
        window.clearTimeout(id);
        activeTimeouts.delete(id);
    },

    /**
     * DETONACIÓN NUCLEAR: Limpia absolutamente todo.
     * Se llama desde el Router (url-manager) al cambiar de página.
     */
    clearAll: () => {
        if (activeIntervals.size > 0 || activeTimeouts.size > 0) {
            console.log(`[TimerManager] Limpiando ${activeIntervals.size} intervalos y ${activeTimeouts.size} timeouts.`);
        }
        
        activeIntervals.forEach(id => window.clearInterval(id));
        activeIntervals.clear();

        activeTimeouts.forEach(id => window.clearTimeout(id));
        activeTimeouts.clear();
    }
};