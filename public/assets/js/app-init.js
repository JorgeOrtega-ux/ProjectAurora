/* =========================================
   APP INITIALIZATION
   Entry point for application scripts
   ========================================= */

// 1. Importamos el gestor de preferencias (NUEVO - Debe ir primero para aplicar temas rápido)
import { prefsManager } from './core/preferences-manager.js';

// 2. Importamos el controlador de UI
import { initMainController } from './main-controller.js';

// 3. Importamos el gestor de URL
import { initUrlManager } from './core/url-manager.js';

document.addEventListener('DOMContentLoaded', function() {
    console.log('App: Inicializando...');

    // Iniciar lógica de interfaz y pasarle el gestor de preferencias
    initMainController(prefsManager);

    // Iniciar sistema de navegación sin recarga
    initUrlManager();
});