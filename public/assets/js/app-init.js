/* =========================================
   APP INITIALIZATION
   Entry point for application scripts
   ========================================= */

// 1. Importamos el controlador de UI (Menús, Header)
import { initMainController } from './main-controller.js';

// 2. Importamos el gestor de URL (SPA Routing)
import { initUrlManager } from './core/url-manager.js';

document.addEventListener('DOMContentLoaded', function() {
    console.log('App: Inicializando...');

    // Iniciar lógica de interfaz (Menús y Botones ya definidos en main-controller)
    initMainController();

    // Iniciar sistema de navegación sin recarga
    initUrlManager();
});