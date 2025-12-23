/**
 * app-init.js
 * Punto de entrada de la aplicación.
 * REFACTORIZADO: Usa eventos en lugar de observar el DOM.
 */

import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 

import { SettingsController } from './modules/settings/settings-controller.js'; 
import { ProfileController } from './modules/settings/profile-controller.js'; 
import { DevicesController } from './modules/settings/devices-controller.js';

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // Inicializadores Globales (se ejecutan una sola vez)
        Toast.init();
        initMainController();
        initAuthController();
        SettingsController.init(); // Listeners globales de settings
        
        // Inicializar Router
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }

        // ============================================================
        // SISTEMA DE RUTAS (Lógica de controladores por vista)
        // ============================================================
        
        // 1. Manejar carga inicial (Landing directo)
        // Obtenemos la sección actual de la URL
        const path = window.location.pathname.replace(window.BASE_PATH, '').replace(/^\/+|\/+$/g, '');
        const initialSection = path || 'main';
        routeDispatcher(initialSection);

        // 2. Manejar navegación SPA (Eventos del Router)
        document.addEventListener('spa:view_loaded', (e) => {
            const section = e.detail.section;
            routeDispatcher(section);
        });
    }
};

/**
 * Función que decide qué controlador iniciar según la sección.
 * Actúa como un "Controller Factory" o Dispatcher.
 */
function routeDispatcher(section) {
    console.log(`Router Dispatch: Inicializando controladores para [${section}]`);

    // Limpieza previa si fuera necesaria (opcional)
    
    // Switch de Rutas
    switch (section) {
        case 'settings/your-profile':
            ProfileController.init();
            break;
            
        case 'settings/devices':
            DevicesController.init();
            break;
            
        // Puedes agregar más casos aquí según crezca la app
        case 'settings/login-security':
            // Si hubiera lógica específica...
            break;
            
        default:
            // Lógica por defecto o "main"
            break;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});